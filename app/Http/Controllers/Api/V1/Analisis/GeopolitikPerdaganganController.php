<?php

namespace App\Http\Controllers\Api\V1\Analisis;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Support\SideCacheKey;
use App\Services\Analisis\GeopolitikPerdaganganService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class GeopolitikPerdaganganController extends Controller
{
  private const LIMIT_TOP_GEO = 5;
  private const LIMIT_TOP_PRODUK = 20;
  private const LIMIT_KOMPARASI = 5;

  public function __construct(private GeopolitikPerdaganganService $service) {}

  private function validateAndBuild(Request $request): array
  {
    $v = $request->validate([
      'tahun' => ['nullable', 'integer', 'min:1900'],
    ]);

    return [[
      'tahun' => isset($v['tahun']) ? (int)$v['tahun'] : null,
      'limit_top_geo' => self::LIMIT_TOP_GEO,
      'limit_top_produk' => self::LIMIT_TOP_PRODUK,
      'limit_komparasi' => self::LIMIT_KOMPARASI,
    ]];
  }

  public function geopolitikPerdagangan(Request $request)
  {
    try {
      [$filters] = $this->validateAndBuild($request);

      $cacheKey = $this->makeCacheKey($filters);
      $ttl = now()->addDays(3);

      $data = Cache::remember($cacheKey, $ttl, function () use ($filters) {
        return $this->service->getGeopolitikPerdagangan($filters);
      });

      return ApiResponse::success($data, 'Data geopolitik perdagangan');
    } catch (\Throwable $e) {
      report($e);
      return ApiResponse::error('Gagal memuat data geopolitik perdagangan');
    }
  }

  private function makeCacheKey(array $filters): string
  {
    $payload = [
      'tahun' => $filters['tahun'] ?? null,
      'top_geo' => $filters['limit_top_geo'] ?? self::LIMIT_TOP_GEO,
      'top_produk' => $filters['limit_top_produk'] ?? self::LIMIT_TOP_PRODUK,
      'komparasi' => $filters['limit_komparasi'] ?? self::LIMIT_KOMPARASI,
    ];

    return SideCacheKey::pairs(
      ['analisis', 'geopolitik-perdagangan'],
      [
        'tahun' => $payload['tahun'] ?? 'all',
        'top-geo' => $payload['top_geo'] ?? self::LIMIT_TOP_GEO,
        'top-produk' => $payload['top_produk'] ?? self::LIMIT_TOP_PRODUK,
        'komparasi' => $payload['komparasi'] ?? self::LIMIT_KOMPARASI,
      ]
    );
  }
}
