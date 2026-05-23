<?php

namespace App\Repositories\AdminDashboard\SidePageViewManagement;

use App\Models\SidePageView;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class SidePageViewManagementRepository implements SidePageViewManagementRepositoryInterface
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 10);
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';

        return $this->baseQuery()
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $builder) use ($search) {
                    $builder
                        ->where('side_page_views.path', 'like', '%' . $search . '%')
                        ->orWhere('side_page_views.module', 'like', '%' . $search . '%')
                        ->orWhere('side_page_views.user_agent', 'like', '%' . $search . '%')
                        ->orWhereHas('user', function (Builder $userQuery) use ($search) {
                            $userQuery
                                ->where('name', 'like', '%' . $search . '%')
                                ->orWhere('email', 'like', '%' . $search . '%');
                        });
                });
            })
            ->when($filters['module'] ?? null, fn (Builder $query, string $module) => $query->where('side_page_views.module', $module))
            ->orderBy('side_page_views.' . $sortBy, $sortDirection)
            ->paginate($perPage, ['side_page_views.*'], 'page', (int) ($filters['page'] ?? 1));
    }

    public function getSummary(): array
    {
        $latestView = SidePageView::query()
            ->with('user:id,uuid,name,email')
            ->where('path', '!=', '/admin-management/analytics/page-view')
            ->latest('created_at')
            ->latest('id')
            ->first();

        return [
            'total_view' => SidePageView::query()->count(),
            'module_aktif' => SidePageView::query()
                ->whereNotNull('module')
                ->distinct('module')
                ->count('module'),
            'view_terbaru' => $latestView ? $this->transformView($latestView) : null,
        ];
    }

    public function getAvailableModules(): Collection
    {
        return SidePageView::query()
            ->whereNotNull('module')
            ->where('module', '!=', '')
            ->select('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module')
            ->map(fn (string $module) => [
                'name' => $module,
            ])
            ->values();
    }

    public function findByIdentifier(int|string $identifier): SidePageView
    {
        $view = $this->baseQuery()
            ->where('side_page_views.id', (int) $identifier)
            ->first();

        if (!$view) {
            throw (new ModelNotFoundException())->setModel(SidePageView::class, [$identifier]);
        }

        return $view;
    }

    private function baseQuery(): Builder
    {
        return SidePageView::query()
            ->with('user:id,uuid,name,email');
    }

    private function transformView(SidePageView $view): array
    {
        return [
            'id' => $view->id,
            'path' => $view->path,
            'module' => $view->module,
            'user' => $view->user ? [
                'id' => $view->user->uuid,
                'name' => $view->user->name,
                'email' => $view->user->email,
            ] : null,
            'user_agent' => $view->user_agent,
            'created_at' => $view->created_at?->utc()->format('Y-m-d\\TH:i:s\\Z'),
            'updated_at' => $view->updated_at?->utc()->format('Y-m-d\\TH:i:s\\Z'),
        ];
    }
}
