<?php

namespace App\Repositories\AdminDashboard\PermissionManagement;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionManagementRepository implements PermissionManagementRepositoryInterface
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 10);
        $sortBy = $filters['sort_by'] ?? 'category';
        $sortDirection = $filters['sort_direction'] ?? (($sortBy === 'category' || $sortBy === 'name') ? 'asc' : 'desc');

        return $this->baseQuery()
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $builder) use ($search) {
                    $builder
                        ->where('name', 'like', '%' . $search . '%')
                        ->orWhere('category', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%');
                });
            })
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->where('category', $category))
            ->orderBy($sortBy, $sortDirection)
            ->when($sortBy !== 'name', fn (Builder $query) => $query->orderBy('name'))
            ->paginate($perPage, ['*'], 'page', (int) ($filters['page'] ?? 1));
    }

    public function getSummary(): array
    {
        $latestPermission = Permission::query()
            ->where('guard_name', 'web')
            ->latest('created_at')
            ->latest('id')
            ->first();

        return [
            'total_permission' => Permission::query()
                ->where('guard_name', 'web')
                ->count(),
            'kategori_aktif' => Permission::query()
                ->where('guard_name', 'web')
                ->whereNotNull('category')
                ->distinct('category')
                ->count('category'),
            'permission_terbaru' => $latestPermission ? [
                'id' => $latestPermission->uuid,
                'name' => $latestPermission->name,
                'category' => $latestPermission->category,
                'description' => $latestPermission->description,
                'created_at' => $latestPermission->created_at?->utc()->format('Y-m-d\\TH:i:s\\Z'),
                'updated_at' => $latestPermission->updated_at?->utc()->format('Y-m-d\\TH:i:s\\Z'),
            ] : null,
        ];
    }

    public function getAvailablePermissions(): Collection
    {
        return Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->map(fn (Permission $permission) => [
                'id' => $permission->uuid,
                'name' => $permission->name,
                'category' => $permission->category,
                'description' => $permission->description,
            ])
            ->values();
    }

    public function findByIdentifier(string $identifier): Permission
    {
        $permission = $this->baseQuery()
            ->where('uuid', $identifier)
            ->first();

        if (!$permission) {
            throw (new ModelNotFoundException())->setModel(Permission::class, [$identifier]);
        }

        return $permission;
    }

    public function create(array $data): Permission
    {
        return DB::transaction(function () use ($data) {
            $permission = Permission::query()->create([
                'uuid' => (string) Str::uuid(),
                'name' => $data['name'],
                'category' => $data['category'],
                'description' => $data['description'] ?? null,
                'guard_name' => 'web',
            ]);

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $this->findByIdentifier($permission->uuid);
        });
    }

    public function update(Permission $permission, array $data): Permission
    {
        return DB::transaction(function () use ($permission, $data) {
            $permission->fill([
                'name' => $data['name'],
                'category' => $data['category'],
                'description' => $data['description'] ?? null,
            ])->save();

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $this->findByIdentifier($permission->uuid);
        });
    }

    public function delete(Permission $permission): void
    {
        DB::transaction(function () use ($permission) {
            $permission->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }

    private function baseQuery(): Builder
    {
        return Permission::query()
            ->where('guard_name', 'web');
    }
}
