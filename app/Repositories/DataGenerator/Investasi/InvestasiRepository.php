<?php

namespace App\Repositories\DataGenerator\Investasi;

use Illuminate\Support\Facades\DB;

class InvestasiRepository implements InvestasiRepositoryInterface
{
    protected string $unit = 'Ribu US$';

    public function getDistinctKodeSumber()
    {
        return DB::connection('server_mysql')
            ->table('tbinvestment')
            ->join('tbsumber', 'tbinvestment.Kode_Sumber', '=', 'tbsumber.KodeSumber')
            ->select('tbinvestment.Kode_Sumber as id', 'tbsumber.NamaSumber as nama')
            ->distinct()
            ->whereNotNull('tbinvestment.Kode_Sumber')
            ->where('tbinvestment.Kode_Sumber', '!=', '')
            ->orderBy('tbsumber.NamaSumber', 'asc')
            ->get();
    }

    public function getDistinctTahun()
    {
        return DB::connection('server_mysql')
            ->table('tbinvestment')
            ->select('Tahun')
            ->whereNotNull('Tahun')
            ->where('Tahun', '!=', '')
            ->distinct()
            ->orderByDesc('Tahun')
            ->get();
    }

    public function getDistinctDefaultTahun()
    {
        return DB::connection('server_mysql')
            ->table('tbinvestment')
            ->select('Tahun')
            ->where('Kode_Sumber', 6)
            ->distinct()
            ->orderByDesc('Tahun')
            ->get();
    }
    /* ====================== Helpers ====================== */

    protected function resolveYears(int $yearFrom, int $yearTo): array
    {
        $validYears = DB::connection('server_mysql')
            ->table('tbinvestment')
            ->distinct()
            ->pluck('Tahun')
            // normalisasi: trim & buang non numeric
            ->map(function ($y) {
                if (is_null($y)) {
                    return null;
                }
                $str = trim((string) $y);

                return $str === '' ? null : $str;
            })
            ->filter(fn ($y) => ! is_null($y) && is_numeric($y))
            ->map(fn ($y) => (int) $y)
            ->unique()
            ->values()
            ->toArray();

        $requested = range($yearFrom, $yearTo);

        return array_values(array_intersect($requested, $validYears));
    }

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
            ->unique()
            ->values()
            ->toArray();
    }

    protected function normalizeToArray(mixed $value): array
    {
        if (is_null($value)) {
            return [];
        }
        if (is_array($value)) {
            return $value;
        }

        // string "CHN" → ["CHN"]
        return [$value];
    }

    /* ====================== TABLE DATA ====================== */

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

        // 2) investmentType & sourceCode
        $investmentType = $filters['investmentType'] ?? '';
        $sourceCode = $filters['sourceCode'] ?? '';

        if (empty($investmentType) || empty($sourceCode)) {
            return [
                'success' => false,
                'message' => 'investmentType dan sourceCode wajib diisi.',
                'data' => [],
                'meta' => [],
                'errors' => ['investmentType/sourceCode tidak boleh kosong'],
            ];
        }

        // 3) origins & destinations (Alpha-3)
        $originInput = $this->normalizeToArray($filters['origins'] ?? []);
        $destinationInput = $this->normalizeToArray($filters['destinations'] ?? []);
        $originGroupIds = $this->normalizeToArray($filters['originGroups'] ?? []);
        $destinationGroupIds = $this->normalizeToArray($filters['destinationGroups'] ?? []);

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

        $results = [];

        /* ---------- 4) ASAL → TUJUAN ---------- */
        $asalKeTujuan = [];
        foreach ($years as $yr) {
            // raw float total
            $queryRaw = DB::connection('server_mysql')
                ->table('tbinvestment as t')
                ->join('tbnegara as na', 't.Kode_Alpha3_Asal', '=', 'na.Kode_Alpha3')
                ->join('tbnegara as nt', 't.Kode_Alpha3_Tujuan', '=', 'nt.Kode_Alpha3')
                ->select(
                    'na.Negara_IDN as asal',
                    'nt.Negara_IDN as tujuan',
                    DB::raw('SUM(t.Nilai_Investasi) as total')
                )
                ->where('t.Tahun', $yr)
                ->where('t.Status', $investmentType)
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

            // per_negara terformat
            $queryRows = DB::connection('server_mysql')
                ->table('tbinvestment as t')
                ->join('tbnegara as na', 't.Kode_Alpha3_Asal', '=', 'na.Kode_Alpha3')
                ->join('tbnegara as nt', 't.Kode_Alpha3_Tujuan', '=', 'nt.Kode_Alpha3')
                ->select(
                    'na.Negara_IDN as asal',
                    'nt.Negara_IDN as tujuan',
                    DB::raw('SUM(t.Nilai_Investasi) as total')
                )
                ->where('t.Tahun', $yr)
                ->where('t.Status', $investmentType)
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
        $results['investasi_asal_ke_tujuan'] = $asalKeTujuan;

        /* ---------- 5) ASAL → DUNIA ---------- */
        $asalKeDunia = [];
        foreach ($years as $yr) {
            $queryRaw = DB::connection('server_mysql')
                ->table('tbinvestment as t')
                ->join('tbnegara as na', 't.Kode_Alpha3_Asal', '=', 'na.Kode_Alpha3')
                ->select(DB::raw('SUM(t.Nilai_Investasi) as total'))
                ->where('t.Tahun', $yr)
                ->where('t.Status', $investmentType)
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
                ->table('tbinvestment as t')
                ->join('tbnegara as na', 't.Kode_Alpha3_Asal', '=', 'na.Kode_Alpha3')
                ->select(
                    'na.Negara_IDN as asal',
                    DB::raw('SUM(t.Nilai_Investasi) as total')
                )
                ->where('t.Tahun', $yr)
                ->where('t.Status', $investmentType)
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
        $results['investasi_asal_ke_dunia'] = $asalKeDunia;

        /* ---------- 6) DUNIA → TUJUAN ---------- */
        $duniaKeTujuan = [];
        foreach ($years as $yr) {
            $queryRaw = DB::connection('server_mysql')
                ->table('tbinvestment as t')
                ->join('tbnegara as nt', 't.Kode_Alpha3_Tujuan', '=', 'nt.Kode_Alpha3')
                ->select(DB::raw('SUM(t.Nilai_Investasi) as total'))
                ->where('t.Tahun', $yr)
                ->where('t.Status', $investmentType)
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
                ->table('tbinvestment as t')
                ->join('tbnegara as nt', 't.Kode_Alpha3_Tujuan', '=', 'nt.Kode_Alpha3')
                ->select(
                    'nt.Negara_IDN as tujuan',
                    DB::raw('SUM(t.Nilai_Investasi) as total')
                )
                ->where('t.Tahun', $yr)
                ->where('t.Status', $investmentType)
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
        $results['investasi_dunia_ke_tujuan'] = $duniaKeTujuan;

        // ======== WRAP dgn success/data/meta (INI YANG PENTING) ========
        return [
            'success' => true,
            'message' => null,
            'data' => $results,
            'meta' => [
                'years' => $years,
                'unit' => $this->unit,
                'sourceCode' => $sourceCode,
                'investmentType' => $investmentType,
                'originCodes' => $originInput,
                'destinationCodes' => $destinationInput,
                'originGroupIds' => $originGroupIds,
                'destinationGroupIds' => $destinationGroupIds,
            ],
            'errors' => [],
        ];
    }

    /* ====================== VISUALISATION DATA ====================== */

    public function getVisualizationFilterData(array $filters): array
    {
        // sementara reuse struktur sama, biar controller gampang
        return $this->getTableFilterData($filters);
    }
}
