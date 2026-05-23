<?php

namespace App\Http\Controllers\Api\AdminDashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminDashboard\UserManagementRequest;
use App\Models\User;
use App\Services\AdminDashboard\UserManagementService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

class UserManagementController extends Controller
{
    public function __construct(
        private readonly UserManagementService $service
    ) {
    }

    public function index(UserManagementRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $sortBy = $validated['sort_by'] ?? 'updated_at';
        $sortDirection = $validated['sort_direction'] ?? 'desc';
        $users = $this->service->paginate($validated);

        return response()->json([
            'success' => true,
            'message' => 'Users fetched successfully',
            'data' => [
                'summary' => $this->service->getSummary(),
                'items' => collect($users->items())
                    ->map(fn (User $user) => $this->transformUser($user))
                    ->values(),
                'meta' => [
                    'page' => $users->currentPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'last_page' => $users->lastPage(),
                    'sort_by' => $sortBy,
                    'sort_direction' => $sortDirection,
                ],
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        try {
            $user = $this->service->findByIdentifier($id);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'User fetched successfully',
            'data' => $this->transformUser($user),
        ]);
    }

    public function store(UserManagementRequest $request): JsonResponse
    {
        $user = $this->service->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => $this->transformUser($user),
        ], 201);
    }

    public function update(UserManagementRequest $request, string $id): JsonResponse
    {
        try {
            $user = $this->service->update($id, $request->validated());
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $this->transformUser($user),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $user = $this->service->delete($id);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully',
            'data' => [
                'id' => $user->uuid,
            ],
        ]);
    }

    public function roles(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Roles fetched successfully',
            'data' => [
                'items' => $this->service->getAvailableRoles()->values(),
            ],
        ]);
    }

    private function transformUser(User $user): array
    {
        return [
            'id' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
            'roles' => $user->roles->pluck('name')->sort()->values()->all(),
            'created_at' => $this->formatDate($user->created_at),
            'updated_at' => $this->formatDate($user->updated_at),
        ];
    }

    private function formatDate($date): ?string
    {
        return $date?->utc()->format('Y-m-d\\TH:i:s\\Z');
    }
}
