<?php

namespace App\Http\Controllers\Api\AdminDashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminDashboard\FaqManagementRequest;
use App\Models\FaqItem;
use App\Models\FaqTopic;
use App\Services\AdminDashboard\FaqManagementService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

class FaqManagementController extends Controller
{
    public function __construct(
        private readonly FaqManagementService $service
    ) {
    }

    public function index(FaqManagementRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $sortBy = $validated['sort_by'] ?? 'order';
        $sortDirection = $validated['sort_direction'] ?? ($sortBy === 'topic' ? 'asc' : 'desc');
        $topics = $this->service->paginate($validated);

        return response()->json([
            'success' => true,
            'message' => 'Faqs fetched successfully',
            'data' => [
                'summary' => $this->service->getSummary(),
                'items' => collect($topics->items())
                    ->map(fn (FaqTopic $topic) => $this->transformTopic($topic))
                    ->values(),
                'meta' => [
                    'page' => $topics->currentPage(),
                    'per_page' => $topics->perPage(),
                    'total' => $topics->total(),
                    'last_page' => $topics->lastPage(),
                    'sort_by' => $sortBy,
                    'sort_direction' => $sortDirection,
                ],
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        try {
            $topic = $this->service->findByIdentifier($id);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Faq not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Faq fetched successfully',
            'data' => $this->transformTopic($topic),
        ]);
    }

    public function store(FaqManagementRequest $request): JsonResponse
    {
        $topic = $this->service->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Faq created successfully',
            'data' => $this->transformTopic($topic),
        ], 201);
    }

    public function update(FaqManagementRequest $request, string $id): JsonResponse
    {
        try {
            $topic = $this->service->update($id, $request->validated());
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Faq not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Faq updated successfully',
            'data' => $this->transformTopic($topic),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $topic = $this->service->delete($id);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Faq not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Faq deleted successfully',
            'data' => [
                'id' => $topic->uuid,
            ],
        ]);
    }

    private function transformTopic(FaqTopic $topic): array
    {
        return [
            'id' => $topic->uuid,
            'topic' => $topic->topic,
            'summary' => $topic->summary,
            'is_featured' => (bool) $topic->is_featured,
            'order' => (int) $topic->order,
            'items_count' => (int) ($topic->items_count ?? $topic->items->count()),
            'items' => $topic->items->map(fn (FaqItem $item) => [
                'id' => $item->uuid,
                'question' => $item->question,
                'answer' => $item->answer,
                'order' => (int) $item->order,
                'created_at' => $this->formatDate($item->created_at),
                'updated_at' => $this->formatDate($item->updated_at),
            ])->values(),
            'created_at' => $this->formatDate($topic->created_at),
            'updated_at' => $this->formatDate($topic->updated_at),
        ];
    }

    private function formatDate($date): ?string
    {
        return $date?->utc()->format('Y-m-d\\TH:i:s\\Z');
    }
}
