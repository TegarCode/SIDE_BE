<?php

namespace App\Http\Controllers\Api\V1\Indonesia;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Support\SideCacheKey;
use App\Services\Indonesia\InfrastrukturService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class InfrastrukturController extends Controller
{
    public function __construct(protected InfrastrukturService $infrastrukturService) {}

    /**
     * ALLOWED:
     *   - wilayah   = kode ID_Wil_Kemlu (Ditjen/Wilayah)
     *   - categories = persis seperti di DB (dibaca dalam bentuk UPPER):
     *       PERWAKILAN DAGANG
     *       IIPC
     *       BUMN
     *       BI/BUMN PERBANKAN
     *       PTRI
     *       KBRI
     *       KJRI
     *       KRI
     */
    private const ALLOWED = [
        'categories' => [
            'PERWAKILAN DAGANG',
            'IIPC',
            'BUMN',
            'BI/BUMN PERBANKAN',
            'PTRI',
            'KBRI',
            'KJRI',
            'KRI',
        ],
        'wilayah' => [
            'AFRIK',
            'AMRI1',
            'AMRI2',
            'ASETE',
            'ASTEN',
            'ASTIM',
            'EROP1',
            'EROP2',
            'PASOS',
            'TITEN',
        ],
    ];

    /* ======================= Endpoints ======================= */

    public function categories(): JsonResponse
    {
        $items = array_map(function (string $category): array {
            return [
                'id' => $category,
                'nama' => $category,
                'group_key' => $this->mapCategoryGroupKey($category),
                'group_label' => $this->mapCategoryGroupLabel($category),
            ];
        }, self::ALLOWED['categories']);

        return ApiResponse::success(
            ['items' => $items],
            'Data kategori infrastruktur berhasil diambil.',
            ['count_items' => count($items)]
        );
    }

    public function perwakilan(Request $request): JsonResponse
    {
        try {
            $filters = $this->normalizeFilters($request);

            // Validasi input
            $invalidDirjen = array_diff($filters['wilayah'] ?? [], self::ALLOWED['wilayah']);
            $invalidCats   = array_diff($filters['categories_raw'] ?? [], self::ALLOWED['categories']);
            if (! empty($invalidDirjen) || ! empty($invalidCats)) {
                return ApiResponse::error(
                    'Filter tidak valid.',
                    [
                        'invalid_wilayah'    => array_values($invalidDirjen),
                        'invalid_categories' => array_values($invalidCats),
                        'allowed_wilayah'    => self::ALLOWED['wilayah'],
                        'allowed_categories' => self::ALLOWED['categories'],
                    ],
                    422
                );
            }

            // Kirim kategori apa adanya (dalam bentuk UPPER label DB)
            $serviceFilters = $filters;
            $serviceFilters['categories'] = $this->mapCategoriesForService($filters['categories_raw'] ?? []);

            $cacheKey    = $this->buildPerwakilanCacheKey($serviceFilters);
            $ttlUntilEod = now()->endOfDay();

            $payload = Cache::remember($cacheKey, $ttlUntilEod, function () use ($serviceFilters) {
                $toService = [
                    'wilayah'    => $serviceFilters['wilayah'] ?? [],
                    'categories' => $serviceFilters['categories'] ?? [],
                ];

                return $this->infrastrukturService->getPerwakilan($toService);
            });

            if (empty($payload)) {
                return ApiResponse::success([], 'Tidak ada data.', [
                    'filters_in'      => $filters,
                    'filters_service' => $serviceFilters,
                ]);
            }

            // Rapikan meta
            $meta = $payload['meta'] ?? [];
            unset($payload['meta'], $meta['applied_filters']);

            return ApiResponse::success($payload, 'Data perwakilan berhasil diambil.', $meta);
        } catch (\Throwable $e) {
            $errors = app()->environment('local')
                ? ['exception' => $e->getMessage()]
                : null;

            return ApiResponse::error('Terjadi kesalahan saat mengambil data perwakilan.', $errors, 500);
        }
    }

    public function perwakilanAsing(Request $request): JsonResponse
    {
        try {
            $filters = $this->normalizeFilters($request);

            $invalidDirjen = array_diff($filters['wilayah'] ?? [], self::ALLOWED['wilayah']);
            $invalidCats   = array_diff($filters['categories_raw'] ?? [], self::ALLOWED['categories']);
            if (! empty($invalidDirjen) || ! empty($invalidCats)) {
                return ApiResponse::error(
                    'Filter tidak valid.',
                    [
                        'invalid_wilayah'    => array_values($invalidDirjen),
                        'invalid_categories' => array_values($invalidCats),
                        'allowed_wilayah'    => self::ALLOWED['wilayah'],
                        'allowed_categories' => self::ALLOWED['categories'],
                    ],
                    422
                );
            }

            $serviceFilters = $filters;
            $serviceFilters['categories'] = $this->mapCategoriesForService($filters['categories_raw'] ?? []);

            $cacheKey    = $this->buildPerwakilanAsingCacheKey($serviceFilters);
            $ttlUntilEod = now()->endOfDay();

            $payload = Cache::remember($cacheKey, $ttlUntilEod, function () use ($serviceFilters) {
                $toService = [
                    'wilayah'    => $serviceFilters['wilayah'] ?? [],
                    'categories' => $serviceFilters['categories'] ?? [],
                ];

                return $this->infrastrukturService->getPerwakilanAsing($toService);
            });

            if (empty($payload)) {
                return ApiResponse::success([], 'Tidak ada data.', [
                    'filters_in'      => $filters,
                    'filters_service' => $serviceFilters,
                    'cache_key'       => $cacheKey,
                    'cached_until'    => $ttlUntilEod->toIso8601String(),
                ]);
            }

            $meta = $payload['meta'] ?? [];
            unset($payload['meta'], $meta['applied_filters']);

            return ApiResponse::success($payload, 'Data perwakilan asing berhasil diambil.', $meta);
        } catch (\Throwable $e) {
            $errors = app()->environment('local')
                ? ['exception' => $e->getMessage()]
                : null;

            return ApiResponse::error('Terjadi kesalahan saat mengambil data perwakilan.', $errors, 500);
        }
    }

    public function pameranIndonesia(Request $request): JsonResponse
    {
        try {
            $filters = $this->normalizeFilters($request);

            $invalidDirjen = array_diff($filters['wilayah'] ?? [], self::ALLOWED['wilayah']);
            $invalidCats   = array_diff($filters['categories_raw'] ?? [], self::ALLOWED['categories']);
            if (! empty($invalidDirjen) || ! empty($invalidCats)) {
                return ApiResponse::error(
                    'Filter tidak valid.',
                    [
                        'invalid_wilayah'    => array_values($invalidDirjen),
                        'invalid_categories' => array_values($invalidCats),
                        'allowed_wilayah'    => self::ALLOWED['wilayah'],
                        'allowed_categories' => self::ALLOWED['categories'],
                    ],
                    422
                );
            }

            $serviceFilters = $filters;
            $serviceFilters['categories'] = $this->mapCategoriesForService($filters['categories_raw'] ?? []);

            $toService = [
                'wilayah'    => $serviceFilters['wilayah'] ?? [],
                'categories' => $serviceFilters['categories'] ?? [],
            ];
            $payload = $this->infrastrukturService->getPameranIndonesia($toService);

            if (empty($payload)) {
                return ApiResponse::success([], 'Tidak ada data.', [
                    'filters_in'      => $filters,
                    'filters_service' => $serviceFilters,
                ]);
            }

            $meta = $payload['meta'] ?? [];
            unset($payload['meta'], $meta['applied_filters']);

            return ApiResponse::success($payload, 'Data pameran di Indonesia berhasil diambil.', $meta);
        } catch (\Throwable $e) {
            $errors = app()->environment('local')
                ? ['exception' => $e->getMessage()]
                : null;

            return ApiResponse::error('Terjadi kesalahan saat mengambil data pameran.', $errors, 500);
        }
    }

    public function pameranPerwakilan(Request $request): JsonResponse
    {
        try {
            $filters = $this->normalizeFilters($request);

            $invalidDirjen = array_diff($filters['wilayah'] ?? [], self::ALLOWED['wilayah']);
            $invalidCats   = array_diff($filters['categories_raw'] ?? [], self::ALLOWED['categories']);
            if (! empty($invalidDirjen) || ! empty($invalidCats)) {
                return ApiResponse::error(
                    'Filter tidak valid.',
                    [
                        'invalid_wilayah'    => array_values($invalidDirjen),
                        'invalid_categories' => array_values($invalidCats),
                        'allowed_wilayah'    => self::ALLOWED['wilayah'],
                        'allowed_categories' => self::ALLOWED['categories'],
                    ],
                    422
                );
            }

            $serviceFilters = $filters;
            $serviceFilters['categories'] = $this->mapCategoriesForService($filters['categories_raw'] ?? []);

            $toService = [
                'wilayah'    => $serviceFilters['wilayah'] ?? [],
                'categories' => $serviceFilters['categories'] ?? [],
            ];
            $payload = $this->infrastrukturService->getPameranPerwakilan($toService);

            if (empty($payload)) {
                return ApiResponse::success([], 'Tidak ada data.', [
                    'filters_in'      => $filters,
                    'filters_service' => $serviceFilters,
                ]);
            }

            $meta = $payload['meta'] ?? [];
            unset($payload['meta'], $meta['applied_filters']);

            return ApiResponse::success($payload, 'Data pameran di perwakilan berhasil diambil.', $meta);
        } catch (\Throwable $e) {
            $errors = app()->environment('local')
                ? ['exception' => $e->getMessage()]
                : null;

            return ApiResponse::error('Terjadi kesalahan saat mengambil data pameran.', $errors, 500);
        }
    }

    public function perjanjianAntarNegara(Request $request): JsonResponse
    {
        try {
            $filters = $this->normalizeFilters($request);

            $invalidDirjen = array_diff($filters['wilayah'] ?? [], self::ALLOWED['wilayah']);
            $invalidCats   = array_diff($filters['categories_raw'] ?? [], self::ALLOWED['categories']);
            if (! empty($invalidDirjen) || ! empty($invalidCats)) {
                return ApiResponse::error(
                    'Filter tidak valid.',
                    [
                        'invalid_wilayah'    => array_values($invalidDirjen),
                        'invalid_categories' => array_values($invalidCats),
                        'allowed_wilayah'    => self::ALLOWED['wilayah'],
                        'allowed_categories' => self::ALLOWED['categories'],
                    ],
                    422
                );
            }

            $serviceFilters = $filters;
            $serviceFilters['categories'] = $this->mapCategoriesForService($filters['categories_raw'] ?? []);

            $toService = [
                'wilayah'    => $serviceFilters['wilayah'] ?? [],
                'categories' => $serviceFilters['categories'] ?? [],
            ];
            $payload = $this->infrastrukturService->getPerjanjian($toService);

            if (empty($payload)) {
                return ApiResponse::success([], 'Tidak ada data.', [
                    'filters_in'      => $filters,
                    'filters_service' => $serviceFilters,
                ]);
            }

            $meta = $payload['meta'] ?? [];
            unset($payload['meta'], $meta['applied_filters']);

            return ApiResponse::success($payload, 'Data perjanjian antar negara berhasil diambil.', $meta);
        } catch (\Throwable $e) {
            $errors = app()->environment('local')
                ? ['exception' => $e->getMessage()]
                : null;

            return ApiResponse::error('Terjadi kesalahan saat mengambil data perjanjian.', $errors, 500);
        }
    }

    /* ======================== Helpers ======================== */

    private function normalizeFilters(Request $request): array
    {
        // --- Wilayah ---
        $djIn = $request->input('wilayah', []);
        if (is_string($djIn)) {
            $wilayah = array_map('trim', explode(',', $djIn));
        } elseif (is_array($djIn)) {
            $wilayah = $djIn;
        } else {
            $wilayah = [];
        }

        $wilayah = array_values(array_unique(array_map(
            fn ($v) => strtoupper((string) $v),
            $wilayah
        )));

        if (in_array('ALL', $wilayah, true)) {
            $wilayah = [];
        }
        sort($wilayah, SORT_STRING);

        // --- Categories (label dari FILTER_CATS / DB) ---
        $ctIn = $request->input('categories', []);
        if (is_string($ctIn)) {
            $categoriesRaw = array_map('trim', explode(',', $ctIn));
        } elseif (is_array($ctIn)) {
            $categoriesRaw = $ctIn;
        } else {
            $categoriesRaw = [];
        }

        $categoriesRaw = array_values(array_unique(array_map(
            fn ($v) => strtoupper((string) $v),
            $categoriesRaw
        )));

        if (in_array('ALL', $categoriesRaw, true)) {
            $categoriesRaw = [];
        }
        sort($categoriesRaw, SORT_STRING);

        return [
            'wilayah'        => $wilayah,
            'categories_raw' => $categoriesRaw, // label kategori DB (dalam bentuk upper)
        ];
    }

    /**
     * DI SINI TIDAK ADA MERGE KE ITPC/PERBANKAN LAGI.
     * Hanya membersihkan + memastikan unique & sort.
     */
    private function mapCategoriesForService(array $categoriesRaw): array
    {
        if (empty($categoriesRaw)) {
            return [];
        }

        $mapped = [];

        foreach ($categoriesRaw as $c) {
            $code = strtoupper(trim($c));
            // hanya izinkan yang ada di ALLOWED['categories']
            if (in_array($code, self::ALLOWED['categories'], true)) {
                $mapped[] = $code;
            }
        }

        $mapped = array_values(array_unique($mapped));
        sort($mapped, SORT_STRING);

        return $mapped;
    }

    private function buildPerwakilanCacheKey(array $filters): string
    {
        $wilayah = $filters['wilayah'] ?? [];
        $cats    = $filters['categories'] ?? [];

        if (! is_array($wilayah)) {
            $wilayah = [];
        }
        sort($wilayah, SORT_STRING);
        if (! is_array($cats)) {
            $cats = [];
        }
        sort($cats, SORT_STRING);

        return SideCacheKey::pairs(
            ['indonesia', 'infrastruktur', 'perwakilan'],
            [
                'wilayah' => $wilayah ?: 'all',
                'kategori' => $cats ?: 'all',
            ]
        );
    }

    private function buildPerwakilanAsingCacheKey(array $filters): string
    {
        $wilayah = $filters['wilayah'] ?? [];

        if (! is_array($wilayah)) {
            $wilayah = [];
        }
        sort($wilayah, SORT_STRING);
        return SideCacheKey::pairs(
            ['indonesia', 'infrastruktur', 'perwakilan-asing'],
            [
                'wilayah' => $wilayah ?: 'all',
            ]
        );
    }

    private function mapCategoryGroupKey(string $category): string
    {
        return match (strtoupper(trim($category))) {
            'PTRI', 'KBRI' => 'KBRI',
            'KJRI', 'KRI' => 'KJRI',
            'PERWAKILAN DAGANG' => 'ITPC',
            'BI/BUMN PERBANKAN' => 'PERBANKAN',
            'IIPC' => 'IIPC',
            'BUMN' => 'BUMN',
            default => strtoupper(trim($category)),
        };
    }

    private function mapCategoryGroupLabel(string $category): string
    {
        return match ($this->mapCategoryGroupKey($category)) {
            'KBRI' => 'Perwakilan Diplomatik (KBRI/PTRI)',
            'KJRI' => 'Perwakilan Konsuler (KJRI/KRI)',
            'ITPC' => 'Perwakilan Dagang (ITPC/KDEI)',
            'PERBANKAN' => 'Perbankan & Keuangan',
            'IIPC' => 'IIPC',
            'BUMN' => 'BUMN',
            default => strtoupper(trim($category)),
        };
    }
}
