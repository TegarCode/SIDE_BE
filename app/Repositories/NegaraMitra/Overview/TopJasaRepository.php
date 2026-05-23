<?php

namespace App\Repositories\NegaraMitra\Overview;

use App\Repositories\NegaraMitra\Overview\TopJasaRepositoryInterface;
use Illuminate\Support\Facades\DB;

class TopJasaRepository implements TopJasaRepositoryInterface
{
    protected string $conn = 'server_mysql';
    /** Nama tabel sumber disimpan di variabel */
    protected string $sumberTable = 'tbsumber';

    public function topJasa(string $alpha3Tujuan, ?int $kodeSumber = 35, int $limit = 20, ?int $year = null): array
    {
        $alpha3Asal   = 'IDN'; // fixed
        $alpha3Tujuan = strtoupper(trim($alpha3Tujuan));
        $db = DB::connection($this->conn);

        $tujuanNama = $alpha3Tujuan;
        $tujuanRow = $db->table('tbnegara')
            ->select('Negara_IDN')
            ->where('Kode_Alpha3', $alpha3Tujuan)
            ->first();
        if ($tujuanRow && $tujuanRow->Negara_IDN) {
            $tujuanNama = (string) $tujuanRow->Negara_IDN;
        }

        /** ===================== 1) Ambil nama sumber dulu (bukan kode) ===================== */
        $sumberNama = '—';
        if (!is_null($kodeSumber)) {
            try {
                $rowSrc = $db->table($this->sumberTable)
                    ->selectRaw("NamaSumber AS nama")
                    ->where(function ($q) use ($kodeSumber) {
                        $q->where('KodeSumber', $kodeSumber)
                          ->orWhere('KodeSumber', $kodeSumber);
                    })
                    ->first();

                if ($rowSrc && $rowSrc->nama) {
                    $sumberNama = $rowSrc->nama;
                }
            } catch (\Throwable $e) {
            }
        }

        /** ===================== 2) Tentukan 2 tahun (latest & prev) ===================== */
        if (is_null($year)) {
            $yearsQuery = $db->table('tbservices')
                ->where('Kode_Alpha3_Asal', $alpha3Asal)
                ->where('Kode_Alpha3_Tujuan', $alpha3Tujuan)
                ->when(!is_null($kodeSumber), fn ($q) => $q->where('KodeSumber', $kodeSumber))
                ->distinct()
                ->orderByDesc('Tahun')
                ->limit(2)
                ->pluck('Tahun');

            $years = $yearsQuery->map(fn ($y) => (int) $y)->values()->all();

            if (count($years) === 0) {
                return [
                    'success' => true,
                    'message' => "Data jasa tidak tersedia.",
                    'data'    => [
                        'meta'  => [
                            'latest_year'  => null,
                            'prev_year'    => null,
                            'asal'         => $alpha3Asal,
                            'tujuan'       => $tujuanNama,
                            'sumber'       => $sumberNama,
                            'total_latest' => 0,
                            'total_prev'   => 0,
                        ],
                        'items' => [
                            'bothYears' => [],
                        ],
                    ],
                ];
            }

            $y2 = $years[0];
            $y1 = $years[1] ?? ($y2 - 1);
        } else {
            $y2 = (int) $year;
            $y1 = $y2 - 1;

            $hasData = $db->table('tbservices')
                ->where('Kode_Alpha3_Asal', $alpha3Asal)
                ->where('Kode_Alpha3_Tujuan', $alpha3Tujuan)
                ->when(!is_null($kodeSumber), fn ($q) => $q->where('KodeSumber', $kodeSumber))
                ->whereIn('Tahun', [$y1, $y2])
                ->exists();

            if (!$hasData) {
                return [
                    'success' => true,
                    'message' => "Data jasa tidak tersedia untuk tahun {$y2}/{$y1}.",
                    'data'    => [
                        'meta'  => [
                            'latest_year'  => $y2,
                            'prev_year'    => $y1,
                            'asal'         => $alpha3Asal,
                            'tujuan'       => $tujuanNama,
                            'sumber'       => $sumberNama,
                            'total_latest' => 0,
                            'total_prev'   => 0,
                        ],
                        'items' => [
                            'bothYears' => [],
                        ],
                    ],
                ];
            }
        }

        /** ===================== 3) Query agregat per profesi ===================== */
        $rows = $db->table('tbservices as s')
            ->leftJoin('tbprofesi as p', 'p.ID_Profesi', '=', 's.ID_Profesi')
            ->selectRaw("
                p.Profesi AS label,
                SUM(CASE WHEN s.Tahun = ? THEN COALESCE(s.Jumlah, 0) ELSE 0 END) AS value_y2,
                SUM(CASE WHEN s.Tahun = ? THEN COALESCE(s.Jumlah, 0) ELSE 0 END) AS value_y1
            ", [$y2, $y1])
            ->where('s.Kode_Alpha3_Asal', $alpha3Asal)
            ->where('s.Kode_Alpha3_Tujuan', $alpha3Tujuan)
            ->when(!is_null($kodeSumber), fn ($q) => $q->where('s.KodeSumber', $kodeSumber))
            ->whereIn('s.Tahun', [$y1, $y2])
            ->groupBy('p.Profesi')
            ->orderByDesc(DB::raw('value_y2'))
            ->limit($limit)
            ->get();

        /** ===================== 4) Hitung total untuk share ===================== */
        $totalLatest = 0;
        $totalPrev   = 0;

        foreach ($rows as $r) {
            $totalLatest += (int) ($r->value_y2 ?? 0);
            $totalPrev   += (int) ($r->value_y1 ?? 0);
        }

        /** ===================== 5) Mapping hasil + share & persen perubahan ===================== */
        $bothYears = [];
        foreach ($rows as $r) {
            $label = trim((string) $r->label);
            $v2    = (int) ($r->value_y2 ?? 0);
            $v1    = (int) ($r->value_y1 ?? 0);

            $share = $totalLatest > 0
                ? ($v2 / $totalLatest) * 100
                : null;

            $change = $v1 !== 0
                ? (($v2 - $v1) / $v1) * 100
                : null;

            $bothYears[] = [
                'label'        => $label,
                "value{$y2}"   => $v2,
                "value{$y1}"   => $v1,
                'share'        => $share,
                'change'       => $change,
            ];
        }

        return [
            'success' => true,
            'message' => "Top jasa (asal {$alpha3Asal} → tujuan {$alpha3Tujuan}) {$y2} vs {$y1}",
            'data'    => [
                'meta'  => [
                    'latest_year'  => $y2,
                    'prev_year'    => $y1,
                    'asal'         => $alpha3Asal,
                    'tujuan'       => $tujuanNama,
                    'sumber'       => $sumberNama,
                    'total_latest' => $totalLatest,
                    'total_prev'   => $totalPrev,
                ],
                'items' => [
                    'bothYears' => $bothYears,
                ],
            ],
        ];
    }
}
