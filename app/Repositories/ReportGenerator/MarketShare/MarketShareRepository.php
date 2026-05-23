<?php

namespace App\Repositories\ReportGenerator\MarketShare;

use Illuminate\Support\Facades\DB;

class MarketShareRepository implements MarketShareRepositoryInterface
{
  public function getTableFilterData(array $filters): array
  {
    $resolveAlpha3 = function (array $groupIds): array {
      // 🔹 Khusus Dunia: ALL → semua Kode_Alpha3 dari tbnegara
      if (in_array('ALL', $groupIds, true)) {
        return DB::connection('server_mysql')
          ->table('tbnegara')
          ->pluck('Kode_Alpha3')
          ->filter()
          ->unique()
          ->values()
          ->toArray();
      }

      // 🔹 Selain ALL: bisa ID_Org (tborgnegara) atau ID_Benua (tbkawasan→tbnegara)
      return collect($groupIds)
        ->flatMap(function ($id) {
          if (is_numeric($id)) {
            // Group organisasi
            return DB::connection('server_mysql')
              ->table('tborgnegara')
              ->where('ID_Org', $id)
              ->pluck('Kode_Alpha3');
          }

          // Kawasan/Benua
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
    };

    // ====== Normalize filters ======
    $originAlpha3 = (string)($filters['origin'] ?? 'IDN');
    $originName   = $this->getCountryName($originAlpha3);

    $destinationGroups = $filters['destination'] ?? [];
    $destinationGroups = is_array($destinationGroups) ? $destinationGroups : [$destinationGroups];

    $destinationList = $resolveAlpha3($destinationGroups);

    if (empty($destinationList)) {
      return [];
    }

    $topN = (int)($filters['top_n'] ?? 5);
    if ($topN <= 0) $topN = 5;

    // ====== Query ======
    $rows = DB::connection('server_mysql')
      ->table('tbtrade as t')
      ->join('tbharmonized as p', 'p.hscode', '=', 't.HsCode')
      ->join('tbnegara as n', 'n.Kode_Alpha3', '=', 't.Kode_Alpha3_Partner')
      ->select([
        't.Kode_Alpha3_Reporter as origin',
        't.Kode_Alpha3_Partner  as destination',
        'n.Negara_IDN           as country_name',
        't.HsCode               as hs4',
        'p.description          as nama_produk',
        DB::raw('SUM(t.Nilai) as nilai'),
      ])
      ->where('t.Status', $filters['strategy1'])
      ->where('t.Kode_Sumber', $filters['sumber'])
      ->where('t.Kode_Alpha3_Reporter', $originAlpha3)
      ->where('t.Tahun', $filters['year'])
      ->whereIn('t.Kode_Alpha3_Partner', $destinationList)
      ->groupBy([
        't.Kode_Alpha3_Reporter',
        't.Kode_Alpha3_Partner',
        'n.Negara_IDN',
        't.HsCode',
        'p.description',
      ])
      ->orderByDesc('nilai')
      ->get();

    // ====== Grouping ======
    $grouped = [];
    foreach ($rows as $row) {
      $country = (string)$row->country_name;
      $value   = (float)$row->nilai;

      if (!isset($grouped[$country])) {
        $grouped[$country] = [
          'negara'   => $country,
          'total'    => 0,
          'products' => [],
        ];
      }

      $grouped[$country]['total'] += $value;
      $grouped[$country]['products'][] = [
        'hs4'         => (string)$row->hs4,
        'nama_produk' => (string)$row->nama_produk,
        'nilai'       => $value,
      ];
    }

    // ====== Build output ======
    $countries = [];

    foreach ($grouped as $c) {
      $total       = (float)$c['total'];
      $productsRaw = $c['products'];

      // Produk sudah terurut dari query (orderByDesc nilai), jadi langsung slice
      $topProducts = array_slice($productsRaw, 0, $topN);

      $products = array_map(function ($prod) use ($total) {
        $nilai  = (float)($prod['nilai'] ?? 0);
        $pangsa = $total > 0 ? ($nilai / $total * 100) : 0;

        return [
          'hs4'         => $prod['hs4'],
          'nama_produk' => $prod['nama_produk'],
          'nilai'       => number_format($nilai, 0, ',', '.'),
          'pangsa'      => number_format($pangsa, 1, ',', '.'),
        ];
      }, $topProducts);

      $countries[] = [
        'KodeNegaraAsal'   => $originAlpha3,
        'NegaraAsal'       => $originName,
        'NegaraTujuan'     => $c['negara'],
        'TipePerdagangan'  => $filters['strategy1'],
        'Tahun'            => $filters['year'],
        'TotalNilai'       => number_format($total, 0, ',', '.'),
        'products'         => $products,
      ];
    }

    // Urutkan negara berdasarkan total nilai (desc)
    usort($countries, function ($a, $b) {
      $aNilai = (int)str_replace('.', '', (string)($a['TotalNilai'] ?? '0'));
      $bNilai = (int)str_replace('.', '', (string)($b['TotalNilai'] ?? '0'));
      return $bNilai <=> $aNilai;
    });

    return $countries;
  }

  public function getSnapshotData(string $origin, string $destination, string $strategy): array
  {
    return DB::connection('server_mysql')
      ->table('tbhasilakhir')
      ->where('KodeNegara_1', $origin)
      ->where('KodeNegara_2', $destination)
      ->where('Strategy', $strategy)
      ->orderByDesc('Ekspor_RI_To_Partner')
      ->limit(20)
      ->get()
      ->toArray();
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
      ?? (string)$id;
  }
}
