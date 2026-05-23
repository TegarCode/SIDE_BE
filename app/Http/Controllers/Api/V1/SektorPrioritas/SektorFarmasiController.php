<?php

namespace App\Http\Controllers\Api\V1\SektorPrioritas;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Support\SideCacheKey;
use App\Services\SektorPrioritas\SektorPrioritasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SektorFarmasiController extends Controller
{
  public function __construct(protected SektorPrioritasService $SektorPrioritasService) {}

  /** Memo per-request agar tidak hit sumber berkali-kali */
  private ?array $allHsMemo = null;

  protected string $conn = 'server_mysql';
  protected string $TB_SEKTOR = 'tbsektor_hscode';
  protected string $cacheKeyHsCode = 'side_cache:sektor-prioritas:farmasi:distinct-hscode';
  protected int $cacheDuration = 259200;
  protected int $ID_Sektor = 11;

  /** GET /api/v1/farmasi/hscode */
  public function hscode(Request $request): JsonResponse
  {
    $rawHs = $request->input(
      'hscode',
      $request->input(
        'hs_list',
        $request->input(
          'hs_codes',
          $request->input('hsCodes', [])
        )
      )
    );
    $hasAll = false;
    if (is_string($rawHs)) {
      $tokens = array_map('trim', explode(',', $rawHs));
      foreach ($tokens as $t) {
        if (strtolower($t) === 'all') {
          $hasAll = true;
          break;
        }
      }
    } elseif (is_array($rawHs)) {
      foreach ($rawHs as $v) {
        if (is_string($v) && strtolower(trim($v)) === 'all') {
          $hasAll = true;
          break;
        }
      }
    }
    $filterList = $this->csvToDigitsArray($rawHs);
    $useProvided = $hasAll || ! empty($filterList);

    $cacheKey = SideCacheKey::pairs(
      ['sektor-prioritas', 'farmasi', 'distinct-hscode'],
      ['hs' => $useProvided ? ($filterList ?: 'all') : 'all']
    );

    $rows = Cache::remember($cacheKey, $this->cacheDuration, function () use ($filterList, $useProvided) {
      if ($useProvided && empty($filterList)) {
        return collect();
      }

      $rows = DB::connection($this->conn)
        ->table("{$this->TB_SEKTOR} as s")
        ->leftJoin('tbsektor as ts', 'ts.ID_Sektor', '=', 's.ID_Sektor')
        ->selectRaw('
          s.ID_Sektor AS id,
          s.Sektor AS sektor,
          s.Kategori AS kategori,
          ts.Desc_Sumber AS desc_sumber,
          GROUP_CONCAT(DISTINCT s.hscode ORDER BY s.hscode SEPARATOR ",") AS hscodes_csv
        ')
        ->where('s.ID_Sektor', $this->ID_Sektor)
        ->when(
          $useProvided && ! empty($filterList),
          fn ($q) => $q->whereIn('s.hscode', $filterList)
        )
        ->groupBy('s.ID_Sektor', 's.Sektor', 's.Kategori', 'ts.Desc_Sumber')
        ->orderBy('s.Sektor')
        ->orderBy('s.Kategori')
        ->get();

      $allCodes = [];
      foreach ($rows as $r) {
        $codes = array_map('trim', explode(',', (string) $r->hscodes_csv));
        foreach ($codes as $c) if ($c !== '') $allCodes[$c] = true;
      }
      $allCodes = array_keys($allCodes);

      $descMap = [];
      if (!empty($allCodes)) {
        $descMap = DB::connection($this->conn)
          ->table('tbharmonized')
          ->whereIn('hscode', $allCodes)
          ->pluck('description', 'hscode')
          ->toArray();
      }

      $items = $rows->map(function ($row) use ($descMap) {
        $codes = array_values(array_filter(array_unique(array_map(
          fn ($x) => trim((string) $x),
          explode(',', (string) $row->hscodes_csv)
        ))));

        $pairs = [];
        foreach ($codes as $code) {
          $pairs[] = [
            'code'        => $code,
            'description' => isset($descMap[$code]) ? trim((string) $descMap[$code]) : '',
          ];
        }

        usort($pairs, fn ($a, $b) => strnatcmp($a['code'], $b['code']));

        return [
          'sektor' => $row->sektor,
          'kategori' => $row->kategori,
          'desc_sumber' => $row->desc_sumber,
          'hscodes' => $pairs,
          'hscodes_count' => count($pairs),
        ];
      });

      return $items
        ->groupBy('id')
        ->map(function ($group) {
          $first = $group->first();
          $seen = [];
          $sorted = $group->sortBy('kategori', SORT_NATURAL | SORT_FLAG_CASE)->values();

          return [
            'sektor' => $first['sektor'],
            'desc_sumber' => $first['desc_sumber'],
            'kategori_groups' => $sorted
              ->map(function ($g) use (&$seen) {
                $filtered = [];
                foreach ($g['hscodes'] as $pair) {
                  $code = $pair['code'];
                  if (isset($seen[$code])) {
                    continue;
                  }
                  $seen[$code] = true;
                  $filtered[] = $pair;
                }

                return [
                  'kategori' => $g['kategori'],
                  'hscodes' => $filtered,
                  'hscodes_count' => count($filtered),
                ];
              })
              ->all(),
          ];
        })
        ->values();
    });

    return response()->json([
      'success' => true,
      'message' => 'Daftar HS Code sektor farmasi',
      'data'    => $rows,
    ]);
  }

  public function nilaiFarmasi(Request $request): JsonResponse
  {
    $filters = $this->normalizeFilters($request);
    unset($filters['partner']);

    // Default: semua HS bila hs_list kosong
    $allHs = $this->allHsCodes();
    $reqHs = $filters['hs_list'] ?? [];
    if (empty($reqHs)) {
      $reqHs = $allHs;
      $filters['hs_list'] = $reqHs;
    }

    $cacheKey = $this->buildCacheKey('nilai-farmasi', $filters);
    $ttl      = now()->addDays(3);

    $data = Cache::remember(
      $cacheKey,
      $ttl,
      fn() => $this->SektorPrioritasService->getNilaiPerdaganganEnergi($filters)
    );

    if (empty($data)) {
      return ApiResponse::success([], 'Tidak ada data.', [
        'filters' => $filters,
      ]);
    }

    [$payload, $meta] = $this->splitPayloadAndMeta($data);
    $meta = array_merge($meta, ['filters' => $filters]);
    return ApiResponse::success($payload, 'Nilai perdagangan farmasi berhasil diambil', $meta);
  }

  public function nilaiFarmasiProduk(Request $request): JsonResponse
  {
    $filters = $this->normalizeFilters($request);

    $kodeSumber = (int) $request->input('sumber', 5);
    $limit      = (int) $request->input('limit', 50);

    $reporters = $this->csvToUpperArray($request->input('origin', []));
    $partners  = $this->csvToUpperArray($request->input('dest', []));
    if (!empty($reporters)) $filters['reporter'] = $reporters;
    if (!empty($partners))  $filters['partner']  = $partners;

    // Default: semua HS bila hs_list kosong
    if (empty($filters['hs_list'])) {
      $filters['hs_list'] = $this->allHsCodes();
    }

    $cacheKey = $this->buildCacheKeyByOriginsDests(
      'farmasi-produk',
      $reporters,
      $partners,
      $filters,
      $kodeSumber,
      $limit
    );
    $ttl  = now()->addDays(3);
    $data = Cache::remember(
      $cacheKey,
      $ttl,
      fn() => $this->SektorPrioritasService->nilaiPerdaganganPerProduk($filters)
    );

    if (empty($data)) {
      return ApiResponse::success([], 'Tidak ada data.');
    }

    [$payload, $meta] = $this->splitPayloadAndMeta($data);

    return ApiResponse::success($payload, 'Produk perdagangan farmasi berhasil diambil', $meta);
  }

  /* ================= Helpers ================= */

  /** Ambil semua HS code sektor farmasi (distinct), cache sampai end-of-day. */
  private function allHsCodes(): array
  {
    if ($this->allHsMemo !== null) {
      return $this->allHsMemo;
    }

    $codes = Cache::remember(
      'side_cache:sektor-prioritas:farmasi:all-hs-codes',
      now()->addDays(3),
      function () {
        $rows = DB::connection($this->conn)
          ->table("{$this->TB_SEKTOR} as s")
          ->where('s.ID_Sektor', $this->ID_Sektor)
          ->distinct()
          ->pluck('s.hscode')
          ->toArray();

        $clean = array_values(array_unique(array_filter(array_map(
          fn ($v) => trim((string) $v),
          $rows
        ))));
        sort($clean, SORT_STRING);
        return $clean;
      }
    );

    $this->allHsMemo = $codes;
    return $codes;
  }

  private function normalizeFilters(Request $request): array
  {
    $filters = [];

    // reporter/partner
    $partner  = $this->csvToUpperArray($request->input('partner', []));
    $reporter = $this->csvToUpperArray($request->input('reporter', []));
    if (!empty($partner))  $filters['partner']  = $partner;
    if (!empty($reporter)) $filters['reporter'] = $reporter;

    // tahun
    $ys = $request->input('year_start');
    $ye = $request->input('year_end');
    if (is_numeric($ys)) $filters['year_start'] = (int)$ys;
    if (is_numeric($ye)) $filters['year_end']   = (int)$ye;

    // status (Export/Import)
    $status = $request->input('status');
    $canon  = function ($v) {
      $s = strtolower(trim((string)$v));
      if (in_array($s, ['export', 'ekspor'], true)) return 'Export';
      if (in_array($s, ['import', 'impor'], true))  return 'Import';
      return null;
    };
    if (is_array($status)) {
      $st = array_values(array_filter(array_unique(array_map($canon, $status))));
      if ($st) $filters['status'] = $st;
    } elseif (is_string($status)) {
      $st = $canon($status);
      if ($st) $filters['status'] = $st;
    }

    // dirjen
    $dirjen = $this->csvToUpperArray($request->input('dirjen', []));
    if (!empty($dirjen)) $filters['dirjen'] = $dirjen;

    // HS LIST (prioritas: hscode -> hs_list -> hs_codes -> hsCodes)
    $rawHs = $request->input(
      'hscode',
      $request->input(
        'hs_list',
        $request->input('hs_codes', $request->input('hsCodes', []))
      )
    );
    if (is_string($rawHs) && strtolower(trim($rawHs)) === 'all') {
      $rawHs = [];
    } elseif (is_array($rawHs)) {
      $hasAll = false;
      foreach ($rawHs as $v) {
        if (is_string($v) && strtolower(trim($v)) === 'all') {
          $hasAll = true;
          break;
        }
      }
      if ($hasAll) {
        $rawHs = [];
      }
    }
    $hsList = $this->csvToDigitsArray($rawHs);
    if (!empty($hsList)) $filters['hs_list'] = $hsList;

    // buang null/kosong
    return array_filter(
      $filters,
      fn($v) => is_array($v) ? count($v) > 0 : !is_null($v) && $v !== ''
    );
  }

  protected function splitPayloadAndMeta(?array $data): array
  {
    $data    = is_array($data) ? $data : [];
    $meta    = Arr::get($data, 'meta', []);
    $payload = $data;
    unset($payload['meta']);
    if (isset($meta['applied_filters'])) unset($meta['applied_filters']);
    return [$payload, $meta];
  }

  private function csvToUpperArray($val): array
  {
    $arr = is_string($val) ? array_map('trim', explode(',', $val)) : (is_array($val) ? $val : []);
    $out = array_values(array_unique(array_filter(array_map(
      fn($v) => strtoupper((string)$v),
      $arr
    ))));
    sort($out, SORT_STRING);
    return $out;
  }

  private function csvToIntArray($val): array
  {
    $arr = is_string($val) ? array_map('trim', explode(',', $val)) : (is_array($val) ? $val : []);
    $out = [];
    foreach ($arr as $v) {
      if ($v === '' || $v === null) continue;
      $n = (int)$v;
      if ($n !== 0 || $v === '0') $out[] = $n;
    }
    $out = array_values(array_unique($out));
    sort($out, SORT_NUMERIC);
    return $out;
  }

  private function csvToDigitsArray($val): array
  {
    $arr = is_string($val) ? array_map('trim', explode(',', $val)) : (is_array($val) ? $val : []);
    $out = [];
    foreach ($arr as $v) {
      $digits = preg_replace('/\D+/', '', (string)$v);
      if ($digits !== '') $out[] = $digits;
    }
    $out = array_values(array_unique($out));
    sort($out, SORT_STRING);
    return $out;
  }

  private function buildCacheKey(string $prefix, array $filters): string
  {
    $stable = $filters;

    // Normalisasi hs_list (unique + sort)
    if (isset($stable['hs_list']) && is_array($stable['hs_list'])) {
      $tmp = array_values(array_unique(array_map('strval', $stable['hs_list'])));
      sort($tmp, SORT_STRING);
      $stable['hs_list'] = $tmp;
    }

    ksort($stable);
    return SideCacheKey::pairs(['sektor-prioritas', 'farmasi', $prefix], $stable);
  }

  private function buildCacheKeyByOriginsDests(
    string $prefix,
    array $origins,
    array $dests,
    array $filters,
    int $kodeSumber,
    int $limit
  ): string {
    $o = $origins; sort($o, SORT_STRING);
    $d = $dests;   sort($d, SORT_STRING);

    // Hilangkan field yang diekspresikan di segmen
    $rest = $filters;
    unset($rest['reporter'], $rest['partner']);

    // Normalisasi hs_list
    if (isset($rest['hs_list']) && is_array($rest['hs_list'])) {
      $tmp = array_values(array_unique(array_map('strval', $rest['hs_list'])));
      sort($tmp, SORT_STRING);
      $rest['hs_list'] = $tmp;
    }

    // sumber & limit
    $rest['_sumber'] = $kodeSumber;
    $rest['_limit']  = $limit;

    return SideCacheKey::pairs(
      ['sektor-prioritas', 'farmasi', $prefix],
      array_merge(
        [
          'origin' => $o ?: 'all',
          'dest' => $d ?: 'all',
        ],
        $rest
      )
    );
  }

  /** perbandingan set string (HS codes) */
  private function sameSetStr(array $a, array $b): bool
  {
    $norm = function(array $x): array {
      $x = array_values(array_unique(array_map('strval', $x)));
      sort($x, SORT_STRING);
      return $x;
    };
    return $norm($a) === $norm($b);
  }
}
