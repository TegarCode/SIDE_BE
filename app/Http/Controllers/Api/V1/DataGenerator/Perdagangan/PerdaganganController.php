<?php

namespace App\Http\Controllers\Api\V1\DataGenerator\Perdagangan;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\DataGenerator\PerdaganganRequest;
use App\Services\DataGenerator\PerdaganganService;
use App\Support\SideCacheKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Throwable;

class PerdaganganController extends Controller
{
    protected PerdaganganService $perdaganganService;

    public function __construct(PerdaganganService $perdaganganService)
    {
        $this->perdaganganService = $perdaganganService;
    }

    public function kodesumber()
    {
        $data = Cache::remember(
            SideCacheKey::path(['data-generator', 'perdagangan', 'distinct-kode-sumber']),
            now()->addDay(),
            fn () => $this->perdaganganService->getKodeSumber()
        );

        return response()->json([
            'success' => true,
            'message' => 'Data kode sumber perdagangan',
            'data' => $data,
        ]);
    }

    public function tahunPerdagangan()
    {
        $data = Cache::remember(
            SideCacheKey::path(['data-generator', 'perdagangan', 'distinct-tahun']),
            now()->addDay(),
            fn () => $this->perdaganganService->getTahun()
        );

        return response()->json([
            'success' => true,
            'message' => 'Data tahun perdagangan',
            'data' => $data,
        ]);
    }

    public function tahunPerdaganganDefault()
    {
        $data = Cache::remember(
            SideCacheKey::path(['data-generator', 'perdagangan', 'distinct-default-tahun']),
            now()->addDay(),
            fn () => $this->perdaganganService->getTahunDefault()
        );

        return response()->json([
            'success' => true,
            'message' => 'Data tahun perdagangan',
            'data' => $data,
        ]);
    }

    public function tablefilter(PerdaganganRequest $request): JsonResponse
    {
        try {
            $filters = $request->validated();
            $page = (int) ($filters['page'] ?? $request->input('page', 1));
            $perPage = (int) ($filters['perPage'] ?? $request->input('perPage', 20));

            // panggil versi paginated → result sudah ada: data, pagination, meta
            $results = $this->perdaganganService->getFilteredTradeData($filters, $page, $perPage);

            return ApiResponse::success(
                $results['data'] ?? [],
                'Data perdagangan berhasil diambil',
                $results['meta'] ?? null,
                200
            );
        } catch (Throwable $e) {
            return ApiResponse::error('Gagal mengambil data perdagangan', [
                'exception' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }

    public function visualizationfilter(PerdaganganRequest $request): JsonResponse
    {
        try {
            $filters = $request->validated();
            $results = $this->perdaganganService->getVisualizationTradeData($filters);

            return ApiResponse::success(
                $results['data'] ?? [],
                'Data visualisasi perdagangan berhasil diambil',
                $results['meta'] ?? null,
                200
            );
        } catch (Throwable $e) {
            return ApiResponse::error('Gagal mengambil data visualisasi perdagangan', [
                'exception' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }
}
