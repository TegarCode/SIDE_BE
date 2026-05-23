<?php

namespace App\Services\AdminDashboard;

use App\Repositories\AdminDashboard\PermissionManagement\PermissionManagementRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\Permission\Models\Permission;

class PermissionManagementService
{
    public function __construct(
        private readonly PermissionManagementRepositoryInterface $repository
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

    public function findByIdentifier(string $identifier): Permission
    {
        return $this->repository->findByIdentifier($identifier);
    }

    public function create(array $data): Permission
    {
        return $this->repository->create($data);
    }

    public function update(string $identifier, array $data): Permission
    {
        $permission = $this->repository->findByIdentifier($identifier);

        return $this->repository->update($permission, $data);
    }

    public function delete(string $identifier): Permission
    {
        $permission = $this->repository->findByIdentifier($identifier);

        $snapshot = clone $permission;

        $this->repository->delete($permission);

        return $snapshot;
    }
}
