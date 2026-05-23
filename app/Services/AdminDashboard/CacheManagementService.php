<?php

namespace App\Services\AdminDashboard;

use App\Repositories\AdminDashboard\CacheManagement\CacheManagementRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CacheManagementService
{
    public function __construct(
        private readonly CacheManagementRepositoryInterface $repository
    ) {
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginate($filters);
    }

    public function getSummary(): array
    {
        return $this->repository->getSummary();
    }

    public function findByIdentifier(string $identifier): object
    {
        return $this->repository->findByIdentifier($identifier);
    }

    public function update(string $identifier, array $data): object
    {
        return $this->repository->update($identifier, $data);
    }

    public function delete(string $identifier): object
    {
        return $this->repository->delete($identifier);
    }
}
