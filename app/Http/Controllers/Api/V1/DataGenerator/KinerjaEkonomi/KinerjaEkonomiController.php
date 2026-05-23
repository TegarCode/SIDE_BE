<?php

namespace App\Http\Controllers\Api\V1\DataGenerator\KinerjaEkonomi;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\DataGenerator\KinerjaEkonomiRequest;
use App\Services\DataGenerator\KinerjaEkonomiService;
use Illuminate\Http\JsonResponse;
use Throwable;

class KinerjaEkonomiController extends Controller
{
    protected KinerjaEkonomiService $service;

    public function __construct(KinerjaEkonomiService $service)
    {
        $this->service = $service;
    }

    public function tablefilter(KinerjaEkonomiRequest $request): JsonResponse
    {
        try {
            $filters = $request->validated();
            $results = $this->service->getTableFilterData($filters);

            if (!($results['success'] ?? false)) {
                return ApiResponse::error(
                    $results['message'] ?? 'Gagal mengambil data kinerja ekonomi',
                    $results['errors'] ?? null,
                    400
                );
            }

            return ApiResponse::success(
                $results['data'] ?? [],
                $results['message'] ?? 'Data kinerja ekonomi berhasil diambil',
                $results['meta'] ?? null,
                200
            );
        } catch (Throwable $e) {
            return ApiResponse::error('Gagal mengambil data kinerja ekonomi', [
                'exception' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }

    public function visualizationfilter(KinerjaEkonomiRequest $request): JsonResponse
    {
        try {
            $filters = $request->validated();
            $results = $this->service->getVisualizationFilterData($filters);

            if (!($results['success'] ?? false)) {
                return ApiResponse::error(
                    $results['message'] ?? 'Gagal mengambil data visualisasi kinerja ekonomi',
                    $results['errors'] ?? null,
                    400
                );
            }

            return ApiResponse::success(
                $results['data'] ?? [],
                $results['message'] ?? 'Data visualisasi kinerja ekonomi berhasil diambil',
                $results['meta'] ?? null,
                200
            );
        } catch (Throwable $e) {
            return ApiResponse::error('Gagal mengambil data visualisasi kinerja ekonomi', [
                'exception' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }
}
