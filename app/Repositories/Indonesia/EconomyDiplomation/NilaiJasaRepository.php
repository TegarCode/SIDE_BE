<?php

namespace App\Repositories\Indonesia\EconomyDiplomation;

use Illuminate\Support\Facades\DB;

class NilaiJasaRepository implements NilaiJasaRepositoryInterface
{
    protected string $conn = 'server_mysql';

    // ================== KONFIG ==================
    protected string $REPORTER = 'IDN';

    protected string $TB_SERVICE = 'tbservices';

    protected string $TB_COUNTRY = 'tbnegara';

    protected string $TB_PROFESI = 'tbprofesi';

    // 🔹 Tambah: tabel sumber
    protected string $TB_SOURCE = 'tbsumber';

    protected string $COL_DIRJEN = 'ID_WIl_Kemlu';

    protected string $UNIT = 'Orang';

    public function nilaiJasa(array $filters, ?int $kodeSumber = 136, int $limit = 50): array
    {
        $filters = $this->normalizeFilters($filters);
        $status = $this->resolveStatus($filters);

        // 🔹 Ambil nama sumber dari tbsumber (boleh null kalau tidak ada)
        $sumberName = $this->getSumberName($kodeSumber);

        // --- Ambil rentang tahun efektif dari tabel berbasis filter/status ---
        [$y1, $y2, $availableYears] = $this->resolveYears($filters, $kodeSumber, $status);
        if (! $y2) {
            return [
                'meta' => [
                    'latest_year' => null,
                    'prev_year' => null,
                    'years' => [],
                    'available_years' => [],
                    'total_world' => 0,
                    'total_world_per_year' => [],
                    'applied_filters' => $filters,
                    'status' => $status,
                    'unit' => $this->UNIT,
                    'format' => ['unit' => $this->UNIT],
                    // 🔹 ikut kirim info sumber
                    'sumber' => $sumberName,
                    'KodeSumber' => $kodeSumber,
                ],
                'items' => [],
                'per_profesi' => [],
            ];
        }

        // gunakan hanya tahun yang tersedia dalam [y1..y2]
        $years = array_values(array_filter($availableYears, fn ($y) => is_int($y) && $y >= $y1 && $y <= $y2));
        sort($years);
        if (empty($years)) {
            return [
                'meta' => [
                    'latest_year' => null,
                    'prev_year' => null,
                    'years' => [],
                    'available_years' => $availableYears,
                    'total_world' => 0,
                    'total_world_per_year' => [],
                    'applied_filters' => $filters,
                    'status' => $status,
                    'unit' => $this->UNIT,
                    'format' => ['unit' => $this->UNIT],
                    // 🔹 ikut kirim info sumber
                    'sumber' => $sumberName,
                    'KodeSumber' => $kodeSumber,
                ],
                'items' => [],
                'per_profesi' => [],
            ];
        }
        $yLast = (int) max($years);

        // ================== BASE QUERY ==================
        $base = DB::connection($this->conn)
            ->table($this->TB_SERVICE.' as t');

        // jika suatu saat kolom KodeSumber ada, aktifkan filter ini
        if ($kodeSumber !== null) {
            $base->where('t.KodeSumber', $kodeSumber);
        }

        // Arah berdasarkan status
        $partnerCol = $this->applyStatusConstraints($base, $status);

        // Tahun
        $base->whereBetween('t.Tahun', [$y1, $y2]);

        // Dirjen (berbasis negara partner)
        if (! empty($filters['dirjen'])) {
            $base->join($this->TB_COUNTRY.' as n_dirjen', 'n_dirjen.Kode_Alpha3', '=', DB::raw($partnerCol))
                ->whereIn('n_dirjen.'.$this->COL_DIRJEN, $filters['dirjen']);
        }

        // Partners (opsional)
        if (! empty($filters['partners'])) {
            $base->whereIn($partnerCol, $filters['partners']);
        }

        // Profesi filter (opsional)
        if (! empty($filters['profesi_ids'])) {
            $base->whereIn('t.ID_Profesi', $filters['profesi_ids']);
        }

        // ================== TOTAL (DUNIA/INDONESIA) PER TAHUN ==================
        $worldRows = (clone $base)
            ->selectRaw('t.Tahun, SUM(t.Jumlah) AS total_world')
            ->groupBy('t.Tahun')
            ->get();

        $worldByYear = [];
        foreach ($years as $yr) {
            $worldByYear[$yr] = 0;
        }
        foreach ($worldRows as $wr) {
            $worldByYear[(int) $wr->Tahun] = (int) $wr->total_world;
        }
        $totalWorldYLast = (int) ($worldByYear[$yLast] ?? 0);

        // ================== PARTNER PER TAHUN ==================
        $partnerYearRows = (clone $base)
            ->selectRaw("
        {$partnerCol} as partner,
        t.Tahun,
        SUM(t.Jumlah) as nilai
      ")
            ->groupBy($partnerCol, 't.Tahun')
            ->get();

        $partnerAgg = [];
        foreach ($partnerYearRows as $r) {
            $p = (string) $r->partner;
            $yr = (int) $r->Tahun;
            if (! in_array($yr, $years, true)) {
                continue;
            }

            $vl = (int) $r->nilai;
            if (! isset($partnerAgg[$p])) {
                $partnerAgg[$p] = [
                    'Jumlah_Jasa' => array_fill_keys($years, 0),
                    'share' => array_fill_keys($years, 0.0),
                ];
            }
            $partnerAgg[$p]['Jumlah_Jasa'][$yr] = $vl;
        }

        // share per tahun terhadap total
        foreach ($partnerAgg as $p => &$agg) {
            foreach ($years as $yr) {
                $den = max(1, (int) ($worldByYear[$yr] ?? 0));
                $agg['share'][$yr] = round(($agg['Jumlah_Jasa'][$yr] / $den) * 100, 2);
            }
        }
        unset($agg);

        // ================== TAHUN AKTIF ==================
        $yearsDesc = array_reverse($years);
        $activeYear = null;
        foreach ($yearsDesc as $yr) { // world ≠ 0
            if ((int) ($worldByYear[$yr] ?? 0) !== 0) {
                $activeYear = $yr;
                break;
            }
        }
        if ($activeYear === null) { // ada partner ≠ 0
            foreach ($yearsDesc as $yr) {
                $found = false;
                foreach ($partnerAgg as $ag) {
                    if ((int) ($ag['Jumlah_Jasa'][$yr] ?? 0) !== 0) {
                        $found = true;
                        break;
                    }
                }
                if ($found) {
                    $activeYear = $yr;
                    break;
                }
            }
        }
        if ($activeYear === null) {
            $activeYear = $yLast;
        }

        $idxActive = array_search($activeYear, $years, true);
        $prevActiveYear = ($idxActive !== false && $idxActive > 0) ? $years[$idxActive - 1] : null;

        // ================== URUTKAN PARTNER BERDASAR TAHUN AKTIF ==================
        uasort($partnerAgg, fn ($a, $b) => ($b['Jumlah_Jasa'][$activeYear] <=> $a['Jumlah_Jasa'][$activeYear]));

        // Limit top-N bila tidak memfilter partners
        if (empty($filters['partners']) && is_int($limit) && $limit > 0) {
            $partnerAgg = array_slice($partnerAgg, 0, $limit, true);
        }

        // Info negara
        $partnerCodes = array_keys($partnerAgg);
        $countryMap = [];
        if (! empty($partnerCodes)) {
            $countryRows = DB::connection($this->conn)
                ->table($this->TB_COUNTRY.' as n')
                ->whereIn('n.Kode_Alpha3', $partnerCodes)
                ->select('n.Kode_Alpha3', 'n.Kode_Alpha2', 'n.Negara_IDN')
                ->get();
            foreach ($countryRows as $cr) {
                $countryMap[$cr->Kode_Alpha3] = [
                    'nama' => (string) $cr->Negara_IDN,
                    'a2' => (string) $cr->Kode_Alpha2,
                    'a3' => (string) $cr->Kode_Alpha3,
                ];
            }
        }

        // Items final (per negara)
        $items = [];
        foreach ($partnerAgg as $code => $series) {
            $meta = $countryMap[$code] ?? ['nama' => $code, 'a2' => null, 'a3' => $code];
            $items[] = [
                'negara' => $meta['nama'],
                'kode_alpha2' => $meta['a2'],
                'kode_alpha3' => $meta['a3'],
                'Jumlah_Jasa' => $series['Jumlah_Jasa'],
                'share' => $series['share'],
            ];
        }

        // ================== PER PROFESI (baru) ==================
        $profesiYearRows = (clone $base)
            ->selectRaw('
        t.ID_Profesi as id_profesi,
        t.Tahun,
        SUM(t.Jumlah) as nilai
      ')
            ->groupBy('t.ID_Profesi', 't.Tahun')
            ->get();

        $profAgg = [];
        foreach ($profesiYearRows as $r) {
            $pid = (int) $r->id_profesi;
            $yr = (int) $r->Tahun;
            if (! in_array($yr, $years, true)) {
                continue;
            }

            $vl = (int) $r->nilai;
            if (! isset($profAgg[$pid])) {
                $profAgg[$pid] = [
                    'jumlah' => array_fill_keys($years, 0),
                    'share' => array_fill_keys($years, 0.0),
                ];
            }
            $profAgg[$pid]['jumlah'][$yr] = $vl;
        }

        foreach ($profAgg as $pid => &$ag) {
            foreach ($years as $yr) {
                $den = max(1, (int) ($worldByYear[$yr] ?? 0));
                $ag['share'][$yr] = round(($ag['jumlah'][$yr] / $den) * 100, 2);
            }
        }
        unset($ag);

        // Urutkan per profesi berdasar tahun aktif
        uasort($profAgg, fn ($a, $b) => ($b['jumlah'][$activeYear] <=> $a['jumlah'][$activeYear]));

        // Batasi hasil per profesi (gunakan limit yang sama, aman 200)
        $limitProf = max(1, min(200, (int) $limit));
        $profAgg = array_slice($profAgg, 0, $limitProf, true);

        // Map nama profesi
        $profIds = array_keys($profAgg);
        $profNames = [];
        if (! empty($profIds)) {
            $profNames = DB::connection($this->conn)
                ->table($this->TB_PROFESI)
                ->whereIn('ID_Profesi', $profIds)
                ->pluck('Profesi', 'ID_Profesi')
                ->toArray();
        }

        $perProfesi = [];
        foreach ($profAgg as $pid => $series) {
            $perProfesi[] = [
                'id_profesi' => $pid,
                'nama_profesi' => (string) ($profNames[$pid] ?? $pid),
                'jumlah' => $series['jumlah'],
                'share' => $series['share'],
            ];
        }

        // ================== TREN (YoY) ==================
        $trenItems = [];
        foreach ($partnerAgg as $code => $series) {
            $curr = (int) ($series['Jumlah_Jasa'][$activeYear] ?? 0);
            $prev = $prevActiveYear !== null ? (int) ($series['Jumlah_Jasa'][$prevActiveYear] ?? 0) : null;

            $delta = $prevActiveYear === null ? null : ($curr - $prev);
            if ($prevActiveYear === null) {
                $pct = null;
            } else {
                if ($prev === 0) {
                    $pct = ($curr === 0) ? 0.0 : 100.0;
                } else {
                    $pct = (($curr - $prev) / abs($prev)) * 100.0;
                }
                $pct = round($pct, 2);
            }

            $meta = $countryMap[$code] ?? ['nama' => $code, 'a2' => null, 'a3' => $code];
            $trenItems[] = [
                'negara' => $meta['nama'],
                'kode_alpha2' => $meta['a2'],
                'kode_alpha3' => $meta['a3'],
                'nilai_prev' => $prevActiveYear === null ? null : $prev,
                'nilai_curr' => $curr,
                'delta' => $delta,
                'delta_pct' => $pct,
            ];
        }
        usort($trenItems, function ($a, $b) {
            $A = $a['delta'];
            $B = $b['delta'];
            if ($A === null && $B === null) {
                return 0;
            }
            if ($A === null) {
                return 1;
            }
            if ($B === null) {
                return -1;
            }

            return $B <=> $A;
        });

        $prevYearMeta = null;
        if (! empty($years) && count($years) >= 2) {
            $prevYearMeta = $years[count($years) - 2];
        }

        return [
            'meta' => [
                'latest_year' => $yLast,
                'prev_year' => $prevYearMeta,
                'years' => $years,
                'available_years' => $availableYears,
                'total_world' => $totalWorldYLast,
                'total_world_per_year' => $worldByYear,
                'active_year' => $activeYear,
                'active_prev_year' => $prevActiveYear,
                // 🔹 sekarang isi nama sumber
                'sumber' => $sumberName,
                'KodeSumber' => $kodeSumber,
                'applied_filters' => $filters,
                'status' => $status,
                'unit' => $this->UNIT,
                'format' => ['unit' => $this->UNIT],
            ],
            'items' => $items,
            'per_profesi' => $perProfesi,

            'tren_jasa_masuk' => [
                'tahun' => $activeYear,
                'tahun_sebelumnya' => $prevActiveYear,
                'items' => $trenItems,
            ],
        ];
    }

    // ================== HELPERS ==================

    protected function normalizeFilters(array $filters): array
    {
        $norm = [];

        // Tahun
        $ys = $filters['year_start'] ?? null;
        $ye = $filters['year_end'] ?? null;
        $norm['year_start'] = is_numeric($ys) ? (int) $ys : null;
        $norm['year_end'] = is_numeric($ye) ? (int) $ye : null;

        // Dirjen -> array unik uppercase
        $dirjen = $filters['dirjen'] ?? [];
        if (is_string($dirjen)) {
            $dirjen = array_map('trim', explode(',', $dirjen));
        }
        if (is_array($dirjen)) {
            $dirjen = array_values(array_unique(array_filter(array_map(fn ($v) => strtoupper((string) $v), $dirjen))));
        } else {
            $dirjen = [];
        }
        $norm['dirjen'] = $dirjen;

        // Partners (A3)
        $partners = $filters['partners'] ?? [];
        if (is_string($partners)) {
            $partners = array_map('trim', explode(',', $partners));
        }
        if (is_array($partners)) {
            $partners = array_values(array_unique(array_filter(array_map(fn ($v) => strtoupper((string) $v), $partners))));
        } else {
            $partners = [];
        }
        $norm['partners'] = $partners;

        // Profesi id(s)
        $prof = $filters['profesi_ids'] ?? [];
        if (is_string($prof)) {
            $prof = array_map('trim', explode(',', $prof));
        }
        if (is_array($prof)) {
            $prof = array_values(array_unique(array_filter(array_map(
                fn ($v) => is_numeric($v) ? (int) $v : null,
                $prof
            ))));
        } else {
            $prof = [];
        }
        if (! empty($prof)) {
            $norm['profesi_ids'] = $prof;
        }

        // Hapus null/empty
        $status = $filters['status'] ?? null;
        if (is_array($status)) {
            $status = $status[0] ?? null;
        }
        $status = $this->canonStatus($status);
        if ($status !== null) {
            $norm['status'] = $status;
        }

        return array_filter($norm, fn ($v) => is_array($v) ? count($v) > 0 : ! is_null($v) && $v !== '');
    }

    protected function canonStatus($v): ?string
    {
        $s = strtolower(trim((string) $v));
        if (in_array($s, ['inbound', 'masuk'], true)) {
            return 'inbound';
        }
        if (in_array($s, ['outbound', 'keluar'], true)) {
            return 'outbound';
        }

        return null;
    }

    protected function resolveStatus(array $filters): string
    {
        return $filters['status'] ?? 'outbound';
    }

    protected function applyStatusConstraints($query, string $status): string
    {
        if ($status === 'inbound') {
            // Inbound: negara asal -> Indonesia
            $query->where('t.Kode_Alpha3_Tujuan', $this->REPORTER);
            return 't.Kode_Alpha3_Asal';
        }

        // Outbound: Indonesia -> negara tujuan
        $query->where('t.Kode_Alpha3_Asal', $this->REPORTER);
        return 't.Kode_Alpha3_Tujuan';
    }

    protected function resolveYears(array $filters, ?int $kodeSumber, string $status): array
    {
        // Ambil min, max, dan daftar tahun tersedia dari tabel berbasis filter/status
        [$minY, $maxY, $list] = $this->getAvailableYears($filters, $kodeSumber, $status);
        if (! $maxY) {
            return [null, null, []]; // tidak ada data sama sekali
        }

        // Normalisasi input
        $ys = $filters['year_start'] ?? null;
        $ye = $filters['year_end'] ?? null;
        $ys = is_numeric($ys) ? (int) $ys : null;
        $ye = is_numeric($ye) ? (int) $ye : null;

        // Jika user memberikan filter tahun
        if ($ys !== null && $ye !== null) {
            $a = min($ys, $ye);
            $b = max($ys, $ye);
            $a = max($minY, min($a, $maxY));
            $b = max($minY, min($b, $maxY));

            return [$a, $b, $list];
        }
        if ($ys !== null) {
            $a = max($minY, min($ys, $maxY));

            return [$a, $maxY, $list];
        }
        if ($ye !== null) {
            $b = max($minY, min($ye, $maxY));

            return [$minY, $b, $list];
        }

        // Tidak ada filter tahun -> ambil 5 tahun terakhir yang tersedia
        $last5 = array_slice($list, -5);
        if (empty($last5)) {
            return [$minY, $maxY, $list];
        }

        $y1 = (int) min($last5);
        $y2 = (int) max($last5);

        return [$y1, $y2, $list];
    }

    /**
     * Ambil min, max, list tahun tersedia berbasis filter/status/kode sumber.
     */
    protected function getAvailableYears(array $filters, ?int $kodeSumber, string $status): array
    {
        $conn = DB::connection($this->conn);
        $q = $conn->table($this->TB_SERVICE.' as t');

        if ($kodeSumber !== null) {
            $q->where('t.KodeSumber', $kodeSumber);
        }

        $partnerCol = $this->applyStatusConstraints($q, $status);

        if (! empty($filters['dirjen'])) {
            $q->join($this->TB_COUNTRY.' as n_dirjen', 'n_dirjen.Kode_Alpha3', '=', DB::raw($partnerCol))
                ->whereIn('n_dirjen.'.$this->COL_DIRJEN, $filters['dirjen']);
        }
        if (! empty($filters['partners'])) {
            $q->whereIn($partnerCol, $filters['partners']);
        }
        if (! empty($filters['profesi_ids'])) {
            $q->whereIn('t.ID_Profesi', $filters['profesi_ids']);
        }

        $mm = (clone $q)->selectRaw('MIN(t.Tahun) AS miny, MAX(t.Tahun) AS maxy')->first();
        if (! $mm || ! $mm->miny || ! $mm->maxy) {
            return [null, null, []];
        }

        $list = (clone $q)->distinct()->orderBy('t.Tahun')->pluck('t.Tahun')->map(fn ($y) => (int) $y)->toArray();

        return [(int) $mm->miny, (int) $mm->maxy, $list];
    }

    // 🔹 Helper untuk ambil NamaSumber dari tbsumber
    protected function getSumberName(?int $kodeSumber): ?string
    {
        if ($kodeSumber === null) {
            return null;
        }

        $row = DB::connection($this->conn)
            ->table($this->TB_SOURCE)
            ->where('KodeSumber', $kodeSumber)
            ->select('NamaSumber')
            ->first();

        return $row?->NamaSumber ?? null;
    }
}
