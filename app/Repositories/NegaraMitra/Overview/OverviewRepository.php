<?php

namespace App\Repositories\NegaraMitra\Overview;

use App\Repositories\NegaraMitra\Overview\OverviewRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OverviewRepository implements OverviewRepositoryInterface
{
  protected string $conn = 'server_mysql';

  /** Map Kode_Sumber → Unit */
  protected function sourceUnit($code): string
  {
    $code = is_null($code) ? null : (string) $code;
    if ($code === '5' || $code === '16') return 'Ribu US$';
    if ($code === '1') return 'Orang';
    return '';
  }

  protected function sumNullable(?float $a, ?float $b): ?float
  {
    if ($a === null || $b === null) return null;
    return $a + $b;
  }

  protected function diffNullable(?float $a, ?float $b): ?float
  {
    if ($a === null || $b === null) return null;
    return $a - $b;
  }

  public function computeStats(string $alpha3, array $sources = []): array
  {
    $alpha3 = strtoupper($alpha3);

    // default sumber
    $sources = array_merge(
      [
        'total'    => 5,  // perdagangan total
        'neraca'   => 5,
        'ekspor'   => 5,
        'impor'    => 5,
        'inbound'  => 16, // investasi inbound
        'outbound' => 16, // investasi outbound
        'tourism'  => 1,  // wisata
        'partner'  => 5,  // top partner perdagangan
      ],
      $sources,
    );

    $db = DB::connection($this->conn);

    /* ======================= TRADE ======================= */
    // Sumber yang dipakai untuk menentukan tahun perdagangan
    $tradeSourceCodes = array_values(array_unique(array_filter([
      $sources['total']   ?? null,
      $sources['neraca']  ?? null,
      $sources['ekspor']  ?? null,
      $sources['impor']   ?? null,
      $sources['partner'] ?? null,
    ], fn($v) => !empty($v))));

    // Ambil 2 tahun terakhir perdagangan (hanya yang total Nilai > 0, dengan filter sumber)
    [$tradeLatest, $tradePrev] = $this->latestTwoYears(
      $db,
      'tbtrade',
      'Kode_Alpha3_Reporter',
      $alpha3,
      'Status',
      ['Export', 'Import'],
      $tradeSourceCodes,
      'Nilai'
    );

    // Fallback kalau benar-benar tidak ada data
    if (!$tradeLatest) {
      $tradeLatest = (int) date('Y');
      $tradePrev   = $tradeLatest - 1;
    }

    $tradeAgg = $this->aggregateTrade($db, $alpha3, [$tradeLatest, $tradePrev], $sources);

    $totalNow   = $this->sumNullable(
      $tradeAgg->get('total')[$tradeLatest]['exp'] ?? null,
      $tradeAgg->get('total')[$tradeLatest]['imp'] ?? null
    );
    $totalPrev  = $this->sumNullable(
      $tradeAgg->get('total')[$tradePrev]['exp'] ?? null,
      $tradeAgg->get('total')[$tradePrev]['imp'] ?? null
    );

    $exportNow  = $tradeAgg->get('ekspor')[$tradeLatest]['exp'] ?? null;
    $exportPrev = $tradeAgg->get('ekspor')[$tradePrev]['exp'] ?? null;

    $importNow  = $tradeAgg->get('impor')[$tradeLatest]['imp'] ?? null;
    $importPrev = $tradeAgg->get('impor')[$tradePrev]['imp'] ?? null;

    $balanceNow = $this->diffNullable(
      $tradeAgg->get('neraca')[$tradeLatest]['exp'] ?? null,
      $tradeAgg->get('neraca')[$tradeLatest]['imp'] ?? null
    );
    $balancePrev = $this->diffNullable(
      $tradeAgg->get('neraca')[$tradePrev]['exp'] ?? null,
      $tradeAgg->get('neraca')[$tradePrev]['imp'] ?? null
    );

    /* ===================== INVESTMENT ==================== */
    // Sumber yang dipakai investment (union inbound/outbound)
    $invSourceCodes = array_values(array_unique(array_filter([
      $sources['inbound']  ?? null,
      $sources['outbound'] ?? null,
    ], fn($v) => !empty($v))));

    [$invLatest, $invPrev] = $this->latestTwoYears(
      $db,
      'tbinvestment',
      'Kode_Alpha3_Asal',      // investasi: selalu filter asal = negara
      $alpha3,
      'Status',
      ['Inbound', 'Outbound'],
      $invSourceCodes,
      'Nilai_Investasi'
    );

    if (!$invLatest) {
      // fallback kalau tidak ada data sama sekali untuk sumber tersebut
      $invLatest = (int) date('Y') - 1;
      $invPrev   = $invLatest - 1;
    }

    $invAgg  = $this->aggregateInvestment($db, $alpha3, [$invLatest, $invPrev], $sources);
    $inNow   = $invAgg->get('inbound')[$invLatest] ?? null;
    $inPrev  = $invAgg->get('inbound')[$invPrev] ?? null;
    $outNow  = $invAgg->get('outbound')[$invLatest] ?? null;
    $outPrev = $invAgg->get('outbound')[$invPrev] ?? null;

    /* ======================= TOURISM ===================== */
    // Sumber tourism
    $tourSourceCodes = array_values(array_unique(array_filter([
      $sources['tourism'] ?? null,
    ], fn($v) => !empty($v))));

    [$tourLatest, $tourPrev] = $this->latestTwoYears(
      $db,
      'tbtourism',
      'Kode_Alpha3_Asal',
      $alpha3,
      null,
      null,
      $tourSourceCodes,
      'Jumlah_Wisatawan'
    );

    if (!$tourLatest) {
      $tourLatest = (int) date('Y') - 1;
      $tourPrev   = $tourLatest - 1;
    }

    $tourAgg     = $this->aggregateTourism($db, $alpha3, [$tourLatest, $tourPrev], $sources);
    $tourNow     = $tourAgg->get('tourism')[$tourLatest] ?? null;
    $tourPrevVal = $tourAgg->get('tourism')[$tourPrev] ?? null;

    /* =============== Nama negara & sumber =============== */
    $countryData = $db->table('tbnegara')
      ->where('Kode_Alpha3', $alpha3)
      ->select('Negara_IDN', 'Kode_Alpha2')
      ->first();

    $countryName = $countryData->Negara_IDN ?? $alpha3;
    $alpha2      = $countryData->Kode_Alpha2 ?? null;

    $usedCodes   = $this->collectUsedSourceCodes($sources);
    $sourceNames = $this->sourceNames($db, $usedCodes);

    /* ============== TOP TRADE PARTNER CARD ============== */
    $topPartner = $this->topTradePartner(
      $db,
      $alpha3,
      $tradeLatest,
      $sources['partner']
    ) ?? [
      'alpha3'     => null,
      'alpha2'     => null,
      'name'       => '—',
      'value'      => null,
      'sourceCode' => $sources['partner'],
    ];

    $partnerCard = $this->buildPartnerCard(
      "Mitra Dagang Terbesar {$countryName} ({$tradeLatest})",
      $topPartner,
      $totalNow,
      $sourceNames,
      'tbtrade'
    );

    /* ===================== Build Cards =================== */
    return [
      'country' => $countryName,
      'alpha3'  => $alpha3,
      'alpha2'  => $alpha2,

      // Trade
      'totalPerdagangan'   => $this->buildMoneyCard(
        "Nilai Perdagangan {$countryName} ke Dunia ({$tradeLatest})",
        $totalNow,
        $totalPrev,
        $tradePrev,
        $sources['total'],
        $sourceNames,
        'tbtrade'
      ),
      'neracaPerdagangan'  => $this->buildMoneyCard(
        "Neraca Perdagangan {$countryName} ke Dunia ({$tradeLatest})",
        $balanceNow,
        $balancePrev,
        $tradePrev,
        $sources['neraca'],
        $sourceNames,
        'tbtrade'
      ),
      'ekspor'             => $this->buildMoneyCard(
        "Jumlah Ekspor {$countryName} ke Dunia ({$tradeLatest})",
        $exportNow,
        $exportPrev,
        $tradePrev,
        $sources['ekspor'],
        $sourceNames,
        'tbtrade'
      ),
      'impor'              => $this->buildMoneyCard(
        "Jumlah Impor {$countryName} dari Dunia ({$tradeLatest})",
        $importNow,
        $importPrev,
        $tradePrev,
        $sources['impor'],
        $sourceNames,
        'tbtrade'
      ),

      // Top partner
      'topTradePartner'    => $partnerCard,

      // Investasi
      'inboundInvestment'  => $this->buildMoneyCard(
        "Total Investasi Masuk {$countryName} dari Dunia ({$invLatest})",
        $inNow,
        $inPrev,
        $invPrev,
        $sources['inbound'],
        $sourceNames,
        'tbinvestment'
      ),
      'outboundInvestment' => $this->buildMoneyCard(
        "Total Investasi Keluar {$countryName} ke Dunia ({$invLatest})",
        $outNow,
        $outPrev,
        $invPrev,
        $sources['outbound'],
        $sourceNames,
        'tbinvestment'
      ),

      // Tourism (jumlah orang)
      'outboundTourism'    => $this->buildCountCard(
        "Kunjungan Wisatawan Keluar dari {$countryName} ({$tourLatest})",
        $tourNow,
        $tourPrevVal,
        $tourPrev,
        $sources['tourism'],
        $sourceNames,
        'tbtourism'
      ),
    ];
  }

  public function computeTradeCountry(?int $year = null, $sources = 5, ?int $limit = null): array
  {
    $db = DB::connection($this->conn);

    // sumber list yang dipakai
    $sourceList = null;
    if (is_int($sources)) {
      $sourceList = [$sources];
    } elseif (is_array($sources) && !empty($sources)) {
      $sourceList = array_values(array_unique(array_map('intval', $sources)));
    }

    // tentukan tahun hanya dari sumber yang dipakai
    if (is_null($year)) {
      $q = $db->table('tbtrade')
        ->whereIn('Status', ['Export', 'Import']);

      if (!is_null($sourceList)) {
        $q->whereIn('Kode_Sumber', $sourceList);
      }

      $year = $q
        ->selectRaw('Tahun, SUM(Nilai) AS total')
        ->groupBy('Tahun')
        ->havingRaw('total > 0')
        ->orderByDesc('Tahun')
        ->limit(1)
        ->pluck('Tahun')
        ->map(fn($y) => (int) $y)
        ->first();

      if (!$year) {
        return ['years' => [], 'items' => []];
      }
    }

    $prevYear = $year - 1;

    $srcKey   = is_null($sourceList) ? 'all' : implode(',', $sourceList);
    $limKey   = is_null($limit) ? 'all' : (string) (int) $limit;
    $cacheKey = "tradeCountry:y{$year}:p{$prevYear}:s{$srcKey}:l{$limKey}";
    $ttl      = now()->diffInSeconds(now()->endOfMonth());

    return Cache::remember($cacheKey, $ttl, function () use ($db, $year, $prevYear, $sourceList, $limit) {

      $rows = $db->table('tbtrade as t')
        ->selectRaw("
          t.Kode_Alpha3_Reporter as alpha3,
          t.Kode_Sumber          as sourceCode,
          t.Tahun                as year,
          SUM(CASE WHEN t.Status='Export' THEN t.Nilai ELSE 0 END) as export,
          SUM(CASE WHEN t.Status='Import' THEN t.Nilai ELSE 0 END) as import
        ")
        ->whereIn('t.Tahun', [$year, $prevYear])
        ->whereIn('t.Status', ['Export', 'Import'])
        ->when(!is_null($sourceList), fn($q) => $q->whereIn('t.Kode_Sumber', $sourceList))
        ->groupBy('t.Kode_Alpha3_Reporter', 't.Kode_Sumber', 't.Tahun')
        ->get();

      if ($rows->isEmpty()) {
        return ['years' => [$year, $prevYear], 'items' => []];
      }

      $nowMap  = [];
      $prevMap = [];
      foreach ($rows as $r) {
        $key = (string) $r->alpha3 . '|' . (int) $r->sourceCode;
        $val = [
          'export' => (float) $r->export,
          'import' => (float) $r->import,
        ];
        if ((int) $r->year === $year)     $nowMap[$key]  = $val;
        if ((int) $r->year === $prevYear) $prevMap[$key] = $val;
      }

      if (empty($nowMap)) {
        return ['years' => [$year, $prevYear], 'items' => []];
      }

      $partners = collect(array_keys($nowMap))
        ->map(fn($k) => explode('|', $k)[0])
        ->unique()
        ->values();

      $meta = $db->table('tbnegara')
        ->whereIn('Kode_Alpha3', $partners)
        ->select('Kode_Alpha3', 'Kode_Alpha2', 'Negara_IDN')
        ->get()
        ->keyBy('Kode_Alpha3');

      $chg = function (float $now, ?float $prev): ?float {
        if ($prev === null || $prev == 0.0) return null;
        return (($now - $prev) / $prev) * 100.0; // persen
      };

      $items = [];
      foreach ($nowMap as $key => $now) {
        [$a3, $src] = explode('|', $key);
        $a3  = (string) $a3;
        $src = (string) $src;

        $prv = $prevMap[$key] ?? ['export' => null, 'import' => null];

        $alpha2  = optional($meta->get($a3))->Kode_Alpha2;
        $country = optional($meta->get($a3))->Negara_IDN ?? $a3;

        $items[] = [
          'alpha3'        => $a3,
          'alpha2'        => $alpha2,
          'country'       => $country,
          'unit'          => $this->sourceUnit($src),
          'export'        => (float) $now['export'],
          'exportPrev'    => is_null($prv['export']) ? null : (float) $prv['export'],
          'exportChange'  => $chg((float) $now['export'], is_null($prv['export']) ? null : (float) $prv['export']),
          'import'        => (float) $now['import'],
          'importPrev'    => is_null($prv['import']) ? null : (float) $prv['import'],
          'importChange'  => $chg((float) $now['import'], is_null($prv['import']) ? null : (float) $prv['import']),
          'balance'       => (float) $now['export'] - (float) $now['import'],
          'sourceCode'    => (int) $src,
        ];
      }

      usort(
        $items,
        fn($a, $b) => (($b['export'] + $b['import']) <=> ($a['export'] + $a['import']))
      );
      if (!is_null($limit) && is_numeric($limit)) {
        $items = array_slice($items, 0, (int) $limit);
      }

      return [
        'years' => [$year, $prevYear],
        'items' => array_values($items),
      ];
    });
  }

  /* ================== AGGREGATORS ================== */

  protected function aggregateTrade($db, string $alpha3, array $years, array $sources): \Illuminate\Support\Collection
  {
    [$yLatest, $yPrev] = $years;
    $yearList = array_values(array_filter([$yLatest, $yPrev], fn($y) => !is_null($y)));

    if (empty($yearList)) {
      return collect([
        'total'  => [],
        'neraca' => [],
        'ekspor' => [],
        'impor'  => [],
      ]);
    }

    $needSrc = array_values(array_unique(array_filter([
      $sources['total']  ?? null,
      $sources['neraca'] ?? null,
      $sources['ekspor'] ?? null,
      $sources['impor']  ?? null,
    ], fn($v) => !empty($v))));

    // Semua sumber (dipakai kalau src null)
    $rowsAll = $db
      ->table('tbtrade')
      ->selectRaw("
        Tahun,
        SUM(CASE WHEN Status='Export' THEN Nilai ELSE 0 END) AS exp_sum,
        SUM(CASE WHEN Status='Import' THEN Nilai ELSE 0 END) AS imp_sum
      ")
      ->where('Kode_Alpha3_Reporter', $alpha3)
      ->whereIn('Tahun', $yearList)
      ->whereIn('Status', ['Export', 'Import'])
      ->when(!empty($needSrc), fn($q) => $q->whereIn('Kode_Sumber', $needSrc))
      ->groupBy('Tahun')
      ->get();

    $mapAll = [];
    foreach ($rowsAll as $r) {
      $mapAll[(int) $r->Tahun] = [
        'exp' => $r->exp_sum === null ? null : (float) $r->exp_sum,
        'imp' => $r->imp_sum === null ? null : (float) $r->imp_sum,
      ];
    }

    // per sumber
    $mapBySrc = [];
    if (!empty($needSrc)) {
      $rowsBySrc = $db
        ->table('tbtrade')
        ->selectRaw("
          Tahun, Kode_Sumber,
          SUM(CASE WHEN Status='Export' THEN Nilai ELSE 0 END) AS exp_sum,
          SUM(CASE WHEN Status='Import' THEN Nilai ELSE 0 END) AS imp_sum
        ")
        ->where('Kode_Alpha3_Reporter', $alpha3)
        ->whereIn('Tahun', $yearList)
        ->whereIn('Status', ['Export', 'Import'])
        ->whereIn('Kode_Sumber', $needSrc)
        ->groupBy('Tahun', 'Kode_Sumber')
        ->get();

      foreach ($rowsBySrc as $r) {
        $mapBySrc[(string) $r->Kode_Sumber][(int) $r->Tahun] = [
          'exp' => $r->exp_sum === null ? null : (float) $r->exp_sum,
          'imp' => $r->imp_sum === null ? null : (float) $r->imp_sum,
        ];
      }
    }

    $buildSeries = function (?string $src) use ($mapAll, $mapBySrc, $yLatest, $yPrev) {
      $years = array_values(array_filter([$yLatest, $yPrev], fn($y) => !is_null($y)));
      $result = [];
      foreach ($years as $y) {
        if (empty($src)) {
          $result[$y] = $mapAll[$y] ?? ['exp' => null, 'imp' => null];
        } else {
          $result[$y] = $mapBySrc[(string) $src][$y] ?? ['exp' => null, 'imp' => null];
        }
      }
      return $result;
    };

    return collect([
      'total'  => $buildSeries($sources['total']  ?? null),
      'neraca' => $buildSeries($sources['neraca'] ?? null),
      'ekspor' => $buildSeries($sources['ekspor'] ?? null),
      'impor'  => $buildSeries($sources['impor']  ?? null),
    ]);
  }

  protected function aggregateInvestment($db, string $alpha3, array $years, array $sources): \Illuminate\Support\Collection
  {
    [$yLatest, $yPrev] = $years;
    $yearList = array_values(array_filter([$yLatest, $yPrev], fn($y) => !is_null($y)));

    if (empty($yearList)) {
      return collect([
        'inbound'  => [],
        'outbound' => [],
      ]);
    }

    $needSrc = array_values(array_unique(array_filter([
      $sources['inbound']  ?? null,
      $sources['outbound'] ?? null,
    ], fn($v) => !empty($v))));

    // agregat semua sumber (dipakai kalau src null)
    $rowsAll = $db
      ->table('tbinvestment')
      ->selectRaw("
        Tahun,
        SUM(CASE WHEN Status='Inbound'  THEN Nilai_Investasi ELSE 0 END) AS in_sum,
        SUM(CASE WHEN Status='Outbound' THEN Nilai_Investasi ELSE 0 END) AS out_sum
      ")
      ->where('Kode_Alpha3_Asal', $alpha3) // ← asal
      ->whereIn('Tahun', $yearList)
      ->whereIn('Status', ['Inbound', 'Outbound'])
      ->when(!empty($needSrc), fn($q) => $q->whereIn('Kode_Sumber', $needSrc))
      ->groupBy('Tahun')
      ->get();

    $all = [];
    foreach ($rowsAll as $r) {
      $all[(int) $r->Tahun] = [
        'in'  => $r->in_sum === null ? null : (float) $r->in_sum,
        'out' => $r->out_sum === null ? null : (float) $r->out_sum,
      ];
    }

    // per sumber
    $bySrc = [];
    if (!empty($needSrc)) {
      $rowsBySrc = $db
        ->table('tbinvestment')
        ->selectRaw("
          Tahun, Kode_Sumber,
          SUM(CASE WHEN Status='Inbound'  THEN Nilai_Investasi ELSE 0 END) AS in_sum,
          SUM(CASE WHEN Status='Outbound' THEN Nilai_Investasi ELSE 0 END) AS out_sum
        ")
        ->where('Kode_Alpha3_Asal', $alpha3)
        ->whereIn('Tahun', $yearList)
        ->whereIn('Status', ['Inbound', 'Outbound'])
        ->whereIn('Kode_Sumber', $needSrc)
        ->groupBy('Tahun', 'Kode_Sumber')
        ->get();

      foreach ($rowsBySrc as $r) {
        $bySrc[(string) $r->Kode_Sumber][(int) $r->Tahun] = [
          'in'  => $r->in_sum === null ? null : (float) $r->in_sum,
          'out' => $r->out_sum === null ? null : (float) $r->out_sum,
        ];
      }
    }

    $buildInbound = function (?string $src) use ($all, $bySrc, $yLatest, $yPrev) {
      $years = array_values(array_filter([$yLatest, $yPrev], fn($y) => !is_null($y)));
      $result = [];
      foreach ($years as $y) {
        if (empty($src)) {
          $result[$y] = $all[$y]['in'] ?? null;
        } else {
          $result[$y] = $bySrc[(string) $src][$y]['in'] ?? null;
        }
      }
      return $result;
    };

    $buildOutbound = function (?string $src) use ($all, $bySrc, $yLatest, $yPrev) {
      $years = array_values(array_filter([$yLatest, $yPrev], fn($y) => !is_null($y)));
      $result = [];
      foreach ($years as $y) {
        if (empty($src)) {
          $result[$y] = $all[$y]['out'] ?? null;
        } else {
          $result[$y] = $bySrc[(string) $src][$y]['out'] ?? null;
        }
      }
      return $result;
    };

    return collect([
      'inbound'  => $buildInbound($sources['inbound']  ?? null),
      'outbound' => $buildOutbound($sources['outbound'] ?? null),
    ]);
  }

  protected function aggregateTourism($db, string $alpha3, array $years, array $sources): \Illuminate\Support\Collection
  {
    [$yLatest, $yPrev] = $years;
    $yearList = array_values(array_filter([$yLatest, $yPrev], fn($y) => !is_null($y)));

    if (empty($yearList)) {
      return collect(['tourism' => []]);
    }

    $rowsAll = $db->table('tbtourism')
      ->selectRaw('Tahun, SUM(Jumlah_Wisatawan) AS visits')
      ->where('Kode_Alpha3_Asal', $alpha3)
      ->whereIn('Tahun', $yearList)
      ->groupBy('Tahun')
      ->get();

    $all = [];
    foreach ($rowsAll as $r) {
      $all[(int) $r->Tahun] = $r->visits === null ? null : (float) $r->visits;
    }

    $tour = [];
    foreach ($yearList as $y) {
      if (!empty($sources['tourism'])) {
        $tour[$y] = $this->tourismBySource($db, $alpha3, $y, $sources['tourism']);
      } else {
        $tour[$y] = $all[$y] ?? null;
      }
    }

    return collect(['tourism' => $tour]);
  }

  protected function tourismBySource($db, string $alpha3, int $year, $sourceCode): ?float
  {
    $row = $db->table('tbtourism')
      ->selectRaw('SUM(Jumlah_Wisatawan) AS total, COUNT(*) AS cnt')
      ->where('Kode_Alpha3_Asal', $alpha3)
      ->where('Tahun', $year)
      ->where('Kode_Sumber', $sourceCode)
      ->first();

    if (!$row || (int) $row->cnt === 0) return null;
    if ($row->total === null) return null;
    return (float) $row->total;
  }

  /* ================== YEAR PICKER ================== */

  /**
   * Ambil 2 tahun terakhir dengan total > 0,
   * bisa filter: negara, status, dan Kode_Sumber.
   */
  protected function latestTwoYears(
    $db,
    string $table,
    ?string $alphaCol,
    ?string $alpha3,
    ?string $statusCol,
    ?array $statusEnums,
    ?array $sourceCodes,
    string $valueColumn
  ): array {
    $q = $db->table($table);

    if ($alphaCol && $alpha3) {
      $q->where($alphaCol, $alpha3);
    }

    if ($statusCol && $statusEnums) {
      $q->whereIn($statusCol, $statusEnums);
    }

    if (!empty($sourceCodes)) {
      $q->whereIn('Kode_Sumber', $sourceCodes);
    }

    $years = $q
      ->selectRaw("Tahun, SUM({$valueColumn}) AS total")
      ->groupBy('Tahun')
      ->havingRaw('total > 0')
      ->orderByDesc('Tahun')
      ->limit(2)
      ->pluck('Tahun')
      ->map(fn($y) => (int) $y)
      ->all();

    $latest = $years[0] ?? null;
    $prev   = $years[1] ?? null;

    return [$latest, $prev];
  }

  /* ================== UTILITIES ================== */

  protected function countryName($db, string $alpha3): string
  {
    return $db->table('tbnegara')->where('Kode_Alpha3', $alpha3)->value('Negara_IDN') ?? $alpha3;
  }

  protected function sourceNames($db, array $codes): array
  {
    if (empty($codes)) return [];
    return $db->table('tbsumber')
      ->whereIn('KodeSumber', $codes)
      ->pluck('NamaSumber', 'KodeSumber')
      ->toArray();
  }

  protected function collectUsedSourceCodes(array $sources): array
  {
    $codes = array_filter([
      $sources['total']   ?? null,
      $sources['neraca']  ?? null,
      $sources['ekspor']  ?? null,
      $sources['impor']   ?? null,
      $sources['inbound'] ?? null,
      $sources['outbound']?? null,
      $sources['tourism'] ?? null,
      $sources['partner'] ?? null,
    ], fn($v) => !empty($v));
    return array_values(array_unique($codes));
  }

  protected function pct(?float $now, ?float $prev): ?float
  {
    if ($prev === null || $prev == 0.0 || $now === null) return null;
    return (($now - $prev) / $prev) * 100.0; // persen
  }

  protected function money($v): string { return 'Ribu US$' . number_format((float) $v, 0, '.', ','); }
  protected function number($v): string { return number_format((float) $v, 0, '.', ','); }
  protected function pctLabel(?float $p): string { return is_null($p) ? '0%' : sprintf('%+.1f%%', $p); }

  /** ====== Builders: RAW numbers + unit per sumber ====== */
  protected function buildMoneyCard(
    string $title,
    ?float $now,
    ?float $prev,
    int $prevYear,
    $sourceCode,
    array $sourceNames,
    string $fallbackSourceLabel
  ): array {

    return [
      'title'  => $title,
      'unit'   => $this->sourceUnit($sourceCode),
      'value'  => $now,
      'prev'   => [
        'year'  => $prevYear,
        'value' => $prev,
      ],
      'source' => $sourceCode
        ? ($sourceNames[$sourceCode] ?? (string) $sourceCode)
        : $fallbackSourceLabel,
    ];
  }

  protected function buildCountCard(
    string $title,
    ?float $now,
    ?float $prev,
    int $prevYear,
    $sourceCode,
    array $sourceNames,
    string $fallbackSourceLabel
  ): array {
    return [
      'title'  => $title,
      'unit'   => $this->sourceUnit($sourceCode),
      'value'  => $now,
      'prev'   => [
        'year'  => $prevYear,
        'value' => $prev,
      ],
      'source' => $sourceCode
        ? ($sourceNames[$sourceCode] ?? (string) $sourceCode)
        : $fallbackSourceLabel,
    ];
  }

  protected function topTradePartner($db, string $alpha3, int $year, $sourceCode = null): ?array
  {
    $tryCols = ['Kode_Alpha3_Partner'];

    foreach ($tryCols as $col) {
      $q = $db
        ->table('tbtrade')
        ->selectRaw("{$col} AS partner, SUM(Nilai) AS total")
        ->where('Kode_Alpha3_Reporter', $alpha3)
        ->where('Tahun', $year)
        ->whereIn('Status', ['Export', 'Import']);

      if (!empty($sourceCode)) {
        $q->where('Kode_Sumber', $sourceCode);
      }

      $q->groupBy($col)
        ->orderByDesc(DB::raw('total'))
        ->limit(1);

      $row = $q->first();
      if ($row && $row->partner) {
        $pAlpha3 = (string) $row->partner;
        $pData = $db->table('tbnegara')
          ->where('Kode_Alpha3', $pAlpha3)
          ->select('Negara_IDN', 'Kode_Alpha2')
          ->first();

        return [
          'alpha3'     => $pAlpha3,
          'alpha2'     => $pData->Kode_Alpha2 ?? null,
          'name'       => $pData->Negara_IDN ?? $pAlpha3,
          'value'      => (float) $row->total,
          'sourceCode' => $sourceCode,
        ];
      }
    }

    return null;
  }

  protected function buildPartnerCard(
    string $title,
    array $partner,
    ?float $totalTradeNow,
    array $sourceNames,
    string $fallbackSourceLabel
  ): array {
    $val   = $partner['value'] ?? null;
    $share = ($val !== null && $totalTradeNow !== null && $totalTradeNow > 0)
      ? ((float) $val / $totalTradeNow) * 100.0
      : null;
    $src   = $partner['sourceCode'] ?? null;

    return [
      'title'        => $title,
      'unit'         => $this->sourceUnit($src),
      'partner'      => [
        'alpha3' => $partner['alpha3'] ?? null,
        'alpha2' => $partner['alpha2'] ?? null,
        'name'   => $partner['name']   ?? '—',
      ],
      'value'        => $val,
      'share'        => is_null($share) ? null : (float) $share,
      'sharePercent' => is_null($share) ? null : (float) $share,
      'source'       => $src
        ? ($sourceNames[$src] ?? (string) $src)
        : $fallbackSourceLabel,
    ];
  }
}
