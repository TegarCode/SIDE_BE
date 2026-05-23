<?php

namespace App\Http\Controllers\Api\AdminDashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminDashboard\CacheManagementRequest;
use App\Services\AdminDashboard\CacheManagementService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

class CacheManagementController extends Controller
{
    public function __construct(
        private readonly CacheManagementService $service
    ) {
    }

    public function index(CacheManagementRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $sortBy = $validated['sort_by'] ?? 'expiration';
        $sortDirection = $validated['sort_direction'] ?? 'desc';
        $caches = $this->service->paginate($validated);

        return response()->json([
            'success' => true,
            'message' => 'Caches fetched successfully',
            'data' => [
                'summary' => $this->service->getSummary(),
                'items' => collect($caches->items())->values(),
                'meta' => [
                    'page' => $caches->currentPage(),
                    'per_page' => $caches->perPage(),
                    'total' => $caches->total(),
                    'last_page' => $caches->lastPage(),
                    'sort_by' => $sortBy,
                    'sort_direction' => $sortDirection,
                ],
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        try {
            $cache = $this->service->findByIdentifier(urldecode($id));
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Cache not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cache fetched successfully',
            'data' => $cache,
        ]);
    }

    public function update(CacheManagementRequest $request, string $id): JsonResponse
    {
        try {
            $cache = $this->service->update(urldecode($id), $request->validated());
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Cache not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cache expiration updated successfully',
            'data' => $cache,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $cache = $this->service->delete(urldecode($id));
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Cache not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cache deleted successfully',
            'data' => [
                'id' => $cache->id,
                'key' => $cache->key,
            ],
        ]);
    }
}
