<?php

namespace App\Repositories\AdminDashboard\TutorialPlaylistManagement;

use App\Models\TutorialPlaylist;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TutorialPlaylistManagementRepository implements TutorialPlaylistManagementRepositoryInterface
{
    private const THUMBNAIL_DIRECTORY = 'tutorial';

    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 10);
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';

        return $this->baseQuery()
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $builder) use ($search) {
                    $builder
                        ->where('title', 'like', '%' . $search . '%')
                        ->orWhere('slug', 'like', '%' . $search . '%')
                        ->orWhere('desc', 'like', '%' . $search . '%')
                        ->orWhere('url', 'like', '%' . $search . '%');
                });
            })
            ->orderBy($sortBy, $sortDirection)
            ->paginate($perPage, ['*'], 'page', (int) ($filters['page'] ?? 1));
    }

    public function getSummary(): array
    {
        $latestPlaylist = TutorialPlaylist::query()
            ->latest('created_at')
            ->first();

        return [
            'total_playlist' => TutorialPlaylist::query()->count(),
            'playlist_terbaru' => $latestPlaylist ? $this->transformPlaylist($latestPlaylist) : null,
        ];
    }

    public function findByIdentifier(string $identifier): TutorialPlaylist
    {
        $playlist = $this->baseQuery()
            ->where('id', $identifier)
            ->first();

        if (!$playlist) {
            throw (new ModelNotFoundException())->setModel(TutorialPlaylist::class, [$identifier]);
        }

        return $playlist;
    }

    public function create(array $data): TutorialPlaylist
    {
        return DB::transaction(function () use ($data) {
            $thumbnailPath = $this->storeThumbnail($data['thumbnail']);

            $playlist = TutorialPlaylist::query()->create([
                'id' => (string) Str::uuid(),
                'title' => $data['title'],
                'slug' => $data['slug'],
                'desc' => $data['desc'],
                'url' => $data['url'],
                'thumbnail' => $thumbnailPath,
            ]);

            return $this->findByIdentifier($playlist->id);
        });
    }

    public function update(TutorialPlaylist $playlist, array $data): TutorialPlaylist
    {
        return DB::transaction(function () use ($playlist, $data) {
            $thumbnailPath = $playlist->thumbnail;

            if (!empty($data['thumbnail']) && $data['thumbnail'] instanceof UploadedFile) {
                $thumbnailPath = $this->storeThumbnail($data['thumbnail']);

                if ($playlist->thumbnail && Storage::disk('public')->exists($playlist->thumbnail)) {
                    Storage::disk('public')->delete($playlist->thumbnail);
                }
            }

            $playlist->fill([
                'title' => $data['title'],
                'slug' => $data['slug'],
                'desc' => $data['desc'],
                'url' => $data['url'],
                'thumbnail' => $thumbnailPath,
            ])->save();

            return $this->findByIdentifier($playlist->id);
        });
    }

    public function delete(TutorialPlaylist $playlist): void
    {
        DB::transaction(function () use ($playlist) {
            if ($playlist->thumbnail && Storage::disk('public')->exists($playlist->thumbnail)) {
                Storage::disk('public')->delete($playlist->thumbnail);
            }

            $playlist->delete();
        });
    }

    private function baseQuery(): Builder
    {
        return TutorialPlaylist::query();
    }

    private function storeThumbnail(UploadedFile $thumbnail): string
    {
        return $thumbnail->store(self::THUMBNAIL_DIRECTORY, 'public');
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
            'created_at' => $playlist->created_at?->utc()->format('Y-m-d\\TH:i:s\\Z'),
            'updated_at' => $playlist->updated_at?->utc()->format('Y-m-d\\TH:i:s\\Z'),
        ];
    }
}
