<?php

namespace App\Repositories\SektorJasa\Overview;

use Illuminate\Support\Facades\DB;

class OverviewRepository implements OverviewRepositoryInterface
{
    protected string $conn = 'server_mysql';
    protected string $table = 'tbservices';
    protected string $tableNeg = 'tbnegara';
    protected string $tableSumber = 'tbsumber';
    protected string $defaultSource = '';
    protected string $defaultUnit   = 'orang';

    protected function last5Years(): array
    {
        $years = DB::connection($this->conn)
            ->table($this->table)
            ->distinct()
            ->orderBy('Tahun', 'desc')
            ->limit(5)
            ->pluck('Tahun')
            ->map(fn ($y) => (int) $y)
            ->values()
            ->all();

        sort($years);
        return $years;
    }

    protected function computeYearsWindow(array $filters): array
    {
        $years5 = $this->last5Years();

        $start = $filters['year_start'] ?? null;
        $end   = $filters['year_end'] ?? null;

        if ($start || $end) {
            $start  = $start ?? ($years5[0] ?? null);
            $end    = $end   ?? ($years5[count($years5) - 1] ?? null);
            $years5 = array_values(array_filter($years5, fn ($y) => $y >= $start && $y <= $end));
        }

        return $years5;
    }

    protected function baseQuery(array $filters, array $yearsWindow)
    {
        $q = DB::connection($this->conn)->table($this->table);

        $partners    = $filters['partners'] ?? [];
        $partnersAll = !empty($filters['partners_all']);

        if (!$partnersAll && !empty($partners)) {
            $q->whereIn('Kode_Alpha3_Tujuan', $partners);
        }

        if (!empty($yearsWindow)) {
            $q->whereIn('Tahun', $yearsWindow);
        }

        return $q;
    }

    protected function partnersMeta(array $partnersA3): array
    {
        if (empty($partnersA3)) {
            return [];
        }

        return DB::connection($this->conn)
            ->table($this->tableNeg)
            ->whereIn('Kode_Alpha3', $partnersA3)
            ->selectRaw('Kode_Alpha3 as a3, Kode_Alpha2 as a2, Negara_IDN as name')
            ->orderBy('Negara_IDN')
            ->get()
            ->map(fn ($r) => [
                'a3'   => (string) $r->a3,
                'a2'   => (string) $r->a2,
                'name' => (string) $r->name,
            ])
            ->values()
            ->all();
    }

    protected function partnersMetaByFiltersAndYears(array $filters, array $yearsWindow): array
    {
        $partners    = $filters['partners'] ?? [];
        $partnersAll = !empty($filters['partners_all']);

        if (!$partnersAll && !empty($partners)) {
            return $this->partnersMeta($partners);
        }

        if (empty($yearsWindow)) {
            return [];
        }

        $a3List = DB::connection($this->conn)
            ->table($this->table)
            ->whereIn('Tahun', $yearsWindow)
            ->distinct()
            ->pluck('Kode_Alpha3_Tujuan')
            ->map(fn ($v) => (string) $v)
            ->values()
            ->all();

        return $this->partnersMeta($a3List);
    }

    protected function resolveSourceAndUnit(array $filters, array $yearsWindow): array
    {
        $srcQuery = DB::connection($this->conn)
            ->table($this->table . ' as s')
            ->leftJoin($this->tableSumber . ' as m', 'm.KodeSumber', '=', 's.KodeSumber');

        if (!empty($yearsWindow)) {
            $srcQuery->whereIn('s.Tahun', $yearsWindow);
        }

        $srcRow = $srcQuery
            ->whereNotNull('m.NamaSumber')
            ->where('m.NamaSumber', '!=', '')
            ->orderBy('s.Tahun', 'desc')
            ->select('m.NamaSumber')
            ->first();

        $dbSource = $srcRow->NamaSumber ?? null;

        $source = $dbSource ?: ($filters['source'] ?? $this->defaultSource);
        $unit   = $filters['unit'] ?? $this->defaultUnit;

        return [
            'source' => (string) $source,
            'unit'   => (string) $unit,
        ];
    }

    public function getNilaiJasa(array $filters): array
    {
        $yearsWindow = $this->computeYearsWindow($filters);
        $q = $this->baseQuery($filters, $yearsWindow);

        $rows = (clone $q)
            ->selectRaw('Tahun as year, Kode_Alpha3_Tujuan as a3, SUM(Jumlah) as value')
            ->groupBy('Tahun', 'Kode_Alpha3_Tujuan')
            ->orderBy('Tahun')
            ->get()
            ->map(fn ($r) => [
                'year'  => (int) $r->year,
                'a3'    => (string) $r->a3,
                'value' => (float) $r->value,
            ])
            ->all();

        $byYear = [];
        foreach ($rows as $r) {
            $y = (string) $r['year'];
            $byYear[$y] ??= [];
            $byYear[$y][] = ['a3' => $r['a3'], 'value' => $r['value']];
        }

        foreach ($byYear as $y => &$arr) {
            usort($arr, fn ($a, $b) => $b['value'] <=> $a['value']);
        }
        unset($arr);

        $partnersMeta = $this->partnersMetaByFiltersAndYears($filters, $yearsWindow);
        $metaSU       = $this->resolveSourceAndUnit($filters, $yearsWindow);

        $result = $byYear;
        $result['meta'] = [
            'partners' => $partnersMeta,
            'years'    => $yearsWindow,
            'source'   => $metaSU['source'],
            'unit'     => $metaSU['unit'],
        ];

        return $result;
    }

    public function getStats(array $filters): array
    {
        $yearsWindow = $this->computeYearsWindow($filters);
        $metaSU = $this->resolveSourceAndUnit($filters, $yearsWindow);

        if (empty($yearsWindow)) {
            return [
                'kpi' => [
                    'total_pekerja_migran' => [],
                    'negara_teratas'       => null,
                    'avg_growth_yoy'       => null,
                ],
                'meta' => [
                    'partners' => [],
                    'years'    => [],
                    'source'   => $metaSU['source'],
                    'unit'     => $metaSU['unit'],
                ],
            ];
        }

        $latest = $yearsWindow[count($yearsWindow) - 1];
        $prev   = $yearsWindow[count($yearsWindow) - 2] ?? null;

        $q = $this->baseQuery($filters, $yearsWindow);

        $latestVal = (clone $q)->where('Tahun', $latest)->sum('Jumlah');
        $prevVal   = $prev !== null ? (clone $q)->where('Tahun', $prev)->sum('Jumlah') : null;

        $totalSubarray = [];
        if ($prev !== null) {
            $totalSubarray[] = ['year' => $prev, 'value' => (float) $prevVal];
        }
        $totalSubarray[] = ['year' => $latest, 'value' => (float) $latestVal];

        $top = (clone $q)
            ->where('Tahun', $latest)
            ->selectRaw('Kode_Alpha3_Tujuan as a3, SUM(Jumlah) as nilai')
            ->groupBy('Kode_Alpha3_Tujuan')
            ->orderByDesc(DB::raw('SUM(Jumlah)'))
            ->first();

        $negaraTeratas = $top ? [
            'year'  => $latest,
            'a3'    => (string) $top->a3,
            'nilai' => (float) $top->nilai,
        ] : null;

        $series = (clone $q)
            ->selectRaw('Tahun as year, SUM(Jumlah) as value')
            ->groupBy('Tahun')
            ->orderBy('Tahun')
            ->get()
            ->map(fn ($r) => ['year' => (int) $r->year, 'value' => (float) $r->value])
            ->values()
            ->all();

        $growths = [];
        for ($i = 1; $i < count($series); $i++) {
            $pv = $series[$i - 1]['value'] ?? 0.0;
            $cv = $series[$i]['value'] ?? 0.0;
            if ($pv == 0.0) continue;
            $growths[] = (($cv - $pv) / $pv) * 100.0;
        }
        $avgGrowth = count($growths) ? array_sum($growths) / count($growths) : null;

        return [
            'kpi' => [
                'total_pekerja_migran' => $totalSubarray,
                'negara_teratas'       => $negaraTeratas,
                'avg_growth_yoy'       => $avgGrowth,
            ],
            'meta' => [
                'partners' => $this->partnersMetaByFiltersAndYears($filters, $yearsWindow),
                'years'    => $yearsWindow,
                'source'   => $metaSU['source'],
                'unit'     => $metaSU['unit'],
            ],
        ];
    }
}
