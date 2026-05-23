<?php

namespace App\Http\Controllers\Api\AdminDashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminDashboard\RoleManagementRequest;
use App\Services\AdminDashboard\RoleManagementService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;

class RoleManagementController extends Controller
{
    public function __construct(
        private readonly RoleManagementService $service
    ) {
    }

    public function index(RoleManagementRequest $request): JsonResponse
    {
        $roles = $this->service->paginate($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Roles fetched successfully',
            'data' => [
                'items' => collect($roles->items())
                    ->map(fn (Role $role) => $this->transformRole($role))
                    ->values(),
                'meta' => [
                    'page' => $roles->currentPage(),
                    'per_page' => $roles->perPage(),
                    'total' => $roles->total(),
                    'last_page' => $roles->lastPage(),
                ],
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        try {
            $role = $this->service->findByIdentifier($id);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Role fetched successfully',
            'data' => $this->transformRole($role),
        ]);
    }

    public function store(RoleManagementRequest $request): JsonResponse
    {
        $role = $this->service->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully',
            'data' => $this->transformRole($role),
        ], 201);
    }

    public function update(RoleManagementRequest $request, string $id): JsonResponse
    {
        try {
            $role = $this->service->update($id, $request->validated());
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully',
            'data' => $this->transformRole($role),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            if (!$this->service->canDelete($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role cannot be deleted because it is assigned to users.',
                    'data' => null,
                ], 422);
            }

            $role = $this->service->delete($id);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully',
            'data' => [
                'id' => $role->uuid,
            ],
        ]);
    }

    public function permissions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Permissions fetched successfully',
            'data' => [
                'items' => $this->service->getAvailablePermissions()->values(),
            ],
        ]);
    }

    private function transformRole(Role $role): array
    {
        $permissions = $role->permissions
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        return [
            'id' => $role->uuid,
            'name' => $role->name,
            'slug' => $role->slug,
            'description' => $role->description,
            'status' => $role->status,
            'user_count' => (int) ($role->users_count ?? 0),
            'permissions_count' => (int) ($role->permissions_count ?? count($permissions)),
            'permissions' => $permissions,
            'created_at' => $this->formatDate($role->created_at),
            'updated_at' => $this->formatDate($role->updated_at),
        ];
    }

    private function formatDate($date): ?string
    {
        return $date?->utc()->format('Y-m-d\\TH:i:s\\Z');
    }
}
