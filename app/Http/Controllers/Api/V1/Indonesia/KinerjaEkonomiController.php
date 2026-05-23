<?php

namespace App\Http\Controllers\Api\V1\Indonesia;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Support\SideCacheKey;
use App\Services\Indonesia\KinerjaEkonomiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class KinerjaEkonomiController extends Controller
{
    protected KinerjaEkonomiService $kinerjaEkonomiService;

    public function __construct(KinerjaEkonomiService $kinerjaEkonomiService)
    {
        $this->kinerjaEkonomiService = $kinerjaEkonomiService;
    }

    public function tahunKinerjaEkonomi()
    {
        $data = Cache::remember(
            SideCacheKey::path(['indonesia', 'kinerja-ekonomi', 'distinct-tahun']),
            now()->addDay(),
            fn () => $this->kinerjaEkonomiService->getTahun()
        );

        return response()->json([
            'success' => true,
            'message' => 'Data tahun kinerja ekonomi berhasil diambil',
            'data'    => $data,
        ]);
    }

    public function indikatorKinerjaEkonomi()
    {
        $data = Cache::remember(
            SideCacheKey::path(['indonesia', 'kinerja-ekonomi', 'indikator']),
            now()->addDay(),
            fn () => $this->kinerjaEkonomiService->getIndikator()
        );

        return response()->json([
            'success' => true,
            'message' => 'Indikator kinerja ekonomi berhasil diambil',
            'data'    => $data,
        ]);
    }

    public function indikatorKinerjaEkonomiAll()
    {
        $data = Cache::remember(
            SideCacheKey::path(['indonesia', 'kinerja-ekonomi', 'indikator-all']),
            now()->addDay(),
            fn () => $this->kinerjaEkonomiService->getIndikatorAll()
        );

        return response()->json([
            'success' => true,
            'message' => 'Semua indikator kinerja ekonomi berhasil diambil',
            'data'    => $data,
        ]);
    }

    public function kinerjaEkonomi(Request $request): JsonResponse
    {
        try {
            $v = $request->validate([
                'indicator_id' => ['required', 'integer'],
                'year'         => ['required', 'integer', 'between:2000,2100'],
            ]);

            $filters = [
                'indicator_id' => (int) $v['indicator_id'],
                'year'         => (int) $v['year'],
            ];

            $isDefault = $filters['indicator_id'] === 1 && $filters['year'] === 2024;

            $cacheKey = SideCacheKey::pairs(
                ['indonesia', 'kinerja-ekonomi', 'avg-per-year-per-country'],
                [
                    'indicator' => $filters['indicator_id'],
                    'year' => $filters['year'],
                ]
            );
            $ttl      = now()->addDay();

            $fetch = function () use ($filters) {
                $kinerja = $this->kinerjaEkonomiService->getKinerja($filters) ?? [];

                // meta dari repository (years, count, sumber, params, dll)
                $kMeta = Arr::except($kinerja['meta'] ?? [], ['applied_filters']);

                // meta FLAT (tanpa key "kinerja")
                $meta = array_merge(
                    ['filters' => $filters, 'partial' => false],
                    $kMeta ?? []
                );

                return [
                    'data' => [
                        'kinerja' => $kinerja['data'] ?? [],
                    ],
                    'meta' => $meta,
                ];
            };

            $payload = $isDefault
                ? Cache::remember($cacheKey, $ttl, $fetch)
                : $fetch();

            if (empty($payload['data']['kinerja'])) {
                return ApiResponse::success(
                    ['kinerja' => []],
                    'Tidak ada data.',
                    $payload['meta'] ?? []
                );
            }

            return ApiResponse::success(
                $payload['data'],
                'Data kinerja (AVG per tahun, per negara) berhasil diambil.',
                $payload['meta'] ?? []
            );
        } catch (\Throwable $e) {
            $errors = app()->environment('local') ? ['exception' => $e->getMessage()] : null;
            return ApiResponse::error('Terjadi kesalahan saat mengambil data kinerja.', $errors, 500);
        }
    }
}
