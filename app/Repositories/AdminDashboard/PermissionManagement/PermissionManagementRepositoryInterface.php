<?php

namespace App\Repositories\AdminDashboard\PermissionManagement;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

interface PermissionManagementRepositoryInterface
{
    public function paginate(array $filters): LengthAwarePaginator;

    public function getSummary(): array;

    public function getAvailablePermissions(): Collection;

    public function findByIdentifier(string $identifier): Permission;

    public function create(array $data): Permission;

    public function update(Permission $permission, array $data): Permission;

    public function delete(Permission $permission): void;
}
