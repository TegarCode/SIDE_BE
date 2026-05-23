<?php

namespace App\Repositories\DataGenerator\Pariwisata;

use Illuminate\Support\Facades\DB;

class PariwisataRepository implements PariwisataRepositoryInterface
{
    /**
     * Default nama kolom nilai di tbtourism.
     * Kalau di DB beda (mis. Jumlah_Wisatawan), ubah di sini.
     */
    protected string $defaultValueColumn = 'Jumlah';

    /* =========================================================
     * Helpers
     * ======================================================= */

    protected function resolveYears(int $yearFrom, int $yearTo): array
    {
        $validYears = DB::connection('server_mysql')
            ->table('tbtourism')
            ->distinct()
            ->pluck('Tahun')
            ->filter(fn ($y) => is_numeric($y))
            ->map(fn ($y) => (int) $y)
            ->values()
            ->toArray();

        $requested = range($yearFrom, $yearTo);
        $years = array_values(array_intersect($requested, $validYears));

        return $years;
    }

    protected function resolveAlpha3(array $groupIds): array
    {
        return collect($groupIds)
            ->flatMap(function ($id) {
                // Numeric = organisasi (tborgnegara)
                if (is_numeric($id)) {
                    return DB::connection('server_mysql')
                        ->table('tborgnegara')
                        ->where('ID_Org', $id)
                        ->pluck('Kode_Alpha3');
                }

                // Non-numeric = ID_benua (tbkawasan)
                return DB::connection('server_mysql')
                    ->table('tbkawasan as k')
                    ->join('tbnegara as n', 'k.ID_Wil', '=', 'n.ID_Wil')
                    ->where('k.ID_benua', $id)
                    ->pluck('n.Kode_Alpha3');
            })
            ->unique()
            ->values()
            ->toArray();
    }

    protected function resolveValueColumn(?string $typeData): string
    {
        $col = $typeData ?: $this->defaultValueColumn;

        // Sanitasi sederhana supaya tidak bisa injeksi
        if (! preg_match('/^[A-Za-z0-9_]+$/', $col)) {
            $col = $this->defaultValueColumn;
        }

        return $col;
    }

    /* =========================================================
     * Dropdown
     * ======================================================= */

    public function getDistinctKodeSumber()
    {
        return DB::connection('server_mysql')
            ->table('tbtourism')
            ->join('tbsumber', 'tbtourism.Kode_Sumber', '=', 'tbsumber.KodeSumber')
            ->select('tbtourism.Kode_Sumber as id', 'tbsumber.NamaSumber as nama')
            ->distinct()
            ->whereNotNull('tbtourism.Kode_Sumber')
            ->where('tbtourism.Kode_Sumber', '!=', '')
            ->orderBy('tbsumber.NamaSumber', 'asc')
            ->get();
    }

    public function getDistinctTahun()
    {
        return DB::connection('server_mysql')
            ->table('tbtourism')
            ->select('Tahun')
            ->distinct()
            ->orderByDesc('Tahun')
            ->get();
    }

    public function getDistinctDefaultTahun()
    {
        return DB::connection('server_mysql')
            ->table('tbtourism')
            ->select('Tahun')
            ->where('Kode_Sumber', 1)
            ->distinct()
            ->orderByDesc('Tahun')
            ->get();
    }

    /* =========================================================
     *  TABLE DATA
     * ======================================================= */

    public function getTableFilterData(array $filters): array
    {
        // 1) Tahun
        $years = $this->resolveYears((int) $filters['yearFrom'], (int) $filters['yearTo']);
        if (empty($years)) {
            return [
                'success' => false,
                'message' => 'Data tidak ditemukan untuk rentang tahun yang dipilih.',
                'data' => [],
                'meta' => [],
                'errors' => ['Tahun tidak tersedia dalam database'],
            ];
        }

        // 2) Sumber & kolom nilai
        $sourceCode = $filters['sourceCode'] ?? '';
        $typeData = $this->resolveValueColumn($filters['typeData'] ?? null);

        if (empty($sourceCode)) {
            return [
                'success' => false,
                'message' => 'sourceCode wajib diisi.',
                'data' => [],
                'meta' => [],
                'errors' => ['sourceCode tidak boleh kosong'],
            ];
        }

        // 3) Origins & destinations
        $originList = collect($filters['origins'] ?? [])
            ->merge($this->resolveAlpha3($filters['originGroups'] ?? []))
            ->unique()
            ->values()
            ->toArray();

        $destinationList = collect($filters['destinations'] ?? [])
            ->merge($this->resolveAlpha3($filters['destinationGroups'] ?? []))
            ->unique()
            ->values()
            ->toArray();

        $results = [];

        /* ---------------- 4) ASAL → TUJUAN ---------------- */
        $asalKeTujuan = [];
        foreach ($years as $yr) {
            // total mentah (float)
            $queryRaw = DB::connection('server_mysql')
                ->table('tbtourism as t')
                ->join('tbnegara as na', 't.Kode_Alpha3_Asal', '=', 'na.Kode_Alpha3')
                ->join('tbnegara as nt', 't.Kode_Alpha3_Tujuan', '=', 'nt.Kode_Alpha3')
                ->select(
                    'na.Negara_IDN as asal',
                    'nt.Negara_IDN as tujuan',
                    DB::raw("SUM(t.$typeData) as total")
                )
                ->where('t.Tahun', $yr)
                ->where('t.Kode_Sumber', $sourceCode);

            if (! empty($originList)) {
                $queryRaw->whereIn('t.Kode_Alpha3_Asal', $originList);
            }
            if (! empty($destinationList)) {
                $queryRaw->whereIn('t.Kode_Alpha3_Tujuan', $destinationList);
            }

            $raw = $queryRaw
                ->groupBy('na.Negara_IDN', 'nt.Negara_IDN')
                ->get()
                ->map(fn ($r) => (float) $r->total)
                ->toArray();

            $sumRaw = array_sum($raw);

            // per_negara (formatted)
            $queryRows = DB::connection('server_mysql')
                ->table('tbtourism as t')
                ->join('tbnegara as na', 't.Kode_Alpha3_Asal', '=', 'na.Kode_Alpha3')
                ->join('tbnegara as nt', 't.Kode_Alpha3_Tujuan', '=', 'nt.Kode_Alpha3')
                ->select(
                    'na.Negara_IDN as asal',
                    'nt.Negara_IDN as tujuan',
                    DB::raw("SUM(t.$typeData) as total")
                )
                ->where('t.Tahun', $yr)
                ->where('t.Kode_Sumber', $sourceCode);

            if (! empty($originList)) {
                $queryRows->whereIn('t.Kode_Alpha3_Asal', $originList);
            }
            if (! empty($destinationList)) {
                $queryRows->whereIn('t.Kode_Alpha3_Tujuan', $destinationList);
            }

            $rows = $queryRows
                ->groupBy('na.Negara_IDN', 'nt.Negara_IDN')
                ->get()
                ->map(fn ($r) => [
                    'asal' => $r->asal,
                    'tujuan' => $r->tujuan,
                    'total' => number_format((float) $r->total, 0, ',', '.'),
                ])
                ->toArray();

            $asalKeTujuan[$yr] = [
                'per_negara' => $rows,
                'total' => number_format($sumRaw, 0, ',', '.'),
            ];
        }
        $results['pariwisata_asal_ke_tujuan'] = $asalKeTujuan;

        /* ---------------- 5) ASAL → DUNIA ---------------- */
        $asalKeDunia = [];
        foreach ($years as $yr) {
            if ((string) $sourceCode === '1') {
                $asalKeDunia[$yr] = [
                    'per_negara' => 'N/A',
                    'total' => 'N/A',
                ];
                continue;
            }

            $queryRaw = DB::connection('server_mysql')
                ->table('tbtourism as t')
                ->join('tbnegara as na', 't.Kode_Alpha3_Asal', '=', 'na.Kode_Alpha3')
                ->select(DB::raw("SUM(t.$typeData) as total"))
                ->where('t.Tahun', $yr)
                ->where('t.Kode_Sumber', $sourceCode);

            if (! empty($originList)) {
                $queryRaw->whereIn('t.Kode_Alpha3_Asal', $originList);
            }

            $raw = $queryRaw
                ->groupBy('na.Negara_IDN')
                ->pluck('total')
                ->map(fn ($v) => (float) $v)
                ->toArray();

            $sumRaw = array_sum($raw);

            $queryRows = DB::connection('server_mysql')
                ->table('tbtourism as t')
                ->join('tbnegara as na', 't.Kode_Alpha3_Asal', '=', 'na.Kode_Alpha3')
                ->select('na.Negara_IDN as asal', DB::raw("SUM(t.$typeData) as total"))
                ->where('t.Tahun', $yr)
                ->where('t.Kode_Sumber', $sourceCode);

            if (! empty($originList)) {
                $queryRows->whereIn('t.Kode_Alpha3_Asal', $originList);
            }

            $rows = $queryRows
                ->groupBy('na.Negara_IDN')
                ->get()
                ->map(fn ($r) => [
                    'asal' => $r->asal,
                    'total' => number_format((float) $r->total, 0, ',', '.'),
                ])
                ->toArray();

            $asalKeDunia[$yr] = [
                'per_negara' => $rows,
                'total' => number_format($sumRaw, 0, ',', '.'),
            ];
        }
        $results['pariwisata_asal_ke_dunia'] = $asalKeDunia;

        /* ---------------- 6) DUNIA → TUJUAN ---------------- */
        $duniaKeTujuan = [];
        foreach ($years as $yr) {
            $queryRaw = DB::connection('server_mysql')
                ->table('tbtourism as t')
                ->join('tbnegara as nt', 't.Kode_Alpha3_Tujuan', '=', 'nt.Kode_Alpha3')
                ->select(DB::raw("SUM(t.$typeData) as total"))
                ->where('t.Tahun', $yr)
                ->where('t.Kode_Sumber', $sourceCode);

            if (! empty($destinationList)) {
                $queryRaw->whereIn('t.Kode_Alpha3_Tujuan', $destinationList);
            }

            $raw = $queryRaw
                ->groupBy('nt.Negara_IDN')
                ->pluck('total')
                ->map(fn ($v) => (float) $v)
                ->toArray();

            $sumRaw = array_sum($raw);

            $queryRows = DB::connection('server_mysql')
                ->table('tbtourism as t')
                ->join('tbnegara as nt', 't.Kode_Alpha3_Tujuan', '=', 'nt.Kode_Alpha3')
                ->select('nt.Negara_IDN as tujuan', DB::raw("SUM(t.$typeData) as total"))
                ->where('t.Tahun', $yr)
                ->where('t.Kode_Sumber', $sourceCode);

            if (! empty($destinationList)) {
                $queryRows->whereIn('t.Kode_Alpha3_Tujuan', $destinationList);
            }

            $rows = $queryRows
                ->groupBy('nt.Negara_IDN')
                ->get()
                ->map(fn ($r) => [
                    'tujuan' => $r->tujuan,
                    'total' => number_format((float) $r->total, 0, ',', '.'),
                ])
                ->toArray();

            $duniaKeTujuan[$yr] = [
                'per_negara' => $rows,
                'total' => number_format($sumRaw, 0, ',', '.'),
            ];
        }
        $results['pariwisata_dunia_ke_tujuan'] = $duniaKeTujuan;

        return [
            'success' => true,
            'message' => null,
            'data' => $results,
            'meta' => [
                'years' => $years,
                'sourceCode' => $sourceCode,
                'typeData' => $typeData,
            ],
            'errors' => [],
        ];
    }

    /* =========================================================
     * VISUALISATION DATA
     * (kalau mau beda struktur boleh, untuk sekarang reuse saja)
     * ======================================================= */

    public function getVisualizationFilterData(array $filters): array
    {
        // kalau mau beda meta / format, boleh duplikasi logikanya dan ubah return.
        // untuk sementara, pakai saja hasil yang sama seperti table.
        return $this->getTableFilterData($filters);
    }
}
