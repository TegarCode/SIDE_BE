<?php

namespace App\Repositories\NegaraMitra\Investasi;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InvestmentRepository implements InvestmentRepositoryInterface
{
    private const CONN = 'server_mysql';

    private const DEFAULT_SOURCE = 16;

    private const STATUS_INBOUND = 'INBOUND';

    private const STATUS_OUTBOUND = 'OUTBOUND';

    private function toAlpha3List(mixed $v): array
    {
        if ($v === null) {
            return [];
        }
        if (is_string($v)) {
            if (strtolower(trim($v)) === 'all' || trim($v) === '') {
                return [];
            }

            return [strtoupper(trim($v))];
        }
        if (is_array($v)) {
            $out = [];
            foreach ($v as $item) {
                $s = strtoupper(trim((string) $item));
                if ($s !== '' && $s !== 'ALL') {
                    $out[] = $s;
                }
            }

            return array_values(array_unique($out));
        }

        return [];
    }

    /**
     * Normalisasi source -> array<int>
     */
    private function normalizedSourceCodes(array $filters): array
    {
        $src = $filters['source'] ?? self::DEFAULT_SOURCE;
        if (is_array($src)) {
            $arr = array_values(array_filter(array_map('intval', $src)));

            return $arr ?: [self::DEFAULT_SOURCE];
        }
        $v = (int) $src;

        return [$v ?: self::DEFAULT_SOURCE];
    }

    private function getSourceNames(array $codes): array
    {
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
            return array_fill_keys($codes, null);
        }

        $rows = DB::connection($conn)
            ->table($table)
            ->whereIn('KodeSumber', $codes)
            ->select('KodeSumber', 'NamaSumber')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r->KodeSumber] = $r->NamaSumber ? (string) $r->NamaSumber : null;
        }
        foreach ($codes as $c) {
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

    /** ------------------------------ */
    /** Helpers: apply filters ke query*/
    /** ------------------------------ */
    private function applyHome(Builder $q, array $filters): Builder
    {
        $country = strtoupper(trim((string) ($filters['country'] ?? 'IDN')));
        if ($country !== '' && $country !== 'ALL') {
            $q->where('t.Kode_Alpha3_Asal', $country);
        }

        return $q;
    }

    /**
     * Filter multi origin/dest untuk line chart (abaikan jika kosong).
     * Kolom investment: Kode_Alpha3_Asal, Kode_Alpha3_Tujuan.
     */
    private function applyOD(Builder $q, array $filters): Builder
    {
        $origins = $this->toAlpha3List($filters['origin'] ?? ($filters['origins'] ?? null));
        $dests = $this->toAlpha3List($filters['dest'] ?? ($filters['dests'] ?? null));

        if (! empty($origins)) {
            $q->whereIn('t.Kode_Alpha3_Asal', $origins);
        }
        if (! empty($dests)) {
            $q->whereIn('t.Kode_Alpha3_Tujuan', $dests);
        }

        return $q;
    }

    /**
     * Filter source (bisa scalar / array).
     */
    private function applySource(Builder $q, array $filters): Builder
    {
        $codes = $this->normalizedSourceCodes($filters);
        if (count($codes) === 1) {
            $q->where('t.Kode_Sumber', $codes[0]);
        } else {
            $q->whereIn('t.Kode_Sumber', $codes);
        }

        return $q;
    }

    /** ------------------------------ */
    /** Query utama                     */
    /** ------------------------------ */
    public function getLatestYear(array $filters): ?int
    {
        $row = DB::connection(self::CONN)
            ->table('tbinvestment as t')
            ->selectRaw('MAX(t.Tahun) as y')
            ->when(isset($filters['country']) && $filters['country'] !== 'all', fn ($q) => $this->applyHome($q, $filters))
            ->tap(fn ($q) => $this->applySource($q, $filters))
            ->first();

        return $row?->y ? (int) $row->y : null;
    }

    /**
     * Ringkasan single (berdasar country+source) dengan year & prevYear.
     */
    public function getSummary(array $filters): array
    {
        $year = (int) ($filters['year'] ?? 0);
        if ($year <= 0) {
            return [
                'inbound_value_now' => 0.0,
                'inbound_projects_now' => 0,
                'outbound_value_now' => 0.0,
                'inbound_value_prev' => 0.0,
                'inbound_projects_prev' => 0,
                'outbound_value_prev' => 0.0,
            ];
        }

        $prevYear = $year - 1;

        $row = DB::connection(self::CONN)
            ->table('tbinvestment as t')
            ->selectRaw('
                SUM(CASE WHEN t.Tahun = ? AND UPPER(t.Status)=? THEN t.Nilai_Investasi          ELSE 0 END) AS inbound_now,
                SUM(CASE WHEN t.Tahun = ? AND UPPER(t.Status)=? THEN COALESCE(t.Nilai_Proyek,0) ELSE 0 END) AS inbound_projects_now,
                SUM(CASE WHEN t.Tahun = ? AND UPPER(t.Status)=? THEN t.Nilai_Investasi          ELSE 0 END) AS outbound_now,

                SUM(CASE WHEN t.Tahun = ? AND UPPER(t.Status)=? THEN t.Nilai_Investasi          ELSE 0 END) AS inbound_prev,
                SUM(CASE WHEN t.Tahun = ? AND UPPER(t.Status)=? THEN COALESCE(t.Nilai_Proyek,0) ELSE 0 END) AS inbound_projects_prev,
                SUM(CASE WHEN t.Tahun = ? AND UPPER(t.Status)=? THEN t.Nilai_Investasi          ELSE 0 END) AS outbound_prev
            ', [
                $year,
                self::STATUS_INBOUND,
                $year,
                self::STATUS_INBOUND,
                $year,
                self::STATUS_OUTBOUND,
                $prevYear,
                self::STATUS_INBOUND,
                $prevYear,
                self::STATUS_INBOUND,
                $prevYear,
                self::STATUS_OUTBOUND,
            ])
            ->tap(fn ($q) => $this->applyHome($q, $filters))
            ->tap(fn ($q) => $this->applySource($q, $filters))
            ->first();

        return [
            'inbound_value_now' => (float) ($row->inbound_now ?? 0),
            'inbound_projects_now' => (int) ($row->inbound_projects_now ?? 0),
            'outbound_value_now' => (float) ($row->outbound_now ?? 0),

            'inbound_value_prev' => (float) ($row->inbound_prev ?? 0),
            'inbound_projects_prev' => (int) ($row->inbound_projects_prev ?? 0),
            'outbound_value_prev' => (float) ($row->outbound_prev ?? 0),
        ];
    }

    /**
     * Tabel inbound by partner (single: country+source), dengan year & prev.
     */
    public function getInboundByPartner(array $filters): array
    {
        $year = (int) ($filters['year'] ?? 0);
        if ($year <= 0) {
            return [];
        }

        $prevYear = $year - 1;
        $limit = (int) ($filters['limit'] ?? 20);

        $rows = DB::connection(self::CONN)
            ->table('tbinvestment as t')
            ->join('tbnegara as c', 'c.Kode_Alpha3', '=', 't.Kode_Alpha3_Tujuan')
            ->selectRaw('
        t.Kode_Alpha3_Tujuan as partner_code,
        MIN(c.Kode_Alpha2) as alpha2,
        MIN(COALESCE(c.Negara_IDN, t.Kode_Alpha3_Tujuan)) as partner_name,

        SUM(CASE WHEN t.Tahun = ? THEN t.Nilai_Investasi          ELSE 0 END) AS total_value_now,
        SUM(CASE WHEN t.Tahun = ? THEN COALESCE(t.Nilai_Proyek,0) ELSE 0 END) AS total_projects_now,

        SUM(CASE WHEN t.Tahun = ? THEN t.Nilai_Investasi          ELSE 0 END) AS total_value_prev,
        SUM(CASE WHEN t.Tahun = ? THEN COALESCE(t.Nilai_Proyek,0) ELSE 0 END) AS total_projects_prev
      ', [$year, $year, $prevYear, $prevYear])
            ->whereIn('t.Tahun', [$year, $prevYear])
            ->whereRaw('UPPER(t.Status) = ?', [self::STATUS_INBOUND])
            ->tap(fn ($q) => $this->applyHome($q, $filters))
            ->tap(fn ($q) => $this->applySource($q, $filters))
            ->groupBy('t.Kode_Alpha3_Tujuan')
            ->orderByDesc('total_value_now')
            ->when($limit > 0, fn ($q) => $q->limit($limit))
            ->get();

        return collect($rows)->map(fn ($r) => [
            'code' => (string) $r->partner_code,
            'alpha2' => (string) $r->alpha2,
            'label' => (string) $r->partner_name,
            'value_now' => (float) ($r->total_value_now ?? 0),
            'value_prev' => (float) ($r->total_value_prev ?? 0),
            'projects_now' => (int) ($r->total_projects_now ?? 0),
            'projects_prev' => (int) ($r->total_projects_prev ?? 0),
        ])->all();
    }

    /**
     * Tabel outbound by partner (single: country+source), dengan year & prev.
     */
    public function getOutboundByPartner(array $filters): array
    {
        $year = (int) ($filters['year'] ?? 0);
        if ($year <= 0) {
            return [];
        }

        $prevYear = $year - 1;
        $limit = (int) ($filters['limit'] ?? 20);

        $rows = DB::connection(self::CONN)
            ->table('tbinvestment as t')
            ->join('tbnegara as c', 'c.Kode_Alpha3', '=', 't.Kode_Alpha3_Tujuan')
            ->selectRaw('
        t.Kode_Alpha3_Tujuan as partner_code,
        MIN(c.Kode_Alpha2) as alpha2,
        MIN(COALESCE(c.Negara_IDN, t.Kode_Alpha3_Tujuan)) as partner_name,

        SUM(CASE WHEN t.Tahun = ? THEN t.Nilai_Investasi ELSE 0 END) AS total_value_now,
        SUM(CASE WHEN t.Tahun = ? THEN t.Nilai_Investasi ELSE 0 END) AS total_value_prev
      ', [$year, $prevYear])
            ->whereIn('t.Tahun', [$year, $prevYear])
            ->whereRaw('UPPER(t.Status) = ?', [self::STATUS_OUTBOUND])
            ->tap(fn ($q) => $this->applyHome($q, $filters))
            ->tap(fn ($q) => $this->applySource($q, $filters))
            ->groupBy('t.Kode_Alpha3_Tujuan')
            ->orderByDesc('total_value_now')
            ->when($limit > 0, fn ($q) => $q->limit($limit))
            ->get();

        return collect($rows)->map(fn ($r) => [
            'code' => (string) $r->partner_code,
            'alpha2' => (string) $r->alpha2,
            'label' => (string) $r->partner_name,
            'value_now' => (float) ($r->total_value_now ?? 0),
            'value_prev' => (float) ($r->total_value_prev ?? 0),
        ])->all();
    }

    /**
     * Timeseries multi (origin+dest) untuk line chart.
     * Mengembalikan meta + deret tahunan (inbound/outbound/volume/balance + projects).
     */
    public function getTimeseries(array $filters): array
    {
        // Rentang tahun opsional
        $year = isset($filters['year']) ? (int) $filters['year'] : null;
        $yearFrom = isset($filters['year_from']) ? (int) $filters['year_from'] : null;
        $yearTo = isset($filters['year_to']) ? (int) $filters['year_to'] : null;
        if ($year && ! $yearFrom && ! $yearTo) {
            $yearFrom = $yearTo = $year;
        }

        $q = DB::connection(self::CONN)
            ->table('tbinvestment as t')
            ->selectRaw("
                t.Tahun,
                SUM(CASE WHEN UPPER(t.Status)='".self::STATUS_INBOUND."'  THEN t.Nilai_Investasi          ELSE 0 END) AS inbound_value,
                SUM(CASE WHEN UPPER(t.Status)='".self::STATUS_INBOUND."'  THEN COALESCE(t.Nilai_Proyek,0) ELSE 0 END) AS inbound_projects,
                SUM(CASE WHEN UPPER(t.Status)='".self::STATUS_OUTBOUND."' THEN t.Nilai_Investasi          ELSE 0 END) AS outbound_value,
                SUM(CASE WHEN UPPER(t.Status)='".self::STATUS_OUTBOUND."' THEN COALESCE(t.Nilai_Proyek,0) ELSE 0 END) AS outbound_projects
            ")
            ->whereIn('t.Status', ['Inbound', 'Outbound']);

        // Multi OD (abaikan jika kosong)
        $q = $this->applyOD($q, $filters);
        // Source wajib
        $q = $this->applySource($q, $filters);

        // Rentang tahun (opsional)
        if ($yearFrom && $yearTo) {
            $q->whereBetween('t.Tahun', [$yearFrom, $yearTo]);
        } elseif ($yearFrom) {
            $q->where('t.Tahun', '>=', $yearFrom);
        } elseif ($yearTo) {
            $q->where('t.Tahun', '<=', $yearTo);
        }

        $rows = $q->groupBy('t.Tahun')
            ->orderBy('t.Tahun')
            ->get();

        $data = collect($rows)->map(function ($r) {
            $in = (float) ($r->inbound_value ?? 0);
            $out = (float) ($r->outbound_value ?? 0);

            return [
                'year' => (int) $r->Tahun,
                'inbound_value' => $in,
                'inbound_projects' => (int) ($r->inbound_projects ?? 0),
                'outbound_value' => $out,
                'outbound_projects' => (int) ($r->outbound_projects ?? 0),
                'volume' => $in + $out,
                'balance' => $in - $out,
            ];
        })->values()->all();

        // Tentukan meta year_from/year_to bila tidak diset
        if (! $yearFrom || ! $yearTo) {
            $years = array_column($data, 'year');
            $minY = $years ? min($years) : null;
            $maxY = $years ? max($years) : null;
        } else {
            $minY = $yearFrom;
            $maxY = $yearTo;
        }

        // Siapkan meta sumber
        $codes = $this->normalizedSourceCodes($filters);
        $names = $this->getSourceNames($codes);
        $dest = $this->toAlpha3List($filters['dest'] ?? ($filters['dests'] ?? null)) ?: null;
        $destNames = $this->resolveCountryNames($filters['dest'] ?? ($filters['dests'] ?? null));

        return [
            'meta' => [
                'origins' => $this->toAlpha3List($filters['origin'] ?? ($filters['origins'] ?? null)) ?: null,
                'dests' => $dest,
                'dest' => $dest,
                'origin_names' => $this->resolveCountryNames($filters['origin'] ?? ($filters['origins'] ?? null)),
                'dest_names' => $destNames,
                'dest_name' => $destNames,
                'source_name' => count($codes) === 1 ? $names[$codes[0]] : array_values($names),
                'year_from' => $minY,
                'year_to' => $maxY,
            ],
            'timeseries' => [
                'data' => $data,
            ],
        ];
    }

    /**
     * Composite endpoint: meta (+nama sumber), summary, tables, timeseries (opsional via include).
     */
    public function getComposite(array $filters, array $include): array
    {
        // Normalisasi default
        $filters['country'] = strtoupper(trim((string) ($filters['country'] ?? 'IDN')));
        $filters['source'] = $filters['source'] ?? self::DEFAULT_SOURCE;
        $filters['limit'] = (int) ($filters['limit'] ?? 20);

        // Tahun default
        $year = (int) ($filters['year'] ?? 0);
        if (! $year) {
            $year = $this->getLatestYear($filters);
        }

        // Siapkan meta sumber
        $codes = $this->normalizedSourceCodes($filters);
        $names = $this->getSourceNames($codes);
        $dest = $this->toAlpha3List($filters['dest'] ?? ($filters['dests'] ?? null)) ?: null;
        $destNames = $this->resolveCountryNames($filters['dest'] ?? ($filters['dests'] ?? null));

        // Jika tidak ada tahun yang valid
        if (! $year) {
            return [
                'meta' => [
                    'year' => null,
                    'prevYear' => null,
                    'country' => $filters['country'],
                    'country_name' => $this->resolveCountryNames($filters['country']),
                    'dest' => $dest,
                    'dest_name' => $destNames,
                    'source_name' => count($codes) === 1 ? $names[$codes[0]] : array_values($names),
                ],
                'summary' => null,
                'table_inbound' => [],
                'table_outbound' => [],
            ];
        }

        $filters['year'] = $year;
        $prevYear = $year > 0 ? $year - 1 : null;
        $out = [
            'meta' => [
                'year' => $year,
                'prevYear' => $prevYear,
                'country' => $filters['country'],
                'country_name' => $this->resolveCountryNames($filters['country']),
                'dest' => $dest,
                'dest_name' => $destNames,
                'source_name' => count($codes) === 1 ? $names[$codes[0]] : array_values($names),
            ],
        ];

        // Default include jika kosong
        $include = $include ?: ['summary', 'table_inbound', 'table_outbound'];

        if (in_array('summary', $include, true)) {
            $sum = $this->getSummary($filters);
            $out['summary'] = [
                'inbound' => [
                    'value_now' => $sum['inbound_value_now'] ?? 0.0,
                    'value_prev' => $sum['inbound_value_prev'] ?? 0.0,
                    'projects_now' => $sum['inbound_projects_now'] ?? 0,
                    'projects_prev' => $sum['inbound_projects_prev'] ?? 0,
                ],
                'outbound' => [
                    'value_now' => $sum['outbound_value_now'] ?? 0.0,
                    'value_prev' => $sum['outbound_value_prev'] ?? 0.0,
                ],
            ];
        }

        if (in_array('table_inbound', $include, true)) {
            $out['table_inbound'] = $this->getInboundByPartner($filters);
        }

        if (in_array('table_outbound', $include, true)) {
            $out['table_outbound'] = $this->getOutboundByPartner($filters);
        }

        if (in_array('timeseries', $include, true)) {
            // timeseries multi: gunakan origin/dest dari filters jika ada
            $out['timeseries'] = $this->getTimeseries($filters);
        }

        return $out;
    }
}
