<?php

namespace App\Http\Controllers\Api\AdminDashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminDashboard\TutorialPlaylistManagementRequest;
use App\Models\TutorialPlaylist;
use App\Services\AdminDashboard\TutorialPlaylistManagementService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class TutorialPlaylistManagementController extends Controller
{
    public function __construct(
        private readonly TutorialPlaylistManagementService $service
    ) {
    }

    public function index(TutorialPlaylistManagementRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortDirection = $validated['sort_direction'] ?? 'desc';
        $playlists = $this->service->paginate($validated);

        return response()->json([
            'success' => true,
            'message' => 'Tutorial playlists fetched successfully',
            'data' => [
                'summary' => $this->service->getSummary(),
                'items' => collect($playlists->items())
                    ->map(fn (TutorialPlaylist $playlist) => $this->transformPlaylist($playlist))
                    ->values(),
                'meta' => [
                    'page' => $playlists->currentPage(),
                    'per_page' => $playlists->perPage(),
                    'total' => $playlists->total(),
                    'last_page' => $playlists->lastPage(),
                    'sort_by' => $sortBy,
                    'sort_direction' => $sortDirection,
                ],
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        try {
            $playlist = $this->service->findByIdentifier($id);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Tutorial playlist not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tutorial playlist fetched successfully',
            'data' => $this->transformPlaylist($playlist),
        ]);
    }

    public function store(TutorialPlaylistManagementRequest $request): JsonResponse
    {
        $playlist = $this->service->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Tutorial playlist created successfully',
            'data' => $this->transformPlaylist($playlist),
        ], 201);
    }

    public function update(TutorialPlaylistManagementRequest $request, string $id): JsonResponse
    {
        try {
            $playlist = $this->service->update($id, $request->validated());
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Tutorial playlist not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tutorial playlist updated successfully',
            'data' => $this->transformPlaylist($playlist),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $playlist = $this->service->delete($id);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Tutorial playlist not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tutorial playlist deleted successfully',
            'data' => [
                'id' => $playlist->id,
            ],
        ]);
    }

    private function transformPlaylist(TutorialPlaylist $playlist): array
    {
        return [
            'id' => $playlist->id,
            'title' => $playlist->title,
            'slug' => $playlist->slug,
            'description' => $playlist->desc,
            'url' => $playlist->url,
            'thumbnail' => $playlist->thumbnail,
            'thumbnail_url' => $playlist->thumbnail ? url(Storage::url($playlist->thumbnail)) : null,
            'created_at' => $this->formatDate($playlist->created_at),
            'updated_at' => $this->formatDate($playlist->updated_at),
        ];
    }

    private function formatDate($date): ?string
    {
        return $date?->utc()->format('Y-m-d\\TH:i:s\\Z');
    }
}
