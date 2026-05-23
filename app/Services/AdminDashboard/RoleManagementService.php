<?php

namespace App\Services\AdminDashboard;

use App\Repositories\AdminDashboard\PermissionManagement\PermissionManagementRepositoryInterface;
use App\Repositories\AdminDashboard\RoleManagement\RoleManagementRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class RoleManagementService
{
    public function __construct(
        private readonly RoleManagementRepositoryInterface $repository,
        private readonly PermissionManagementRepositoryInterface $permissionRepository,
    ) {
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginate($filters);
    }

    public function findByIdentifier(string $identifier): Role
    {
        return $this->repository->findByIdentifier($identifier);
    }

    public function create(array $data): Role
    {
        return $this->repository->create($data);
    }

    public function update(string $identifier, array $data): Role
    {
        $role = $this->repository->findByIdentifier($identifier);

        return $this->repository->update($role, $data);
    }

    public function canDelete(string $identifier): bool
    {
        $role = $this->repository->findByIdentifier($identifier);

        return (int) ($role->users_count ?? 0) === 0;
    }

    public function delete(string $identifier): Role
    {
        $role = $this->repository->findByIdentifier($identifier);

        $snapshot = clone $role;

        $this->repository->delete($role);

        return $snapshot;
    }

    public function getAvailablePermissions(): Collection
    {
        return $this->permissionRepository->getAvailablePermissions();
    }
}
