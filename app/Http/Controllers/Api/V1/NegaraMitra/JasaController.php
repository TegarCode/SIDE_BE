<?php

namespace App\Http\Controllers\Api\V1\NegaraMitra;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\NegaraMitra\OverviewJasaCountryRequest;
use App\Http\Requests\NegaraMitra\OverviewJasaRequest;
use App\Services\NegaraMitra\ServiceOverviewService;
use App\Support\SideCacheKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class JasaController extends Controller
{
    public function __construct(private ServiceOverviewService $service) {}

    /** Helper kecil untuk memisahkan meta dari payload service */
    private function splitMeta(array $arr): array
    {
        $meta = $arr['meta'] ?? [];
        unset($arr['meta']);

        return [$meta, $arr];
    }

    /**
     * Bangun cache key berdasarkan filter + include + versi
     */
    private function makeCacheKey(array $filters, array $include): string
    {
        return SideCacheKey::pairs(
            ['negara-mitra', 'jasa', 'overview'],
            [
                'version' => 1,
                'filters' => $filters,
                'include' => $include,
            ]
        );
    }

    private function makeCountryCacheKey(array $filters, array $include): string
    {
        return SideCacheKey::pairs(
            ['negara-mitra', 'jasa', 'country'],
            [
                'version' => 1,
                'filters' => $filters,
                'include' => $include,
            ]
        );
    }

    public function overview(OverviewJasaRequest $request)
    {
        try {
            $filters = $request->sanitizedFilters();
            $include = $request->sanitizedInclude();

            $cacheKey = $this->makeCacheKey($filters, $include);

            // TTL sampai akhir hari (jam 23:59:59 hari ini)
            $ttl = now()->endOfDay();

            $composite = Cache::remember($cacheKey, $ttl, function () use ($filters, $include) {
                return $this->service->getComposite($filters, $include);
            });

            // pisahkan meta -> kirim sebagai arg ke-3 ApiResponse::success
            [$meta, $payload] = $this->splitMeta(is_array($composite) ? $composite : []);

            return ApiResponse::success($payload, 'Composite services overview', $meta);
        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::error('Gagal memuat composite services overview');
        }
    }

    public function countryOverview(OverviewJasaCountryRequest $request)
    {
        try {
            $filters = $request->sanitizedFilters();
            $include = $request->sanitizedInclude();

            $cacheKey = $this->makeCountryCacheKey($filters, $include);
            $ttl = now()->endOfDay();

            $composite = Cache::remember($cacheKey, $ttl, function () use ($filters, $include) {
                return $this->service->getCountryComposite($filters, $include);
            });

            [$meta, $payload] = $this->splitMeta(is_array($composite) ? $composite : []);

            return ApiResponse::success($payload, 'Composite services country overview', $meta);
        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::error('Gagal memuat services country overview');
        }
    }
}
