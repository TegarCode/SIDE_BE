<?php

namespace App\Http\Controllers\Api\V1\NegaraMitra;

use App\Http\Controllers\Controller;
use App\Support\SideCacheKey;
use App\Services\NegaraMitra\OverviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class OverviewController extends Controller
{
  public function __construct(protected OverviewService $overviewService) {}

  private function buildCacheKey(Request $request, string $prefix): string
  {
    $filters = $request->only(['negara', 'year', 'tahun']);
    $filters = array_filter($filters, static fn ($value) => $value !== null && $value !== '');
    $segments = array_values(array_filter(explode(':', trim($prefix, ':'))));

    return SideCacheKey::filters(
      array_merge(['negara-mitra'], $segments),
      $filters,
      ['negara', 'year', 'tahun']
    );
  }

  public function stats(Request $request): JsonResponse
  {
    $negara = strtoupper((string) $request->query('negara', ''));

    if (!preg_match('/^[A-Z]{3}$/', $negara)) {
      return response()->json(
        [
          'success' => false,
          'message' => 'Parameter negara (alpha-3) wajib diisi, contoh: CHN',
          'data'    => (object) [],
        ],
        422
      );
    }

    try {
      $cacheKey = $this->buildCacheKey($request, 'stats:');
      $ttl = now()->addDays(3);

      $stats = Cache::remember($cacheKey, $ttl, function () use ($negara) {
        return $this->overviewService->getComputeStats($negara);
      });

      if (empty($stats)) {
        return response()->json([
          'success' => true,
          'message' => "Tidak ada data untuk {$negara}",
          'data'    => (object) [],
        ]);
      }

      return response()->json([
        'success' => true,
        'message' => "Stats negara {$negara}",
        'data'    => $stats
      ]);
    } catch (\Throwable $e) {
      return response()->json(
        [
          'success' => false,
          'message' => 'Terjadi kesalahan saat mengambil stats.',
          'data'    => (object) [],
        ],
        500
      );
    }
  }

  public function tradeCountry(Request $request): JsonResponse
  {

    try {
      $cacheKey = $this->buildCacheKey($request, 'tradeSummary:');
      $ttl = now()->addDays(3);

      $tradeSummary = Cache::remember($cacheKey, $ttl, function () {
        return $this->overviewService->getComputeTradeCountry();
      });

      return response()->json([
        'success' => true,
        'message' => "Perdagangan per negara",
        'data'    => $tradeSummary
      ]);
    } catch (\Throwable $e) {
      return response()->json(
        [
          'success' => false,
          'message' => 'Terjadi kesalahan saat mengambil stats.',
          'data'    => (object) [],
        ],
        500
      );
    }
  }

  public function topPerdagangan(Request $request): JsonResponse
  {
    $negara = strtoupper((string) $request->query('negara', ''));
    if (strlen($negara) !== 3) {
      return response()->json(
        [
          'success' => false,
          'message' => 'Parameter negara (alpha-3) wajib diisi, contoh: CHN',
          'data'    => (object) [],
        ],
        422
      );
    }

    $cacheKey = $this->buildCacheKey($request, 'top-perdagangan:');
    $ttl = now()->addDays(3);

    $data = Cache::remember($cacheKey, $ttl, function () use ($negara) {
      return $this->overviewService->getTopPerdagangan($negara);
    });

    if (empty($data)) {
      return response()->json([
        'success' => true,
        'message' => "Tidak ada data untuk {$negara}",
        'data'    => (object) [],
      ]);
    }

    return response()->json([
      'success' => true,
      'message' => "Top partner perdagangan {$negara}",
      'data'    => $data,
    ]);
  }


  public function topInvestasi(Request $request): JsonResponse
  {
    $negara = strtoupper((string) $request->query('negara', ''));
    if (strlen($negara) !== 3) {
      return response()->json(
        [
          'success' => false,
          'message' => 'Parameter negara (alpha-3) wajib diisi, contoh: CHN',
          'data'    => (object) [],
        ],
        422
      );
    }

    $cacheKey = $this->buildCacheKey($request, 'top-investasi:');
    $ttl = now()->addDays(3);

    $data = Cache::remember($cacheKey, $ttl, function () use ($negara) {
      return $this->overviewService->getTopInvestasi($negara);
    });

    if (empty($data)) {
      return response()->json([
        'success' => true,
        'message' => "Tidak ada data untuk {$negara}",
        'data'    => (object) [],
      ]);
    }

    return response()->json([
      'success' => true,
      'message' => "Top partner investasi {$negara}",
      'data'    => $data,
    ]);
  }

  public function topPariwisata(Request $request): JsonResponse
  {
    $negara = strtoupper((string) $request->query('negara', ''));
    if (strlen($negara) !== 3) {
      return response()->json(
        [
          'success' => false,
          'message' => 'Parameter negara (alpha-3) wajib diisi, contoh: CHN',
          'data'    => (object) [],
        ],
        422
      );
    }

    $cacheKey = $this->buildCacheKey($request, 'top-pariwisata:');
    $ttl = now()->addDays(3);

    $data = Cache::remember($cacheKey, $ttl, function () use ($negara) {
      return $this->overviewService->getTopPariwisata($negara);
    });

    if (empty($data)) {
      return response()->json([
        'success' => true,
        'message' => "Tidak ada data untuk {$negara}",
        'data'    => (object) [],
      ]);
    }

    return response()->json([
      'success' => true,
      'message' => "Top partner pariwisata {$negara}",
      'data'    => $data,
    ]);
  }

  public function topJasa(Request $request): JsonResponse
  {
    $negara = strtoupper((string) $request->query('negara', ''));
    if (strlen($negara) !== 3) {
      return response()->json(
        [
          'success' => false,
          'message' => 'Parameter negara (alpha-3) wajib diisi, contoh: CHN',
          'data'    => (object) [],
        ],
        422
      );
    }

    $cacheKey = $this->buildCacheKey($request, 'top-jasa:');
    $ttl = now()->addDays(3);

    $data = Cache::remember($cacheKey, $ttl, function () use ($negara) {
      return $this->overviewService->getTopJasa($negara);
    });

    if (empty($data)) {
      return response()->json([
        'success' => true,
        'message' => "Tidak ada data untuk {$negara}",
        'data'    => (object) [],
      ]);
    }

    return response()->json([
      'success' => true,
      'message' => "Top partner jasa {$negara}",
      'data'    => $data,
    ]);
  }
}
