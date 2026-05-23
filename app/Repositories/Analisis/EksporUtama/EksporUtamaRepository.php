<?php

namespace App\Repositories\Analisis\EksporUtama;

use Illuminate\Support\Facades\DB;

class EksporUtamaRepository implements EksporUtamaRepositoryInterface
{
  protected string $conn = 'server_mysql';

  // ================== KONFIGURASI KUNCI ==================
  protected string $REPORTER = 'IDN';
  protected string $TB_TRADE   = 'tbtrade';
  protected string $TB_COUNTRY = 'tbnegara';
  protected string $TB_SOURCE  = 'tbsumber';
  protected string $TB_HS      = 'tbharmonized';
  protected string $COL_DIRJEN = 'ID_WIl_Kemlu';
  protected string $UNIT = 'Ribu US$';

  public function getEksporUtama(array $filters, int $kodeSumber = 5): array
  {
    $db = DB::connection($this->conn);

    // Naikkan batas GROUP_CONCAT biar JSON list (ASEAN full) tidak kepotong
    // Sesuaikan kalau dataset kamu besar sekali (mis. 5MB: 5*1024*1024)
    $db->statement("SET SESSION group_concat_max_len = 1024*1024");

    // Helpers
    $toA3Arr = function ($v): array {
      if (is_null($v)) return [];
      $arr = is_array($v) ? $v : [$v];
      $arr = array_map(fn($x) => strtoupper(trim((string)$x)), $arr);
      if (in_array('ALL', $arr, true)) return ['ALL'];
      return array_values(array_filter($arr, fn($x) => preg_match('/^[A-Z]{3}$/', $x)));
    };

    $pickFirstOr = function ($arr, $fallback) {
      if (is_array($arr) && count($arr) > 0) return strtoupper((string)$arr[0]);
      if (is_string($arr) && $arr !== '') return strtoupper($arr);
      return strtoupper($fallback);
    };

    $normalizeLimit = function ($v): ?int {
      if ($v === null) return null;
      if (is_string($v)) {
        $s = strtolower(trim($v));
        if ($s === 'all') return null;
        if (is_numeric($s)) $v = (int)$s;
      }
      $allowed = [10, 25, 50];
      return in_array((int)$v, $allowed, true) ? (int)$v : 50; // fallback 50
    };

    // Filters (default: IDN)
    $origin     = $pickFirstOr($toA3Arr($filters['origin'] ?? $this->REPORTER), $this->REPORTER);
    $destArrRaw = $toA3Arr($filters['dest'] ?? []);
    $destIsAll  = in_array('ALL', $destArrRaw, true);
    $destArr    = $destIsAll ? [] : $destArrRaw;
    $limitInput = array_key_exists('limit', $filters) ? $filters['limit'] : 50;
    $limit      = $normalizeLimit($limitInput);

    // Tahun terbaru (y2)
    $qYear = $db->table($this->TB_TRADE . ' as t')
      ->where('t.Status', 'Export')
      ->when(!empty($destArr), fn($q) => $q->whereIn('t.Kode_Alpha3_Partner', $destArr))
      ->where('t.Kode_Sumber', $kodeSumber);

    $latestYear = (int) ($filters['year'] ?? ($qYear->max('t.Tahun') ?: date('Y')));
    $y2 = $latestYear;
    $y1 = max(0, $y2 - 2);
    $ym = ($y2 - 1);
    $yearsSpan = max(1, $y2 - $y1);

    $sumber = $db->table($this->TB_SOURCE)
      ->select('KodeSumber as kode', 'NamaSumber as nama')
      ->where('KodeSumber', $kodeSumber)
      ->first();

    // ===== Ambil nama + A2 untuk origin & dest (untuk meta) =====
    $originRow = $db->table($this->TB_COUNTRY)
      ->select('Negara_IDN', 'Kode_Alpha2')
      ->where('Kode_Alpha3', $origin)
      ->first();

    $originName = $originRow->Negara_IDN ?? null;
    $originA2   = $originRow->Kode_Alpha2 ?? null;

    $destNames = [];
    $destA2Map = [];
    if (!$destIsAll && !empty($destArr)) {
      $destRows = $db->table($this->TB_COUNTRY)
        ->select('Kode_Alpha3', 'Negara_IDN', 'Kode_Alpha2')
        ->whereIn('Kode_Alpha3', $destArr)
        ->get();

      foreach ($destRows as $r) {
        $destNames[$r->Kode_Alpha3]  = $r->Negara_IDN;
        $destA2Map[$r->Kode_Alpha3]  = $r->Kode_Alpha2;
      }
    }

    // Pakai kolom hs_len agar filter HS4 bisa memanfaatkan index.
    $hsLevel = 4;

    // ================= Pivot ekspor (3 tahun) =================
    $expY1Alias = "exp_{$y1}";
    $expYmAlias = "exp_{$ym}";
    $expY2Alias = "exp_{$y2}";
    $revY1Alias = "exp_rev_{$y1}";
    $revYmAlias = "exp_rev_{$ym}";
    $revY2Alias = "exp_rev_{$y2}";

    $eksporPivot = $db->table($this->TB_TRADE . ' as t')
      ->selectRaw("t.HsCode as hs4")
      ->selectRaw("SUM(CASE WHEN t.Tahun = ? THEN t.Nilai ELSE 0 END) as {$expY1Alias}", [$y1])
      ->selectRaw("SUM(CASE WHEN t.Tahun = ? THEN t.Nilai ELSE 0 END) as {$expYmAlias}", [$ym])
      ->selectRaw("SUM(CASE WHEN t.Tahun = ? THEN t.Nilai ELSE 0 END) as {$expY2Alias}", [$y2])
      ->where('t.Status', 'Export')
      ->where('t.Kode_Alpha3_Reporter', $origin)
      ->when(!empty($destArr), fn($q) => $q->whereIn('t.Kode_Alpha3_Partner', $destArr))
      ->whereBetween('t.Tahun', [$y1, $y2])
      ->where('t.Kode_Sumber', $kodeSumber)
      ->where('t.hs_len', $hsLevel)
      ->groupBy('hs4');

    // ================= Total dunia per HS4 (3 tahun) =================
    $worldTotalPivot = $db->table($this->TB_TRADE . ' as t')
      ->selectRaw("t.HsCode as hs4")
      ->selectRaw("SUM(CASE WHEN t.Tahun = ? THEN t.Nilai ELSE 0 END) as total_{$y1}", [$y1])
      ->selectRaw("SUM(CASE WHEN t.Tahun = ? THEN t.Nilai ELSE 0 END) as total_{$ym}", [$ym])
      ->selectRaw("SUM(CASE WHEN t.Tahun = ? THEN t.Nilai ELSE 0 END) as total_{$y2}", [$y2])
      ->where('t.Status', 'Export')
      ->when(!empty($destArr), fn($q) => $q->whereIn('t.Kode_Alpha3_Partner', $destArr))
      ->whereBetween('t.Tahun', [$y1, $y2])
      ->where('t.Kode_Sumber', $kodeSumber)
      ->where('t.hs_len', $hsLevel)
      ->groupBy('hs4');

    // ================= Reverse (Import) ekspor (3 tahun) =================
    // Reverse: reporter <-> partner, status Export -> Import
    $eksporReversePivot = $db->table($this->TB_TRADE . ' as t')
      ->selectRaw("t.HsCode as hs4")
      ->selectRaw("SUM(CASE WHEN t.Tahun = ? THEN t.Nilai ELSE 0 END) as {$revY1Alias}", [$y1])
      ->selectRaw("SUM(CASE WHEN t.Tahun = ? THEN t.Nilai ELSE 0 END) as {$revYmAlias}", [$ym])
      ->selectRaw("SUM(CASE WHEN t.Tahun = ? THEN t.Nilai ELSE 0 END) as {$revY2Alias}", [$y2])
      ->where('t.Status', 'Import')
      ->where('t.Kode_Alpha3_Partner', $origin)
      ->when(!empty($destArr), fn($q) => $q->whereIn('t.Kode_Alpha3_Reporter', $destArr))
      ->whereBetween('t.Tahun', [$y1, $y2])
      ->where('t.Kode_Sumber', $kodeSumber)
      ->where('t.hs_len', $hsLevel)
      ->groupBy('hs4');

    // ================= Kompetitor (Top10 Global, ASEAN Full) =================
    $k0_base = $db->table($this->TB_TRADE . ' as t')
      ->selectRaw("t.HsCode as hs4")
      ->addSelect('t.Kode_Alpha3_Reporter as reporter')
      ->selectRaw("SUM(t.Nilai) as nilai")
      ->where('t.Status', 'Export')
      ->when(!empty($destArr), fn($q) => $q->whereIn('t.Kode_Alpha3_Partner', $destArr))
      ->where('t.Tahun', $y2)
      ->where('t.Kode_Sumber', $kodeSumber)
      ->where('t.hs_len', $hsLevel)
      ->groupBy('hs4', 'reporter');

    // Pastikan origin & IDN selalu muncul (nilai 0) per HS4 jika tidak ada data
    $forceReporters = array_values(array_unique([$origin, 'IDN']));
    $forceListSql = "'" . implode("','", $forceReporters) . "'";

    $k0_origin = $db->query()
      ->fromSub($eksporPivot, 'ep')
      ->selectRaw("ep.hs4, r.reporter, 0 as nilai")
      ->crossJoin(DB::raw("(SELECT {$forceListSql} AS reporter) as r"))
      ->whereNotExists(function ($q) use ($k0_base, $forceReporters) {
        $q->fromSub($k0_base, 'kx')
          ->selectRaw('1')
          ->whereColumn('kx.hs4', 'ep.hs4')
          ->whereIn('kx.reporter', $forceReporters)
          ->whereColumn('kx.reporter', 'r.reporter');
      });

    $k0 = $db->query()->fromSub($k0_base->unionAll($k0_origin), 'k0');

    $ASEAN = ['BRN', 'KHM', 'LAO', 'MYS', 'MMR', 'PHL', 'SGP', 'THA', 'VNM', 'IDN', 'TLS'];
    $aseanList = "'" . implode("','", $ASEAN) . "'";

    // Base: total per hs4 + share
    $k = $db->query()
      ->fromSub($k0, 'k0')
      ->selectRaw('k0.hs4, k0.reporter, k0.nilai')
      ->selectRaw('SUM(k0.nilai) OVER (PARTITION BY k0.hs4) as total_hs4')
      ->selectRaw("ROUND(k0.nilai / NULLIF(SUM(k0.nilai) OVER (PARTITION BY k0.hs4),0) * 100, 2) as share_pct")
      ->selectRaw("CASE WHEN k0.reporter IN ($aseanList) THEN 1 ELSE 0 END as is_asean");

    // Global ranking
    $k_global = $db->query()
      ->fromSub($k, 'k')
      ->selectRaw('k.hs4, k.reporter, k.nilai, k.share_pct')
      ->selectRaw('ROW_NUMBER() OVER (PARTITION BY k.hs4 ORDER BY k.nilai DESC) as rn_global');

    // ASEAN ranking (exclude origin) — full list (tidak dibatasi)
    $k_asean = $db->query()
      ->fromSub($k, 'k')
      ->leftJoinSub($k_global, 'kg', function ($join) {
        $join->on('kg.hs4', '=', 'k.hs4')->on('kg.reporter', '=', 'k.reporter');
      })
      ->where('k.is_asean', 1)
      ->selectRaw('k.hs4, k.reporter, k.nilai, k.share_pct, kg.rn_global as rn_global')
      ->selectRaw('ROW_NUMBER() OVER (PARTITION BY k.hs4 ORDER BY k.nilai DESC) as rn_asean');

    // Aggregate Global TOP10 => JSON array string per hs4
    $kw_list = $db->query()
      ->fromSub($k_global, 'g')
      ->leftJoin($this->TB_COUNTRY . ' as ng', 'ng.Kode_Alpha3', '=', 'g.reporter')
      ->where(function ($q) use ($forceReporters) {
        $q->where('g.rn_global', '<=', 10)
          ->orWhereIn('g.reporter', $forceReporters);
      })
      ->groupBy('g.hs4')
      ->selectRaw('g.hs4')
      ->selectRaw("
        CONCAT(
          '[',
          COALESCE(
            GROUP_CONCAT(
              JSON_OBJECT(
                'kode', g.reporter,
                'nama', ng.Negara_IDN,
                'a2', ng.Kode_Alpha2,
                'nilai', g.nilai,
                'share_pct', g.share_pct,
                'rank', g.rn_global
              )
              ORDER BY g.nilai DESC
              SEPARATOR ','
            ),
            ''
          ),
          ']'
        ) as kompetitor_global_json
      ");

    // Aggregate ASEAN FULL => JSON array string per hs4
    $ka_list = $db->query()
      ->fromSub($k_asean, 'a')
      ->leftJoin($this->TB_COUNTRY . ' as na', 'na.Kode_Alpha3', '=', 'a.reporter')
      ->groupBy('a.hs4')
      ->selectRaw('a.hs4')
      ->selectRaw("
        CONCAT(
          '[',
          COALESCE(
            GROUP_CONCAT(
              JSON_OBJECT(
                'kode', a.reporter,
                'nama', na.Negara_IDN,
                'a2', na.Kode_Alpha2,
                'nilai', a.nilai,
                'share_pct', a.share_pct,
                'rank', a.rn_asean,
                'rank_global', a.rn_global
              )
              ORDER BY a.nilai DESC
              SEPARATOR ','
            ),
            ''
          ),
          ']'
        ) as kompetitor_asean_json
      ");

    // ================= Final (join HS description + kompetitor json list) =================
    $final = $db->query()
      ->fromSub($eksporPivot, 'ep')
      ->leftJoinSub($eksporReversePivot, 'er', 'er.hs4', '=', 'ep.hs4')
      ->leftJoinSub($worldTotalPivot, 'wt', 'wt.hs4', '=', 'ep.hs4')
      ->leftJoinSub($kw_list, 'kw', 'kw.hs4', '=', 'ep.hs4')
      ->leftJoinSub($ka_list, 'ka', 'ka.hs4', '=', 'ep.hs4')
      ->leftJoin($this->TB_HS . ' as hs', 'hs.HsCode', '=', 'ep.hs4')
      ->selectRaw('ep.hs4 as hs4')
      ->selectRaw('COALESCE(hs.description) as hs_desc')
      ->selectRaw("ep.{$expY1Alias} as `{$expY1Alias}`")
      ->selectRaw("ep.{$expYmAlias} as `{$expYmAlias}`")
      ->selectRaw("ep.{$expY2Alias} as `{$expY2Alias}`")
      ->selectRaw("COALESCE(er.{$revY1Alias}, 0) as `{$revY1Alias}`")
      ->selectRaw("COALESCE(er.{$revYmAlias}, 0) as `{$revYmAlias}`")
      ->selectRaw("COALESCE(er.{$revY2Alias}, 0) as `{$revY2Alias}`")
      ->selectRaw("ROUND( ep.{$expY1Alias} / NULLIF(wt.total_{$y1}, 0) * 100, 2 ) as `share_pct_{$y1}`")
      ->selectRaw("ROUND( ep.{$expYmAlias} / NULLIF(wt.total_{$ym}, 0) * 100, 2 ) as `share_pct_{$ym}`")
      ->selectRaw("ROUND( ep.{$expY2Alias} / NULLIF(wt.total_{$y2}, 0) * 100, 2 ) as `share_pct_{$y2}`")
      ->selectRaw("ROUND( (POW( NULLIF(ep.{$expY2Alias},0) / NULLIF(ep.{$expY1Alias},0), " . (1 / $yearsSpan) . " ) - 1) * 100, 2 ) as growth_cagr_pct")
      ->selectRaw("kw.kompetitor_global_json")
      ->selectRaw("ka.kompetitor_asean_json")
      ->orderByDesc("ep.$expY2Alias");

    if (!is_null($limit)) {
      $final->limit($limit);
    }

    $rows = $final->get();

    // Helper cast list
    $castList = function ($arr) {
      if (!is_array($arr)) return [];
      return array_values(array_map(function ($x) {
        $item = [
          'kode'      => $x['kode'] ?? null,
          'nama'      => $x['nama'] ?? null,
          'a2'        => $x['a2'] ?? null,
          'nilai'     => isset($x['nilai']) ? (float) $x['nilai'] : null,
          'share_pct' => isset($x['share_pct']) ? (float) $x['share_pct'] : null,
          'rank'      => isset($x['rank']) ? (int) $x['rank'] : null,
        ];
        if (isset($x['rank_global'])) {
          $item['rank_global'] = (int) $x['rank_global'];
        }
        return $item;
      }, $arr));
    };

    return [
      'meta' => [
        'origin'       => $origin,
        'origin_name'  => $originName,
        'origin_a2'    => $originA2,
        'dest'         => $destIsAll ? ['ALL'] : $destArr,
        'dest_names'   => $destNames,
        'dest_a2'      => $destA2Map,
        'years'        => [$y1, $ym, $y2],
        'unit'         => $this->UNIT,
        'limit'        => $limit ?? 'ALL',
        'sumber'       => $sumber?->nama,
      ],
      'data' => [
        'items' => $rows->map(function ($r) use ($expY1Alias, $expYmAlias, $expY2Alias, $revY1Alias, $revYmAlias, $revY2Alias, $y1, $ym, $y2, $castList) {

          $global = json_decode($r->kompetitor_global_json ?: '[]', true) ?: [];
          $asean  = json_decode($r->kompetitor_asean_json ?: '[]', true) ?: [];
          $shareY1Key = "share_pct_{$y1}";
          $shareYmKey = "share_pct_{$ym}";
          $shareY2Key = "share_pct_{$y2}";

          return [
            'hs4'             => $r->hs4,
            'hs_desc'         => $r->hs_desc,
            $expY1Alias       => (float) $r->{$expY1Alias},
            $expYmAlias       => (float) $r->{$expYmAlias},
            $expY2Alias       => (float) $r->{$expY2Alias},
            $revY1Alias       => (float) $r->{$revY1Alias},
            $revYmAlias       => (float) $r->{$revYmAlias},
            $revY2Alias       => (float) $r->{$revY2Alias},
            $shareY1Key       => is_null($r->{$shareY1Key}) ? null : (float) $r->{$shareY1Key},
            $shareYmKey       => is_null($r->{$shareYmKey}) ? null : (float) $r->{$shareYmKey},
            $shareY2Key       => is_null($r->{$shareY2Key}) ? null : (float) $r->{$shareY2Key},
            'growth_cagr_pct' => is_null($r->growth_cagr_pct) ? null : (float) $r->growth_cagr_pct,

            // LIST (bukan 1 pemenang)
            'kompetitor_global' => $castList($global), // Top 10
            'kompetitor_asean'  => $castList($asean),  // Full ASEAN
          ];
        })->toArray(),
      ],
    ];
  }
}
