<?php

namespace App\Repositories\AdminDashboard\FaqManagement;

use App\Models\FaqItem;
use App\Models\FaqTopic;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FaqManagementRepository implements FaqManagementRepositoryInterface
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 10);
        $sortBy = $filters['sort_by'] ?? 'order';
        $sortDirection = $filters['sort_direction'] ?? ($sortBy === 'topic' ? 'asc' : 'desc');

        return $this->baseQuery()
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $builder) use ($search) {
                    $builder
                        ->where('topic', 'like', '%' . $search . '%')
                        ->orWhere('summary', 'like', '%' . $search . '%')
                        ->orWhereHas('items', function (Builder $itemQuery) use ($search) {
                            $itemQuery
                                ->where('question', 'like', '%' . $search . '%')
                                ->orWhere('answer', 'like', '%' . $search . '%');
                        });
                });
            })
            ->when(array_key_exists('is_featured', $filters), fn (Builder $query) => $query->where('is_featured', (bool) $filters['is_featured']))
            ->orderBy($sortBy, $sortDirection)
            ->when($sortBy !== 'topic', fn (Builder $query) => $query->orderBy('topic'))
            ->paginate($perPage, ['*'], 'page', (int) ($filters['page'] ?? 1));
    }

    public function getSummary(): array
    {
        $latestTopic = FaqTopic::query()
            ->with(['items' => fn ($query) => $query->orderBy('order')->orderBy('created_at')])
            ->latest('created_at')
            ->latest('id')
            ->first();

        return [
            'total_faq_topic' => FaqTopic::query()->count(),
            'faq_featured' => FaqTopic::query()->where('is_featured', true)->count(),
            'faq_terbaru' => $latestTopic ? $this->transformTopic($latestTopic) : null,
        ];
    }

    public function findByIdentifier(string $identifier): FaqTopic
    {
        $topic = $this->baseQuery()
            ->where('uuid', $identifier)
            ->first();

        if (!$topic) {
            throw (new ModelNotFoundException())->setModel(FaqTopic::class, [$identifier]);
        }

        return $topic;
    }

    public function create(array $data): FaqTopic
    {
        return DB::transaction(function () use ($data) {
            $topic = FaqTopic::query()->create([
                'uuid' => (string) Str::uuid(),
                'topic' => $data['topic'],
                'summary' => $data['summary'] ?? null,
                'is_featured' => $data['is_featured'],
                'order' => $data['order'],
            ]);

            $this->replaceItems($topic, $data['items']);

            return $this->findByIdentifier($topic->uuid);
        });
    }

    public function update(FaqTopic $topic, array $data): FaqTopic
    {
        return DB::transaction(function () use ($topic, $data) {
            $topic->fill([
                'topic' => $data['topic'],
                'summary' => $data['summary'] ?? null,
                'is_featured' => $data['is_featured'],
                'order' => $data['order'],
            ])->save();

            $topic->items()->delete();
            $this->replaceItems($topic, $data['items']);

            return $this->findByIdentifier($topic->uuid);
        });
    }

    public function delete(FaqTopic $topic): void
    {
        DB::transaction(function () use ($topic) {
            $topic->items()->delete();
            $topic->delete();
        });
    }

    private function replaceItems(FaqTopic $topic, array $items): void
    {
        foreach ($items as $index => $item) {
            $topic->items()->create([
                'uuid' => (string) Str::uuid(),
                'question' => $item['question'],
                'answer' => $item['answer'],
                'order' => $item['order'] ?? $index,
            ]);
        }
    }

    private function baseQuery(): Builder
    {
        return FaqTopic::query()
            ->with(['items' => fn ($query) => $query->orderBy('order')->orderBy('created_at')])
            ->withCount('items');
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
                'created_at' => $item->created_at?->utc()->format('Y-m-d\\TH:i:s\\Z'),
                'updated_at' => $item->updated_at?->utc()->format('Y-m-d\\TH:i:s\\Z'),
            ])->values()->all(),
            'created_at' => $topic->created_at?->utc()->format('Y-m-d\\TH:i:s\\Z'),
            'updated_at' => $topic->updated_at?->utc()->format('Y-m-d\\TH:i:s\\Z'),
        ];
    }
}
