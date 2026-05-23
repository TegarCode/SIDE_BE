<?php

namespace App\Repositories\AdminDashboard\ApiClientManagement;

use App\Models\ApiClient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApiClientManagementRepository implements ApiClientManagementRepositoryInterface
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 10);
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? ($sortBy === 'name' ? 'asc' : 'desc');

        return $this->baseQuery()
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $builder) use ($search) {
                    $builder
                        ->where('name', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%');
                });
            })
            ->when(array_key_exists('active', $filters), function (Builder $query) use ($filters) {
                $active = filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                if ($active !== null) {
                    $query->where('active', $active);
                }
            })
            ->orderBy($sortBy, $sortDirection)
            ->when($sortBy !== 'name', fn (Builder $query) => $query->orderBy('name'))
            ->paginate($perPage, ['*'], 'page', (int) ($filters['page'] ?? 1));
    }

    public function getSummary(): array
    {
        $latestClient = ApiClient::query()
            ->latest('created_at')
            ->latest('id')
            ->first();

        return [
            'total_client' => ApiClient::query()->count(),
            'client_aktif' => ApiClient::query()->where('active', true)->count(),
            'client_terbaru' => $latestClient ? $this->transformApiClient($latestClient) : null,
        ];
    }

    public function findByIdentifier(string $identifier): ApiClient
    {
        $apiClient = $this->baseQuery()
            ->where('uuid', $identifier)
            ->first();

        if (!$apiClient) {
            throw (new ModelNotFoundException())->setModel(ApiClient::class, [$identifier]);
        }

        return $apiClient;
    }

    public function create(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $plainTextApiKey = 'bskln_' . Str::random(48);

            $apiClient = ApiClient::query()->create([
                'uuid' => (string) Str::uuid(),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'api_key' => hash('sha256', $plainTextApiKey),
                'abilities' => array_values($data['abilities']),
                'allowed_domains' => array_values($data['allowed_domains'] ?? []),
                'active' => (bool) $data['active'],
            ]);

            return [
                'api_client' => $this->findByIdentifier($apiClient->uuid),
                'plain_text_api_key' => $plainTextApiKey,
            ];
        });
    }

    public function update(ApiClient $apiClient, array $data): ApiClient
    {
        return DB::transaction(function () use ($apiClient, $data) {
            $apiClient->fill([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'abilities' => array_values($data['abilities']),
                'allowed_domains' => array_values($data['allowed_domains'] ?? []),
                'active' => (bool) $data['active'],
            ])->save();

            return $this->findByIdentifier($apiClient->uuid);
        });
    }

    public function regenerateKey(ApiClient $apiClient): array
    {
        return DB::transaction(function () use ($apiClient) {
            $plainTextApiKey = 'bskln_' . Str::random(48);

            $apiClient->forceFill([
                'api_key' => hash('sha256', $plainTextApiKey),
            ])->save();

            return [
                'api_client' => $this->findByIdentifier($apiClient->uuid),
                'plain_text_api_key' => $plainTextApiKey,
            ];
        });
    }

    public function delete(ApiClient $apiClient): void
    {
        DB::transaction(function () use ($apiClient) {
            $apiClient->delete();
        });
    }

    private function baseQuery(): Builder
    {
        return ApiClient::query();
    }

    private function transformApiClient(ApiClient $apiClient): array
    {
        return [
            'id' => $apiClient->uuid,
            'name' => $apiClient->name,
            'description' => $apiClient->description,
            'abilities' => $apiClient->abilities ?? [],
            'allowed_domains' => $apiClient->allowed_domains ?? [],
            'active' => (bool) $apiClient->active,
            'created_at' => $apiClient->created_at?->utc()->format('Y-m-d\\TH:i:s\\Z'),
            'updated_at' => $apiClient->updated_at?->utc()->format('Y-m-d\\TH:i:s\\Z'),
        ];
    }
}
