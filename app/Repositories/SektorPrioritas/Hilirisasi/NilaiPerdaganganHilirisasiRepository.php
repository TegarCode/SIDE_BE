<?php

namespace App\Repositories\SektorPrioritas\Hilirisasi;

use Illuminate\Support\Facades\DB;

class NilaiPerdaganganHilirisasiRepository implements NilaiPerdaganganHilirisasiRepositoryInterface
{
    protected string $conn = 'server_mysql';

    // ================== KONFIGURASI KUNCI ==================
    protected string $DEFAULT_REPORTER = 'IDN';

    protected string $TB_TRADE = 'tbtrade';

    protected string $TB_COUNTRY = 'tbnegara';

    protected string $TB_SOURCE = 'tbsumber';

    protected string $TB_HS = 'tbharmonized';

    protected string $TB_SEKTOR = 'tbsektor_hilirisasi';

    protected string $TB_SEKTOR_HS = 'tbsektor_hscode';

    protected string $COL_DIRJEN = 'ID_WIl_Kemlu';

    protected string $UNIT = 'Ribu US$';

    // ============================================================================
    //  A. PER-NEGARA (PARTNER)
    // ============================================================================
    public function nilaiPerdaganganPerNegara(array $filters, int $kodeSumber = 5): array
    {
        $filters = $this->normalizeFilters($filters);

        [$y1, $y2, $availableYears] = $this->resolveYears($filters, $kodeSumber);
        if (! $y2) {
            return ['meta' => $this->emptyMeta($filters), 'items' => []];
        }

        $years = $this->filterYearsInRange($availableYears, $y1, $y2);
        if (empty($years)) {
            return ['meta' => $this->emptyMeta($filters, $availableYears, $y1, $y2), 'items' => []];
        }
        $yLast = (int) max($years);

        $sumber = $this->getSumber($kodeSumber);

        $base = $this->baseQuery($kodeSumber, $y1, $y2, $filters);

        // list HS dari sektor hilirisasi
        $allHs = $this->loadAllHsFromSektor();

        // kalau ada hs_list di filter → batasi ke HS tersebut
        if (! empty($filters['hs_list'] ?? [])) {
            $allHs = array_values(array_intersect($allHs, $filters['hs_list']));
        }

        // kalau tidak ada HS yang tersisa → return meta kosong
        if (empty($allHs)) {
            return [
                'meta' => $this->emptyMeta($filters, $availableYears, $y1, $y2),
                'items' => [],
            ];
        }

        $worldByYear = $this->getWorldTotalsByYear($base, $years);
        $totalWorldYLast = (int) ($worldByYear[$yLast]['world'] ?? 0);
        $items = [];

        if (! empty($allHs)) {
            $partnerYearRows = (clone $base)
                ->whereIn('t.HsCode', $allHs)
                ->selectRaw("
                    t.Kode_Alpha3_Partner as partner,
                    t.Tahun,
                    SUM(CASE WHEN t.Status = 'Export' THEN t.Nilai ELSE 0 END) as eksp,
                    SUM(CASE WHEN t.Status = 'Import' THEN t.Nilai ELSE 0 END) as imp
                ")
                ->groupBy('t.Kode_Alpha3_Partner', 't.Tahun')
                ->get();

            $partnerAgg = [];
            foreach ($partnerYearRows as $r) {
                $p = (string) $r->partner;
                $yr = (int) $r->Tahun;
                if (! in_array($yr, $years, true)) {
                    continue;
                }

                $ek = (int) $r->eksp;
                $im = (int) $r->imp;

                if (! isset($partnerAgg[$p])) {
                    $partnerAgg[$p] = [
                        'nilai_perdagangan' => array_fill_keys($years, 0),
                        'neraca' => array_fill_keys($years, 0),
                        'proporsi' => array_fill_keys($years, 0.0),
                    ];
                }
                $partnerAgg[$p]['nilai_perdagangan'][$yr] = $ek + $im;
                $partnerAgg[$p]['neraca'][$yr] = $ek - $im;
            }

            foreach ($partnerAgg as $p => &$agg) {
                foreach ($years as $yr) {
                    $worldDen = max(1, (int) ($worldByYear[$yr]['world'] ?? 0));
                    $agg['proporsi'][$yr] = round(($agg['nilai_perdagangan'][$yr] / $worldDen) * 100, 2);
                }
            }
            unset($agg);

            uasort($partnerAgg, fn ($a, $b) => ($b['nilai_perdagangan'][$yLast] <=> $a['nilai_perdagangan'][$yLast]));

            $countryMap = $this->mapCountryMeta(array_keys($partnerAgg));

            foreach ($partnerAgg as $code => $series) {
                $meta = $countryMap[$code] ?? ['nama' => $code, 'a2' => null, 'a3' => $code];
                $items[] = [
                    'negara' => $meta['nama'],
                    'kode_alpha2' => $meta['a2'],
                    'kode_alpha3' => $meta['a3'],
                    'nilai_perdagangan' => $series['nilai_perdagangan'],
                    'neraca' => $series['neraca'],
                    'proporsi' => $series['proporsi'],
                ];
            }
        }

        return [
            'meta' => [
                'years' => $years,
                'total_world' => $totalWorldYLast,
                'total_world_per_year' => array_map(fn ($v) => $v['world'], $worldByYear),
                'sumber' => $sumber?->nama,
                'applied_filters' => $filters,
                'hs_level' => $filters['hs'] ?? null,
                'unit' => $this->UNIT,
            ],
            'items' => $items,
        ];
    }

    // ============================================================================
    //  B. PER-PRODUK (HS) PER SEKTOR HILIRISASI — TANPA NERACA, KIRIM EKSPOR/IMPOR/TOTAL
    // ============================================================================
    public function nilaiPerdaganganPerProduk(array $filters, int $kodeSumber = 5, int $limit = 50): array
    {
        $filters = $this->normalizeFilters($filters);

        [$y1, $y2, $availableYears] = $this->resolveYears($filters, $kodeSumber);
        if (! $y2) {
            return ['meta' => $this->emptyMeta($filters), 'sektor_produk' => []];
        }

        $years = $this->filterYearsInRange($availableYears, $y1, $y2);
        if (empty($years)) {
            return ['meta' => $this->emptyMeta($filters, $availableYears, $y1, $y2), 'sektor_produk' => []];
        }
        $yLast = (int) max($years);

        $sumber = $this->getSumber($kodeSumber);
        $base = $this->baseQuery($kodeSumber, $y1, $y2, $filters);

        // total dunia per tahun (ekspor+impor) — sudah terfilter hs_list di $base
        $worldByYear = $this->getWorldTotalsByYear($base, $years);

        // list sektor beserta HS4
        $sektors = $this->loadSektorHilirisasiWithHs();

        // kalau ada hs_list, batasi hscodes di tiap sektor
        $hsListFilter = $filters['hs_list'] ?? [];
        if (! empty($hsListFilter)) {
            $hsListFilter = array_map('strval', $hsListFilter);
            foreach ($sektors as &$sek) {
                $sek['hscodes'] = array_values(array_intersect($sek['hscodes'], $hsListFilter));
            }
            unset($sek);
        }

        // map nama HS
        $allHs = [];
        foreach ($sektors as $s) {
            $allHs = array_merge($allHs, $s['hscodes']);
        }
        $allHs = array_values(array_unique($allHs));
        $hsNames = $this->mapHsNames($allHs);

        $limitHs = max(1, min(200, (int) $limit));
        $sektorProduk = [];

        foreach ($sektors as $sek) {
            if (empty($sek['hscodes'])) {
                continue;
            }

            // Ambil agregasi ekspor & impor per HS per tahun
            $hsRows = (clone $base)
                ->whereIn('t.HsCode', $sek['hscodes'])
                ->selectRaw("
                    t.HsCode,
                    t.Tahun,
                    SUM(CASE WHEN t.Status = 'Export' THEN t.Nilai ELSE 0 END) AS eksp,
                    SUM(CASE WHEN t.Status = 'Import' THEN t.Nilai ELSE 0 END) AS imp
                ")
                ->groupBy('t.HsCode', 't.Tahun')
                ->get();

            // Susun series per HS: ekspor, impor, total, share
            $hsAgg = [];
            foreach ($hsRows as $r) {
                $code = (string) $r->HsCode;
                $yr = (int) $r->Tahun;
                if (! in_array($yr, $years, true)) {
                    continue;
                }

                $ek = (int) $r->eksp;
                $im = (int) $r->imp;

                if (! isset($hsAgg[$code])) {
                    $hsAgg[$code] = [
                        'ekspor' => array_fill_keys($years, 0),
                        'impor' => array_fill_keys($years, 0),
                        'total' => array_fill_keys($years, 0),
                        'share' => array_fill_keys($years, 0.0),
                    ];
                }
                $hsAgg[$code]['ekspor'][$yr] = $ek;
                $hsAgg[$code]['impor'][$yr] = $im;
                $hsAgg[$code]['total'][$yr] = $ek + $im;
            }

            // Hitung share per tahun terhadap total dunia (berdasar total = ekspor+impor)
            foreach ($hsAgg as $code => &$ag) {
                foreach ($years as $yr) {
                    $den = max(1, (int) ($worldByYear[$yr]['world'] ?? 0));
                    $ag['share'][$yr] = round(($ag['total'][$yr] / $den) * 100, 2);
                }
            }
            unset($ag);

            // Urutkan berdasarkan total tahun terakhir, ambil limit
            uasort($hsAgg, fn ($a, $b) => ($b['total'][$yLast] <=> $a['total'][$yLast]));
            $hsAgg = array_slice($hsAgg, 0, $limitHs, true);

            // Bentuk payload produk (tanpa neraca)
            $produk = [];
            foreach ($hsAgg as $code => $ag) {
                $produk[] = [
                    'kodeHS' => $code,
                    'namaHS' => (string) ($hsNames[$code] ?? $code),
                    'ekspor' => $ag['ekspor'],
                    'impor' => $ag['impor'],
                    'total' => $ag['total'],
                    'share' => $ag['share'],
                ];
            }

            $sektorProduk[] = [
                'sektor' => $sek['sektor'],
                'produk' => $produk,
            ];
        }

        return [
            'meta' => [
                'latest_year' => $yLast,
                'prev_year' => (int) min($years),
                'years' => $years,
                'available_years' => $availableYears,
                'sumber' => $sumber?->nama,
                'unit' => $this->UNIT,
                'partner' => $this->buildCountryMetaCollection($filters['partner'] ?? []),
                'reporter' => $this->buildCountryMetaCollection($filters['reporter'] ?? []),
                'sektor_count' => count($sektorProduk),
            ],
            'sektor_produk' => $sektorProduk,
        ];
    }

    // ============================================================================
    //  SHARED HELPERS
    // ============================================================================
    protected function baseQuery(int $kodeSumber, int $y1, int $y2, array $filters)
    {
        $q = DB::connection($this->conn)
            ->table($this->TB_TRADE.' as t')
            ->where('t.Kode_Sumber', $kodeSumber);

        if (! empty($filters['reporter'])) {
            $q->whereIn('t.Kode_Alpha3_Reporter', $filters['reporter']);
        } else {
            $q->where('t.Kode_Alpha3_Reporter', $this->DEFAULT_REPORTER);
        }

        $q = $this->applyYearRange($q, $y1, $y2);
        $q = $this->applyDirjenFilter($q, $filters);
        $q = $this->applyHsWhereLength($q, $filters);
        $q = $this->applyStatusFilter($q, $filters);
        $q = $this->applyPartnerFilter($q, $filters);
        $q = $this->applyHsListFilter($q, $filters);

        return $q;
    }

    protected function getWorldTotalsByYear($base, array $years): array
    {
        $rows = (clone $base)
            ->selectRaw('t.Tahun, SUM(t.Nilai) as total_world')
            ->groupBy('t.Tahun')
            ->get();

        $byYear = [];
        foreach ($years as $yr) {
            $byYear[$yr] = ['world' => 0];
        }
        foreach ($rows as $r) {
            $yr = (int) $r->Tahun;
            if (! array_key_exists($yr, $byYear)) {
                continue;
            }
            $byYear[$yr]['world'] = (int) $r->total_world;
        }

        return $byYear;
    }

    protected function getSumber(int $kodeSumber)
    {
        return DB::connection($this->conn)
            ->table($this->TB_SOURCE)
            ->select('KodeSumber as kode', 'NamaSumber as nama')
            ->where('KodeSumber', $kodeSumber)
            ->first();
    }

    protected function mapCountryMeta(array $alpha3s): array
    {
        if (empty($alpha3s)) {
            return [];
        }
        $rows = DB::connection($this->conn)
            ->table($this->TB_COUNTRY.' as n')
            ->whereIn('n.Kode_Alpha3', $alpha3s)
            ->select('n.Kode_Alpha3', 'n.Kode_Alpha2', 'n.Negara_IDN')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $map[$r->Kode_Alpha3] = [
                'nama' => (string) $r->Negara_IDN,
                'a2' => (string) $r->Kode_Alpha2,
                'a3' => (string) $r->Kode_Alpha3,
            ];
        }

        return $map;
    }

    protected function loadSektorHilirisasiWithHs(): array
    {
        $conn = DB::connection($this->conn);

        $sektors = $conn->table($this->TB_SEKTOR)
            ->select('ID_Sektor as id', 'Sektor as sektor')
            ->orderBy('ID_Sektor')
            ->get();

        if (! $sektors->count()) {
            return [];
        }

        $ids = $sektors->pluck('id')->toArray();

        $hsMap = $conn->table($this->TB_SEKTOR_HS)
            ->whereIn('ID_Sektor', $ids)
            ->select('ID_Sektor', 'Sektor', 'hscode')
            ->get();

        $bySek = [];
        foreach ($sektors as $r) {
            $bySek[(int) $r->id] = [
                'id' => (int) $r->id,
                'sektor' => (string) $r->sektor,
                'hscodes' => [],
            ];
        }

        foreach ($hsMap as $h) {
            $sid = (int) $h->ID_Sektor;
            $digits = preg_replace('/\D+/', '', (string) $h->hscode);
            if ($digits !== '' && strlen($digits) >= 4) {
                $bySek[$sid]['hscodes'][] = substr($digits, 0, 4); // HS4
            }
        }

        foreach ($bySek as &$s) {
            $s['hscodes'] = array_values(array_unique(array_filter($s['hscodes'])));
            sort($s['hscodes'], SORT_STRING);
        }
        unset($s);

        return array_values($bySek);
    }

    protected function loadAllHsFromSektor(): array
    {
        $sektors = $this->loadSektorHilirisasiWithHs();
        $allHs = [];
        foreach ($sektors as $s) {
            $allHs = array_merge($allHs, $s['hscodes']);
        }
        $allHs = array_values(array_unique(array_filter($allHs)));
        sort($allHs, SORT_STRING);

        return $allHs;
    }

    protected function mapHsNames(array $hsCodes): array
    {
        if (empty($hsCodes)) {
            return [];
        }
        $map = DB::connection($this->conn)
            ->table($this->TB_HS)
            ->whereIn('hscode', $hsCodes)
            ->pluck('description', 'hscode');

        return $map->toArray();
    }

    // ============================================================================
    //  UTILITIES / NORMALIZERS
    // ============================================================================
    protected function emptyMeta(array $filters, array $availableYears = [], $y1 = null, $y2 = null): array
    {
        return [
            'latest_year' => null,
            'prev_year' => null,
            'years' => [],
            'available_years' => $availableYears,
            'sumber' => null,
            'total_world' => 0,
            'total_world_per_year' => [],
            'applied_filters' => $filters,
            'hs_level' => $filters['hs'] ?? null,
            'unit' => $this->UNIT,
            'format' => ['unit' => $this->UNIT],
            'effective_years' => ['start' => $y1, 'end' => $y2],
        ];
    }

    protected function normalizeFilters(array $filters): array
    {
        $norm = [];

        // Tahun
        $ys = $filters['year_start'] ?? null;
        $ye = $filters['year_end'] ?? null;
        $norm['year_start'] = is_numeric($ys) ? (int) $ys : null;
        $norm['year_end'] = is_numeric($ye) ? (int) $ye : null;

        // HS level (optional)
        $hs = $filters['hs'] ?? null;
        if (is_string($hs)) {
            $digits = preg_replace('/\D+/', '', $hs);
            $hs = ($digits === '' ? null : (int) $digits);
        } elseif (is_numeric($hs)) {
            $hs = (int) $hs;
        } else {
            $hs = null;
        }
        $norm['hs'] = $hs;

        // hs_list: daftar kode HS spesifik
        $hsList = $filters['hs_list'] ?? null;
        if (is_string($hsList)) {
            $hsList = array_map('trim', explode(',', $hsList));
        } elseif (! is_array($hsList)) {
            $hsList = [];
        }

        if (is_array($hsList)) {
            $hsList = array_values(array_unique(array_filter(array_map(
                fn ($v) => trim((string) $v),
                $hsList
            ))));
        }

        if (! empty($hsList)) {
            $norm['hs_list'] = $hsList;
        }

        // Dirjen
        $dirjen = $filters['dirjen'] ?? [];
        if (is_string($dirjen)) {
            $dirjen = array_map('trim', explode(',', $dirjen));
        }
        if (is_array($dirjen)) {
            $dirjen = array_values(array_unique(array_filter(array_map(
                fn ($v) => strtoupper((string) $v),
                $dirjen
            ))));
        } else {
            $dirjen = [];
        }
        $norm['dirjen'] = $dirjen;

        // partner/dest → norm['partner']
        $partner = $filters['partner'] ?? ($filters['dest'] ?? []);
        if (is_string($partner)) {
            $partner = array_map('trim', explode(',', $partner));
        }
        if (is_array($partner)) {
            $partner = array_values(array_unique(array_filter(array_map(
                fn ($v) => strtoupper((string) $v),
                $partner
            ))));
        } else {
            $partner = [];
        }
        $norm['partner'] = $partner;

        // reporter/origin → norm['reporter']
        $reporter = $filters['reporter'] ?? ($filters['origin'] ?? []);
        if (is_string($reporter)) {
            $reporter = array_map('trim', explode(',', $reporter));
        }
        if (is_array($reporter)) {
            $reporter = array_values(array_unique(array_filter(array_map(
                fn ($v) => strtoupper((string) $v),
                $reporter
            ))));
        } else {
            $reporter = [];
        }
        $norm['reporter'] = $reporter;

        // Status
        $canon = function ($v) {
            $s = strtolower(trim((string) $v));
            if (in_array($s, ['export', 'ekspor'], true)) {
                return 'Export';
            }
            if (in_array($s, ['import', 'impor'], true)) {
                return 'Import';
            }

            return null;
        };
        $status = $filters['status'] ?? null;
        if (is_array($status)) {
            $status = array_values(array_filter(array_unique(array_map($canon, $status))));
            if (! count($status)) {
                $status = null;
            }
        } elseif (is_string($status)) {
            $status = $canon($status);
        } else {
            $status = null;
        }
        $norm['status'] = $status;

        // Bersihkan null/empty
        return array_filter($norm, function ($v) {
            if (is_array($v)) {
                return count($v) > 0;
            }

            return ! is_null($v) && $v !== '';
        });
    }

    protected function getAvailableYears(int $kodeSumber, array $reporters = []): array
    {
        $conn = DB::connection($this->conn);
        $q = $conn->table($this->TB_TRADE)
            ->where('Kode_Sumber', $kodeSumber);

        // pertimbangkan reporter bila ada
        if (! empty($reporters)) {
            $q->whereIn('Kode_Alpha3_Reporter', $reporters);
        } else {
            $q->where('Kode_Alpha3_Reporter', $this->DEFAULT_REPORTER);
        }

        $mm = (clone $q)->selectRaw('MIN(Tahun) AS miny, MAX(Tahun) AS maxy')->first();
        if (! $mm || ! $mm->miny || ! $mm->maxy) {
            return [null, null, []];
        }

        $list = (clone $q)
            ->distinct()
            ->orderBy('Tahun')
            ->pluck('Tahun')
            ->map(fn ($y) => (int) $y)
            ->toArray();

        return [(int) $mm->miny, (int) $mm->maxy, $list];
    }

    protected function resolveYears(array $filters, int $kodeSumber): array
    {
        // pass reporters agar rentang tahun relevan
        [$minY, $maxY, $list] = $this->getAvailableYears($kodeSumber, $filters['reporter'] ?? []);
        if (! $maxY) {
            return [null, null, []];
        }

        $ys = $filters['year_start'] ?? null;
        $ye = $filters['year_end'] ?? null;

        if (is_int($ys) && is_int($ye)) {
            $a = max(min($ys, $ye), $minY);
            $b = min(max($ys, $ye), $maxY);
            if ($a > $b) {
                return [$minY, $maxY, $list];
            }

            return [$a, $b, $list];
        }
        if (is_int($ys) && ! is_int($ye)) {
            return [max(min($ys, $maxY), $minY), $maxY, $list];
        }
        if (! is_int($ys) && is_int($ye)) {
            return [$minY, min(max($ye, $minY), $maxY), $list];
        }

        return [$minY, $maxY, $list];
    }

    protected function filterYearsInRange(array $allYears, int $y1, int $y2): array
    {
        $ys = array_values(array_filter($allYears, fn ($y) => is_int($y) && $y >= $y1 && $y <= $y2));
        sort($ys);

        return $ys;
    }

    protected function applyYearRange($query, int $y1, int $y2)
    {
        return $query->whereBetween('t.Tahun', [$y1, $y2]);
    }

    protected function applyDirjenFilter($query, array $filters)
    {
        if (! empty($filters['dirjen'])) {
            $query->join($this->TB_COUNTRY.' as n_dirjen', 'n_dirjen.Kode_Alpha3', '=', 't.Kode_Alpha3_Partner')
                ->whereIn('n_dirjen.'.$this->COL_DIRJEN, $filters['dirjen']);
        }

        return $query;
    }

    protected function applyHsWhereLength($query, array $filters)
    {
        $hs = $filters['hs'] ?? null;
        if (is_int($hs) && $hs > 0) {
            $hs = max(2, min(10, $hs));
            $query->whereRaw('CHAR_LENGTH(t.HsCode) = ?', [$hs]);
        }

        return $query;
    }

    protected function applyStatusFilter($query, array $filters)
    {
        if (! array_key_exists('status', $filters)) {
            return $query;
        }

        $st = $filters['status'];
        if (is_array($st) && count($st) > 0) {
            return $query->whereIn('t.Status', $st);
        }
        if (is_string($st) && $st !== '') {
            return $query->where('t.Status', $st);
        }

        return $query;
    }

    protected function applyPartnerFilter($query, array $filters)
    {
        if (! empty($filters['partner'])) {
            $query->whereIn('t.Kode_Alpha3_Partner', $filters['partner']);
        }

        return $query;
    }

    /**
     * Filter HS spesifik berdasarkan hs_list (opsional).
     */
    protected function applyHsListFilter($query, array $filters)
    {
        if (! empty($filters['hs_list']) && is_array($filters['hs_list'])) {
            $query->whereIn('t.HsCode', $filters['hs_list']);
        }

        return $query;
    }

    // ======================= META HELPERS =======================
    protected function buildCountryMetaCollection(array $alpha3s): array
    {
        if (empty($alpha3s)) {
            return [];
        }
        $map = $this->mapCountryMeta($alpha3s);
        $out = [];
        foreach ($alpha3s as $a3) {
            $row = $map[$a3] ?? null;
            $out[] = [
                'a3' => $a3,
                'a2' => $row['a2'] ?? null,
                'nama' => $row['nama'] ?? null,
            ];
        }

        return $out;
    }
}
