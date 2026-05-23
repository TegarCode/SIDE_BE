<?php

namespace App\Repositories\Analisis\GeopolitikPerdagangan;

use Illuminate\Support\Facades\DB;

class GeopolitikPerdaganganRepository implements GeopolitikPerdaganganRepositoryInterface
{
  protected string $conn = 'server_mysql';
  protected string $TB_TRADE = 'tbtrade';
  protected string $TB_COUNTRY = 'tbnegara';
  protected string $TB_HS = 'tbharmonized';
  protected string $UNIT = 'Ribu US$';
  protected string $INDONESIA_A3 = 'IDN';
  protected int $DEFAULT_SOURCE = 5;
  protected array $FIXED_COMPARE_A3 = ['IDN', 'CHN', 'USA', 'RUS', 'FRA', 'GBR'];

  public function getGeopolitikPerdagangan(array $filters): array
  {
    $db = DB::connection($this->conn);
    $kodeSumber = (int)($filters['kode_sumber'] ?? $this->DEFAULT_SOURCE);

    $requestedYear = isset($filters['tahun']) ? (int)$filters['tahun'] : null;
    if ($requestedYear) {
      $year = $requestedYear;
    } else {
      $year = (int)($db->table($this->TB_TRADE)
        ->where('Kode_Sumber', $kodeSumber)
        ->max('Tahun') ?? 0);
    }
    $prevYear = max(0, $year - 1);

    $limitTopGeo = max(1, (int)($filters['limit_top_geo'] ?? 5));
    $limitTopProduk = max(1, (int)($filters['limit_top_produk'] ?? 20));

    $countryMetaMap = $this->getCountryMetaMap($this->FIXED_COMPARE_A3);

    // Ranking negara: hitung seluruh dunia (GROUP BY reporter), lalu ambil negara fixed.
    $allByStatus = $this->fetchAllCountryTotalsByStatus($year, $prevYear, $kodeSumber);

    // Top HS dari Indonesia untuk top_produk dan komparasi.
    $topExportHs = $this->fetchTopHsIndonesiaByStatus('Export', $year, $prevYear, $kodeSumber, $limitTopProduk);
    $topImportHs = $this->fetchTopHsIndonesiaByStatus('Import', $year, $prevYear, $kodeSumber, $limitTopProduk);

    $hsUnion = array_values(array_unique(array_merge(
      array_map(fn($r) => $r['hs'], $topExportHs),
      array_map(fn($r) => $r['hs'], $topImportHs),
    )));

    // Ranking negara per HS: hitung seluruh dunia (GROUP BY status, hs, reporter).
    $allByStatusHs = $this->fetchAllCountryTotalsByStatusAndHs($year, $prevYear, $kodeSumber, $hsUnion);
    $hsNameMap = $this->getHsNameMap($hsUnion);

    return [
      'meta' => [
        'year' => $year,
        'prev_year' => $prevYear,
        'unit' => $this->UNIT,
        'limits' => [
          'top_geo_countries' => $limitTopGeo,
          'top_products' => $limitTopProduk,
        ],
        'geo_countries' => $this->buildGeoCountriesMeta($countryMetaMap),
      ],
      'top_geo_countries' => [
        'export' => $this->buildTopGeoByStatus(
          $allByStatus['Export'] ?? [],
          $countryMetaMap,
          $limitTopGeo
        ),
        'import' => $this->buildTopGeoByStatus(
          $allByStatus['Import'] ?? [],
          $countryMetaMap,
          $limitTopGeo
        ),
      ],
      'top_produk' => [
        'ekspor' => $this->buildProductListByStatus(
          $topExportHs,
          $allByStatusHs['Export'] ?? [],
          $countryMetaMap,
          $hsNameMap,
          true
        ),
        'impor' => $this->buildProductListByStatus(
          $topImportHs,
          $allByStatusHs['Import'] ?? [],
          $countryMetaMap,
          $hsNameMap,
          true
        ),
      ],
    ];
  }

  private function fetchAllCountryTotalsByStatus(int $year, int $prevYear, int $kodeSumber): array
  {
    $rows = DB::connection($this->conn)
      ->table($this->TB_TRADE . ' as t')
      ->selectRaw('t.Status as status')
      ->selectRaw('t.Kode_Alpha3_Reporter as code_alpha3')
      ->selectRaw('SUM(CASE WHEN t.Tahun = ? THEN t.Nilai ELSE 0 END) as prev_value', [$prevYear])
      ->selectRaw('SUM(CASE WHEN t.Tahun = ? THEN t.Nilai ELSE 0 END) as curr_value', [$year])
      ->whereIn('t.Status', ['Export', 'Import'])
      ->where('t.Kode_Sumber', $kodeSumber)
      ->whereIn('t.Tahun', [$prevYear, $year])
      ->whereNotNull('t.Kode_Alpha3_Reporter')
      ->where('t.Kode_Alpha3_Reporter', '!=', '')
      ->where('t.Kode_Alpha3_Reporter', '!=', '0')
      ->where('t.Kode_Alpha3_Reporter', '!=', 'WLD')
      ->groupBy('t.Status', 't.Kode_Alpha3_Reporter')
      ->havingRaw(
        'SUM(CASE WHEN t.Tahun = ? THEN t.Nilai ELSE 0 END) > 0 OR SUM(CASE WHEN t.Tahun = ? THEN t.Nilai ELSE 0 END) > 0',
        [$prevYear, $year]
      )
      ->get();

    $out = ['Export' => [], 'Import' => []];
    foreach ($rows as $row) {
      $status = (string)($row->status ?? '');
      if (!isset($out[$status])) {
        continue;
      }
      $out[$status][] = [
        'code_alpha3' => (string)$row->code_alpha3,
        'prev' => (float)($row->prev_value ?? 0),
        'curr' => (float)($row->curr_value ?? 0),
      ];
    }

    return $out;
  }

  private function fetchTopHsIndonesiaByStatus(
    string $status,
    int $year,
    int $prevYear,
    int $kodeSumber,
    int $limit
  ): array {
    $rows = DB::connection($this->conn)
      ->table($this->TB_TRADE . ' as t')
      ->selectRaw('t.HsCode as hs')
      ->selectRaw('SUM(CASE WHEN t.Tahun = ? THEN t.Nilai ELSE 0 END) as prev_value', [$prevYear])
      ->selectRaw('SUM(CASE WHEN t.Tahun = ? THEN t.Nilai ELSE 0 END) as curr_value', [$year])
      ->where('t.Kode_Alpha3_Reporter', $this->INDONESIA_A3)
      ->where('t.Status', $status)
      ->where('t.Kode_Sumber', $kodeSumber)
      ->where('t.hs_len', 4)
      ->whereIn('t.Tahun', [$prevYear, $year])
      ->whereNotNull('t.HsCode')
      ->where('t.HsCode', '!=', '')
      ->groupBy('t.HsCode')
      ->havingRaw(
        'SUM(CASE WHEN t.Tahun = ? THEN t.Nilai ELSE 0 END) > 0 OR SUM(CASE WHEN t.Tahun = ? THEN t.Nilai ELSE 0 END) > 0',
        [$prevYear, $year]
      )
      ->orderByDesc('curr_value')
      ->orderByDesc('prev_value')
      ->limit($limit)
      ->get();

    $out = [];
    foreach ($rows as $row) {
      $hs = trim((string)($row->hs ?? ''));
      if ($hs === '') {
        continue;
      }
      $out[] = [
        'hs' => $hs,
        'prev' => (float)($row->prev_value ?? 0),
        'curr' => (float)($row->curr_value ?? 0),
      ];
    }

    return $out;
  }

  private function fetchAllCountryTotalsByStatusAndHs(int $year, int $prevYear, int $kodeSumber, array $hsList): array
  {
    if (empty($hsList)) {
      return ['Export' => [], 'Import' => []];
    }

    $rows = DB::connection($this->conn)
      ->table($this->TB_TRADE . ' as t')
      ->selectRaw('t.Status as status')
      ->selectRaw('t.HsCode as hs')
      ->selectRaw('t.Kode_Alpha3_Reporter as code_alpha3')
      ->selectRaw('SUM(CASE WHEN t.Tahun = ? THEN t.Nilai ELSE 0 END) as prev_value', [$prevYear])
      ->selectRaw('SUM(CASE WHEN t.Tahun = ? THEN t.Nilai ELSE 0 END) as curr_value', [$year])
      ->whereIn('t.Status', ['Export', 'Import'])
      ->where('t.Kode_Sumber', $kodeSumber)
      ->where('t.hs_len', 4)
      ->whereIn('t.HsCode', $hsList)
      ->whereIn('t.Tahun', [$prevYear, $year])
      ->whereNotNull('t.Kode_Alpha3_Reporter')
      ->where('t.Kode_Alpha3_Reporter', '!=', '')
      ->where('t.Kode_Alpha3_Reporter', '!=', '0')
      ->where('t.Kode_Alpha3_Reporter', '!=', 'WLD')
      ->groupBy('t.Status', 't.HsCode', 't.Kode_Alpha3_Reporter')
      ->havingRaw(
        'SUM(CASE WHEN t.Tahun = ? THEN t.Nilai ELSE 0 END) > 0 OR SUM(CASE WHEN t.Tahun = ? THEN t.Nilai ELSE 0 END) > 0',
        [$prevYear, $year]
      )
      ->get();

    $out = ['Export' => [], 'Import' => []];
    foreach ($rows as $row) {
      $status = (string)($row->status ?? '');
      $hs = (string)($row->hs ?? '');
      if ($hs === '' || !isset($out[$status])) {
        continue;
      }
      if (!isset($out[$status][$hs])) {
        $out[$status][$hs] = [];
      }
      $out[$status][$hs][] = [
        'code_alpha3' => (string)$row->code_alpha3,
        'prev' => (float)($row->prev_value ?? 0),
        'curr' => (float)($row->curr_value ?? 0),
      ];
    }

    return $out;
  }

  private function buildTopGeoByStatus(array $allRowsStatus, array $countryMetaMap, int $limitTopGeo): array
  {
    $rankPack = $this->buildGlobalRankPack($allRowsStatus);
    $worldPrev = $rankPack['world_prev'];
    $worldCurr = $rankPack['world_curr'];
    $byCode = $rankPack['by_code'];

    $geoRows = [];
    foreach (array_values(array_filter($this->FIXED_COMPARE_A3, fn($a3) => $a3 !== $this->INDONESIA_A3)) as $a3) {
      $meta = $countryMetaMap[$a3] ?? ['code_alpha3' => $a3, 'code_alpha2' => null, 'name' => $a3];
      $row = $byCode[$a3] ?? ['prev' => 0.0, 'curr' => 0.0, 'rank' => null];

      $geoRows[] = [
        'rank' => $row['rank'],
        'code_alpha3' => $meta['code_alpha3'],
        'code_alpha2' => $meta['code_alpha2'],
        'name' => $meta['name'],
        'prev' => [
          'value' => (float)$row['prev'],
          'share' => $this->sharePct((float)$row['prev'], $worldPrev),
        ],
        'curr' => [
          'value' => (float)$row['curr'],
          'share' => $this->sharePct((float)$row['curr'], $worldCurr),
        ],
        'change_pct' => $this->changePct((float)$row['curr'], (float)$row['prev']),
      ];
    }

    usort($geoRows, fn($a, $b) => $this->rankAscComparator($a['rank'], $b['rank']));
    $geoRows = array_slice($geoRows, 0, $limitTopGeo);

    $idnMeta = $countryMetaMap[$this->INDONESIA_A3] ?? [
      'code_alpha3' => $this->INDONESIA_A3,
      'code_alpha2' => null,
      'name' => $this->INDONESIA_A3,
    ];
    $idnRow = $byCode[$this->INDONESIA_A3] ?? ['prev' => 0.0, 'curr' => 0.0, 'rank' => null];

    $geoRows[] = [
      'rank' => $idnRow['rank'],
      'code_alpha3' => $idnMeta['code_alpha3'],
      'code_alpha2' => $idnMeta['code_alpha2'],
      'name' => $idnMeta['name'],
      'prev' => [
        'value' => (float)$idnRow['prev'],
        'share' => $this->sharePct((float)$idnRow['prev'], $worldPrev),
      ],
      'curr' => [
        'value' => (float)$idnRow['curr'],
        'share' => $this->sharePct((float)$idnRow['curr'], $worldCurr),
      ],
      'change_pct' => $this->changePct((float)$idnRow['curr'], (float)$idnRow['prev']),
    ];

    return [
      'world' => [
        'code_alpha3' => 'WLD',
        'name' => 'Dunia',
        'prev' => [
          'value' => $worldPrev,
          'share' => $worldPrev > 0 ? 100.0 : 0.0,
        ],
        'curr' => [
          'value' => $worldCurr,
          'share' => $worldCurr > 0 ? 100.0 : 0.0,
        ],
        'change_pct' => $this->changePct($worldCurr, $worldPrev),
      ],
      'ranks' => array_values($geoRows),
    ];
  }

  private function buildProductListByStatus(
    array $topHs,
    array $allRowsByHs,
    array $countryMetaMap,
    array $hsNameMap,
    bool $withNumber
  ): array {
    $items = [];
    $no = 1;

    foreach ($topHs as $row) {
      $hs = (string)($row['hs'] ?? '');
      if ($hs === '') {
        continue;
      }

      $countriesPack = $this->buildCountriesForProduct(
        $allRowsByHs[$hs] ?? [],
        $countryMetaMap
      );

      $item = [
        'hs' => $hs,
        'produk' => $hsNameMap[$hs] ?? $hs,
        'world' => $countriesPack['world'],
        'countries' => $countriesPack['countries'],
      ];

      if ($withNumber) {
        $item = ['no' => $no] + $item;
      }

      $items[] = $item;
      $no++;
    }

    return $items;
  }

  private function buildCountriesForProduct(array $allRowsForHs, array $countryMetaMap): array
  {
    $rankPack = $this->buildGlobalRankPack($allRowsForHs);
    $worldPrev = $rankPack['world_prev'];
    $worldCurr = $rankPack['world_curr'];
    $byCode = $rankPack['by_code'];

    $selected = [];
    foreach ($this->FIXED_COMPARE_A3 as $a3) {
      $meta = $countryMetaMap[$a3] ?? ['code_alpha3' => $a3, 'code_alpha2' => null, 'name' => $a3];
      $row = $byCode[$a3] ?? ['prev' => 0.0, 'curr' => 0.0, 'rank' => null];

      $selected[] = [
        'kode_alpha3' => $meta['code_alpha3'],
        'kode_alpha2' => $meta['code_alpha2'],
        'nama' => $meta['name'],
        'value' => (float)$row['curr'],
        'prev_value' => (float)$row['prev'],
        'share' => $this->sharePct((float)$row['curr'], $worldCurr),
        'rank' => $row['rank'],
      ];
    }

    usort($selected, function (array $a, array $b) {
      $rankSort = $this->rankAscComparator($a['rank'], $b['rank']);
      if ($rankSort !== 0) {
        return $rankSort;
      }
      return ((float)$b['value']) <=> ((float)$a['value']);
    });

    return [
      'world' => [
        'code_alpha3' => 'WLD',
        'name' => 'Dunia',
        'prev' => [
          'value' => $worldPrev,
          'share' => $worldPrev > 0 ? 100.0 : 0.0,
        ],
        'curr' => [
          'value' => $worldCurr,
          'share' => $worldCurr > 0 ? 100.0 : 0.0,
        ],
        'change_pct' => $this->changePct($worldCurr, $worldPrev),
      ],
      'countries' => array_values($selected),
    ];
  }

  private function buildGlobalRankPack(array $rows): array
  {
    $normalized = [];
    $worldPrev = 0.0;
    $worldCurr = 0.0;

    foreach ($rows as $row) {
      $a3 = strtoupper(trim((string)($row['code_alpha3'] ?? '')));
      if ($a3 === '' || $a3 === '0' || $a3 === 'WLD') {
        continue;
      }

      $prev = (float)($row['prev'] ?? 0.0);
      $curr = (float)($row['curr'] ?? 0.0);
      $worldPrev += $prev;
      $worldCurr += $curr;

      $normalized[] = [
        'code_alpha3' => $a3,
        'prev' => $prev,
        'curr' => $curr,
      ];
    }

    usort($normalized, function (array $a, array $b) {
      $currSort = ((float)$b['curr']) <=> ((float)$a['curr']);
      if ($currSort !== 0) {
        return $currSort;
      }
      return ((float)$b['prev']) <=> ((float)$a['prev']);
    });

    $byCode = [];
    foreach ($normalized as $i => $row) {
      $row['rank'] = $i + 1;
      $byCode[$row['code_alpha3']] = $row;
    }

    return [
      'world_prev' => $worldPrev,
      'world_curr' => $worldCurr,
      'by_code' => $byCode,
    ];
  }

  private function getCountryMetaMap(array $alpha3List): array
  {
    $rows = DB::connection($this->conn)
      ->table($this->TB_COUNTRY)
      ->select('Kode_Alpha3', 'Kode_Alpha2', 'Negara_IDN')
      ->whereIn('Kode_Alpha3', $alpha3List)
      ->get();

    $map = [];
    foreach ($alpha3List as $a3) {
      $map[$a3] = [
        'code_alpha3' => $a3,
        'code_alpha2' => null,
        'name' => $a3,
      ];
    }

    foreach ($rows as $row) {
      $a3 = (string)$row->Kode_Alpha3;
      $map[$a3] = [
        'code_alpha3' => $a3,
        'code_alpha2' => $row->Kode_Alpha2 ? (string)$row->Kode_Alpha2 : null,
        'name' => $row->Negara_IDN ? (string)$row->Negara_IDN : $a3,
      ];
    }

    return $map;
  }

  private function getHsNameMap(array $hsList): array
  {
    if (empty($hsList)) {
      return [];
    }

    $rows = DB::connection($this->conn)
      ->table($this->TB_HS)
      ->select('hscode', 'description')
      ->whereIn('hscode', $hsList)
      ->get();

    $map = [];
    foreach ($rows as $row) {
      $hs = (string)($row->hscode ?? '');
      if ($hs === '') {
        continue;
      }
      $map[$hs] = (string)($row->description ?? $hs);
    }

    return $map;
  }

  private function buildGeoCountriesMeta(array $countryMetaMap): array
  {
    $rows = [[
      'code_alpha3' => 'WLD',
      'code_alpha2' => null,
      'name' => 'Dunia',
    ]];

    foreach ($this->FIXED_COMPARE_A3 as $a3) {
      $rows[] = $countryMetaMap[$a3] ?? [
        'code_alpha3' => $a3,
        'code_alpha2' => null,
        'name' => $a3,
      ];
    }

    return $rows;
  }

  private function rankAscComparator($a, $b): int
  {
    $aNull = is_null($a);
    $bNull = is_null($b);
    if ($aNull && $bNull) {
      return 0;
    }
    if ($aNull) {
      return 1;
    }
    if ($bNull) {
      return -1;
    }
    return ((int)$a) <=> ((int)$b);
  }

  private function sharePct(float $value, float $total): float
  {
    if ($total <= 0) {
      return 0.0;
    }
    return round(($value / $total) * 100, 2);
  }

  private function changePct(float $curr, float $prev): float
  {
    if ($prev == 0.0) {
      return $curr == 0.0 ? 0.0 : 100.0;
    }
    return round((($curr - $prev) / abs($prev)) * 100, 2);
  }
}
