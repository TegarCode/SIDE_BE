<?php

namespace App\Services\AdminDashboard;

use App\Models\ApiClient;
use App\Models\User;
use App\Repositories\AdminDashboard\ApiClientManagement\ApiClientManagementRepositoryInterface;
use App\Repositories\AdminDashboard\PermissionManagement\PermissionManagementRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;

class ApiClientManagementService
{
    public function __construct(
        private readonly ApiClientManagementRepositoryInterface $repository,
        private readonly PermissionManagementRepositoryInterface $permissionRepository
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

    public function findByIdentifier(string $identifier): ApiClient
    {
        return $this->repository->findByIdentifier($identifier);
    }

    public function getAvailablePermissions(): Collection
    {
        return $this->permissionRepository->getAvailablePermissions();
    }

    public function create(array $data): array
    {
        return $this->repository->create($data);
    }

    public function update(string $identifier, array $data): ApiClient
    {
        $apiClient = $this->repository->findByIdentifier($identifier);

        return $this->repository->update($apiClient, $data);
    }

    public function regenerateKey(string $identifier, User $user, string $currentPassword): array
    {
        if (!Hash::check($currentPassword, $user->password)) {
            throw new AuthorizationException('Password saat ini tidak valid.');
        }

        $apiClient = $this->repository->findByIdentifier($identifier);

        return $this->repository->regenerateKey($apiClient);
    }

    public function delete(string $identifier): ApiClient
    {
        $apiClient = $this->repository->findByIdentifier($identifier);
        $snapshot = clone $apiClient;
        $this->repository->delete($apiClient);

        return $snapshot;
    }
}
