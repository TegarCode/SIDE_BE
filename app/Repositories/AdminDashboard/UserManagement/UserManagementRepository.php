<?php

namespace App\Repositories\AdminDashboard\UserManagement;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserManagementRepository implements UserManagementRepositoryInterface
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 10);
        $sortBy = $filters['sort_by'] ?? 'updated_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';

        return $this->baseQuery()
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $builder) use ($search) {
                    $builder
                        ->where('users.name', 'like', '%' . $search . '%')
                        ->orWhere('users.email', 'like', '%' . $search . '%');
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('users.status', $status))
            ->when($filters['role'] ?? null, function (Builder $query, string $role) {
                $query->whereHas('roles', fn (Builder $roleQuery) => $roleQuery->where('name', $role)->where('guard_name', 'web'));
            })
            ->orderBy('users.' . $sortBy, $sortDirection)
            ->paginate($perPage, ['users.*'], 'page', (int) ($filters['page'] ?? 1));
    }

    public function getSummary(): array
    {
        $latestUser = User::query()
            ->with(['roles:id,name'])
            ->latest('created_at')
            ->latest('id')
            ->first();

        return [
            'total_user' => User::query()->count(),
            'role_aktif' => DB::table('model_has_roles as mhr')
                ->join('users', function ($join) {
                    $join->on('users.id', '=', 'mhr.model_id')
                        ->where('mhr.model_type', User::class)
                        ->whereNull('users.deleted_at');
                })
                ->distinct('mhr.role_id')
                ->count('mhr.role_id'),
            'user_terbaru' => $latestUser ? $this->transformUser($latestUser) : null,
        ];
    }

    public function findByIdentifier(string $identifier): User
    {
        $user = $this->baseQuery()
            ->where('users.uuid', $identifier)
            ->first();

        if (!$user) {
            throw (new ModelNotFoundException())->setModel(User::class, [$identifier]);
        }

        return $user;
    }

    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::query()->create([
                'uuid' => (string) Str::uuid(),
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'status' => $data['status'],
            ]);

            $user->syncRoles($data['roles']);

            return $this->findByIdentifier($user->uuid);
        });
    }

    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $payload = [
                'name' => $data['name'],
                'email' => $data['email'],
                'status' => $data['status'],
            ];

            if (!empty($data['password'])) {
                $payload['password'] = $data['password'];
            }

            $user->fill($payload)->save();
            $user->syncRoles($data['roles']);

            return $this->findByIdentifier($user->uuid);
        });
    }

    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->delete();
        });
    }

    private function baseQuery(): Builder
    {
        return User::query()
            ->with(['roles:id,name'])
            ->whereNull('users.deleted_at');
    }

    private function transformUser(User $user): array
    {
        return [
            'id' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
            'roles' => $user->roles->pluck('name')->sort()->values()->all(),
            'created_at' => $user->created_at?->utc()->format('Y-m-d\\TH:i:s\\Z'),
            'updated_at' => $user->updated_at?->utc()->format('Y-m-d\\TH:i:s\\Z'),
        ];
    }
}
