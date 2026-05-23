<?php

namespace App\Http\Controllers\Api\AdminDashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminDashboard\AuthenticationLogManagementRequest;
use App\Models\User;
use App\Services\AdminDashboard\AuthenticationLogManagementService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Rappasoft\LaravelAuthenticationLog\Models\AuthenticationLog;

class AuthenticationLogManagementController extends Controller
{
    public function __construct(
        private readonly AuthenticationLogManagementService $service
    ) {
    }

    public function index(AuthenticationLogManagementRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $sortBy = $validated['sort_by'] ?? 'login_at';
        $sortDirection = $validated['sort_direction'] ?? 'desc';
        $logs = $this->service->paginate($validated);

        return response()->json([
            'success' => true,
            'message' => 'Authentication logs fetched successfully',
            'data' => [
                'summary' => $this->service->getSummary(),
                'items' => collect($logs->items())
                    ->map(fn (AuthenticationLog $log) => $this->transformLog($log))
                    ->values(),
                'meta' => [
                    'page' => $logs->currentPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'last_page' => $logs->lastPage(),
                    'sort_by' => $sortBy,
                    'sort_direction' => $sortDirection,
                ],
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        try {
            $log = $this->service->findByIdentifier($id);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication log not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Authentication log fetched successfully',
            'data' => $this->transformLog($log),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $log = $this->service->delete($id);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication log not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Authentication log deleted successfully',
            'data' => [
                'id' => $log->uuid,
            ],
        ]);
    }

    private function transformLog(AuthenticationLog $log): array
    {
        $user = $log->authenticatable instanceof User ? $log->authenticatable : null;

        return [
            'id' => $log->uuid,
            'user' => $user ? [
                'id' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
            ] : null,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'login_at' => $this->formatDate($log->login_at),
            'login_successful' => (bool) $log->login_successful,
            'logout_at' => $this->formatDate($log->logout_at),
            'location' => $log->location,
        ];
    }

    private function formatDate($date): ?string
    {
        return $date?->utc()->format('Y-m-d\\TH:i:s\\Z');
    }
}
