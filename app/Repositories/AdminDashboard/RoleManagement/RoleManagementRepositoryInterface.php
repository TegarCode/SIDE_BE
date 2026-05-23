<?php

namespace App\Repositories\AdminDashboard\RoleManagement;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

interface RoleManagementRepositoryInterface
{
    public function paginate(array $filters): LengthAwarePaginator;

    public function getAvailableRoles(): Collection;

    public function findByIdentifier(string $identifier): Role;

    public function create(array $data): Role;

    public function update(Role $role, array $data): Role;

    public function delete(Role $role): void;
}
