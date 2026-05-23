<?php

namespace App\Repositories\AdminDashboard\UserManagement;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserManagementRepositoryInterface
{
    public function paginate(array $filters): LengthAwarePaginator;

    public function getSummary(): array;

    public function findByIdentifier(string $identifier): User;

    public function create(array $data): User;

    public function update(User $user, array $data): User;

    public function delete(User $user): void;
}
