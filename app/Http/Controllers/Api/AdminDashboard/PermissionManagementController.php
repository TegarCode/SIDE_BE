<?php

namespace App\Http\Controllers\Api\AdminDashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminDashboard\PermissionManagementRequest;
use App\Services\AdminDashboard\PermissionManagementService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;

class PermissionManagementController extends Controller
{
    public function __construct(
        private readonly PermissionManagementService $service
    ) {
    }

    public function index(PermissionManagementRequest $request): JsonResponse
    {
        $permissions = $this->service->paginate($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Permissions fetched successfully',
            'data' => [
                'summary' => $this->service->getSummary(),
                'items' => collect($permissions->items())
                    ->map(fn (Permission $permission) => $this->transformPermission($permission))
                    ->values(),
                'meta' => [
                    'page' => $permissions->currentPage(),
                    'per_page' => $permissions->perPage(),
                    'total' => $permissions->total(),
                    'last_page' => $permissions->lastPage(),
                    'sort_by' => $request->validated('sort_by', 'category'),
                    'sort_direction' => $request->validated('sort_direction', $request->validated('sort_by') === 'created_at' || $request->validated('sort_by') === 'updated_at' ? 'desc' : 'asc'),
                ],
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        try {
            $permission = $this->service->findByIdentifier($id);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Permission not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Permission fetched successfully',
            'data' => $this->transformPermission($permission),
        ]);
    }

    public function store(PermissionManagementRequest $request): JsonResponse
    {
        $permission = $this->service->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Permission created successfully',
            'data' => $this->transformPermission($permission),
        ], 201);
    }

    public function update(PermissionManagementRequest $request, string $id): JsonResponse
    {
        try {
            $permission = $this->service->update($id, $request->validated());
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Permission not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Permission updated successfully',
            'data' => $this->transformPermission($permission),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $permission = $this->service->delete($id);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Permission not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Permission deleted successfully',
            'data' => [
                'id' => $permission->uuid,
            ],
        ]);
    }

    private function transformPermission(Permission $permission): array
    {
        return [
            'id' => $permission->uuid,
            'name' => $permission->name,
            'category' => $permission->category,
            'description' => $permission->description,
            'created_at' => $this->formatDate($permission->created_at),
            'updated_at' => $this->formatDate($permission->updated_at),
        ];
    }

    private function formatDate($date): ?string
    {
        return $date?->utc()->format('Y-m-d\\TH:i:s\\Z');
    }
}
