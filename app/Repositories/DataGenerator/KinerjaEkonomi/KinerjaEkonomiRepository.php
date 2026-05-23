<?php

namespace App\Repositories\DataGenerator\KinerjaEkonomi;

use Illuminate\Support\Facades\DB;

class KinerjaEkonomiRepository implements KinerjaEkonomiRepositoryInterface
{
    protected string $conn = 'server_mysql';
    protected string $TB_KinerjaEkonomi = 'tbkin_ekonomi';
    protected string $TB_Indikator = 'tbindikator_kinek';
    protected string $TB_Negara = 'tbnegara';
    protected string $TB_Sumber = 'tbsumber';

    public function getTableFilterData(array $filters): array
    {
        $indicatorId = (int) ($filters['indicator_id'] ?? 0);
        $yearFrom = (int) ($filters['yearFrom'] ?? 0);
        $yearTo = (int) ($filters['yearTo'] ?? 0);
        $viewType = (string) ($filters['viewType'] ?? '');

        if ($indicatorId <= 0 || $yearFrom <= 0 || $yearTo <= 0 || $yearFrom > $yearTo) {
            return [
                'success' => false,
                'message' => 'Parameter tidak valid.',
                'data' => [],
                'meta' => [],
                'errors' => ['indicator_id/yearFrom/yearTo tidak valid'],
            ];
        }

        $years = $this->resolveYears($yearFrom, $yearTo);
        if (empty($years)) {
            return [
                'success' => false,
                'message' => 'Data tidak ditemukan untuk rentang tahun yang dipilih.',
                'data' => [],
                'meta' => [],
                'errors' => ['Tahun tidak tersedia dalam database'],
            ];
        }

        $metaIndikator = $this->getIndikatorMeta($indicatorId);
        $orderDir = $metaIndikator['order'] ?? 'desc';
        if (!in_array($orderDir, ['asc', 'desc'], true)) {
            $orderDir = 'desc';
        }

        $rows = DB::connection($this->conn)
            ->table($this->TB_KinerjaEkonomi . ' as k')
            ->leftJoin($this->TB_Negara . ' as n', 'n.Kode_Alpha3', '=', 'k.Kode_Alpha3')
            ->where('k.ID_Indikator', $indicatorId)
            ->whereIn('k.Tahun', $years)
            ->selectRaw('
                k.Kode_Alpha3,
                n.Negara_IDN as negara,
                k.Tahun,
                AVG(k.Nilai) as nilai_avg
            ')
            ->groupBy('k.Kode_Alpha3', 'n.Negara_IDN', 'k.Tahun')
            ->get();

        $byYear = [];
        foreach ($rows as $r) {
            if (is_null($r->Tahun)) {
                continue;
            }
            $yr = (int) $r->Tahun;
            $byYear[$yr] ??= [];
            $byYear[$yr][] = [
                'negara' => $r->negara ?? $r->Kode_Alpha3,
                'kode_alpha3' => (string) $r->Kode_Alpha3,
                'tahun' => $yr,
                'nilai' => is_null($r->nilai_avg) ? null : (float) $r->nilai_avg,
            ];
        }

        $data = [];
        foreach ($years as $yr) {
            $list = $byYear[$yr] ?? [];
            usort($list, function ($a, $b) use ($orderDir) {
                $va = $a['nilai'];
                $vb = $b['nilai'];
                if ($va === $vb) {
                    return 0;
                }
                if ($va === null) {
                    return 1;
                }
                if ($vb === null) {
                    return -1;
                }
                return $orderDir === 'asc' ? ($va <=> $vb) : ($vb <=> $va);
            });

            $rank = 1;
            foreach ($list as &$row) {
                $row['rank'] = $rank++;
            }
            unset($row);

            $data[(string) $yr] = $list;
        }

        return [
            'success' => true,
            'message' => null,
            'data' => $data,
            'meta' => [
                'indicator_id' => $indicatorId,
                'indicator_name' => $metaIndikator['indikator'] ?? null,
                'years' => [$yearFrom, $yearTo],
                'order' => $orderDir,
                'is_yoy' => $metaIndikator['is_yoy'] ?? null,
                'sumber' => $metaIndikator['sumber'] ?? null,
                'viewType' => $viewType,
                'count' => array_sum(array_map('count', $data)),
            ],
            'errors' => [],
        ];
    }

    public function getVisualizationFilterData(array $filters): array
    {
        return $this->getTableFilterData($filters);
    }

    private function resolveYears(int $yearFrom, int $yearTo): array
    {
        $validYears = DB::connection($this->conn)
            ->table($this->TB_KinerjaEkonomi)
            ->distinct()
            ->pluck('Tahun')
            ->filter(fn ($y) => is_numeric($y))
            ->map(fn ($y) => (int) $y)
            ->values()
            ->toArray();

        return array_values(array_intersect(range($yearFrom, $yearTo), $validYears));
    }

    private function getIndikatorMeta(int $indicatorId): array
    {
        $row = DB::connection($this->conn)
            ->table($this->TB_Indikator . ' as i')
            ->leftJoin($this->TB_Sumber . ' as s', 's.KodeSumber', '=', 'i.KodeSumber')
            ->where('i.ID_Indikator', $indicatorId)
            ->select('i.Indikator', 'i.order', 'i.is_yoy', 's.NamaSumber')
            ->first();

        return [
            'indikator' => $row?->Indikator,
            'order' => $row ? strtolower((string) $row->order) : null,
            'is_yoy' => $row?->is_yoy,
            'sumber' => $row?->NamaSumber,
        ];
    }
}
