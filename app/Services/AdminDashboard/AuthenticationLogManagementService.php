<?php

namespace App\Services\AdminDashboard;

use App\Repositories\AdminDashboard\AuthenticationLogManagement\AuthenticationLogManagementRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Rappasoft\LaravelAuthenticationLog\Models\AuthenticationLog;

class AuthenticationLogManagementService
{
    public function __construct(
        private readonly AuthenticationLogManagementRepositoryInterface $repository
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

    public function findByIdentifier(string $identifier): AuthenticationLog
    {
        return $this->repository->findByIdentifier($identifier);
    }

    public function delete(string $identifier): AuthenticationLog
    {
        $log = $this->repository->findByIdentifier($identifier);
        $snapshot = clone $log;
        $this->repository->delete($log);

        return $snapshot;
    }
}
