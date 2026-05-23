<?php

namespace App\Http\Controllers\Api\V1\NegaraMitra;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\NegaraMitra\OverviewPerdaganganRequest;
use App\Repositories\NegaraMitra\Perdagangan\TradeRepositoryInterface;
use App\Support\SideCacheKey;
use Illuminate\Support\Facades\Cache;

class PerdaganganController extends Controller
{
  public function __construct(private TradeRepositoryInterface $service) {}

  private function splitMeta(array $arr): array
  {
    $meta = $arr['meta'] ?? [];
    unset($arr['meta']);
    return [$meta, $arr];
  }

  private function normalizeLimit(mixed $v): ?int
  {
    if (is_string($v)) {
      $s = strtolower(trim($v));
      if ($s === 'all') return null;
      if (is_numeric($s)) $v = (int) $s;
    }
    if ($v === 0 || $v === '0') return null;
    $allowed = [10, 25, 50];
    return in_array((int)$v, $allowed, true) ? (int)$v : 50;
  }

  public function overview(OverviewPerdaganganRequest $request)
  {
    try {
      $filters = $request->sanitizedFilters();
      $filters['limit'] = $this->normalizeLimit($filters['limit'] ?? null);
      $include = $request->sanitizedInclude();

      $isIdnAll = (function ($f) {
        $o = $f['origin'] ?? null;
        $d = $f['dest']   ?? null;
        $isOriginIdn = (is_array($o) && count($o) === 1 && strtoupper($o[0]) === 'IDN')
          || (is_string($o) && strtoupper($o) === 'IDN');
        $isDestAll = !isset($d) || $d === null
          || (is_string($d) && strtolower($d) === 'all')
          || (is_array($d) && count($d) === 0);
        return $isOriginIdn && $isDestAll;
      })($filters);

      $isChnToIdn = (function ($f) {
        $o = $f['origin'] ?? null;
        $d = $f['dest']   ?? null;
        $isOriginChn = (is_array($o) && count($o) === 1 && strtoupper($o[0]) === 'CHN')
          || (is_string($o) && strtoupper($o) === 'CHN');
        $isDestIdn = (is_array($d) && count($d) === 1 && strtoupper($d[0]) === 'IDN')
          || (is_string($d) && strtoupper($d) === 'IDN');
        return $isOriginChn && $isDestIdn;
      })($filters);

      $year = (int)($filters['year'] ?? 0);
      $nowY = (int) date('Y');
      $ttl  = $year >= $nowY ? now()->addHours(6) : now()->addDays(7);

      if ($isIdnAll || $isChnToIdn) {
        $cacheKey = $this->makeTradeCacheKey($filters, $include);
        $data = Cache::remember($cacheKey, $ttl, function () use ($filters, $include) {
          return $this->service->getComposite($filters, $include);
        });
      } else {
        $data = $this->service->getComposite($filters, $include);
      }

      [$meta, $payload] = $this->splitMeta(is_array($data) ? $data : []);
      return ApiResponse::success($payload, 'Composite trade overview', $meta);
    } catch (\Throwable $e) {
      report($e);
      return ApiResponse::error('Gagal memuat composite trade overview');
    }
  }

  private function makeTradeCacheKey(array $filters, array $include = []): string
  {
    $origin = $filters['origin'] ?? null;
    $dest   = $filters['dest']   ?? null;
    $hs     = $filters['hsCode'] ?? null;

    return SideCacheKey::pairs(
      ['negara-mitra', 'trade', 'overview'],
      [
        'origin' => $origin ?: 'all',
        'dest' => $dest ?: 'all',
        'hs' => $hs === 'ALL' ? 'all' : ($hs ?: 'all'),
        'year' => (int) ($filters['year'] ?? 0),
        'source' => $filters['source'] ?? 5,
        'limit' => $filters['limit'] ?? 'all',
        'include' => $include,
      ]
    );
  }
}
