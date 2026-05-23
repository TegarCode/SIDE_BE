<?php

namespace App\Repositories\NegaraMitra\Overview;

use App\Repositories\NegaraMitra\Overview\TopPariwisataRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TopPariwisataRepository implements TopPariwisataRepositoryInterface
{
  protected string $conn = 'server_mysql';

  public function topPariwisata(string $alpha3, int $kodeSumber = 1, int $limit = 20, ?int $year = null): array
  {
    $alpha3 = strtoupper(trim($alpha3));
    $db = DB::connection($this->conn);

    $tujuanNama = $alpha3;
    $tujuanRow = $db->table('tbnegara')
      ->select('Negara_IDN')
      ->where('Kode_Alpha3', $alpha3)
      ->first();
    if ($tujuanRow && $tujuanRow->Negara_IDN) {
      $tujuanNama = (string) $tujuanRow->Negara_IDN;
    }

    // 1) Tentukan tahun terbaru (y2) dan tahun sebelumnya (y1)
    if (is_null($year)) {
      $y2 = (int) $db->table('tbtourism')
        ->when($kodeSumber, fn($q) => $q->where('Kode_Sumber', $kodeSumber))
        ->max('Tahun');
      if (!$y2) {
        return [
          'success' => true,
          'message' => "Data pariwisata tidak tersedia.",
          'data'    => [
            'meta'  => [
              'latest_year' => null,
              'prev_year'   => null,
              'tujuan'      => $tujuanNama,
              'sumber'      => (string) $kodeSumber,
            ],
            'items' => [
              'inbound'  => [],
              'outbound' => [],
            ],
          ],
        ];
      }
    } else {
      $y2 = (int) $year;
    }
    $y1 = $y2 - 1;

    // 2) Ambil nama sumber (opsional)
    $sumberNama = (string) $kodeSumber;
    try {
      $rowSrc = $db->table('tbsumber')
        ->select('NamaSumber')
        ->where('KodeSumber', $kodeSumber)
        ->first();
      if ($rowSrc && $rowSrc->NamaSumber) {
        $sumberNama = $rowSrc->NamaSumber;
      }
    } catch (\Throwable $e) {
      // abaikan jika tbsumber tidak ada
    }

    // 3) INBOUND: wisatawan masuk (destination = $alpha3, group by origin)
    $inboundRows = $db->table('tbtourism as t')
      ->leftJoin('tbnegara as n', 'n.Kode_Alpha3', '=', 't.Kode_Alpha3_Asal')
      ->selectRaw("
                t.Kode_Alpha3_Asal                               as alpha3,
                COALESCE(MAX(n.Negara_IDN), t.Kode_Alpha3_Asal)  as country,
                SUM(CASE WHEN t.Tahun = ? THEN t.Jumlah_Wisatawan ELSE 0 END) as value_y2,
                SUM(CASE WHEN t.Tahun = ? THEN t.Jumlah_Wisatawan ELSE 0 END) as value_y1
            ", [$y2, $y1])
      ->where('t.Kode_Alpha3_Tujuan', $alpha3)
      ->where('t.Kode_Sumber', $kodeSumber)
      ->whereIn('t.Tahun', [$y1, $y2])
      ->groupBy('t.Kode_Alpha3_Asal')
      ->orderByDesc(DB::raw('value_y2'))
      ->limit($limit)
      ->get();

    // 4) OUTBOUND: wisatawan keluar (origin = $alpha3, group by destination)
    $outboundRows = $db->table('tbtourism as t')
      ->leftJoin('tbnegara as n', 'n.Kode_Alpha3', '=', 't.Kode_Alpha3_Tujuan')
      ->selectRaw("
                t.Kode_Alpha3_Tujuan                              as alpha3,
                COALESCE(MAX(n.Negara_IDN), t.Kode_Alpha3_Tujuan) as country,
                SUM(CASE WHEN t.Tahun = ? THEN t.Jumlah_Wisatawan ELSE 0 END)     as value_y2,
                SUM(CASE WHEN t.Tahun = ? THEN t.Jumlah_Wisatawan ELSE 0 END)     as value_y1
            ", [$y2, $y1])
      ->where('t.Kode_Alpha3_Asal', $alpha3)
      ->where('t.Kode_Sumber', $kodeSumber)
      ->whereIn('t.Tahun', [$y1, $y2])
      ->groupBy('t.Kode_Alpha3_Tujuan')
      ->orderByDesc(DB::raw('value_y2'))
      ->limit($limit)
      ->get();

    // 5) Mapping ke format { country, valueYYYY, valueYYYY-1 }
    $mapRows = function ($rows, int $y2, int $y1): array {
      $out = [];
      foreach ($rows as $r) {
        $out[] = [
          'country'    => (string) $r->country,
          "value{$y2}" => (int) ($r->value_y2 ?? 0),
          "value{$y1}" => (int) ($r->value_y1 ?? 0),
        ];
      }
      return $out;
    };

    $inbound  = $mapRows($inboundRows, $y2, $y1);
    $outbound = $mapRows($outboundRows, $y2, $y1);

    // 6) Return sesuai format
    return [
      'success' => true,
      'message' => "Top pariwisata (inbound & outbound) untuk {$alpha3} ({$y2} vs {$y1})",
      'data'    => [
        'meta'  => [
          'latest_year' => $y2,
          'prev_year'   => $y1,
          'tujuan'      => $tujuanNama,
          'sumber'      => $sumberNama,
        ],
        'items' => [
          'inbound'  => $inbound,
          'outbound' => $outbound,
        ],
      ],
    ];
  }
}
