<?php

namespace App\Repositories\SektorJasa\InsightPasarPMI;

use Illuminate\Support\Facades\DB;

class InsightPasarPMIRepository implements InsightPasarPMIRepositoryInterface
{
    protected string $conn         = 'server_mysql';

    protected string $tableEdu     = 'tbILO_Education';
    protected string $tableJob     = 'tbILO_Job';
    protected string $tableWage    = 'tbILO_Wage';
    protected string $tableNeg     = 'tbnegara';
    protected string $tableKawasan = 'tbkawasan';
    protected string $tableBenua   = 'tbbenua';

    protected string $defaultSource = 'International Labour Organization';
    protected string $defaultUnit   = 'orang';

    /* =======================================================================
     *  Helpers Tahun & Meta
     * ======================================================================= */

    protected function last5Years(): array
    {
        $years = DB::connection($this->conn)
            ->table($this->tableEdu . ' as e')
            ->distinct()
            ->orderBy('e.Tahun', 'desc')
            ->limit(5)
            ->pluck('e.Tahun')
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
            $start = $start ?? ($years5[0] ?? null);
            $end   = $end   ?? ($years5[count($years5) - 1] ?? null);

            $years5 = array_values(
                array_filter(
                    $years5,
                    fn ($y) => $y >= $start && $y <= $end
                )
            );
        }

        return $years5;
    }

    protected function baseEducationQuery(array $filters, array $yearsWindow)
    {
        $q = DB::connection($this->conn)->table($this->tableEdu . ' as e');

        $partners    = $filters['partners'] ?? [];
        $partnersAll = !empty($filters['partners_all']);

        if (!$partnersAll && !empty($partners)) {
            $q->whereIn('e.KodeAlpha3', $partners);
        }

        if (!empty($yearsWindow)) {
            $q->whereIn('e.Tahun', $yearsWindow);
        }

        return $q;
    }

    protected function baseWageQuery(array $filters, array $yearsWindow)
    {
        $q = DB::connection($this->conn)->table($this->tableWage . ' as w');

        $partners    = $filters['partners'] ?? [];
        $partnersAll = !empty($filters['partners_all']);

        if (!$partnersAll && !empty($partners)) {
            $q->whereIn('w.KodeAlpha3', $partners);
        }

        if (!empty($yearsWindow)) {
            $q->whereIn('w.Tahun', $yearsWindow);
        }

        return $q;
    }

    protected function partnersMeta(array $partnersA3): array
    {
        if (empty($partnersA3)) {
            return [];
        }

        $partnersA3 = array_values(array_unique($partnersA3));

        $rows = DB::connection($this->conn)
            ->table($this->tableNeg . ' as n')
            ->whereIn('n.Kode_Alpha3', $partnersA3)
            ->selectRaw('
                n.Kode_Alpha3 as a3,
                n.Kode_Alpha2 as a2,
                n.Negara_IDN as name
            ')
            ->orderBy('n.Negara_IDN')
            ->get();

        $byA3 = [];
        foreach ($rows as $r) {
            $a3        = (string) $r->a3;
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
        $partnersAll = !empty($filters['partners_all']);

        if (!$partnersAll && !empty($partners)) {
            return $this->partnersMeta($partners);
        }

        if (empty($yearsWindow)) {
            return [];
        }

        $a3List = DB::connection($this->conn)
            ->table($this->tableEdu . ' as e')
            ->whereIn('e.Tahun', $yearsWindow)
            ->distinct()
            ->pluck('e.KodeAlpha3')
            ->map(fn ($v) => (string) $v)
            ->values()
            ->all();

        return $this->partnersMeta($a3List);
    }

    protected function resolveSourceAndUnit(array $filters, array $yearsWindow): array
    {
        $source = $filters['source'] ?? $this->defaultSource;
        $unit   = $filters['unit'] ?? $this->defaultUnit;

        return [
            'source' => (string) $source,
            'unit'   => (string) $unit,
        ];
    }

    /* =======================================================================
     *  STATS:
     *  - estimasi_peluang_penempatan
     *  - sektor_prioritas
     *  - negara_peluang
     * ======================================================================= */

    public function getStats(array $filters): array
    {
        $yearsWindow = $this->computeYearsWindow($filters);
        $metaSU      = $this->resolveSourceAndUnit($filters, $yearsWindow);

        if (empty($yearsWindow)) {
            return [
                'kpi'  => [
                    'estimasi_peluang_penempatan' => ['value' => 0],
                    'sektor_prioritas'            => ['job_id' => null, 'label' => null, 'value' => 0],
                    'negara_peluang'              => ['a3' => null, 'a2' => null, 'name' => null, 'value' => 0],
                ],
                'meta' => [
                    'partners' => [],
                    'years'    => [],
                    'source'   => $metaSU['source'],
                    'unit'     => $metaSU['unit'],
                ],
            ];
        }

        $qEdu = $this->baseEducationQuery($filters, $yearsWindow);

        $totalWorkers = (clone $qEdu)
            ->selectRaw('SUM(e.Jumlah) as total')
            ->value('total') ?? 0;

        $topJobRow = (clone $qEdu)
            ->join($this->tableJob . ' as j', 'j.JobID', '=', 'e.JobID')
            ->selectRaw('
                j.JobID as job_id,
                j.Pekerjaan as label,
                SUM(e.Jumlah) as total
            ')
            ->groupBy('j.JobID', 'j.Pekerjaan')
            ->orderByDesc('total')
            ->limit(1)
            ->first();

        $sektorPrioritas = [
            'job_id' => $topJobRow?->job_id !== null ? (int) $topJobRow->job_id : null,
            'label'  => $topJobRow?->label !== null ? (string) $topJobRow->label : null,
            'value'  => $topJobRow?->total !== null ? (float) $topJobRow->total : 0.0,
        ];

        $topCountryRow = (clone $qEdu)
            ->leftJoin($this->tableNeg . ' as n', 'n.Kode_Alpha3', '=', 'e.KodeAlpha3')
            ->selectRaw('
                e.KodeAlpha3 as a3,
                MAX(COALESCE(NULLIF(n.Kode_Alpha2, ""), e.KodeAlpha3)) as a2,
                MAX(COALESCE(NULLIF(n.Negara_IDN, ""), e.KodeAlpha3)) as country,
                SUM(e.Jumlah) as total
            ')
            ->groupBy('e.KodeAlpha3')
            ->orderByDesc('total')
            ->limit(1)
            ->first();

        $negaraPeluang = [
            'a3'    => $topCountryRow?->a3 !== null ? (string) $topCountryRow->a3 : null,
            'a2'    => $topCountryRow?->a2 !== null ? (string) $topCountryRow->a2 : null,
            'name'  => $topCountryRow?->country !== null ? (string) $topCountryRow->country : null,
            'value' => $topCountryRow?->total !== null ? (float) $topCountryRow->total : 0.0,
        ];

        return [
            'kpi'  => [
                'estimasi_peluang_penempatan' => [
                    'value' => (float) $totalWorkers,
                ],
                'sektor_prioritas'            => $sektorPrioritas,
                'negara_peluang'              => $negaraPeluang,
            ],
            'meta' => [
                'partners' => $this->partnersMetaByFiltersAndYears($filters, $yearsWindow),
                'years'    => $yearsWindow,
                'source'   => $metaSU['source'],
                'unit'     => $metaSU['unit'],
            ],
        ];
    }

    /* =======================================================================
     *  NILAI JASA (Insight Pasar PMI)
     *  - ranking_jumlah_pekerja_migran_asing
     *  - proporsi_pekerja_migran_asing_seiring_waktu
     *  - aliran_pekerja_migran_sektor_ke_negara
     *  - proporsi_pekerja_migran_asing_berdasarkan_gender
     *  - pertumbuhan_yoy_pekerja_migran
     *  - ranking_pertumbuhan_yoy
     * ======================================================================= */

    public function getNilaiJasa(array $filters): array
    {
        $yearsWindow = $this->computeYearsWindow($filters);
        $metaSU      = $this->resolveSourceAndUnit($filters, $yearsWindow);

        if (empty($yearsWindow)) {
            return [
                'ranking_jumlah_pekerja_migran_asing'               => ['data' => []],
                'proporsi_pekerja_migran_asing_seiring_waktu'       => ['data' => []],
                'aliran_pekerja_migran_sektor_ke_negara'            => ['nodes' => [], 'links' => []],
                'proporsi_pekerja_migran_asing_berdasarkan_gender'  => ['data' => []],
                'pertumbuhan_yoy_pekerja_migran'                    => ['data' => []],
                'ranking_pertumbuhan_yoy'                           => ['data' => []],
                'meta'                                              => [
                    'partners' => [],
                    'years'    => [],
                    'source'   => $metaSU['source'],
                    'unit'     => $metaSU['unit'],
                ],
            ];
        }

        $qEdu  = $this->baseEducationQuery($filters, $yearsWindow);
        $qWage = $this->baseWageQuery($filters, $yearsWindow);

        $lastYear = $yearsWindow[count($yearsWindow) - 1];
        $prevYear = count($yearsWindow) >= 2
            ? $yearsWindow[count($yearsWindow) - 2]
            : null;

        /* --------------------------------------------------------------
         * 1) Ranking Jumlah Pekerja Migran Asing
         *    induk: negara, sub: years[]
         * -------------------------------------------------------------- */
        $yearsForRank = $prevYear !== null ? [$prevYear, $lastYear] : [$lastYear];

        $qEduRank = $this->baseEducationQuery($filters, $yearsForRank);

        $rankRows = (clone $qEduRank)
            ->leftJoin($this->tableNeg . ' as n', 'n.Kode_Alpha3', '=', 'e.KodeAlpha3')
            ->selectRaw('
                e.Tahun as year,
                e.KodeAlpha3 as a3,
                MAX(COALESCE(NULLIF(n.Kode_Alpha2, ""), e.KodeAlpha3)) as a2,
                MAX(COALESCE(NULLIF(n.Negara_IDN, ""), e.KodeAlpha3)) as country,
                SUM(e.Jumlah) as total
            ')
            ->groupBy('e.Tahun', 'e.KodeAlpha3')
            ->orderBy('e.Tahun', 'asc')
            ->orderByDesc('total')
            ->get();

        $rankingByCountry = [];

        foreach ($rankRows as $r) {
            $a3   = (string) $r->a3;
            $a2   = (string) $r->a2;
            $name = (string) $r->country;

            if (!isset($rankingByCountry[$a3])) {
                $rankingByCountry[$a3] = [
                    'a3'    => $a3,
                    'a2'    => $a2,
                    'name'  => $name,
                    'years' => [],
                ];
            }

            $rankingByCountry[$a3]['years'][] = [
                'year'  => (int) $r->year,
                'value' => (float) $r->total,
            ];
        }

        foreach ($rankingByCountry as &$item) {
            usort($item['years'], fn ($a, $b) => $a['year'] <=> $b['year']);
        }
        unset($item);

        usort($rankingByCountry, function ($a, $b) {
            $lastA = $a['years'] ? end($a['years'])['value'] : 0;
            $lastB = $b['years'] ? end($b['years'])['value'] : 0;
            return $lastB <=> $lastA;
        });

        $rankingJumlah = array_values($rankingByCountry);

        /* --------------------------------------------------------------
         * 2) Proporsi Pekerja Migran Asing Seiring Waktu
         *    per tahun, di dalamnya daftar negara:
         *    items: [{ a3, name, value }]
         * -------------------------------------------------------------- */
        $propRows = (clone $qEdu)
            ->leftJoin($this->tableNeg . ' as n', 'n.Kode_Alpha3', '=', 'e.KodeAlpha3')
            ->selectRaw('
                e.Tahun as year,
                e.KodeAlpha3 as a3,
                COALESCE(NULLIF(n.Negara_IDN, ""), e.KodeAlpha3) as country,
                SUM(e.Jumlah) as total
            ')
            ->groupBy('e.Tahun', 'e.KodeAlpha3', 'n.Negara_IDN')
            ->orderBy('e.Tahun')
            ->get();

        $byYearCountry = [];
        foreach ($propRows as $r) {
            $year   = (int) $r->year;
            $a3     = (string) $r->a3;
            $name   = (string) $r->country;
            $total  = (float) $r->total;

            $byYearCountry[$year] ??= [];
            $byYearCountry[$year][] = [
                'a3'    => $a3,
                'name'  => $name,
                'value' => $total,
            ];
        }

        ksort($byYearCountry);

        $proporsiSeiringWaktu = [];
        foreach ($byYearCountry as $year => $items) {
            $proporsiSeiringWaktu[] = [
                'year'  => (int) $year,
                'items' => $items,
            ];
        }

        /* --------------------------------------------------------------
         * 3) Aliran Pekerja Migran: Sektor ke Negara Tujuan
         * -------------------------------------------------------------- */
        $flowRows = (clone $qEdu)
            ->join($this->tableJob . ' as j', 'j.JobID', '=', 'e.JobID')
            ->leftJoin($this->tableNeg . ' as n', 'n.Kode_Alpha3', '=', 'e.KodeAlpha3')
            ->selectRaw('
                j.JobID as job_id,
                j.Pekerjaan as job_label,
                e.KodeAlpha3 as a3,
                COALESCE(NULLIF(n.Negara_IDN, ""), e.KodeAlpha3) as country,
                SUM(e.Jumlah) as total
            ')
            ->groupBy('j.JobID', 'j.Pekerjaan', 'e.KodeAlpha3', 'n.Negara_IDN')
            ->orderByDesc('total')
            ->limit(80)
            ->get();

        $nodesMap = [];
        $nodes    = [];
        $links    = [];

        foreach ($flowRows as $r) {
            $jobId    = (int) $r->job_id;
            $jobLabel = (string) $r->job_label;
            $a3       = (string) $r->a3;
            $country  = (string) $r->country;
            $value    = (float) $r->total;

            if ($value <= 0) {
                continue;
            }

            if (!isset($nodesMap[$jobId])) {
                $nodesMap[$jobId] = true;
                $nodes[]          = [
                    'job_id' => $jobId,
                    'label'  => $jobLabel,
                ];
            }

            $links[] = [
                'job_id'    => $jobId,
                'job_label' => $jobLabel,
                'a3'        => $a3,
                'country'   => $country,
                'value'     => $value,
            ];
        }

        /* --------------------------------------------------------------
         * 4) Proporsi Pekerja Migran Asing berdasarkan Gender (per tahun)
         * -------------------------------------------------------------- */
        $genderYearRows = (clone $qEdu)
            ->selectRaw('
                e.Tahun as year,
                e.Gender as gender,
                SUM(e.Jumlah) as total
            ')
            ->groupBy('e.Tahun', 'e.Gender')
            ->orderBy('e.Tahun')
            ->get();

        $byYearGender = [];
        foreach ($genderYearRows as $r) {
            $year   = (int) $r->year;
            $gender = (string) $r->gender;
            $total  = (float) $r->total;

            $byYearGender[$year] ??= ['male' => 0.0, 'female' => 0.0];

            if (strcasecmp($gender, 'Male') === 0) {
                $byYearGender[$year]['male'] += $total;
            } elseif (strcasecmp($gender, 'Female') === 0) {
                $byYearGender[$year]['female'] += $total;
            }
        }

        $proporsiGenderData = [];
        foreach ($byYearGender as $year => $vals) {
            $tot = max(1.0, $vals['male'] + $vals['female']);
            $proporsiGenderData[] = [
                'year'       => (int) $year,
                'male'       => (float) $vals['male'],
                'female'     => (float) $vals['female'],
                'male_pct'   => ($vals['male'] / $tot) * 100.0,
                'female_pct' => ($vals['female'] / $tot) * 100.0,
            ];
        }

        /* --------------------------------------------------------------
         * 5) Pertumbuhan YoY Pekerja Migran (total)
         * -------------------------------------------------------------- */
        $yearTotalRows = (clone $qEdu)
            ->selectRaw('
                e.Tahun as year,
                SUM(e.Jumlah) as total
            ')
            ->groupBy('e.Tahun')
            ->orderBy('e.Tahun')
            ->get();

        $pertumbuhanYoy = [];
        $prevTotal      = null;

        foreach ($yearTotalRows as $r) {
            $year  = (int) $r->year;
            $total = (float) $r->total;

            $growth = null;
            if ($prevTotal !== null && $prevTotal != 0.0) {
                $growth = (($total - $prevTotal) / $prevTotal) * 100.0;
            }

            $pertumbuhanYoy[] = [
                'year'  => $year,
                'value' => $growth,
                'total' => $total,
            ];

            $prevTotal = $total;
        }

        /* --------------------------------------------------------------
         * 6) Ranking Pertumbuhan (% YoY) per negara
         * -------------------------------------------------------------- */
        $countryYearRows = (clone $qEdu)
            ->leftJoin($this->tableNeg . ' as n', 'n.Kode_Alpha3', '=', 'e.KodeAlpha3')
            ->selectRaw('
                e.KodeAlpha3 as a3,
                MAX(COALESCE(NULLIF(n.Kode_Alpha2, ""), e.KodeAlpha3)) as a2,
                MAX(COALESCE(NULLIF(n.Negara_IDN, ""), e.KodeAlpha3)) as country,
                e.Tahun as year,
                SUM(e.Jumlah) as total
            ')
            ->groupBy('e.KodeAlpha3', 'e.Tahun')
            ->orderBy('e.KodeAlpha3')
            ->orderBy('e.Tahun')
            ->get();

        $perCountry = [];
        foreach ($countryYearRows as $r) {
            $a3   = (string) $r->a3;
            $a2   = (string) $r->a2;
            $name = (string) $r->country;
            $year = (int) $r->year;
            $val  = (float) $r->total;

            if (!isset($perCountry[$a3])) {
                $perCountry[$a3] = [
                    'a3'    => $a3,
                    'a2'    => $a2,
                    'name'  => $name,
                    'years' => [],
                ];
            }

            $perCountry[$a3]['years'][$year] = $val;
        }

        $rankingGrowth = [];
        foreach ($perCountry as $item) {
            if (empty($item['years'])) {
                continue;
            }

            $years = array_keys($item['years']);
            sort($years);
            $firstYear = $years[0];
            $lastYear  = $years[count($years) - 1];

            $firstVal = (float) ($item['years'][$firstYear] ?? 0);
            $lastVal  = (float) ($item['years'][$lastYear] ?? 0);

            if ($firstVal <= 0) {
                continue;
            }

            $growth = (($lastVal - $firstVal) / $firstVal) * 100.0;

            $rankingGrowth[] = [
                'a3'        => $item['a3'],
                'a2'        => $item['a2'],
                'name'      => $item['name'],
                'growth'    => $growth,
                'firstYear' => $firstYear,
                'lastYear'  => $lastYear,
                'firstVal'  => $firstVal,
                'lastVal'   => $lastVal,
            ];
        }

        usort($rankingGrowth, fn ($a, $b) => $b['growth'] <=> $a['growth']);

        return [
            'ranking_jumlah_pekerja_migran_asing' => [
                'data' => $rankingJumlah,
            ],
            'proporsi_pekerja_migran_asing_seiring_waktu' => [
                'data' => $proporsiSeiringWaktu,
            ],
            'aliran_pekerja_migran_sektor_ke_negara' => [
                'nodes' => $nodes,
                'links' => $links,
            ],
            'proporsi_pekerja_migran_asing_berdasarkan_gender' => [
                'data' => $proporsiGenderData,
            ],
            'pertumbuhan_yoy_pekerja_migran' => [
                'data' => $pertumbuhanYoy,
            ],
            'ranking_pertumbuhan_yoy' => [
                'data' => $rankingGrowth,
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
