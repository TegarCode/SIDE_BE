<?php

namespace App\Services\AdminDashboard;

use App\Models\User;
use App\Repositories\AdminDashboard\RoleManagement\RoleManagementRepositoryInterface;
use App\Repositories\AdminDashboard\UserManagement\UserManagementRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class UserManagementService
{
    public function __construct(
        private readonly UserManagementRepositoryInterface $repository,
        private readonly RoleManagementRepositoryInterface $roleRepository,
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

    public function findByIdentifier(string $identifier): User
    {
        return $this->repository->findByIdentifier($identifier);
    }

    public function create(array $data): User
    {
        return $this->repository->create($data);
    }

    public function update(string $identifier, array $data): User
    {
        $user = $this->repository->findByIdentifier($identifier);

        return $this->repository->update($user, $data);
    }

    public function delete(string $identifier): User
    {
        $user = $this->repository->findByIdentifier($identifier);

        $snapshot = clone $user;

        $this->repository->delete($user);

        return $snapshot;
    }

    public function getAvailableRoles(): Collection
    {
        return $this->roleRepository->getAvailableRoles();
    }
}
