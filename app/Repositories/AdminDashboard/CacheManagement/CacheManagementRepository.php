<?php

namespace App\Repositories\AdminDashboard\CacheManagement;

use Carbon\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CacheManagementRepository implements CacheManagementRepositoryInterface
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 10);
        $page = (int) ($filters['page'] ?? 1);
        $sortBy = $filters['sort_by'] ?? 'expiration';
        $sortDirection = $filters['sort_direction'] ?? 'desc';

        $items = $this->baseCollection()
            ->when($filters['search'] ?? null, function (Collection $collection, string $search) {
                return $collection->filter(fn (array $item) => str_contains($item['key'], $search));
            })
            ->when($filters['category'] ?? null, function (Collection $collection, string $category) {
                return $collection->filter(fn (array $item) => $item['category'] === $category);
            })
            ->sortBy($sortBy, options: SORT_NATURAL, descending: $sortDirection === 'desc')
            ->values();

        $total = $items->count();
        $paginatedItems = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return new Paginator(
            $paginatedItems,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    public function getSummary(): array
    {
        $items = $this->baseCollection();
        $latestCache = $items
            ->sortByDesc('expiration')
            ->first();

        return [
            'total_cache' => $items->count(),
            'kategori_aktif' => $items->pluck('category')->filter()->unique()->count(),
            'cache_terbaru' => $latestCache,
        ];
    }

    public function findByIdentifier(string $identifier): object
    {
        $cache = DB::table('cache')
            ->where('key', $identifier)
            ->where('key', 'like', 'side_cache:%')
            ->first(['key', 'value', 'expiration']);

        if (!$cache) {
            throw (new ModelNotFoundException())->setModel('cache', [$identifier]);
        }

        return (object) $this->transformRow($cache, true);
    }

    public function update(string $identifier, array $data): object
    {
        $cache = $this->findByIdentifier($identifier);

        DB::table('cache')
            ->where('key', $cache->key)
            ->update([
                'expiration' => Carbon::parse($data['expiration_at'])->timestamp,
            ]);

        return $this->findByIdentifier($identifier);
    }

    public function delete(string $identifier): object
    {
        $cache = $this->findByIdentifier($identifier);

        DB::table('cache')
            ->where('key', $cache->key)
            ->delete();

        return $cache;
    }

    private function baseCollection(): Collection
    {
        return DB::table('cache')
            ->where('key', 'like', 'side_cache:%')
            ->get(['key', 'expiration'])
            ->map(fn (object $row) => $this->transformRow($row, false))
            ->values();
    }

    private function transformRow(object $row, bool $includeValue = false): array
    {
        $segments = explode(':', $row->key);
        $parent = $segments[1] ?? null;
        $child = $segments[2] ?? null;
        $category = $parent && $child ? $parent . '>' . $child : ($parent ?? 'uncategorized');

        $data = [
            'id' => $row->key,
            'key' => $row->key,
            'category' => $category,
            'category_parent' => $parent,
            'category_child' => $child,
            'expiration' => Carbon::createFromTimestampUTC((int) $row->expiration)->format('Y-m-d\\TH:i:s\\Z'),
            'expiration_timestamp' => (int) $row->expiration,
        ];

        if ($includeValue && property_exists($row, 'value')) {
            $data['value'] = $this->decodeCacheValue($row->value);
        }

        return $data;
    }

    private function decodeCacheValue(?string $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $jsonDecoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $jsonDecoded;
        }

        try {
            $unserialized = @unserialize($value, [
                'allowed_classes' => [
                    SupportCollection::class,
                ],
            ]);

            if ($unserialized !== false || $value === 'b:0;') {
                return $this->normalizeDecodedValue($unserialized);
            }

            $unserialized = @unserialize($value, ['allowed_classes' => false]);

            if ($unserialized !== false || $value === 'b:0;') {
                return $this->normalizeDecodedValue($unserialized);
            }
        } catch (\Throwable) {
            // Fallback ke raw value jika decode gagal.
        }

        return $value;
    }

    private function normalizeDecodedValue(mixed $value): mixed
    {
        if ($value instanceof SupportCollection) {
            return $value
                ->map(fn ($item) => $this->normalizeDecodedValue($item))
                ->values()
                ->all();
        }

        if (is_object($value)) {
            return json_decode(json_encode($value, JSON_UNESCAPED_UNICODE), true);
        }

        if (is_array($value)) {
            return array_map(fn ($item) => $this->normalizeDecodedValue($item), $value);
        }

        return $value;
    }
}
