<?php

namespace App\Repositories\DataGenerator\Jasa;

use Illuminate\Support\Facades\DB;

class JasaRepository implements JasaRepositoryInterface
{
    /* ====================== DISTINCT ====================== */

    public function getDistinctKodeSumber()
    {
        return DB::connection('server_mysql')
            ->table('tbservices')
            ->join('tbsumber', 'tbservices.KodeSumber', '=', 'tbsumber.KodeSumber')
            ->select('tbservices.KodeSumber as id', 'tbsumber.NamaSumber as nama')
            ->distinct()
            ->whereNotNull('tbservices.KodeSumber')
            ->where('tbservices.KodeSumber', '!=', '')
            ->orderBy('tbsumber.NamaSumber', 'asc')
            ->get();
    }

    public function getDistinctTahun()
    {
        return DB::connection('server_mysql')
            ->table('tbservices')
            ->select('Tahun')
            ->whereNotNull('Tahun')
            ->where('Tahun', '!=', '')
            ->distinct()
            ->orderByDesc('Tahun')
            ->get();
    }

    /* ====================== HELPERS ====================== */

    /**
     * Normalisasi input ke array.
     */
    protected function normalizeToArray(mixed $value): array
    {
        if (is_null($value)) return [];
        if (is_array($value)) return $value;
        return [$value];
    }

    /**
     * Resolve group (organisasi / kawasan) menjadi daftar kode Alpha-3.
     * - numeric  => tborgnegara.ID_Org → Kode_Alpha3
     * - non-num  => tbkawasan.ID_benua → tbnegara.Kode_Alpha3
     */
    protected function resolveAlpha3(array $groupIds): array
    {
        return collect($groupIds)
            ->flatMap(function ($id) {
                // numeric = organisasi (tborgnegara)
                if (is_numeric($id)) {
                    return DB::connection('server_mysql')
                        ->table('tborgnegara')
                        ->where('ID_Org', $id)
                        ->pluck('Kode_Alpha3');
                }

                // non-numeric = ID_benua (tbkawasan → tbnegara)
                return DB::connection('server_mysql')
                    ->table('tbkawasan as k')
                    ->join('tbnegara as n', 'k.ID_Wil', '=', 'n.ID_Wil')
                    ->where('k.ID_benua', $id)
                    ->pluck('n.Kode_Alpha3');
            })
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Ambil daftar tahun yang valid dalam rentang (kalau ada),
     * kalau yearFrom/yearTo kosong akan ambil semua tahun yang tersedia.
     */
    protected function resolveYears(?int $yearFrom, ?int $yearTo, $conn): array
    {
        $query = $conn->table('tbservices')
            ->whereNotNull('Tahun')
            ->where('Tahun', '!=', '');

        // Jika user mengirim range tahun yang jelas, batasi di sini
        if ($yearFrom && $yearTo && $yearFrom <= $yearTo) {
            $query->whereBetween('Tahun', [$yearFrom, $yearTo]);
        }

        return $query
            ->distinct()
            ->orderBy('Tahun')
            ->pluck('Tahun')
            ->map(fn ($y) => (int) $y)
            ->toArray();
    }

    /* =========================================================
     * TABLE DATA
     * ======================================================= */
    public function getTableFilterData(array $filters): array
    {
        $conn = DB::connection('server_mysql');

        /* ---------- 1) Tahun ---------- */
        $yearFrom = isset($filters['yearFrom']) ? (int) $filters['yearFrom'] : null;
        $yearTo   = isset($filters['yearTo'])   ? (int) $filters['yearTo']   : null;

        // Ambil tahun yang valid dari DB (sudah otomatis dibatasi ke range kalau diisi)
        $years = $this->resolveYears($yearFrom, $yearTo, $conn);

        if (empty($years)) {
            return [
                'success' => false,
                'message' => 'Data tidak ditemukan untuk rentang tahun yang dipilih.',
                'data'    => [],
                'meta'    => [],
                'errors'  => ['Tahun tidak tersedia dalam database'],
            ];
        }

        // Kalau yearFrom/yearTo tidak diisi, pakai min–max dari years
        if (!$yearFrom) {
            $yearFrom = min($years);
        }
        if (!$yearTo) {
            $yearTo = max($years);
        }

        /* ---------- 2) Origins & Destinations (Alpha-3 + Group) ---------- */
        $originInput         = $this->normalizeToArray($filters['origins'] ?? []);
        $destinationInput    = $this->normalizeToArray($filters['destinations'] ?? []);
        $originGroupIds      = $this->normalizeToArray($filters['originGroups'] ?? []);
        $destinationGroupIds = $this->normalizeToArray($filters['destinationGroups'] ?? []);

        // merge negara langsung + hasil resolveAlpha3
        $originList = collect($originInput)
            ->merge($this->resolveAlpha3($originGroupIds))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $destinationList = collect($destinationInput)
            ->merge($this->resolveAlpha3($destinationGroupIds))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($originList) || empty($destinationList)) {
            return [
                'success' => false,
                'message' => 'origins dan destinations (termasuk group) wajib diisi.',
                'data'    => [],
                'meta'    => [],
                'errors'  => ['origin/destination final list kosong'],
            ];
        }

        /* ---------- 3) Profesi ---------- */
        $profesiList   = $this->normalizeToArray($filters['idProfesi'] ?? ['all']);
        $filterProfesi = in_array('all', $profesiList, true) ? null : $profesiList;

        /* ---------- 4) Gender ---------- */
        $gender       = strtoupper($filters['gender'] ?? 'all');
        $filterGender = in_array($gender, ['L', 'P'], true) ? $gender : null;

        /* ---------- 5) Sumber data (scalar) ---------- */
        $sourceCode = $filters['sourceCode'] ?? null;

        /* ---------- 6) Base query (tanpa JOIN negara) ---------- */
        $baseQuery = $conn
            ->table('tbservices as t')
            ->when($filterProfesi, fn ($q) => $q->whereIn('t.ID_Profesi', $filterProfesi))
            ->when($filterGender, fn ($q) => $q->where('t.Jenis_Kelamin', $filterGender))
            ->when($sourceCode, fn ($q) => $q->where('t.KodeSumber', $sourceCode))
            ->whereIn('t.Tahun', $years);  // gunakan list tahun valid

        /* ------------------ 7) ASAL → TUJUAN ------------------ */
        $queryAT = (clone $baseQuery)
            ->join('tbnegara as na', 't.Kode_Alpha3_Asal', '=', 'na.Kode_Alpha3')
            ->join('tbnegara as nt', 't.Kode_Alpha3_Tujuan', '=', 'nt.Kode_Alpha3')
            ->whereIn('t.Kode_Alpha3_Asal', $originList)
            ->whereIn('t.Kode_Alpha3_Tujuan', $destinationList);

        $rowsAT = $queryAT
            ->select([
                't.Tahun as year',
                'na.Negara_IDN as asal',
                'nt.Negara_IDN as tujuan',
                DB::raw('SUM(t.Jumlah) as total'),
            ])
            ->groupBy('t.Tahun', 'asal', 'tujuan')
            ->orderBy('t.Tahun')
            ->orderBy('asal')
            ->orderBy('tujuan')
            ->get()
            ->groupBy('year')
            ->map(fn ($group, $year) => [
                'per_negara' => $group->map(fn ($r) => [
                    'asal'   => $r->asal,
                    'tujuan' => $r->tujuan,
                    'total'  => number_format($r->total, 0, ',', '.'),
                ])->values(),
                'total' => number_format($group->sum('total'), 0, ',', '.'),
            ])
            ->toArray();

        /* ------------------ 8) ASAL → DUNIA ------------------ */
        $queryAD = (clone $baseQuery)
            ->join('tbnegara as na', 't.Kode_Alpha3_Asal', '=', 'na.Kode_Alpha3')
            ->whereIn('t.Kode_Alpha3_Asal', $originList);

        $rowsAD = $queryAD
            ->select([
                't.Tahun as year',
                'na.Negara_IDN as asal',
                DB::raw('SUM(t.Jumlah) as total'),
            ])
            ->groupBy('t.Tahun', 'asal')
            ->orderBy('t.Tahun')
            ->orderBy('asal')
            ->get()
            ->groupBy('year')
            ->map(fn ($group, $year) => [
                'per_negara' => $group->map(fn ($r) => [
                    'asal'  => $r->asal,
                    'total' => number_format($r->total, 0, ',', '.'),
                ])->values(),
                'total' => number_format($group->sum('total'), 0, ',', '.'),
            ])
            ->toArray();

        /* ------------------ 9) DUNIA → TUJUAN ------------------ */
        $queryDT = (clone $baseQuery)
            ->join('tbnegara as nt', 't.Kode_Alpha3_Tujuan', '=', 'nt.Kode_Alpha3')
            ->whereIn('t.Kode_Alpha3_Tujuan', $destinationList);

        $rowsDT = $queryDT
            ->select([
                't.Tahun as year',
                'nt.Negara_IDN as tujuan',
                DB::raw('SUM(t.Jumlah) as total'),
            ])
            ->groupBy('t.Tahun', 'tujuan')
            ->orderBy('t.Tahun')
            ->orderBy('tujuan')
            ->get()
            ->groupBy('year')
            ->map(function ($group, $year) use ($sourceCode) {
                if ((string) $sourceCode === '136') {
                    return [
                        'per_negara' => 'N/A',
                        'total' => 'N/A',
                    ];
                }

                return [
                    'per_negara' => $group->map(fn ($r) => [
                        'tujuan' => $r->tujuan,
                        'total'  => number_format($r->total, 0, ',', '.'),
                    ])->values(),
                    'total' => number_format($group->sum('total'), 0, ',', '.'),
                ];
            })
            ->toArray();

        $data = [
            'asal_ke_tujuan'  => $rowsAT,
            'asal_ke_dunia'   => $rowsAD,
            'dunia_ke_tujuan' => $rowsDT,
        ];

        $allCodes = array_values(array_unique(array_merge($originList, $destinationList)));
        $namesMap = [];
        if (! empty($allCodes)) {
            $namesMap = $conn
                ->table('tbnegara')
                ->whereIn('Kode_Alpha3', $allCodes)
                ->pluck('Negara_IDN', 'Kode_Alpha3')
                ->toArray();
        }

        $originNames = array_values(array_unique(array_map(
            fn ($code) => $namesMap[$code] ?? $code,
            $originList
        )));
        $destinationNames = array_values(array_unique(array_map(
            fn ($code) => $namesMap[$code] ?? $code,
            $destinationList
        )));

        return [
            'success' => true,
            'message' => null,
            'data'    => $data,
            'meta'    => [
                'years'              => $years,
                'year_from'          => $yearFrom,
                'year_to'            => $yearTo,
                'origins'            => $originNames,
                'destinations'       => $destinationNames,
                'originGroupIds'     => $originGroupIds,
                'destinationGroupIds'=> $destinationGroupIds,
                'idProfesi'          => $profesiList,
                'gender'             => $gender,
                'sourceCode'         => $sourceCode,
            ],
            'errors'  => [],
        ];
    }

    /* =========================================================
     * VISUALISATION DATA
     * ======================================================= */
    public function getVisualizationFilterData(array $filters): array
    {
        // sementara pakai struktur yang sama dengan tabel
        return $this->getTableFilterData($filters);
    }
}
