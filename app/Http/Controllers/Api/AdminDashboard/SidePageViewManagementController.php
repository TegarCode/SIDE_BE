<?php

namespace App\Http\Controllers\Api\AdminDashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminDashboard\SidePageViewManagementRequest;
use App\Models\SidePageView;
use App\Services\AdminDashboard\SidePageViewManagementService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

class SidePageViewManagementController extends Controller
{
    public function __construct(
        private readonly SidePageViewManagementService $service
    ) {
    }

    public function index(SidePageViewManagementRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortDirection = $validated['sort_direction'] ?? 'desc';
        $views = $this->service->paginate($validated);

        return response()->json([
            'success' => true,
            'message' => 'Side page views fetched successfully',
            'data' => [
                'summary' => $this->service->getSummary(),
                'items' => collect($views->items())
                    ->map(fn (SidePageView $view) => $this->transformView($view))
                    ->values(),
                'meta' => [
                    'page' => $views->currentPage(),
                    'per_page' => $views->perPage(),
                    'total' => $views->total(),
                    'last_page' => $views->lastPage(),
                    'sort_by' => $sortBy,
                    'sort_direction' => $sortDirection,
                ],
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        try {
            $view = $this->service->findByIdentifier($id);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Side page view not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Side page view fetched successfully',
            'data' => $this->transformView($view, true),
        ]);
    }

    public function modules(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Modules fetched successfully',
            'data' => [
                'items' => $this->service->getAvailableModules(),
            ],
        ]);
    }

    private function transformView(SidePageView $view, bool $includeIpHash = false): array
    {
        $data = [
            'id' => $view->id,
            'path' => $view->path,
            'module' => $view->module,
            'user' => $view->user ? [
                'id' => $view->user->uuid,
                'name' => $view->user->name,
                'email' => $view->user->email,
            ] : null,
            'user_agent' => $view->user_agent,
            'created_at' => $this->formatDate($view->created_at),
            'updated_at' => $this->formatDate($view->updated_at),
        ];

        if ($includeIpHash) {
            $data['ip_hash'] = $view->ip_hash;
        }

        return $data;
    }

    private function formatDate($date): ?string
    {
        return $date?->utc()->format('Y-m-d\\TH:i:s\\Z');
    }
}
