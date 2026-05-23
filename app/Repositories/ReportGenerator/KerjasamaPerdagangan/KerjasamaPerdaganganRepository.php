<?php

namespace App\Repositories\ReportGenerator\KerjasamaPerdagangan;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KerjasamaPerdaganganRepository implements KerjasamaPerdaganganRepositoryInterface
{

  public function getTableFilterData(array $filters): array
  {
    $destinations = (array) ($filters['destinations'] ?? []);

    if (empty($destinations)) {
      return [];
    }

    $origin   = $filters['origin'];
    $sumber   = $filters['sumber'];
    $yearStart = $filters['year_start'];
    $yearEnd   = $filters['year_end'];

    // Query export/import sums grouped by partner and year
    $rows = DB::connection('server_mysql')
      ->table('tbtrade')
      ->select([
        'Kode_Alpha3_Partner as destination',
        'Tahun as tahun',
        DB::raw("SUM(CASE WHEN Status = 'EXPORT' THEN Nilai ELSE 0 END) AS ekspor"),
        DB::raw("SUM(CASE WHEN Status = 'IMPORT' THEN Nilai ELSE 0 END) AS impor"),
      ])
      ->where('Kode_Sumber', $sumber)
      ->where('Kode_Alpha3_Reporter', $origin)
      ->whereIn('Kode_Alpha3_Partner', $destinations)
      ->whereBetween('Tahun', [$yearStart, $yearEnd])
      ->groupBy('Kode_Alpha3_Partner', 'Tahun')
      ->orderBy('Kode_Alpha3_Partner')
      ->orderBy('Tahun')
      ->get();

    $grouped = [];
    foreach ($rows as $row) {
      $countryLabel = $this->getCountryName($row->destination);
      $neraca = $row->ekspor - $row->impor;
      $total  = $row->ekspor + $row->impor;

      // Initialize group
      if (!isset($grouped[$countryLabel])) {
        $grouped[$countryLabel] = [
          'NegaraTujuan' => $countryLabel,
          'NegaraAsal'   => $this->getCountryName($origin),
          'per'          => [],
        ];
      }

      // Append yearly detail
      $grouped[$countryLabel]['per'][] = [
        'tahun' => (string) $row->tahun,
        'detail' => [
          [
            'ekspor' => number_format($row->ekspor, 0, ',', '.'),
            'impor'  => number_format($row->impor, 0, ',', '.'),
            'neraca' => number_format($neraca, 0, ',', '.'),
            'total'  => number_format($total, 0, ',', '.'),
          ],
        ],
      ];
    }

    return array_values($grouped);
  }

  public function getCountryName(string $alpha3): string
  {
    return DB::connection('server_mysql')
      ->table('tbnegara')
      ->where('Kode_Alpha3', $alpha3)
      ->value('Negara_IDN') ?? $alpha3;
  }

  public function getSourceName(int $id): string
  {
    return DB::connection('server_mysql')
      ->table('tbsumber')
      ->where('KodeSumber', $id)
      ->value('NamaSumber')
      ?? (string)$id;  // fallback ke ID jika nama tidak ditemukan
  }
}
