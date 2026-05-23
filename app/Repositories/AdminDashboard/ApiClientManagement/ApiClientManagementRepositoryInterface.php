<?php

namespace App\Repositories\AdminDashboard\ApiClientManagement;

use App\Models\ApiClient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ApiClientManagementRepositoryInterface
{
    public function paginate(array $filters): LengthAwarePaginator;

    public function getSummary(): array;

    public function findByIdentifier(string $identifier): ApiClient;

    public function create(array $data): array;

    public function update(ApiClient $apiClient, array $data): ApiClient;

    public function regenerateKey(ApiClient $apiClient): array;

    public function delete(ApiClient $apiClient): void;
}
