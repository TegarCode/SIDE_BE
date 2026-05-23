<?php

namespace App\Repositories\NegaraMitra\Overview;

use App\Repositories\NegaraMitra\Overview\TopPerdaganganRepositoryInterface;
use Illuminate\Support\Facades\DB;

class TopPerdaganganRepository implements TopPerdaganganRepositoryInterface
{
  protected string $conn = 'server_mysql';

  protected string $UNIT = 'Ribu US$';
  protected string $TABLE_TRADE = 'tbtrade';
  protected string $TABLE_NEGARA = 'tbnegara';
  protected string $TABLE_SUMBER = 'tbsumber';
  protected string $TABLE_HS = 'tbharmonized';
  protected string $TABLE_ORG_NEGARA = 'tborgnegara';
  protected int $TOP_N = 10;
  protected int $HS_LEN = 4;

  protected function buildTujuanByHs(
    string $reporterAlpha3,
    int $kodeSumber,
    int $year,
    array $hsList,
    string $status
  ): array {
    if (empty($hsList)) {
      return [];
    }

    $rows = DB::connection($this->conn)
      ->table($this->TABLE_TRADE . ' as t')
      ->selectRaw("
        t.HsCode as hscode,
        t.Kode_Alpha3_Partner as partner,
        SUM(t.Nilai) as nilai
      ")
      ->where('t.Kode_Alpha3_Reporter', $reporterAlpha3)
      ->where('t.Kode_Sumber', $kodeSumber)
      ->where('t.Status', $status)
      ->where('t.Tahun', $year)
      ->where('t.hs_len', $this->HS_LEN)
      ->whereIn('t.HsCode', $hsList)
      ->groupBy('t.HsCode', 't.Kode_Alpha3_Partner')
      ->get();

    $agg = [];
    foreach ($rows as $r) {
      $hs = (string) $r->hscode;
      $partner = (string) $r->partner;
      $agg[$hs][$partner] = (int) ($r->nilai ?? 0);
    }

    $destCodes = [];
    foreach ($agg as $hs => $dests) {
      foreach (array_keys($dests) as $code) {
        $destCodes[$code] = true;
      }
    }

    $destMap = [];
    $destCodeList = array_keys($destCodes);
    if (!empty($destCodeList)) {
      $countryRows = DB::connection($this->conn)
        ->table($this->TABLE_NEGARA)
        ->whereIn('Kode_Alpha3', $destCodeList)
        ->select('Kode_Alpha3', 'Kode_Alpha2', 'Negara_IDN')
        ->get();
      foreach ($countryRows as $row) {
        $destMap[(string) $row->Kode_Alpha3] = [
          'negara' => (string) $row->Negara_IDN,
          'kode_alpha2' => (string) $row->Kode_Alpha2,
          'kode_alpha3' => (string) $row->Kode_Alpha3,
        ];
      }
    }

    $result = [];
    foreach ($agg as $hs => $dests) {
      arsort($dests);
      $rankMap = [];
      $rank = 0;
      foreach ($dests as $destAlpha3 => $val) {
        $rank++;
        $rankMap[$destAlpha3] = $rank;
      }

      $top = array_slice($dests, 0, $this->TOP_N, true);
      if (!array_key_exists('IDN', $top) && array_key_exists('IDN', $dests)) {
        $top['IDN'] = $dests['IDN'];
      }

      $list = [];
      foreach ($top as $destAlpha3 => $val) {
        $meta = $destMap[$destAlpha3] ?? [
          'negara' => $destAlpha3,
          'kode_alpha2' => null,
          'kode_alpha3' => $destAlpha3,
        ];
        $list[] = [
          'rank' => $rankMap[$destAlpha3] ?? null,
          'negara' => $meta['negara'],
          'kode_alpha2' => $meta['kode_alpha2'],
          'kode_alpha3' => $meta['kode_alpha3'],
          'nilai' => (int) $val,
        ];
      }
      $result[$hs] = $list;
    }

    return $result;
  }

  protected function buildKompetitorTopTujuanByHs(
    int $kodeSumber,
    int $year,
    array $hsList,
    array $tujuanByHs,
    string $status,
    array $allowedReporters = []
  ): array {
    if (empty($hsList) || empty($tujuanByHs)) {
      return [];
    }

    $topDestByHs = [];
    foreach ($tujuanByHs as $hs => $tujuan) {
      if (!is_array($tujuan) || empty($tujuan)) {
        continue;
      }
      $dest = strtoupper((string) ($tujuan[0]['kode_alpha3'] ?? ''));
      if ($dest !== '') {
        $topDestByHs[(string) $hs] = $dest;
      }
    }
    if (empty($topDestByHs)) {
      return [];
    }

    $destinations = array_values(array_unique(array_values($topDestByHs)));
    $rows = DB::connection($this->conn)
      ->table($this->TABLE_TRADE . ' as t')
      ->selectRaw("
        t.HsCode as hscode,
        t.Kode_Alpha3_Partner as destination,
        t.Kode_Alpha3_Reporter as reporter,
        SUM(t.Nilai) as nilai
      ")
      ->where('t.Kode_Sumber', $kodeSumber)
      ->where('t.Status', $status)
      ->where('t.Tahun', $year)
      ->where('t.hs_len', $this->HS_LEN)
      ->whereIn('t.HsCode', $hsList)
      ->whereIn('t.Kode_Alpha3_Partner', $destinations)
      ->whereColumn('t.Kode_Alpha3_Reporter', '!=', 't.Kode_Alpha3_Partner')
      ->when(!empty($allowedReporters), fn($q) => $q->whereIn('t.Kode_Alpha3_Reporter', $allowedReporters))
      ->groupBy('t.HsCode', 't.Kode_Alpha3_Partner', 't.Kode_Alpha3_Reporter')
      ->get();

    $agg = [];
    $reporterCodes = [];
    foreach ($rows as $row) {
      $hs = (string) $row->hscode;
      $dest = (string) $row->destination;
      $rep = (string) $row->reporter;
      $val = (int) ($row->nilai ?? 0);
      $agg[$hs][$dest][$rep] = $val;
      $reporterCodes[$rep] = true;
    }

    $reporterMap = [];
    $codes = array_keys($reporterCodes);
    if (!empty($codes)) {
      $countryRows = DB::connection($this->conn)
        ->table($this->TABLE_NEGARA)
        ->whereIn('Kode_Alpha3', $codes)
        ->select('Kode_Alpha3', 'Kode_Alpha2', 'Negara_IDN')
        ->get();
      foreach ($countryRows as $cr) {
        $reporterMap[(string) $cr->Kode_Alpha3] = [
          'negara' => (string) $cr->Negara_IDN,
          'kode_alpha2' => (string) $cr->Kode_Alpha2,
          'kode_alpha3' => (string) $cr->Kode_Alpha3,
        ];
      }
    }

    $result = [];
    foreach ($topDestByHs as $hs => $dest) {
      $byReporter = $agg[$hs][$dest] ?? [];
      if (empty($byReporter)) {
        $result[$hs] = [];
        continue;
      }

      arsort($byReporter);
      $rankMap = [];
      $rank = 0;
      foreach ($byReporter as $rep => $val) {
        $rank++;
        $rankMap[$rep] = $rank;
      }

      $topN = array_slice($byReporter, 0, $this->TOP_N, true);
      if (!array_key_exists('IDN', $topN)) {
        if (array_key_exists('IDN', $byReporter)) {
          $topN['IDN'] = $byReporter['IDN'];
        } else {
          $topN['IDN'] = 0;
        }
      }

      $list = [];
      foreach ($topN as $rep => $val) {
        $meta = $reporterMap[$rep] ?? [
          'negara' => $rep,
          'kode_alpha2' => null,
          'kode_alpha3' => $rep,
        ];
        $list[] = [
          'rank' => $rankMap[$rep] ?? null,
          'negara' => $meta['negara'],
          'kode_alpha2' => $meta['kode_alpha2'],
          'kode_alpha3' => $meta['kode_alpha3'],
          'nilai' => (int) $val,
        ];
      }
      $result[$hs] = $list;
    }

    return $result;
  }

  public function topPerdagangan(string $alpha3, int $kodeSumber = 5, int $limit = 20): array
  {
    $alpha3 = strtoupper($alpha3);

    $asal = DB::connection($this->conn)
      ->table($this->TABLE_NEGARA)
      ->select('Negara_IDN as negara', 'Kode_Alpha2 as kode_alpha2')
      ->where('Kode_Alpha3', $alpha3)
      ->first();

    $asalNama = $asal->negara ?? $alpha3;
    $asalAlpha2 = $asal->kode_alpha2 ?? null;

    $y2 = DB::connection($this->conn)
      ->table($this->TABLE_TRADE)
      ->where('Kode_Alpha3_Reporter', $alpha3)
      ->where('hs_len', $this->HS_LEN)
      ->max('Tahun');

    if (!$y2) {
      return [
        'meta'       => [
          'latest_year'         => null,
          'prev_year'           => null,
          'sumber'              => null,
          'total_world'         => 0,
          'total_export_y2'     => 0,
          'total_import_y2'     => 0,
          'total_export_y1'     => 0,
          'total_import_y1'     => 0,
          'asal'                => $asalNama,
          'asal_alpha2'         => $asalAlpha2,
          'asal_alpha3'         => $alpha3,
          'unit'                => $this->UNIT,
        ],
        'items'      => [],
        'top_produk' => ['ekspor' => [], 'impor' => []],
      ];
    }
    $y1 = (int) $y2 - 1;

    $sumber = DB::connection($this->conn)
      ->table($this->TABLE_SUMBER)
      ->select('KodeSumber as kode', 'NamaSumber as nama')
      ->where('KodeSumber', $kodeSumber)
      ->first();

    $totalWorldY2 = (int) DB::connection($this->conn)
      ->table($this->TABLE_TRADE)
      ->where('Kode_Alpha3_Reporter', $alpha3)
      ->where('Kode_Sumber', $kodeSumber)
      ->where('Tahun', $y2)
      ->where('hs_len', $this->HS_LEN)
      ->sum('Nilai');

    $totalExpY2 = (int) DB::connection($this->conn)
      ->table($this->TABLE_TRADE)
      ->where('Kode_Alpha3_Reporter', $alpha3)
      ->where('Kode_Sumber', $kodeSumber)
      ->where('Tahun', $y2)
      ->where('Status', 'Export')
      ->where('hs_len', $this->HS_LEN)
      ->sum('Nilai');

    $totalImpY2 = (int) DB::connection($this->conn)
      ->table($this->TABLE_TRADE)
      ->where('Kode_Alpha3_Reporter', $alpha3)
      ->where('Kode_Sumber', $kodeSumber)
      ->where('Tahun', $y2)
      ->where('Status', 'Import')
      ->where('hs_len', $this->HS_LEN)
      ->sum('Nilai');

    $totalExpY1 = (int) DB::connection($this->conn)
      ->table($this->TABLE_TRADE)
      ->where('Kode_Alpha3_Reporter', $alpha3)
      ->where('Kode_Sumber', $kodeSumber)
      ->where('Tahun', $y1)
      ->where('Status', 'Export')
      ->where('hs_len', $this->HS_LEN)
      ->sum('Nilai');

    $totalImpY1 = (int) DB::connection($this->conn)
      ->table($this->TABLE_TRADE)
      ->where('Kode_Alpha3_Reporter', $alpha3)
      ->where('Kode_Sumber', $kodeSumber)
      ->where('Tahun', $y1)
      ->where('Status', 'Import')
      ->where('hs_len', $this->HS_LEN)
      ->sum('Nilai');

    $y2 = (int) $y2;
    $y1 = (int) $y1;

    $partnerSub = DB::connection($this->conn)
      ->table($this->TABLE_TRADE . ' as t')
      ->selectRaw("
        t.Kode_Alpha3_Partner as partner,
        SUM(CASE WHEN t.Tahun = {$y2} AND t.Status = 'Export' THEN t.Nilai ELSE 0 END) as eksp_y2,
        SUM(CASE WHEN t.Tahun = {$y2} AND t.Status = 'Import' THEN t.Nilai ELSE 0 END) as imp_y2,
        SUM(CASE WHEN t.Tahun = {$y1} AND t.Status = 'Export' THEN t.Nilai ELSE 0 END) as eksp_y1,
        SUM(CASE WHEN t.Tahun = {$y1} AND t.Status = 'Import' THEN t.Nilai ELSE 0 END) as imp_y1
      ")
      ->where('t.Kode_Alpha3_Reporter', $alpha3)
      ->where('t.Kode_Sumber', $kodeSumber)
      ->whereIn('t.Tahun', [$y1, $y2])
      ->where('t.hs_len', $this->HS_LEN)
      ->groupBy('t.Kode_Alpha3_Partner');

    $partnerRows = DB::connection($this->conn)
      ->table(DB::raw("({$partnerSub->toSql()}) as a"))
      ->mergeBindings($partnerSub)
      ->join($this->TABLE_NEGARA . ' as n', 'n.Kode_Alpha3', '=', 'a.partner')
      ->selectRaw("
        n.Negara_IDN  as negara,
        n.Kode_Alpha2 as kode_alpha2,
        n.Kode_Alpha3 as kode_alpha3,
        (a.eksp_y2 + a.imp_y2) as total_y2,
        (a.eksp_y1 + a.imp_y1) as total_y1,
        a.eksp_y2, a.imp_y2, a.eksp_y1, a.imp_y1
      ")
      ->orderByDesc('total_y2')
      ->limit($limit)
      ->get();

    $items = [];
    $denomWorldY2 = $totalWorldY2 > 0 ? $totalWorldY2 : 1;
    $rank = 1;
    foreach ($partnerRows as $r) {
      $total_y2 = (int) ($r->total_y2 ?? 0);
      $total_y1 = (int) ($r->total_y1 ?? 0);
      $eksp_y2  = (int) ($r->eksp_y2  ?? 0);
      $imp_y2   = (int) ($r->imp_y2   ?? 0);
      $eksp_y1  = (int) ($r->eksp_y1  ?? 0);
      $imp_y1   = (int) ($r->imp_y1   ?? 0);

      $proporsi_y2 = ($total_y2 / $denomWorldY2) * 100.0;

      $items[] = [
        'rank'            => $rank,
        'negara'          => (string) $r->negara,
        'kode_alpha2'     => (string) $r->kode_alpha2,
        'kode_alpha3'     => (string) $r->kode_alpha3,
        'ekspor_latest_year' => $eksp_y2,
        'impor_latest_year'  => $imp_y2,
        'total_latest_year'  => $total_y2,
        'ekspor_prev_year'   => $eksp_y1,
        'impor_prev_year'    => $imp_y1,
        'total_prev_year'    => $total_y1,
        'proporsi_y2'     => round($proporsi_y2, 2),
      ];
      $rank++;
    }

    $indonesia = DB::connection($this->conn)
      ->table(DB::raw("({$partnerSub->toSql()}) as a"))
      ->mergeBindings($partnerSub)
      ->join($this->TABLE_NEGARA . ' as n', 'n.Kode_Alpha3', '=', 'a.partner')
      ->selectRaw("
        n.Negara_IDN  as negara,
        n.Kode_Alpha2 as kode_alpha2,
        n.Kode_Alpha3 as kode_alpha3,
        (a.eksp_y2 + a.imp_y2) as total_y2,
        (a.eksp_y1 + a.imp_y1) as total_y1,
        a.eksp_y2, a.imp_y2, a.eksp_y1, a.imp_y1
      ")
      ->where('a.partner', 'IDN')
      ->first();

    $indonesiaRank = null;
    if ($indonesia) {
      $indTotal = (int) ($indonesia->total_y2 ?? 0);
      $higherCount = DB::connection($this->conn)
        ->table(DB::raw("({$partnerSub->toSql()}) as a"))
        ->mergeBindings($partnerSub)
        ->whereRaw('(a.eksp_y2 + a.imp_y2) > ?', [$indTotal])
        ->count();
      $indonesiaRank = $higherCount + 1;
    }

    if ($indonesia) {
      $exists = collect($items)->firstWhere('kode_alpha3', 'IDN');
      if (!$exists) {
        $indTotal = (int) ($indonesia->total_y2 ?? 0);
        $proporsiInd = ($indTotal / $denomWorldY2) * 100.0;

        $items[] = [
          'rank'            => $indonesiaRank,
          'negara'          => (string) $indonesia->negara,
          'kode_alpha2'     => (string) $indonesia->kode_alpha2,
          'kode_alpha3'     => (string) $indonesia->kode_alpha3,
          'ekspor_y2'       => (int) ($indonesia->eksp_y2 ?? 0),
          'impor_y2'        => (int) ($indonesia->imp_y2 ?? 0),
          'total_y2'        => $indTotal,
          'ekspor_y1'       => (int) ($indonesia->eksp_y1 ?? 0),
          'impor_y1'        => (int) ($indonesia->imp_y1 ?? 0),
          'total_y1'        => (int) ($indonesia->total_y1 ?? 0),
          'proporsi_y2'     => round($proporsiInd, 2),
        ];
      }
    }

    $expSub = DB::connection($this->conn)
      ->table($this->TABLE_TRADE . ' as t')
      ->selectRaw("
        t.HsCode as hscode,
        SUM(CASE WHEN t.Tahun = ? THEN t.Nilai ELSE 0 END) as nilai_y2,
        SUM(CASE WHEN t.Tahun = ? THEN t.Nilai ELSE 0 END) as nilai_y1
      ", [$y2, $y1])
      ->where('t.Kode_Alpha3_Reporter', $alpha3)
      ->where('t.Kode_Sumber', $kodeSumber)
      ->where('t.Status', 'Export')
      ->whereIn('t.Tahun', [$y1, $y2])
      ->where('t.hs_len', $this->HS_LEN)
      ->groupBy('t.HsCode');

    $expRows = DB::connection($this->conn)
      ->table(DB::raw("({$expSub->toSql()}) as e"))
      ->mergeBindings($expSub)
      ->orderByDesc('nilai_y2')
      ->limit($limit)
      ->get();

    $impSub = DB::connection($this->conn)
      ->table($this->TABLE_TRADE . ' as t')
      ->selectRaw("
        t.HsCode as hscode,
        SUM(CASE WHEN t.Tahun = ? THEN t.Nilai ELSE 0 END) as nilai_y2,
        SUM(CASE WHEN t.Tahun = ? THEN t.Nilai ELSE 0 END) as nilai_y1
      ", [$y2, $y1])
      ->where('t.Kode_Alpha3_Reporter', $alpha3)
      ->where('t.Kode_Sumber', $kodeSumber)
      ->where('t.Status', 'Import')
      ->whereIn('t.Tahun', [$y1, $y2])
      ->where('t.hs_len', $this->HS_LEN)
      ->groupBy('t.HsCode');

    $impRows = DB::connection($this->conn)
      ->table(DB::raw("({$impSub->toSql()}) as i"))
      ->mergeBindings($impSub)
      ->orderByDesc('nilai_y2')
      ->limit($limit)
      ->get();

    $hsList = collect($expRows)->pluck('hscode')
      ->merge(collect($impRows)->pluck('hscode'))
      ->filter()->unique()->values();

    $hsNames = [];
    if ($hsList->isNotEmpty()) {
      $map = DB::connection($this->conn)
        ->table($this->TABLE_HS)
        ->whereIn('hscode', $hsList)
        ->pluck('description', 'hscode');
      $hsNames = $map->toArray();
    }

    $aseanAlpha3 = DB::connection($this->conn)
      ->table($this->TABLE_ORG_NEGARA)
      ->where('ID_Org', 1)
      ->pluck('Kode_Alpha3')
      ->map(fn ($v) => (string) $v)
      ->unique()
      ->values()
      ->all();

    $expHsList = $expRows->pluck('hscode')->filter()->unique()->values()->all();
    $impHsList = $impRows->pluck('hscode')->filter()->unique()->values()->all();
    $kompetitorSource = ((int) $kodeSumber === 1) ? 5 : (int) $kodeSumber;

    $tujuanEksporByHs = $this->buildTujuanByHs(
      $alpha3,
      $kodeSumber,
      $y2,
      $expHsList,
      'Export'
    );
    $tujuanImporByHs = $this->buildTujuanByHs(
      $alpha3,
      $kodeSumber,
      $y2,
      $impHsList,
      'Import'
    );

    $kompetitorGlobalEksporByHs = $this->buildKompetitorTopTujuanByHs(
      $kompetitorSource,
      $y2,
      $expHsList,
      $tujuanEksporByHs,
      'Export'
    );
    $kompetitorAseanEksporByHs = $this->buildKompetitorTopTujuanByHs(
      $kompetitorSource,
      $y2,
      $expHsList,
      $tujuanEksporByHs,
      'Export',
      $aseanAlpha3
    );
    $kompetitorGlobalImporByHs = $this->buildKompetitorTopTujuanByHs(
      $kompetitorSource,
      $y2,
      $impHsList,
      $tujuanImporByHs,
      'Import'
    );
    $kompetitorAseanImporByHs = $this->buildKompetitorTopTujuanByHs(
      $kompetitorSource,
      $y2,
      $impHsList,
      $tujuanImporByHs,
      'Import',
      $aseanAlpha3
    );

    $denomExp = $totalExpY2 > 0 ? $totalExpY2 : 1;
    $topEkspor = [];
    foreach ($expRows as $r) {
      $ini   = (int) ($r->nilai_y2 ?? 0);
      $lalu  = (int) ($r->nilai_y1 ?? 0);
      $share = ($ini / $denomExp) * 100.0;

      $kode = (string) $r->hscode;
      $nama = (string) ($hsNames[$kode] ?? $kode);

      $topEkspor[] = [
        'kodeHS'         => $kode,
        'namaHS'         => $nama,
        'nilai_latest_year' => $ini,
        'nilai_prev_year'   => $lalu,
        'share'          => round($share, 2),
        'tujuan_ekspor' => $tujuanEksporByHs[$kode] ?? [],
        'kompetitor_global_top_tujuan_ekspor' => $kompetitorGlobalEksporByHs[$kode] ?? [],
        'kompetitor_asean_top_tujuan_ekspor' => $kompetitorAseanEksporByHs[$kode] ?? [],
      ];
    }

    $denomImp = $totalImpY2 > 0 ? $totalImpY2 : 1;
    $topImpor = [];
    foreach ($impRows as $r) {
      $ini   = (int) ($r->nilai_y2 ?? 0);
      $lalu  = (int) ($r->nilai_y1 ?? 0);
      $share = ($ini / $denomImp) * 100.0;

      $kode = (string) $r->hscode;
      $nama = (string) ($hsNames[$kode] ?? $kode);

      $topImpor[] = [
        'kodeHS'         => $kode,
        'namaHS'         => $nama,
        'nilai_latest_year' => $ini,
        'nilai_prev_year'   => $lalu,
        'share'          => round($share, 2),
        'tujuan_impor'  => $tujuanImporByHs[$kode] ?? [],
        'kompetitor_global_top_tujuan_impor' => $kompetitorGlobalImporByHs[$kode] ?? [],
        'kompetitor_asean_top_tujuan_impor' => $kompetitorAseanImporByHs[$kode] ?? [],
      ];
    }
    return [
      'items' => $items,
      'top_produk' => [
        'ekspor' => $topEkspor,
        'impor'  => $topImpor,
      ],
      'meta'  => [
        'latest_year'         => (int) $y2,
        'prev_year'           => (int) $y1,
        'total_world'         => (int) $totalWorldY2,
        'total_export_y2'     => (int) $totalExpY2,
        'total_import_y2'     => (int) $totalImpY2,
        'total_export_y1'     => (int) $totalExpY1,
        'total_import_y1'     => (int) $totalImpY1,
        'sumber'              => $sumber?->nama,
        'asal'                => $asalNama,
        'asal_alpha2'         => $asalAlpha2,
        'asal_alpha3'         => $alpha3,
        'unit'                => $this->UNIT,
      ],
    ];
  }
}
