<?php

namespace App\Repositories\SektorJasa\DataPMI;

use Illuminate\Support\Facades\DB;

class DataPMIRepository implements DataPMIRepositoryInterface
{
    protected string $conn = 'server_mysql';

    protected string $table = 'tbILO_Citizenship';

    protected string $tableNeg = 'tbnegara';

    protected string $tableJob = 'tbILO_Job';

    protected string $tableSumber = 'tbsumber';

    protected string $defaultSource = 'International Labour Organization';

    protected string $defaultUnit = 'orang';

    /* ========================= Helper Tahun ========================= */

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
            $years5 = array_values(
                array_filter($years5, fn ($y) => $y >= $start && $y <= $end)
            );
        }

        return $years5;
    }

    /* ========================= Base Query ========================= */

    protected function baseQuery(array $filters, array $yearsWindow)
    {
        $q = DB::connection($this->conn)->table($this->table.' as c');

        $partners    = $filters['partners'] ?? [];
        $partnersAll = ! empty($filters['partners_all']);

        if (! $partnersAll && ! empty($partners)) {
            $q->whereIn('c.KodeAlpha3', $partners);
        }

        if (! empty($yearsWindow)) {
            $q->whereIn('c.Tahun', $yearsWindow);
        }

        return $q;
    }

    /* ========================= Meta Negara ========================= */

    protected function partnersMeta(array $partnersA3): array
    {
        if (empty($partnersA3)) {
            return [];
        }

        $partnersA3 = array_values(array_unique($partnersA3));

        $rows = DB::connection($this->conn)
            ->table($this->tableNeg.' as n')
            ->whereIn('n.Kode_Alpha3', $partnersA3)
            ->selectRaw('n.Kode_Alpha3 as a3, n.Kode_Alpha2 as a2, n.Negara_IDN as name')
            ->orderBy('n.Negara_IDN')
            ->get();

        $byA3 = [];
        foreach ($rows as $r) {
            $a3       = (string) $r->a3;
            $byA3[$a3] = [
                'a3'   => $a3,
                'a2'   => (string) $r->a2,
                'name' => (string) $r->name,
            ];
        }

        usort($byA3, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return array_values($byA3);
    }

    protected function partnersMetaByFiltersAndYears(array $filters, array $yearsWindow): array
    {
        $partners    = $filters['partners'] ?? [];
        $partnersAll = ! empty($filters['partners_all']);

        if (! $partnersAll && ! empty($partners)) {
            return $this->partnersMeta($partners);
        }

        if (empty($yearsWindow)) {
            return [];
        }

        $a3List = DB::connection($this->conn)
            ->table($this->table.' as c')
            ->whereIn('c.Tahun', $yearsWindow)
            ->distinct()
            ->pluck('c.KodeAlpha3')
            ->map(fn ($v) => (string) $v)
            ->values()
            ->all();

        return $this->partnersMeta($a3List);
    }

    /* ========================= Source & Unit (DEFAULT ONLY) ========================= */

    protected function resolveSourceAndUnit(array $filters, array $yearsWindow): array
    {
        // Tidak cek ke DB lagi, langsung pakai default / override dari filter jika ada
        $source = $filters['source'] ?? $this->defaultSource;
        $unit   = $filters['unit']   ?? $this->defaultUnit;

        return [
            'source' => (string) $source,
            'unit'   => (string) $unit,
        ];
    }

    /* ========================= Citizenship Aggregation ========================= */

    protected function aggregateCitizenship(array $filters, array $yearsWindow): array
    {
        $q = $this->baseQuery($filters, $yearsWindow);

        return (clone $q)
            ->selectRaw('c.Citizenship as cit_key, c.Citizenship as label, SUM(c.Jumlah) as value')
            ->groupBy('c.Citizenship')
            ->orderByDesc('value')
            ->get()
            ->map(fn ($r) => [
                'key'   => (string) $r->cit_key,
                'label' => (string) $r->label,
                'value' => (float) $r->value,
            ])
            ->values()
            ->all();
    }

    /* ========================= getNilaiJasa ========================= */

    public function getNilaiJasa(array $filters): array
    {
        $yearsWindow = $this->computeYearsWindow($filters);
        $qBase       = $this->baseQuery($filters, $yearsWindow);

        $rows = (clone $qBase)
            ->selectRaw('c.Tahun as year, c.KodeAlpha3 as a3, SUM(c.Jumlah) as value')
            ->groupBy('c.Tahun', 'c.KodeAlpha3')
            ->orderBy('c.Tahun')
            ->get()
            ->map(fn ($r) => [
                'year'  => (int) $r->year,
                'a3'    => (string) $r->a3,
                'value' => (float) $r->value,
            ])
            ->all();

        $distribusiByYear = [];
        foreach ($rows as $r) {
            $y = (string) $r['year'];
            if (! isset($distribusiByYear[$y])) {
                $distribusiByYear[$y] = [
                    'year'  => $r['year'],
                    'value' => 0.0,
                ];
            }
            $distribusiByYear[$y]['value'] += $r['value'];
        }

        ksort($distribusiByYear);
        $distribusiPerTahun = array_values($distribusiByYear);

        $citizenship = $this->aggregateCitizenship($filters, $yearsWindow);

        $jobs = $this->baseQuery($filters, $yearsWindow)
            ->join($this->tableJob.' as j', 'j.JobID', '=', 'c.JobID')
            ->selectRaw('j.JobID as job_id, j.Pekerjaan as label, SUM(c.Jumlah) as value')
            ->groupBy('j.JobID', 'j.Pekerjaan')
            ->orderByDesc('value')
            ->get()
            ->map(fn ($r) => [
                'job_id' => (int) $r->job_id,
                'label'  => (string) $r->label,
                'value'  => (float) $r->value,
            ])
            ->values()
            ->all();

        $scoreByYear = [];
        foreach ($rows as $r) {
            $a3 = $r['a3'];
            $y  = (string) $r['year'];
            $scoreByYear[$a3] ??= [];
            $scoreByYear[$a3][$y] = $r['value'];
        }

        $partnersMetaList = $this->partnersMetaByFiltersAndYears($filters, $yearsWindow);

        $partnersIndex = [];
        foreach ($partnersMetaList as $m) {
            $partnersIndex[$m['a3']] = $m;
        }

        $map = [];
        foreach ($scoreByYear as $a3 => $scoreYears) {
            $meta = $partnersIndex[$a3] ?? [
                'a3'   => $a3,
                'a2'   => null,
                'name' => $a3,
            ];

            $map[] = [
                'a3'            => $meta['a3'],
                'a2'            => $meta['a2'],
                'name'          => $meta['name'],
                'score_by_year' => $scoreYears,
            ];
        }

        $metaSU = $this->resolveSourceAndUnit($filters, $yearsWindow);

        return [
            'distribusi_pmi' => [
                'data' => $distribusiPerTahun,
            ],
            'citizenship' => [
                'data' => $citizenship,
            ],
            'komposisi_pekerjaan' => [
                'data' => $jobs,
            ],
            'peta_persebaran_pmi' => [
                'data' => $map,
            ],
            'meta' => [
                'partners' => $partnersMetaList,
                'years'    => $yearsWindow,
                'source'   => $metaSU['source'],
                'unit'     => $metaSU['unit'],
            ],
        ];
    }

    /* ========================= getStats ========================= */

    public function getStats(array $filters): array
    {
        $yearsWindow = $this->computeYearsWindow($filters);
        $metaSU      = $this->resolveSourceAndUnit($filters, $yearsWindow);

        if (empty($yearsWindow)) {
            return [
                'kpi' => [
                    'total_pekerja_migran' => [
                        'series' => [],
                    ],
                    'persebaran_yoy' => [
                        'value' => null,
                    ],
                    'citizenship' => [
                        'data' => [],
                    ],
                ],
                'meta' => [
                    'partners' => [],
                    'years'    => [],
                    'source'   => $metaSU['source'],
                    'unit'     => $metaSU['unit'],
                ],
            ];
        }

        $q = $this->baseQuery($filters, $yearsWindow);

        $series = (clone $q)
            ->selectRaw('c.Tahun as year, SUM(c.Jumlah) as value')
            ->groupBy('c.Tahun')
            ->orderBy('c.Tahun')
            ->get()
            ->map(fn ($r) => [
                'year'  => (int) $r->year,
                'value' => (float) $r->value,
            ])
            ->values()
            ->all();

        $persebaranYoy = null;
        if (count($series) >= 2) {
            $last = $series[count($series) - 1];
            $prev = $series[count($series) - 2];

            $pv = $prev['value'] ?? 0.0;
            $cv = $last['value'] ?? 0.0;

            if ($pv != 0.0) {
                $persebaranYoy = (($cv - $pv) / $pv) * 100.0;
            }
        }

        $citizenship = $this->aggregateCitizenship($filters, $yearsWindow);

        return [
            'kpi' => [
                'total_pekerja_migran' => [
                    'series' => $series,
                ],
                'persebaran_yoy' => [
                    'value' => $persebaranYoy,
                ],
                'citizenship' => [
                    'data' => $citizenship,
                ],
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
