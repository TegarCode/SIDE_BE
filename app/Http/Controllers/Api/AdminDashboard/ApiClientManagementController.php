<?php

namespace App\Http\Controllers\Api\AdminDashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminDashboard\ApiClientManagementRequest;
use App\Models\ApiClient;
use App\Services\AdminDashboard\ApiClientManagementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiClientManagementController extends Controller
{
    public function __construct(
        private readonly ApiClientManagementService $service
    ) {
    }

    public function index(ApiClientManagementRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortDirection = $validated['sort_direction'] ?? ($sortBy === 'name' ? 'asc' : 'desc');
        $apiClients = $this->service->paginate($validated);

        return response()->json([
            'success' => true,
            'message' => 'API clients fetched successfully',
            'data' => [
                'summary' => $this->service->getSummary(),
                'items' => collect($apiClients->items())
                    ->map(fn (ApiClient $apiClient) => $this->transformApiClient($apiClient))
                    ->values(),
                'meta' => [
                    'page' => $apiClients->currentPage(),
                    'per_page' => $apiClients->perPage(),
                    'total' => $apiClients->total(),
                    'last_page' => $apiClients->lastPage(),
                    'sort_by' => $sortBy,
                    'sort_direction' => $sortDirection,
                ],
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        try {
            $apiClient = $this->service->findByIdentifier($id);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'API client not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'API client fetched successfully',
            'data' => $this->transformApiClient($apiClient),
        ]);
    }

    public function permissions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Permissions fetched successfully',
            'data' => [
                'items' => $this->service->getAvailablePermissions(),
            ],
        ]);
    }

    public function store(ApiClientManagementRequest $request): JsonResponse
    {
        $result = $this->service->create($request->validated());
        /** @var ApiClient $apiClient */
        $apiClient = $result['api_client'];

        return response()->json([
            'success' => true,
            'message' => 'API client created successfully',
            'data' => $this->transformApiClient($apiClient),
            'metadata' => [
                'plain_text_api_key' => $result['plain_text_api_key'],
                'api_key_notice' => 'API key hanya ditampilkan sekali. Simpan dengan aman.',
            ],
        ], 201);
    }

    public function update(ApiClientManagementRequest $request, string $id): JsonResponse
    {
        try {
            $apiClient = $this->service->update($id, $request->validated());
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'API client not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'API client updated successfully',
            'data' => $this->transformApiClient($apiClient),
        ]);
    }

    public function regenerateKey(ApiClientManagementRequest $request, string $id): JsonResponse
    {
        $user = $request->user('sanctum') ?? $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User authentication is required to regenerate API key',
                'data' => null,
            ], 403);
        }

        try {
            $result = $this->service->regenerateKey($id, $user, $request->validated('current_password'));
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'API client not found',
                'data' => null,
            ], 404);
        } catch (AuthorizationException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => null,
            ], 422);
        }

        /** @var ApiClient $apiClient */
        $apiClient = $result['api_client'];

        return response()->json([
            'success' => true,
            'message' => 'API key regenerated successfully',
            'data' => $this->transformApiClient($apiClient),
            'metadata' => [
                'plain_text_api_key' => $result['plain_text_api_key'],
                'api_key_notice' => 'API key baru hanya ditampilkan sekali. Simpan dengan aman.',
            ],
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $apiClient = $this->service->delete($id);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'API client not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'API client deleted successfully',
            'data' => [
                'id' => $apiClient->uuid,
            ],
        ]);
    }

    private function transformApiClient(ApiClient $apiClient): array
    {
        return [
            'id' => $apiClient->uuid,
            'name' => $apiClient->name,
            'description' => $apiClient->description,
            'abilities' => $apiClient->abilities ?? [],
            'allowed_domains' => $apiClient->allowed_domains ?? [],
            'active' => (bool) $apiClient->active,
            'created_at' => $this->formatDate($apiClient->created_at),
            'updated_at' => $this->formatDate($apiClient->updated_at),
        ];
    }

    private function formatDate($date): ?string
    {
        return $date?->utc()->format('Y-m-d\\TH:i:s\\Z');
    }
}
