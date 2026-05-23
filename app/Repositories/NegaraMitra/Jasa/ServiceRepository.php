<?php

namespace App\Repositories\NegaraMitra\Jasa;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ServiceRepository implements ServiceRepositoryInterface
{
    private const CONN = 'server_mysql';

    protected string $conn = 'server_mysql';

    private const DEFAULT_SOURCE = 35;

    /* =============================================================
     * Helpers
     * =========================================================== */

    private function toAlpha3List(mixed $v): array
    {
        if ($v === null) {
            return [];
        }

        if (is_string($v)) {
            $s = strtoupper(trim($v));
            if ($s === '' || $s === 'ALL') {
                return [];
            }

            return [$s];
        }

        if (is_array($v)) {
            $out = [];
            foreach ($v as $item) {
                if (is_string($item)) {
                    $s = strtoupper(trim($item));
                    if ($s !== '' && $s !== 'ALL') {
                        $out[] = $s;
                    }
                } elseif (is_array($item)) {
                    $code = $item['value'] ?? $item['code'] ?? $item['alpha3'] ?? null;
                    if (is_string($code)) {
                        $s = strtoupper(trim($code));
                        if ($s !== '' && $s !== 'ALL') {
                            $out[] = $s;
                        }
                    }
                }
            }

            return array_values(array_unique($out));
        }

        return [];
    }

    private function normalizeCountry(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        if (is_string($v)) {
            $s = strtoupper(trim($v));
            if ($s === '' || $s === 'ALL') {
                return null;
            }
            return $s;
        }
        return null;
    }

    /**
     * Normalisasi kode sumber:
     * - null / tidak ada → [DEFAULT_SOURCE]
     * - "all"            → [] (tanpa filter sumber)
     * - int / int[]      → hanya >0 & unik
     */
    private function normalizedSourceCodes(array $filters): array
    {
        $src = $filters['source'] ?? self::DEFAULT_SOURCE;

        if (is_string($src) && strtolower(trim($src)) === 'all') {
            return [];
        }

        $codes = [];

        if (is_array($src)) {
            foreach ($src as $v) {
                $n = (int) $v;
                if ($n > 0) {
                    $codes[] = $n;
                }
            }
        } else {
            $n = (int) $src;
            if ($n > 0) {
                $codes[] = $n;
            }
        }

        $codes = array_values(array_unique($codes));

        if (empty($codes) && self::DEFAULT_SOURCE !== null) {
            $codes[] = (int) self::DEFAULT_SOURCE;
        }

        return $codes;
    }

    private function getSourceNames(array $codes): array
    {
        if (empty($codes)) {
            return [];
        }

        $conn = self::CONN;
        $candidates = ['tbsumber', 'tbref_sumber', 'ref_sumber'];
        $table = null;

        foreach ($candidates as $t) {
            if (Schema::connection($conn)->hasTable($t)) {
                $table = $t;
                break;
            }
        }

        if (! $table) {
            $out = [];
            foreach ($codes as $c) {
                $out[(int) $c] = null;
            }

            return $out;
        }

        $schema = Schema::connection($conn);
        $codeCol = $schema->hasColumn($table, 'Kode_Sumber') ? 'Kode_Sumber' : 'KodeSumber';
        $nameCol = $schema->hasColumn($table, 'Nama_Sumber') ? 'Nama_Sumber' : 'NamaSumber';

        $rows = DB::connection($conn)
            ->table($table)
            ->whereIn($codeCol, $codes)
            ->select([$codeCol.' as code', $nameCol.' as name'])
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r->code] = $r->name ? (string) $r->name : null;
        }

        foreach ($codes as $c) {
            $c = (int) $c;
            if (! array_key_exists($c, $map)) {
                $map[$c] = null;
            }
        }

        return $map;
    }

    private function resolveCountryNames(string|array|null $alpha3): array|string|null
    {
        if ($alpha3 === null) {
            return null;
        }

        $list = is_array($alpha3) ? $alpha3 : [$alpha3];
        $list = array_values(array_unique(array_filter(array_map(
            fn ($x) => strtoupper(trim((string) $x)),
            $list
        ), fn ($x) => $x !== '')));

        if (empty($list)) {
            return is_array($alpha3) ? [] : null;
        }

        $rows = DB::connection(self::CONN)
            ->table('tbnegara')
            ->whereIn('Kode_Alpha3', $list)
            ->pluck('Negara_IDN', 'Kode_Alpha3')
            ->toArray();

        $map = [];
        foreach ($list as $code) {
            $map[$code] = $rows[$code] ?? $code;
        }

        if (is_array($alpha3)) {
            return $map;
        }

        return $map[$list[0]] ?? $list[0];
    }

    private function applySource($q, array $filters)
    {
        $src = $filters['source'] ?? [];

        if (is_array($src)) {
            $srcArr = array_values(array_filter(
                array_map('intval', $src),
                fn ($v) => $v > 0
            ));
            if (! empty($srcArr)) {
                $q->whereIn('t.KodeSumber', $srcArr);
            }

            return $q;
        }

        $src = (int) $src;
        if ($src > 0) {
            $q->where('t.KodeSumber', $src);
        }

        return $q;
    }

    private function applyOriginDest($q, array $filters)
    {
        $origins = $this->toAlpha3List($filters['origin'] ?? null);
        $dests = $this->toAlpha3List($filters['dest'] ?? null);

        if (! empty($origins)) {
            $q->whereIn('t.Kode_Alpha3_Asal', $origins);
        }
        if (! empty($dests)) {
            $q->whereIn('t.Kode_Alpha3_Tujuan', $dests);
        }

        return $q;
    }

    private function applyCountryEitherSide($q, ?string $country)
    {
        if ($country) {
            $q->where(function ($w) use ($country) {
                $w->where('t.Kode_Alpha3_Asal', $country)
                    ->orWhere('t.Kode_Alpha3_Tujuan', $country);
            });
        }

        return $q;
    }

    private function applyCountryInbound($q, ?string $country)
    {
        if ($country) {
            $q->where('t.Kode_Alpha3_Tujuan', $country);
        }

        return $q;
    }

    private function applyCountryOutbound($q, ?string $country)
    {
        if ($country) {
            $q->where('t.Kode_Alpha3_Asal', $country);
        }

        return $q;
    }

    private function applyExcludeWor($q)
    {
        return $q->where('t.Kode_Alpha3_Asal', '!=', 'WOR')
            ->where('t.Kode_Alpha3_Tujuan', '!=', 'WOR');
    }

    /* =============================================================
     * Year helpers
     * =========================================================== */

    public function getLatestYear(array $filters): ?int
    {
        $db = DB::connection($this->conn);

        $origins = $this->toAlpha3List($filters['origin'] ?? null);
        $dests = $this->toAlpha3List($filters['dest'] ?? null);

        $q = $db->table('tbservices as t')
            ->selectRaw('MAX(t.Tahun) as y');

        $q = $this->applyExcludeWor($q);

        // filter sumber dulu
        $q = $this->applySource($q, $filters);

        // kalau origin & dest dua-duanya diisi → cek dua arah (forward + reverse)
        if (! empty($origins) && ! empty($dests)) {
            $q->where(function ($qq) use ($origins, $dests) {
                $qq->where(function ($q1) use ($origins, $dests) {
                    // forward: origin → dest
                    $q1->whereIn('t.Kode_Alpha3_Asal', $origins)
                        ->whereIn('t.Kode_Alpha3_Tujuan', $dests);
                })->orWhere(function ($q2) use ($origins, $dests) {
                    // reverse: dest → origin
                    $q2->whereIn('t.Kode_Alpha3_Asal', $dests)
                        ->whereIn('t.Kode_Alpha3_Tujuan', $origins);
                });
            });
        } else {
            // cuma salah satu yang di-filter
            if (! empty($origins)) {
                $q->whereIn('t.Kode_Alpha3_Asal', $origins);
            }
            if (! empty($dests)) {
                $q->whereIn('t.Kode_Alpha3_Tujuan', $dests);
            }
        }

        $row = $q->first();

        return $row?->y ? (int) $row->y : null;
    }

    private function getLatestYearByCountry(array $filters): ?int
    {
        $db = DB::connection($this->conn);
        $country = $this->normalizeCountry($filters['country'] ?? null);

        $q = $db->table('tbservices as t')
            ->selectRaw('MAX(t.Tahun) as y');

        $q = $this->applyExcludeWor($q);
        $q = $this->applySource($q, $filters);
        $q = $this->applyCountryEitherSide($q, $country);

        $row = $q->first();

        return $row?->y ? (int) $row->y : null;
    }

    private function getPrevYearByCountry(array $filters, int $latestYear): ?int
    {
        $db = DB::connection($this->conn);
        $country = $this->normalizeCountry($filters['country'] ?? null);

        $q = $db->table('tbservices as t')
            ->selectRaw('MAX(t.Tahun) as y')
            ->where('t.Tahun', '<', $latestYear);

        $q = $this->applyExcludeWor($q);
        $q = $this->applySource($q, $filters);
        $q = $this->applyCountryEitherSide($q, $country);

        $row = $q->first();

        return $row?->y ? (int) $row->y : null;
    }

    public function getPrevYear(array $filters, int $latestYear): ?int
    {
        $db = DB::connection($this->conn);

        $origins = $this->toAlpha3List($filters['origin'] ?? null);
        $dests = $this->toAlpha3List($filters['dest'] ?? null);

        $q = $db->table('tbservices as t')
            ->selectRaw('MAX(t.Tahun) as y');

        $q = $this->applyExcludeWor($q);

        $q = $this->applySource($q, $filters);
        $q->where('t.Tahun', '<', $latestYear);

        if (! empty($origins) && ! empty($dests)) {
            $q->where(function ($qq) use ($origins, $dests) {
                $qq->where(function ($q1) use ($origins, $dests) {
                    $q1->whereIn('t.Kode_Alpha3_Asal', $origins)
                        ->whereIn('t.Kode_Alpha3_Tujuan', $dests);
                })->orWhere(function ($q2) use ($origins, $dests) {
                    $q2->whereIn('t.Kode_Alpha3_Asal', $dests)
                        ->whereIn('t.Kode_Alpha3_Tujuan', $origins);
                });
            });
        } else {
            if (! empty($origins)) {
                $q->whereIn('t.Kode_Alpha3_Asal', $origins);
            }
            if (! empty($dests)) {
                $q->whereIn('t.Kode_Alpha3_Tujuan', $dests);
            }
        }

        $row = $q->first();

        return $row?->y ? (int) $row->y : null;
    }

    /* =============================================================
     * Summary
     * =========================================================== */

    public function getSummary(array $filters): array
    {
        $db = DB::connection($this->conn);
        $latest = (int) ($filters['year'] ?? 0);
        $prev = isset($filters['year_prev']) ? (int) $filters['year_prev'] : $latest - 1;

        if ($latest <= 0) {
            return [
                'inbound_now' => 0.0,
                'inbound_prev' => 0.0,
                'outbound_now' => 0.0,
                'outbound_prev' => 0.0,
            ];
        }

        $origins = $this->toAlpha3List($filters['origin'] ?? null);
        $dests = $this->toAlpha3List($filters['dest'] ?? null);

        // INBOUND: dest -> origin (Asal=dests, Tujuan=origins)
        $qIn = $db->table('tbservices as t')
            ->selectRaw('
                SUM(CASE WHEN t.Tahun = ? THEN COALESCE(t.Jumlah,0) ELSE 0 END) AS val_now,
                SUM(CASE WHEN t.Tahun = ? THEN COALESCE(t.Jumlah,0) ELSE 0 END) AS val_prev
            ', [$latest, $prev]);

        $qIn = $this->applyExcludeWor($qIn);

        if (! empty($dests)) {
            $qIn->whereIn('t.Kode_Alpha3_Asal', $dests);
        }
        if (! empty($origins)) {
            $qIn->whereIn('t.Kode_Alpha3_Tujuan', $origins);
        }

        $qIn = $this->applySource($qIn, $filters);
        $inRow = $qIn->first();

        // OUTBOUND: origin -> dest (Asal=origins, Tujuan=dests)
        $qOut = $db->table('tbservices as t')
            ->selectRaw('
                SUM(CASE WHEN t.Tahun = ? THEN COALESCE(t.Jumlah,0) ELSE 0 END) AS val_now,
                SUM(CASE WHEN t.Tahun = ? THEN COALESCE(t.Jumlah,0) ELSE 0 END) AS val_prev
            ', [$latest, $prev]);

        $qOut = $this->applyExcludeWor($qOut);

        if (! empty($origins)) {
            $qOut->whereIn('t.Kode_Alpha3_Asal', $origins);
        }
        if (! empty($dests)) {
            $qOut->whereIn('t.Kode_Alpha3_Tujuan', $dests);
        }

        $qOut = $this->applySource($qOut, $filters);
        $outRow = $qOut->first();

        return [
            'inbound_now' => (float) ($inRow->val_now ?? 0),
            'inbound_prev' => (float) ($inRow->val_prev ?? 0),
            'outbound_now' => (float) ($outRow->val_now ?? 0),
            'outbound_prev' => (float) ($outRow->val_prev ?? 0),
        ];
    }

    /* =============================================================
     * Timeseries – semua tahun yang tersedia
     * =========================================================== */

    public function getTimeseries(array $filters): array
    {
        $db = DB::connection($this->conn);

        $yearFrom = isset($filters['year_from']) ? (int) $filters['year_from'] : null;
        $yearTo = isset($filters['year_to']) ? (int) $filters['year_to'] : null;
        if (! $yearFrom && ! $yearTo) {
            $latestYear = $this->getLatestYear($filters);
            if ($latestYear) {
                $yearTo = $latestYear;
                $yearFrom = $latestYear - 4;
            }
        }

        $origins = $this->toAlpha3List($filters['origin'] ?? null);
        $dests = $this->toAlpha3List($filters['dest'] ?? null);

        // INBOUND: dest -> origin (Asal=dests, Tujuan=origins)
        $qIn = $db->table('tbservices as t')
            ->selectRaw('t.Tahun, SUM(COALESCE(t.Jumlah,0)) as val');

        $qIn = $this->applyExcludeWor($qIn);

        if (! empty($dests)) {
            $qIn->whereIn('t.Kode_Alpha3_Asal', $dests);
        }
        if (! empty($origins)) {
            $qIn->whereIn('t.Kode_Alpha3_Tujuan', $origins);
        }

        $qIn = $this->applySource($qIn, $filters);

        if ($yearFrom && $yearTo) {
            $qIn->whereBetween('t.Tahun', [$yearFrom, $yearTo]);
        } elseif ($yearFrom) {
            $qIn->where('t.Tahun', '>=', $yearFrom);
        } elseif ($yearTo) {
            $qIn->where('t.Tahun', '<=', $yearTo);
        }

        $rowsIn = $qIn->groupBy('t.Tahun')->orderBy('t.Tahun')->get();

        // OUTBOUND: origin -> dest (Asal=origins, Tujuan=dests)
        $qOut = $db->table('tbservices as t')
            ->selectRaw('t.Tahun, SUM(COALESCE(t.Jumlah,0)) as val');

        $qOut = $this->applyExcludeWor($qOut);

        if (! empty($origins)) {
            $qOut->whereIn('t.Kode_Alpha3_Asal', $origins);
        }
        if (! empty($dests)) {
            $qOut->whereIn('t.Kode_Alpha3_Tujuan', $dests);
        }

        $qOut = $this->applySource($qOut, $filters);

        if ($yearFrom && $yearTo) {
            $qOut->whereBetween('t.Tahun', [$yearFrom, $yearTo]);
        } elseif ($yearFrom) {
            $qOut->where('t.Tahun', '>=', $yearFrom);
        } elseif ($yearTo) {
            $qOut->where('t.Tahun', '<=', $yearTo);
        }

        $rowsOut = $qOut->groupBy('t.Tahun')->orderBy('t.Tahun')->get();

        // gabung
        $byYear = [];

        foreach ($rowsIn as $r) {
            $y = (int) $r->Tahun;
            if (! isset($byYear[$y])) {
                $byYear[$y] = ['year' => $y, 'inbound_value' => 0.0, 'outbound_value' => 0.0];
            }
            $byYear[$y]['inbound_value'] = (float) ($r->val ?? 0);
        }

        foreach ($rowsOut as $r) {
            $y = (int) $r->Tahun;
            if (! isset($byYear[$y])) {
                $byYear[$y] = ['year' => $y, 'inbound_value' => 0.0, 'outbound_value' => 0.0];
            }
            $byYear[$y]['outbound_value'] = (float) ($r->val ?? 0);
        }

        ksort($byYear);

        return array_values($byYear);
    }

    /* =============================================================
     * Top services (profesi)
     * =========================================================== */

    /**
     * Top services per flow:
     *  - flow = "inbound": dest -> origin  (Asal=dests, Tujuan=origins)
     *  - flow = "outbound": origin -> dest (Asal=origins, Tujuan=dests)
     *
     * Ambil 2 tahun:
     *  - latestYear
     *  - prevYear (sebelumnya yang ADA data; bisa null → prev = 0)
     *
     * Output item:
     *  - code        (ID_Profesi)
     *  - label       (Profesi dari tbprofesi)
     *  - value       (alias value_now)
     *  - value_now   (jumlah di latestYear)
     *  - value_prev  (jumlah di prevYear)
     */
    public function getTopServices(array $filters, string $flow): array
    {
        $db = DB::connection($this->conn);
        $year = (int) ($filters['year'] ?? 0);
        $prev = isset($filters['year_prev']) ? (int) $filters['year_prev'] : 0;
        $flow = strtolower(trim($flow)); // inbound | outbound
        $limit = (int) ($filters['limit'] ?? 20);

        if ($year <= 0) {
            return [];
        }

        $origins = $this->toAlpha3List($filters['origin'] ?? null);
        $dests = $this->toAlpha3List($filters['dest'] ?? null);

        $sub = $db->table('tbservices as t')
            ->whereIn('t.Tahun', $prev > 0 && $prev !== $year ? [$year, $prev] : [$year]);

        $sub = $this->applyExcludeWor($sub);

        if ($flow === 'inbound') {
            // dest -> origin
            if (! empty($dests)) {
                $sub->whereIn('t.Kode_Alpha3_Asal', $dests);
            }
            if (! empty($origins)) {
                $sub->whereIn('t.Kode_Alpha3_Tujuan', $origins);
            }
        } else {
            // OUTBOUND: origin -> dest
            if (! empty($origins)) {
                $sub->whereIn('t.Kode_Alpha3_Asal', $origins);
            }
            if (! empty($dests)) {
                $sub->whereIn('t.Kode_Alpha3_Tujuan', $dests);
            }
        }

        $sub = $this->applySource($sub, $filters)
            ->selectRaw('
                t.ID_Profesi as profesi,
                SUM(CASE WHEN t.Tahun = ? THEN COALESCE(t.Jumlah,0) ELSE 0 END) AS total_now,
                SUM(CASE WHEN t.Tahun = ? THEN COALESCE(t.Jumlah,0) ELSE 0 END) AS total_prev
            ', [$year, $prev])
            ->groupBy('t.ID_Profesi')
            ->orderByDesc('total_now');

        if ($limit > 0) {
            $sub->limit($limit);
        }

        // Join ke tbprofesi(ID_Profesi, Profesi)
        $rows = $db->table(DB::raw("({$sub->toSql()}) as aggr"))
            ->mergeBindings($sub)
            ->leftJoin('tbprofesi as p', 'p.ID_Profesi', '=', 'aggr.profesi')
            ->selectRaw('
                aggr.profesi,
                COALESCE(p.Profesi, aggr.profesi) as profesi_nama,
                aggr.total_now,
                aggr.total_prev
            ')
            ->orderByDesc('aggr.total_now')
            ->get();

        return collect($rows)->map(fn ($r) => [
            'code' => (string) ($r->profesi ?? ''),
            'label' => (string) ($r->profesi_nama ?? $r->profesi ?? ''),
            'value' => (float) ($r->total_now ?? 0), // alias untuk FE sekarang
            'value_now' => (float) ($r->total_now ?? 0),
            'value_prev' => (float) ($r->total_prev ?? 0),
        ])->all();
    }

    private function getTopCountries(array $filters, string $flow): array
    {
        $db = DB::connection($this->conn);
        $year = (int) ($filters['year'] ?? 0);
        $prev = isset($filters['year_prev']) ? (int) $filters['year_prev'] : 0;
        $flow = strtolower(trim($flow)); // inbound | outbound
        $limit = (int) ($filters['limit'] ?? 20);

        if ($year <= 0) {
            return [];
        }

        $country = $this->normalizeCountry($filters['country'] ?? null);

        $partnerCol = $flow === 'inbound' ? 't.Kode_Alpha3_Asal' : 't.Kode_Alpha3_Tujuan';
        $joinCol = $flow === 'inbound' ? 't.Kode_Alpha3_Asal' : 't.Kode_Alpha3_Tujuan';

        $q = $db->table('tbservices as t')
            ->leftJoin('tbnegara as c', 'c.Kode_Alpha3', '=', $joinCol)
            ->selectRaw("
                {$partnerCol} as partner_code,
                c.Kode_Alpha2 as a2,
                COALESCE(c.Negara_IDN, {$partnerCol}) as partner_name,
                SUM(CASE WHEN t.Tahun = ? THEN COALESCE(t.Jumlah,0) ELSE 0 END) AS total_now,
                SUM(CASE WHEN t.Tahun = ? THEN COALESCE(t.Jumlah,0) ELSE 0 END) AS total_prev
            ", [$year, $prev])
            ->whereIn('t.Tahun', $prev > 0 && $prev !== $year ? [$year, $prev] : [$year]);

        $q = $this->applyExcludeWor($q);
        $q = $this->applySource($q, $filters);

        if ($flow === 'inbound') {
            $q = $this->applyCountryInbound($q, $country);
        } else {
            $q = $this->applyCountryOutbound($q, $country);
        }

        $rows = $q->groupBy($partnerCol, 'c.Negara_IDN', 'c.Kode_Alpha2')
            ->orderByDesc('total_now')
            ->when($limit > 0, fn ($qq) => $qq->limit($limit))
            ->get();

        return collect($rows)->map(fn ($r) => [
            'alpha2' => (string) ($r->a2 ?? ''),
            'alpha3' => (string) ($r->partner_code ?? ''),
            'label' => (string) ($r->partner_name ?? $r->partner_code ?? ''),
            'value_now' => (float) ($r->total_now ?? 0),
            'value_prev' => (float) ($r->total_prev ?? 0),
        ])->all();
    }

    /* =============================================================
     * Composite
     * =========================================================== */

    public function getComposite(array $filters, array $include): array
    {
        // 1) Normalisasi source dari FE → codes
        $codes = $this->normalizedSourceCodes($filters);
        $names = $this->getSourceNames($codes);

        // 2) Siapkan filters untuk query DB
        $queryFilters = $filters;
        $queryFilters['source'] = $codes; // array of int (bisa kosong = all)

        // origin/dest untuk meta
        $originList = $this->toAlpha3List($filters['origin'] ?? null) ?: null;
        $destList = $this->toAlpha3List($filters['dest'] ?? null) ?: null;

        // 3) Tahun terbaru & tahun sebelumnya yang ADA datanya
        $latestYear = isset($filters['year']) ? (int) $filters['year'] : 0;
        if ($latestYear <= 0) {
            $latestYear = $this->getLatestYear($queryFilters);
        }

        $prevYear = $latestYear ? $this->getPrevYear($queryFilters, $latestYear) : null;

        // 4) Build meta
        $metaSource = ! empty($codes) ? $codes : null;
        $metaSourceName = match (count($codes)) {
            0 => null,
            1 => ($names[$codes[0]] ?? null),
            default => array_values($names),
        };

        if (! $latestYear) {
            return [
                'meta' => [
                    'year' => null,
                    'prevYear' => null,
                    'origin' => $originList,
                    'dest' => $destList,
                    'origin_names' => $this->resolveCountryNames($filters['origin'] ?? null),
                    'dest_names' => $this->resolveCountryNames($filters['dest'] ?? null),
                    'unit' => 'Orang',
                    'source' => $metaSource,
                    'source_name' => $metaSourceName,
                ],
                'summary' => null,
                'timeseries' => ['data' => []],
                'top_services_inbound' => [],
                'top_services_outbound' => [],
            ];
        }

        // tambahkan year & year_prev ke queryFilters
        $queryFilters['year'] = $latestYear;
        $queryFilters['year_prev'] = $prevYear;

        $out = [
            'meta' => [
                'year' => $latestYear,
                'prevYear' => $prevYear,
                'origin' => $originList,
                'dest' => $destList,
                'origin_names' => $this->resolveCountryNames($filters['origin'] ?? null),
                'dest_names' => $this->resolveCountryNames($filters['dest'] ?? null),
                'unit' => 'Orang',
                'source' => $metaSource,
                'source_name' => $metaSourceName,
            ],
        ];

        // 5) Normalisasi include
        $normalizedInclude = array_map('strtolower', $include ?: []);
        if (in_array('top_services', $normalizedInclude, true)) {
            $normalizedInclude[] = 'top_services_inbound';
            $normalizedInclude[] = 'top_services_outbound';
        }
        if (empty($normalizedInclude)) {
            $normalizedInclude = [
                'summary',
                'timeseries',
                'top_services_inbound',
                'top_services_outbound',
            ];
        }

        // 6) Summary
        if (in_array('summary', $normalizedInclude, true)) {
            $s = $this->getSummary($queryFilters);
            $out['summary'] = [
                'inbound' => [
                    'value_now' => $s['inbound_now'] ?? 0.0,
                    'value_prev' => $s['inbound_prev'] ?? 0.0,
                ],
                'outbound' => [
                    'value_now' => $s['outbound_now'] ?? 0.0,
                    'value_prev' => $s['outbound_prev'] ?? 0.0,
                ],
            ];
        }

        // 7) Timeseries – all years
        if (in_array('timeseries', $normalizedInclude, true)) {
            $out['timeseries'] = ['data' => $this->getTimeseries($queryFilters)];
        }

        // 8) Top services inbound/outbound
        if (
            in_array('top_services_inbound', $normalizedInclude, true) ||
            in_array('top_services', $normalizedInclude, true)
        ) {
            $out['top_services_inbound'] = $this->getTopServices($queryFilters, 'inbound');
        }

        if (
            in_array('top_services_outbound', $normalizedInclude, true) ||
            in_array('top_services', $normalizedInclude, true)
        ) {
            $out['top_services_outbound'] = $this->getTopServices($queryFilters, 'outbound');
        }

        return $out;
    }

    public function getCountryComposite(array $filters, array $include): array
    {
        $filters['country'] = $this->normalizeCountry($filters['country'] ?? 'IDN');
        $filters['source'] = $filters['source'] ?? self::DEFAULT_SOURCE;
        $filters['limit'] = (int) ($filters['limit'] ?? 20);

        $codes = $this->normalizedSourceCodes($filters);
        $names = $this->getSourceNames($codes);

        $queryFilters = $filters;
        $queryFilters['source'] = $codes;

        $latestYear = (int) ($filters['year'] ?? 0);
        if ($latestYear <= 0) {
            $latestYear = $this->getLatestYearByCountry($queryFilters);
        }

        $prevYear = $latestYear ? $this->getPrevYearByCountry($queryFilters, $latestYear) : null;

        $metaSourceName = match (count($codes)) {
            0 => null,
            1 => ($names[$codes[0]] ?? null),
            default => array_values($names),
        };

        if (! $latestYear) {
            return [
                'meta' => [
                    'year' => null,
                    'prevYear' => null,
                    'source_name' => $metaSourceName,
                ],
                'summary' => null,
                'top_countries_inbound' => [],
                'top_countries_outbound' => [],
            ];
        }

        $queryFilters['year'] = $latestYear;
        $queryFilters['year_prev'] = $prevYear;

        $out = [
            'meta' => [
                'year' => $latestYear,
                'prevYear' => $prevYear,
                'source_name' => $metaSourceName,
            ],
        ];

        $normalizedInclude = array_map('strtolower', $include ?: []);
        if (in_array('top_countries', $normalizedInclude, true)) {
            $normalizedInclude[] = 'top_countries_inbound';
            $normalizedInclude[] = 'top_countries_outbound';
        }
        if (empty($normalizedInclude)) {
            $normalizedInclude = [
                'summary',
                'top_countries_inbound',
                'top_countries_outbound',
            ];
        }

        if (in_array('summary', $normalizedInclude, true)) {
            $sumFilters = $queryFilters;
            $sumFilters['origin'] = $queryFilters['country'] ?? null;
            $sumFilters['dest'] = null;

            $s = $this->getSummary($sumFilters);
            $out['summary'] = [
                'inbound' => [
                    'value_now' => $s['inbound_now'] ?? 0.0,
                    'value_prev' => $s['inbound_prev'] ?? 0.0,
                ],
                'outbound' => [
                    'value_now' => $s['outbound_now'] ?? 0.0,
                    'value_prev' => $s['outbound_prev'] ?? 0.0,
                ],
            ];
        }

        if (in_array('top_countries_inbound', $normalizedInclude, true)) {
            $out['top_countries_inbound'] = $this->getTopCountries($queryFilters, 'inbound');
        }

        if (in_array('top_countries_outbound', $normalizedInclude, true)) {
            $out['top_countries_outbound'] = $this->getTopCountries($queryFilters, 'outbound');
        }

        return $out;
    }
}
