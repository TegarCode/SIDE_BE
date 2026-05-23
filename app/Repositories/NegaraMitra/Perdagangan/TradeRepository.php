<?php

namespace App\Repositories\NegaraMitra\Perdagangan;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TradeRepository implements TradeRepositoryInterface
{
  protected string $conn = 'server_mysql';
  private const DEFAULT_SOURCE = 5;
  private const CONN = 'server_mysql';
  protected string $UNIT = 'Ribu US$';

  private function applyOD($q, array $filters)
  {
    $origin = $filters['origin'] ?? null;
    $dest   = $filters['dest']   ?? null;

    if (is_array($origin) && !empty($origin)) $q->whereIn('t.Kode_Alpha3_Reporter', $origin);
    if (is_array($dest)   && !empty($dest))   $q->whereIn('t.Kode_Alpha3_Partner', $dest);
    return $q;
  }

  private function applySource($q, array $filters)
  {
    $src = $filters['source'] ?? self::DEFAULT_SOURCE;
    if (is_string($src) && strtolower(trim($src)) === 'all') return $q;

    if (is_array($src)) {
      $arr = array_values(array_filter(array_map('intval', $src)));
      if (!empty($arr)) $q->whereIn('t.Kode_Sumber', $arr);
      return $q;
    }

    $v = (int)$src;
    if ($v > 0) $q->where('t.Kode_Sumber', $v);
    return $q;
  }

  private function normalizeHsList(array|string|null $hs): ?array
  {
    if ($hs === null || (is_string($hs) && strtoupper(trim($hs)) === 'ALL')) return null;

    $list = [];
    if (is_string($hs)) $list = [$hs];
    elseif (is_array($hs)) $list = $hs;

    $list = array_values(array_unique(array_filter(array_map(
      fn($x) => strtoupper(trim((string)$x)),
      $list
    ), fn($x) => $x !== '')));

    return $list ?: null;
  }

  private function applyHs($q, array $filters)
  {
    $raw = $filters['hsCode'] ?? $filters['hs'] ?? null;
    $codes = $this->normalizeHsList($raw);
    if (!$codes) return $q;

    $q->where(function ($w) use ($codes) {
      foreach ($codes as $c) {
        if (str_contains($c, '*')) {
          $w->orWhere('t.HsCode', 'like', str_replace('*', '%', $c));
          continue;
        }
        $len = strlen($c);
        if ($len === 2 || $len === 4) {
          $w->orWhere('t.HsCode', 'like', $c.'%');
        } else {
          $w->orWhere('t.HsCode', '=', $c);
        }
      }
    });

    return $q;
  }

  private function normalizedSourceCodes(array $filters): array
  {
    $src = $filters['source'] ?? self::DEFAULT_SOURCE;
    if (is_array($src)) {
      $arr = array_values(array_filter(array_map('intval', $src)));
      return $arr ?: [self::DEFAULT_SOURCE];
    }
    $v = (int) $src;
    return [$v ?: self::DEFAULT_SOURCE];
  }

  private function getSourceNames(array $codes): array
  {
    $conn = self::CONN;
    $candidates = ['tbsumber', 'tbref_sumber', 'ref_sumber'];
    $table = null;

    foreach ($candidates as $t) {
      if (Schema::connection($conn)->hasTable($t)) { $table = $t; break; }
    }
    if (!$table) return array_fill_keys($codes, null);

    $schema = Schema::connection($conn);
    $codeCol = $schema->hasColumn($table, 'Kode_Sumber') ? 'Kode_Sumber' : 'KodeSumber';
    $nameCol = $schema->hasColumn($table, 'Nama_Sumber') ? 'Nama_Sumber' : 'NamaSumber';

    $rows = DB::connection($conn)
      ->table($table)
      ->whereIn($codeCol, $codes)
      ->select([$codeCol . ' as code', $nameCol . ' as name'])
      ->get();

    $map = [];
    foreach ($rows as $r) $map[(int)$r->code] = $r->name ? (string)$r->name : null;
    foreach ($codes as $c) if (!array_key_exists($c, $map)) $map[$c] = null;
    return $map;
  }

  private function resolveCountryNames(string|array|null $alpha3): array|string|null
  {
    if ($alpha3 === null) return null;

    $list = is_array($alpha3) ? $alpha3 : [$alpha3];
    $list = array_values(array_unique(array_filter(array_map(
      fn($x) => strtoupper(trim((string)$x)),
      $list
    ), fn($x) => $x !== '')));

    if (empty($list)) return is_array($alpha3) ? [] : null;

    $rows = DB::connection($this->conn)
      ->table('tbnegara')
      ->whereIn('Kode_Alpha3', $list)
      ->pluck('Negara_IDN', 'Kode_Alpha3')
      ->toArray();

    $map = [];
    foreach ($list as $code) {
      $map[$code] = $rows[$code] ?? $code;
    }

    if (is_array($alpha3)) return $map;
    return $map[$list[0]] ?? $list[0];
  }

  public function getLatestYear(array $filters): ?int
  {
    $db = DB::connection($this->conn);
    $q  = $db->table('tbtrade as t')->selectRaw('MAX(t.Tahun) as y');

    $q = $this->applyOD($q, $filters);
    $q = $this->applySource($q, $filters);
    $q = $this->applyHs($q, $filters);

    $row = $q->first();
    return $row?->y ? (int)$row->y : null;
  }

  public function getSummary(array $filters): array
  {
    $filters = ['source' => ($filters['source'] ?? self::DEFAULT_SOURCE)] + $filters;

    $db = DB::connection($this->conn);
    $year = (int)($filters['year'] ?? 0);
    if ($year <= 0) {
      return ['export' => 0.0, 'import' => 0.0, 'export_prev' => 0.0, 'import_prev' => 0.0];
    }
    $prevYear = $year - 1;

    $q = $db->table('tbtrade as t')
      ->selectRaw("
        SUM(CASE WHEN t.Tahun = ? AND t.Status='Export' THEN t.Nilai ELSE 0 END) AS export_total,
        SUM(CASE WHEN t.Tahun = ? AND t.Status='Import' THEN t.Nilai ELSE 0 END) AS import_total,
        SUM(CASE WHEN t.Tahun = ? AND t.Status='Export' THEN t.Nilai ELSE 0 END) AS export_prev_total,
        SUM(CASE WHEN t.Tahun = ? AND t.Status='Import' THEN t.Nilai ELSE 0 END) AS import_prev_total
      ", [$year, $year, $prevYear, $prevYear])
      ->whereIn('t.Status', ['Export', 'Import']);

    $q = $this->applyOD($q, $filters);
    $q = $this->applySource($q, $filters);
    $q = $this->applyHs($q, $filters);

    $row = $q->first();

    return [
      'export'      => (float)($row->export_total ?? 0),
      'import'      => (float)($row->import_total ?? 0),
      'export_prev' => (float)($row->export_prev_total ?? 0),
      'import_prev' => (float)($row->import_prev_total ?? 0),
    ];
  }

  public function getTimeseries(array $filters): array
  {
    $db = DB::connection($this->conn);

    $q = $db->table('tbtrade as t')
      ->selectRaw("
        t.Tahun,
        SUM(CASE WHEN t.Status='Export' THEN t.Nilai ELSE 0 END) AS ekspor,
        SUM(CASE WHEN t.Status='Import' THEN t.Nilai ELSE 0 END) AS impor
      ")
      ->whereIn('t.Status', ['Export', 'Import']);

    $q = $this->applyOD($q, $filters);
    $q = $this->applySource($q, $filters);
    $q = $this->applyHs($q, $filters);

    $rows = $q->groupBy('t.Tahun')->orderBy('t.Tahun')->get();

    return collect($rows)->map(fn($r) => [
      'year'   => (int)$r->Tahun,
      'export' => (float)$r->ekspor,
      'import' => (float)$r->impor,
    ])->all();
  }

  private function swapOD(array $filters): array
  {
    $out = $filters;
    $origin = $filters['origin'] ?? null;
    $dest = $filters['dest'] ?? null;
    $out['origin'] = $dest;
    $out['dest'] = $origin;
    return $out;
  }

  public function getTopProducts(array $filters, string $flow): array
  {
    $db   = DB::connection($this->conn);
    $year = (int)($filters['year'] ?? 0);
    $flow = ucfirst(strtolower($flow)); // Export | Import

    $sub = $db->table('tbtrade as t')
      ->selectRaw('t.HsCode as hs2, SUM(t.Nilai) as total_now')
      ->where('t.Tahun', $year)
      ->where('t.Status', $flow);

    $sub = $this->applyOD($sub, $filters);
    $sub = $this->applySource($sub, $filters);
    $sub = $this->applyHs($sub, $filters);

    $subSql = $sub->groupBy('t.HsCode')->orderByDesc('total_now');

    $rows = $db->table(DB::raw("({$subSql->toSql()}) as aggr"))
      ->mergeBindings($subSql)
      ->leftJoin('tbharmonized as h', 'h.hscode', '=', 'aggr.hs2')
      ->selectRaw('aggr.hs2, COALESCE(h.description, aggr.hs2) as hs_name, aggr.total_now')
      ->orderByDesc('aggr.total_now')
      ->get();

    return collect($rows)->map(fn($r) => [
      'code'  => (string)($r->hs2 ?? ''),
      'label' => (string)($r->hs_name ?? $r->hs2 ?? ''),
      'value' => (float)($r->total_now ?? 0),
    ])->all();
  }

  public function getTopProductsWithReverse(array $filters, string $flow): array
  {
    $flow = ucfirst(strtolower($flow)); // Export | Import
    $reverseFlow = $flow === 'Export' ? 'Import' : 'Export';

    $od = $this->getTopProducts($filters, $flow);
    $reverseFilters = $this->swapOD($filters);
    $rev = $this->getTopProducts($reverseFilters, $reverseFlow);

    $revMap = [];
    foreach ($rev as $r) {
      $revMap[(string)($r['code'] ?? '')] = (float)($r['value'] ?? 0);
    }

    return collect($od)->map(function ($r) use ($revMap) {
      $code = (string)($r['code'] ?? '');
      return [
        'code' => $code,
        'label' => (string)($r['label'] ?? $code),
        'value_od' => (float)($r['value'] ?? 0),
        'value_reverse' => (float)($revMap[$code] ?? 0),
      ];
    })->all();
  }

  public function getComposite(array $filters, array $include): array
  {
    $codes = $this->normalizedSourceCodes($filters);
    $names = $this->getSourceNames($codes);

    if (!array_key_exists('source', $filters)) $filters['source'] = self::DEFAULT_SOURCE;

    $year = (int)($filters['year'] ?? 0);
    if (!$year) $year = $this->getLatestYear($filters);

    if (!$year) {
      return [
        'meta' => [
          'year'        => null,
          'origin'      => $filters['origin'] ?? null,
          'origin_name' => $this->resolveCountryNames($filters['origin'] ?? null),
          'dest'        => $filters['dest'] ?? null,
          'dest_name'   => $this->resolveCountryNames($filters['dest'] ?? null),
          'hsCode'      => $filters['hsCode'] ?? null,
          'source_name' => count($codes) === 1 ? $names[$codes[0]] : array_values($names),
          'unit'        => $this->UNIT,
        ],
        'summary'             => null,
        'timeseries'          => ['data' => []],
        'top_products_export' => [],
        'top_products_import' => [],
      ];
    }

    $filters['year'] = $year;

    $out = [
      'meta' => [
        'year'        => $year,
        'origin'      => $filters['origin'] ?? null,
        'origin_name' => $this->resolveCountryNames($filters['origin'] ?? null),
        'dest'        => $filters['dest'] ?? null,
        'dest_name'   => $this->resolveCountryNames($filters['dest'] ?? null),
        'hsCode'      => $filters['hsCode'] ?? null,
        'source_name' => count($codes) === 1 ? $names[$codes[0]] : array_values($names),
        'unit'        => $this->UNIT,
      ],
    ];

    if (in_array('summary', $include, true)) {
      $sum = $this->getSummary($filters);
      $out['summary'] = [
        'export' => [
          'value_now'  => $sum['export']      ?? 0.0,
          'value_prev' => $sum['export_prev'] ?? 0.0,
        ],
        'import' => [
          'value_now'  => $sum['import']      ?? 0.0,
          'value_prev' => $sum['import_prev'] ?? 0.0,
        ],
      ];
    }

    if (in_array('timeseries', $include, true)) {
      $out['timeseries'] = ['data' => $this->getTimeseries($filters)];
    }

    if (in_array('top_products_export', $include, true)) {
      $out['top_products_export'] = $this->getTopProductsWithReverse($filters, 'Export');
    }

    if (in_array('top_products_import', $include, true)) {
      $out['top_products_import'] = $this->getTopProductsWithReverse($filters, 'Import');
    }

    return $out;
  }
}
