<?php

namespace App\Repositories\AdminDashboard\RoleManagement;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class RoleManagementRepository implements RoleManagementRepositoryInterface
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 10);

        return $this->baseQuery()
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $builder) use ($search) {
                    $builder
                        ->where('name', 'like', '%' . $search . '%')
                        ->orWhere('slug', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%');
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', (int) ($filters['page'] ?? 1));
    }

    public function findByIdentifier(string $identifier): Role
    {
        $role = $this->baseQuery()
            ->where('uuid', $identifier)
            ->first();

        if (!$role) {
            throw (new ModelNotFoundException())->setModel(Role::class, [$identifier]);
        }

        return $role;
    }

    public function getAvailableRoles(): Collection
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->uuid,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
            ])
            ->values();
    }

    public function create(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            $role = Role::query()->create([
                'uuid' => (string) Str::uuid(),
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'],
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($data['permissions']);

            return $this->findByIdentifier($role->uuid);
        });
    }

    public function update(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data) {
            $role->fill([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'],
            ])->save();

            $role->syncPermissions($data['permissions']);

            return $this->findByIdentifier($role->uuid);
        });
    }

    public function delete(Role $role): void
    {
        DB::transaction(function () use ($role) {
            $role->syncPermissions([]);
            $role->delete();
        });
    }

    private function baseQuery(): Builder
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->with(['permissions:id,name'])
            ->withCount(['permissions'])
            ->select('roles.*')
            ->selectSub(function ($query) {
                $query->from('model_has_roles')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('model_has_roles.role_id', 'roles.id')
                    ->where('model_has_roles.model_type', User::class);
            }, 'users_count');
    }
}
