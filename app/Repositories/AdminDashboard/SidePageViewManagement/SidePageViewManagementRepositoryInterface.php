<?php

namespace App\Repositories\AdminDashboard\SidePageViewManagement;

use App\Models\SidePageView;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface SidePageViewManagementRepositoryInterface
{
    public function paginate(array $filters): LengthAwarePaginator;

    public function getSummary(): array;

    public function getAvailableModules(): Collection;

    public function findByIdentifier(int|string $identifier): SidePageView;
}
