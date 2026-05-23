<?php

namespace App\Http\Controllers\Api\V1\Analisis;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Support\SideCacheKey;
use App\Services\Analisis\EksporUtamaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class KomoditasEksporUtamaController extends Controller
{
  public function __construct(private EksporUtamaService $service) {}

  private function splitMeta(array $arr): array
  {
    $meta = $arr['meta'] ?? [];
    unset($arr['meta']);
    return [$meta, $arr];
  }

  private function toA3Array(mixed $v): array
  {
    $arr = is_array($v) ? $v : (isset($v) ? [$v] : []);
    $arr = array_map(fn($x) => strtoupper(trim((string)$x)), $arr);
    if (in_array('ALL', $arr, true)) {
      return ['ALL'];
    }
    // hanya 3 huruf A-Z
    $arr = array_values(array_filter($arr, fn($x) => (bool)preg_match('/^[A-Z]{3}$/', $x)));
    // unik & urut biar cache key stabil
    $arr = array_values(array_unique($arr));
    sort($arr);
    return $arr;
  }

  private function validateAndBuild(Request $request): array
  {
    $origin = $this->toA3Array($request->input('origin', ['IDN']));
    if (empty($origin)) $origin = ['IDN'];

    $dest = $this->toA3Array($request->input('dest', []));

    $limitParam = $request->input('limit', '__MISSING__');
    if ($limitParam === '__MISSING__') {
      $limit = 50; // default
    } elseif (is_string($limitParam) && strtolower($limitParam) === 'all') {
      $limit = null; // ALL
    } else {
      $n = (int)$limitParam;
      $allowed = [10, 25, 50];
      $limit = in_array($n, $allowed, true) ? $n : 50;
    }

    return [[
      'origin' => $origin,
      'dest'   => $dest,
      'limit'  => $limit,
      'year'   => $request->filled('year') ? (int)$request->input('year') : null,
    ]];
  }

  public function eksporUtama(Request $request)
  {
    try {
      [$filters] = $this->validateAndBuild($request);

      $ttl = now()->addDays(3);
      $cacheKey = $this->makeTradeCacheKey($filters);
      $data = Cache::remember($cacheKey, $ttl, function () use ($filters) {
        return $this->service->getEksporUtama($filters);
      });

      [$meta, $payload] = $this->splitMeta(is_array($data) ? $data : []);

      return ApiResponse::success($payload, 'Komoditas Ekspor Utama', $meta);
    } catch (\Throwable $e) {
      report($e);
      return ApiResponse::error('Gagal memuat komoditas ekspor utama');
    }
  }

  private function makeTradeCacheKey(array $filters): string
  {
    $keyPayload = [
      'o'     => implode(',', $filters['origin'] ?? []),
      'd'     => implode(',', $filters['dest']   ?? []),
      's'     => $filters['source'] ?? 5,
      'limit' => ($filters['limit'] === null ? 'ALL' : (string)($filters['limit'] ?? '50')),
      'year'  => $filters['year'] ?? null,
    ];

    return SideCacheKey::pairs(
      ['analisis', 'komoditas-ekspor-utama'],
      [
        'origin' => $filters['origin'] ?? ['IDN'],
        'dest' => $filters['dest'] ?? ['ALL'],
        'sumber' => $filters['source'] ?? 5,
        'limit' => $keyPayload['limit'],
        'year' => $filters['year'] ?? 'all',
      ]
    );
  }
}
