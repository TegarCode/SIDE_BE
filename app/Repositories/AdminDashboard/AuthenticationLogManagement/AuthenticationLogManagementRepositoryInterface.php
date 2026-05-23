<?php

namespace App\Repositories\AdminDashboard\AuthenticationLogManagement;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Rappasoft\LaravelAuthenticationLog\Models\AuthenticationLog;

interface AuthenticationLogManagementRepositoryInterface
{
    public function paginate(array $filters): LengthAwarePaginator;

    public function getSummary(): array;

    public function findByIdentifier(string $identifier): AuthenticationLog;

    public function delete(AuthenticationLog $log): void;
}
