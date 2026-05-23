<?php

namespace App\Repositories\AdminDashboard\CacheManagement;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CacheManagementRepositoryInterface
{
    public function paginate(array $filters): LengthAwarePaginator;

    public function getSummary(): array;

    public function findByIdentifier(string $identifier): object;

    public function update(string $identifier, array $data): object;

    public function delete(string $identifier): object;
}
