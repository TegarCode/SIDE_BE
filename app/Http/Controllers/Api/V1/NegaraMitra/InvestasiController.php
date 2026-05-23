<?php

namespace App\Http\Controllers\Api\V1\NegaraMitra;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\NegaraMitra\Investasi\OverviewInvestasiMultiRequest;
use App\Http\Requests\NegaraMitra\Investasi\OverviewInvestasiSingleRequest;
use App\Services\NegaraMitra\InvestmentOverviewService;
use App\Support\SideCacheKey;
use Illuminate\Support\Facades\Cache;

class InvestasiController extends Controller
{
  public function __construct(private InvestmentOverviewService $service) {}

  /** Helper kecil untuk memisahkan meta dari payload */
  private function splitMeta(array $arr): array
  {
    $meta = $arr['meta'] ?? [];
    unset($arr['meta']); // buang meta dari data
    return [$meta, $arr];
  }

  private function resolveTtl(?int $year)
  {
    $nowY = (int) date('Y');
    if (!$year) {
      return now()->addHours(6);
    }
    return $year >= $nowY ? now()->addHours(6) : now()->addDays(7);
  }

  private function makeSingleCacheKey(array $filters, array $include = []): string
  {
    $country = strtoupper((string) ($filters['country'] ?? 'IDN'));
    $year = (int) ($filters['year'] ?? 0);
    $limit = (int) ($filters['limit'] ?? 20);
    $source = $filters['source'] ?? 16;
    return SideCacheKey::pairs(
      ['negara-mitra', 'investasi', 'single'],
      [
        'country' => $country,
        'year' => $year,
        'source' => $source,
        'limit' => $limit,
        'include' => $include,
      ]
    );
  }

  private function makeMultiCacheKey(array $filters, array $include = []): string
  {
    $origin = $filters['origin'] ?? null;
    $dest = $filters['dest'] ?? null;
    $year = (int) ($filters['year'] ?? 0);
    $limit = (int) ($filters['limit'] ?? 20);
    $source = $filters['source'] ?? 16;
    return SideCacheKey::pairs(
      ['negara-mitra', 'investasi', 'multi'],
      [
        'origin' => $origin ?: 'all',
        'dest' => $dest ?: 'all',
        'year' => $year,
        'limit' => $limit,
        'source' => $source,
        'include' => $include,
      ]
    );
  }

  public function singleOverview(OverviewInvestasiSingleRequest $request)
  {
    try {
      $filters = $request->sanitizedFilters();
      $include = $request->sanitizedInclude();

      $ttl = $this->resolveTtl(isset($filters['year']) ? (int) $filters['year'] : null);
      $cacheKey = $this->makeSingleCacheKey($filters, $include);

      $composite = Cache::remember($cacheKey, $ttl, function () use ($filters, $include) {
        return $this->service->getComposite($filters, $include);
      });
      [$meta, $payload] = $this->splitMeta(is_array($composite) ? $composite : []);

      return ApiResponse::success($payload, 'Composite investment overview', $meta);
    } catch (\Throwable $e) {
      report($e);
      return ApiResponse::error('Gagal memuat composite investment overview');
    }
  }

  public function multiOverview(OverviewInvestasiMultiRequest $request)
  {
    try {
      $filters = $request->sanitizedFilters();
      $include = $request->sanitizedInclude();

      $ttl = $this->resolveTtl(isset($filters['year']) ? (int) $filters['year'] : null);
      $cacheKey = $this->makeMultiCacheKey($filters, $include);

      $ts = Cache::remember($cacheKey, $ttl, function () use ($filters) {
        return $this->service->getTimeseries($filters);
      });
      [$meta, $payload] = $this->splitMeta(is_array($ts) ? $ts : []);

      return ApiResponse::success($payload, 'Composite investment overview', $meta);
    } catch (\Throwable $e) {
      report($e);
      return ApiResponse::error('Gagal memuat composite investment overview');
    }
  }
}
