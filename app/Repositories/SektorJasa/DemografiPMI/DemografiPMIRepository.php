<?php

namespace App\Repositories\SektorJasa\DemografiPMI;

use Illuminate\Support\Facades\DB;

class DemografiPMIRepository implements DemografiPMIRepositoryInterface
{
    protected string $conn = 'server_mysql';

    protected string $tableEdu = 'tbILO_Education';

    protected string $tableJob = 'tbILO_Job';

    protected string $tableWage = 'tbILO_Wage';

    protected string $tableNeg = 'tbnegara';

    protected string $defaultSource = 'International Labour Organization';

    protected string $defaultUnit = 'orang';

    /* =======================================================================
     *  Helper Tahun
     * ======================================================================= */

    /**
     * Ambil 5 tahun terakhir yang tersedia di tbILO_Education.
     */
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

    /**
     * Hitung window tahun berdasarkan filter year_start / year_end
     * default: 5 tahun terakhir yang tersedia.
     */
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

    /* =======================================================================
     *  Base Query Education & Wage
     * ======================================================================= */

    /**
     * Query dasar untuk tbILO_Education (jumlah pekerja).
     */
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

    /**
     * Query dasar untuk tbILO_Wage (gaji).
     */
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

    /* =======================================================================
     *  Meta Negara (Partners)
     * ======================================================================= */

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

    /**
     * Ambil meta negara berdasarkan filter dan window tahun (dari Education).
     */
    protected function partnersMetaByFiltersAndYears(array $filters, array $yearsWindow): array
    {
        $partners    = $filters['partners'] ?? [];
        $partnersAll = !empty($filters['partners_all']);

        // Jika partners spesifik diberikan, langsung pakai itu.
        if (!$partnersAll && !empty($partners)) {
            return $this->partnersMeta($partners);
        }

        // Jika tidak ada tahun, tidak bisa ambil.
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

    /* =======================================================================
     *  Source & Unit (Default)
     * ======================================================================= */

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
     *  STATS: Total Pekerja, Rata-rata Gaji, Negara Tujuan
     * ======================================================================= */

    public function getStats(array $filters): array
    {
        $yearsWindow = $this->computeYearsWindow($filters);
        $metaSU      = $this->resolveSourceAndUnit($filters, $yearsWindow);

        if (empty($yearsWindow)) {
            return [
                'kpi'  => [
                    'total_pekerja'   => ['value' => 0],
                    'rata_rata_gaji'  => ['value' => null, 'currency' => null],
                    'negara_tujuan'   => ['value' => 0],
                ],
                'meta' => [
                    'partners' => [],
                    'years'    => [],
                    'source'   => $metaSU['source'],
                    'unit'     => $metaSU['unit'],
                ],
            ];
        }

        // Total pekerja dari tbILO_Education
        $qEdu = $this->baseEducationQuery($filters, $yearsWindow);

        $totalWorkers = (clone $qEdu)
            ->selectRaw('SUM(e.Jumlah) as total')
            ->value('total') ?? 0;

        // Negara tujuan (distinct KodeAlpha3)
        $countryCount = (clone $qEdu)
            ->selectRaw('COUNT(DISTINCT e.KodeAlpha3) as c')
            ->value('c') ?? 0;

        // Rata-rata gaji dari tbILO_Wage (AVG Gaji Rata-rata)
        $qWage      = $this->baseWageQuery($filters, $yearsWindow);
        $avgWageRow = (clone $qWage)
            ->selectRaw('
                AVG(w.`Gaji Rata-rata`) as avg_wage,
                MAX(w.Currency) as currency
            ')
            ->first();

        $avgWage  = $avgWageRow?->avg_wage ?? null;
        $currency = $avgWageRow?->currency ?? null;

        return [
            'kpi'  => [
                'total_pekerja'  => [
                    'value' => (float) $totalWorkers,
                ],
                'rata_rata_gaji' => [
                    'value'    => $avgWage !== null ? (float) $avgWage : null,
                    'currency' => $currency ? (string) $currency : null,
                ],
                'negara_tujuan' => [
                    'value' => (int) $countryCount,
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

    /* =======================================================================
     *  NILAI JASA:
     *  - Distribusi Gender
     *  - Top Job Types
     *  - Distribusi Pendidikan
     *  - Gaji per Pekerjaan  (baru)
     *  - Rentang Gaji (legacy, kalau masih mau dipakai)
     *  - Tabel Demografi (ringkasan)
     * ======================================================================= */

    public function getNilaiJasa(array $filters): array
    {
        $yearsWindow = $this->computeYearsWindow($filters);
        $metaSU      = $this->resolveSourceAndUnit($filters, $yearsWindow);

        if (empty($yearsWindow)) {
            return [
                'distribusi_gender'   => ['data' => []],
                'top_job_types'       => ['data' => []],
                'pendidikan'          => ['data' => []],
                'gaji_per_pekerjaan'  => ['data' => []],
                'rentang_gaji'        => ['data' => []],
                'tabel_demografi'     => ['rows' => []],
                'meta'                => [
                    'partners' => [],
                    'years'    => [],
                    'source'   => $metaSU['source'],
                    'unit'     => $metaSU['unit'],
                ],
            ];
        }

        $qEdu  = $this->baseEducationQuery($filters, $yearsWindow);
        $qWage = $this->baseWageQuery($filters, $yearsWindow);

        /* -------------------------------------------------------------------
         *  Distribusi Gender (dari tbILO_Education)
         * ------------------------------------------------------------------- */
        $genderRows = (clone $qEdu)
            ->selectRaw('e.Gender as gender, SUM(e.Jumlah) as total')
            ->groupBy('e.Gender')
            ->orderBy('total', 'desc')
            ->get();

        $genderData = $genderRows->map(function ($r) {
            $key = (string) $r->gender;

            $label = $key;
            if (strcasecmp($key, 'Male') === 0) {
                $label = 'Pria';
            } elseif (strcasecmp($key, 'Female') === 0) {
                $label = 'Wanita';
            }

            return [
                'key'   => $key,
                'label' => $label,
                'value' => (float) $r->total,
            ];
        })->values()->all();

        /* -------------------------------------------------------------------
         *  Top Job Types (join tbILO_Job) - jumlah pekerja
         * ------------------------------------------------------------------- */
        $jobRows = (clone $qEdu)
            ->join($this->tableJob . ' as j', 'j.JobID', '=', 'e.JobID')
            ->selectRaw('
                j.JobID as job_id,
                j.Pekerjaan as label,
                SUM(e.Jumlah) as total
            ')
            ->groupBy('j.JobID', 'j.Pekerjaan')
            ->orderByDesc('total')
            ->get();

        $jobTypesData = $jobRows->map(fn ($r) => [
            'job_id' => (int) $r->job_id,
            'label'  => (string) $r->label,
            'value'  => (float) $r->total,
        ])->values()->all();

        /* -------------------------------------------------------------------
         *  Distribusi Pendidikan (tbILO_Education.Pendidikan)
         * ------------------------------------------------------------------- */
        $eduRows = (clone $qEdu)
            ->selectRaw('e.Pendidikan as level, SUM(e.Jumlah) as total')
            ->groupBy('e.Pendidikan')
            ->orderByDesc('total')
            ->get();

        $educationData = $eduRows->map(function ($r) {
            $key   = (string) $r->level;
            $label = $key;

            // Optional mapping ke IDN (kalau struktur lama masih dipakai)
            if (strcasecmp($key, 'none') === 0) {
                $label = 'Tidak sekolah';
            } elseif (strcasecmp($key, 'primary') === 0) {
                $label = 'Primary education';
            } elseif (strcasecmp($key, 'secondary') === 0) {
                $label = 'Secondary education';
            } elseif (strcasecmp($key, 'tertiary') === 0) {
                $label = 'Tertiary education';
            }

            return [
                'key'   => $key,
                'label' => $label,
                'value' => (float) $r->total,
            ];
        })->values()->all();

        /* -------------------------------------------------------------------
         *  Gaji per Pekerjaan (baru) - rata-rata gaji per job
         *  Asumsi: tbILO_Wage juga punya kolom JobID yang match dengan tbILO_Job
         * ------------------------------------------------------------------- */
        $wageJobRows = (clone $qWage)
            ->join($this->tableJob . ' as j', 'j.JobID', '=', 'w.JobID')
            ->selectRaw('
                j.JobID as job_id,
                j.Pekerjaan as label,
                AVG(w.`Gaji Rata-rata`) as avg_wage,
                MAX(w.Currency) as currency
            ')
            ->groupBy('j.JobID', 'j.Pekerjaan')
            ->orderByDesc('avg_wage')
            ->get();

        $wageJobData = $wageJobRows->map(fn ($r) => [
            'job_id'   => (int) $r->job_id,
            'label'    => (string) $r->label,
            'value'    => (float) $r->avg_wage,
            'currency' => $r->currency ? (string) $r->currency : null,
        ])->values()->all();

        /* -------------------------------------------------------------------
         *  Rentang Gaji (legacy, kalau masih diperlukan di tempat lain)
         * ------------------------------------------------------------------- */
        $wageRangeRows = (clone $qWage)
            ->selectRaw('
                CASE
                    WHEN w.`Gaji Rata-rata` < 1000000 THEN "<1jt"
                    WHEN w.`Gaji Rata-rata` >= 1000000 AND w.`Gaji Rata-rata` <= 3000000 THEN "1-3jt"
                    ELSE ">3jt"
                END as wage_range,
                SUM(w.`Gaji Rata-rata`) as total_wage
            ')
            ->groupBy('wage_range')
            ->orderBy('total_wage', 'desc')
            ->get();

        $wageRangeData = $wageRangeRows->map(fn ($r) => [
            'range' => (string) $r->wage_range,
            'value' => (float) $r->total_wage,
        ])->values()->all();

        /* -------------------------------------------------------------------
         *  Tabel Demografi (ringkasan) – per negara & jobType, male/female/total
         * ------------------------------------------------------------------- */
        $demografiRows = (clone $qEdu)
            ->join($this->tableJob . ' as j', 'j.JobID', '=', 'e.JobID')
            ->leftJoin($this->tableNeg . ' as n', 'n.Kode_Alpha3', '=', 'e.KodeAlpha3')
            ->selectRaw('
                e.KodeAlpha3 as a3,
                COALESCE(NULLIF(n.Kode_Alpha2, ""), e.KodeAlpha3) as a2,
                COALESCE(NULLIF(n.Negara_IDN, ""), e.KodeAlpha3) as country,
                j.Pekerjaan as job_type,
                e.Gender as gender,
                SUM(e.Jumlah) as total
            ')
            ->groupBy(
                'e.KodeAlpha3',
                'n.Kode_Alpha2',
                'n.Negara_IDN',
                'j.Pekerjaan',
                'e.Gender'
            )
            ->orderBy('country')
            ->orderBy('j.Pekerjaan')
            ->get();

        $tableIndex = [];

        foreach ($demografiRows as $r) {
            $a3   = (string) $r->a3;
            $a2   = $r->a2 !== null ? trim((string) $r->a2) : '';
            $name = $r->country !== null ? trim((string) $r->country) : '';

            if ($a2 === '') {
                $a2 = $a3;
            }
            if ($name === '') {
                $name = $a3;
            }

            $jobType = (string) $r->job_type;
            $key     = $a3 . '|' . $jobType;

            if (!isset($tableIndex[$key])) {
                $tableIndex[$key] = [
                    'a3'      => $a3,
                    'a2'      => $a2,
                    'country' => $name,
                    'jobType' => $jobType,
                    'male'    => 0.0,
                    'female'  => 0.0,
                    'total'   => 0.0,
                ];
            }

            $gender = (string) $r->gender;
            $val    = (float) $r->total;

            if (strcasecmp($gender, 'Male') === 0) {
                $tableIndex[$key]['male'] += $val;
            } elseif (strcasecmp($gender, 'Female') === 0) {
                $tableIndex[$key]['female'] += $val;
            }

            $tableIndex[$key]['total'] += $val;
        }

        $tabelDemografiRows = array_values($tableIndex);

        /* -------------------------------------------------------------------
         *  Return
         * ------------------------------------------------------------------- */
        return [
            'distribusi_gender'  => [
                'data' => $genderData,
            ],
            'top_job_types'      => [
                'data' => $jobTypesData,
            ],
            'pendidikan'         => [
                'data' => $educationData,
            ],
            'gaji_per_pekerjaan' => [
                'data' => $wageJobData,
            ],
            'rentang_gaji'       => [
                'data' => $wageRangeData,
            ],
            'tabel_demografi'    => [
                'rows' => $tabelDemografiRows,
            ],
            'meta'               => [
                'partners' => $this->partnersMetaByFiltersAndYears($filters, $yearsWindow),
                'years'    => $yearsWindow,
                'source'   => $metaSU['source'],
                'unit'     => $metaSU['unit'],
            ],
        ];
    }
}
