<?php

namespace App\Services\AdminDashboard;

use App\Models\SidePageView;
use App\Repositories\AdminDashboard\SidePageViewManagement\SidePageViewManagementRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SidePageViewManagementService
{
    public function __construct(
        private readonly SidePageViewManagementRepositoryInterface $repository
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

    public function getAvailableModules(): Collection
    {
        return $this->repository->getAvailableModules();
    }

    public function findByIdentifier(int|string $identifier): SidePageView
    {
        return $this->repository->findByIdentifier($identifier);
    }
}
