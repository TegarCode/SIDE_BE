<?php

namespace App\Repositories\NegaraMitra\Overview;

use App\Repositories\NegaraMitra\Overview\TopInvestasiRepositoryInterface;
use Illuminate\Support\Facades\DB;

class TopInvestasiRepository implements TopInvestasiRepositoryInterface
{
    protected string $conn = 'server_mysql';

    // TODO: sesuaikan dengan isi kolom di tbinvestment (Status / Jenis / dsb)
    private const STATUS_INBOUND  = 'inbound';
    private const STATUS_OUTBOUND = 'outbound';

    public function topInvestasi(string $alpha3, int $kodeSumber = 16, int $limit = 20, ?int $year = null): array
    {
    $alpha3 = strtoupper($alpha3);
    $db     = DB::connection($this->conn);

    $asal = $db->table('tbnegara')
      ->select('Negara_IDN as negara', 'Kode_Alpha2 as kode_alpha2')
      ->where('Kode_Alpha3', $alpha3)
      ->first();

    $asalNama = $asal->negara ?? $alpha3;
    $asalAlpha2 = $asal->kode_alpha2 ?? null;

        /* ============================================================
         * 1. Helper: ambil 2 tahun terakhir UNTUK SATU STATUS
         *    - status = 'inbound'  (investasi masuk)
         *    - status = 'outbound' (investasi keluar)
         *    Filter SELALU: Kode_Alpha3_Asal = :alpha3
         * ============================================================ */
        $resolveYears = function (string $status) use ($db, $alpha3, $kodeSumber, $year) {
            if ($year === null) {
                // Tidak dipaksa tahun → ambil 2 tahun terakhir yang ADA untuk pasangan (asal,status)
                $years = $db->table('tbinvestment')
                    ->select('Tahun')
                    ->where('Kode_Alpha3_Asal', $alpha3)
                    ->where('Status', $status) // TODO: sesuaikan kolom status
                    ->when($kodeSumber !== null, fn ($q) => $q->where('Kode_Sumber', $kodeSumber))
                    ->distinct()
                    ->orderByDesc('Tahun')
                    ->limit(2)
                    ->pluck('Tahun')
                    ->map(fn ($y) => (int) $y)
                    ->all();

                $y2 = $years[0] ?? null; // tahun terbaru
                $y1 = $years[1] ?? null; // tahun sebelumnya (kalau ada)
            } else {
                // Dipaksa tahun → jadikan y2, tapi tetap cek ada datanya
                $y2 = (int) $year;

                $hasY2 = $db->table('tbinvestment')
                    ->where('Kode_Alpha3_Asal', $alpha3)
                    ->where('Status', $status)
                    ->when($kodeSumber !== null, fn ($q) => $q->where('Kode_Sumber', $kodeSumber))
                    ->where('Tahun', $y2)
                    ->exists();

                if (!$hasY2) {
                    // Tidak ada data untuk year ini → arah/status ini dianggap kosong
                    $y2 = null;
                    $y1 = null;
                } else {
                    // Cari tahun sebelumnya yang ADA
                    $y1 = $db->table('tbinvestment')
                        ->where('Kode_Alpha3_Asal', $alpha3)
                        ->where('Status', $status)
                        ->when($kodeSumber !== null, fn ($q) => $q->where('Kode_Sumber', $kodeSumber))
                        ->where('Tahun', '<', $y2)
                        ->max('Tahun');

                    $y1 = $y1 ? (int) $y1 : null;
                }
            }

            return [$y2, $y1];
        };

        // Tahun per STATUS (dengan filter Asal = alpha3)
        [$y2Inbound, $y1Inbound]   = $resolveYears(self::STATUS_INBOUND);
        [$y2Outbound, $y1Outbound] = $resolveYears(self::STATUS_OUTBOUND);

        // Kalau dua-duanya nggak ada data sama sekali
        if (!$y2Inbound && !$y2Outbound) {
            return [
                'success' => true,
                'message' => "Tidak ada data investasi terkait {$alpha3}.",
                'data'    => [
                    'meta'  => [
                        'latest_year'          => null,
                        'prev_year'            => null,
                        'tujuan'               => $alpha3,
                        'sumber'               => null,
                    'inbound_latest_year'  => null,
                    'inbound_prev_year'    => null,
                    'outbound_latest_year' => null,
                    'outbound_prev_year'   => null,
                    'asal'                 => $asalNama,
                    'asal_alpha2'          => $asalAlpha2,
                    'asal_alpha3'          => $alpha3,
                  ],
                  'items' => [
                    'inbound'  => [],
                    'outbound' => [],
                    ],
                ],
            ];
        }

        /* ============================================================
         * 2. Nama sumber (optional)
         * ============================================================ */
        $sumberNama = null;
        if ($kodeSumber !== null) {
            $sumberNama = optional(
                $db->table('tbsumber')
                    ->select('NamaSumber')
                    ->where('KodeSumber', $kodeSumber)
                    ->first()
            )->NamaSumber;
        }

        /* ============================================================
         * 3. Helper: ambil top investasi per STATUS
         *    - Filter utama: Kode_Alpha3_Asal = :alpha3
         *    - Group: Kode_Alpha3_Tujuan (partner)
         *    - Urutan: nilai terbesar di tahun terakhir (y2)
         *    - Key nilai: nama tahun (string)
         * ============================================================ */
        $getData = function (
            string $status,
            ?int $y2,
            ?int $y1
        ) use ($db, $alpha3, $kodeSumber, $limit) {
            if (!$y2) {
                // Tidak ada data untuk status ini
                return [];
            }

            $selects = [
                't.Kode_Alpha3_Tujuan as alpha3',
                DB::raw('n.Kode_Alpha2 as alpha2'),
                DB::raw('COALESCE(n.Negara_IDN, t.Kode_Alpha3_Tujuan) as negara'),
                // nilai di tahun terakhir → dipakai buat ORDER BY
                DB::raw("SUM(CASE WHEN t.Tahun = {$y2} THEN t.Nilai_Investasi ELSE 0 END) as total_y2"),
            ];

            if ($y1 !== null) {
                $selects[] = DB::raw("SUM(CASE WHEN t.Tahun = {$y1} THEN t.Nilai_Investasi ELSE 0 END) as total_y1");
            } else {
                $selects[] = DB::raw("0 as total_y1");
            }

            $query = $db->table('tbinvestment as t')
                ->join('tbnegara as n', 'n.Kode_Alpha3', '=', 't.Kode_Alpha3_Tujuan')
                ->select($selects)
                ->where('t.Kode_Alpha3_Asal', $alpha3)
                ->where('t.Status', $status) // TODO: sesuaikan nama kolom status
                ->when($kodeSumber !== null, fn ($q) => $q->where('t.Kode_Sumber', $kodeSumber))
                ->when(
                    $y1 !== null,
                    fn ($q) => $q->whereIn('t.Tahun', [$y1, $y2]),
                    fn ($q) => $q->where('t.Tahun', $y2)
                )
                ->groupBy('t.Kode_Alpha3_Tujuan', 'n.Kode_Alpha2', 'n.Negara_IDN')
                ->orderByDesc('total_y2') // nilai terbesar tahun terakhir
                ->limit($limit);

            $rows = $query->get();

            return $rows->map(function ($r) use ($y2, $y1) {
                $now  = (float) $r->total_y2;
                $prev = (float) $r->total_y1;

                $pct = ($y1 !== null && $prev > 0.0)
                    ? (($now - $prev) / $prev) * 100.0
                    : null;

                $item = [
                    'negara' => (string) $r->negara,
                    'alpha3' => (string) $r->alpha3,
                    'alpha2' => $r->alpha2 ? (string) $r->alpha2 : null,
                    'persen' => $pct,
                ];

                // nilai tahun terakhir
                $item[(string) $y2] = $now;

                // nilai tahun sebelumnya, kalau 0 → null (biar jelas tidak ada)
                if ($y1 !== null) {
                    $item[(string) $y1] = $prev > 0 ? $prev : null;
                }

                return $item;
            })->values()->all();
        };

        // inbound = Status INBOUND, Asal = alpha3, partner = Tujuan
        $inbound = $getData(self::STATUS_INBOUND, $y2Inbound, $y1Inbound);

        // outbound = Status OUTBOUND, Asal = alpha3, partner = Tujuan
        $outbound = $getData(self::STATUS_OUTBOUND, $y2Outbound, $y1Outbound);

        // Meta global (kalau mau dipakai untuk judul dsb)
        $latestYear = max($y2Inbound ?: 0, $y2Outbound ?: 0) ?: null;
        $prevCandidates = array_filter([$y1Inbound, $y1Outbound], fn ($v) => $v !== null);
        $prevYear       = !empty($prevCandidates) ? max($prevCandidates) : null;

        $labelYears = $latestYear
            ? ($prevYear ? " ({$latestYear} vs {$prevYear})" : " ({$latestYear})")
            : "";

        return [
            'success' => true,
            'message' => "Top investasi masuk & keluar untuk {$alpha3}{$labelYears}",
            'data'    => [
                'meta'  => [
                    'latest_year'          => $latestYear,
                    'prev_year'            => $prevYear,
                    'tujuan'               => $alpha3,
                    'sumber'               => $sumberNama,
                    'inbound_latest_year'  => $y2Inbound,
                    'inbound_prev_year'    => $y1Inbound,
                    'outbound_latest_year' => $y2Outbound,
                    'outbound_prev_year'   => $y1Outbound,
                    'asal'                 => $asalNama,
                    'asal_alpha2'          => $asalAlpha2,
                    'asal_alpha3'          => $alpha3,
                ],
                'items' => [
                    'inbound'  => $inbound,
                    'outbound' => $outbound,
                ],
            ],
        ];
    }
}
