<?php

namespace App\Repositories\AdminDashboard\AuthenticationLogManagement;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Rappasoft\LaravelAuthenticationLog\Models\AuthenticationLog;

class AuthenticationLogManagementRepository implements AuthenticationLogManagementRepositoryInterface
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 10);
        $sortBy = $filters['sort_by'] ?? 'login_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';

        return $this->baseQuery()
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $builder) use ($search) {
                    $builder
                        ->where('ip_address', 'like', '%' . $search . '%')
                        ->orWhere('user_agent', 'like', '%' . $search . '%')
                        ->orWhereHasMorph('authenticatable', [User::class], function (Builder $userQuery) use ($search) {
                            $userQuery
                                ->where('name', 'like', '%' . $search . '%')
                                ->orWhere('email', 'like', '%' . $search . '%');
                        });
                });
            })
            ->when(array_key_exists('login_successful', $filters), function (Builder $query) use ($filters) {
                $value = filter_var($filters['login_successful'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($value !== null) {
                    $query->where('login_successful', $value);
                }
            })
            ->orderBy($sortBy, $sortDirection)
            ->paginate($perPage, ['*'], 'page', (int) ($filters['page'] ?? 1));
    }

    public function getSummary(): array
    {
        $latestLog = AuthenticationLog::query()
            ->with('authenticatable')
            ->latest('login_at')
            ->latest('id')
            ->first();

        return [
            'total_log' => AuthenticationLog::query()->count(),
            'login_berhasil' => AuthenticationLog::query()->where('login_successful', true)->count(),
            'log_terbaru' => $latestLog ? $this->transformLog($latestLog) : null,
        ];
    }

    public function findByIdentifier(string $identifier): AuthenticationLog
    {
        $log = $this->baseQuery()
            ->where('uuid', $identifier)
            ->first();

        if (!$log) {
            throw (new ModelNotFoundException())->setModel(AuthenticationLog::class, [$identifier]);
        }

        return $log;
    }

    public function delete(AuthenticationLog $log): void
    {
        DB::transaction(function () use ($log) {
            $log->delete();
        });
    }

    private function baseQuery(): Builder
    {
        return AuthenticationLog::query()
            ->with('authenticatable');
    }

    private function transformLog(AuthenticationLog $log): array
    {
        $user = $log->authenticatable instanceof User ? $log->authenticatable : null;

        return [
            'id' => $log->uuid,
            'user' => $user ? [
                'id' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
            ] : null,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'login_at' => $log->login_at?->utc()->format('Y-m-d\\TH:i:s\\Z'),
            'login_successful' => (bool) $log->login_successful,
            'logout_at' => $log->logout_at?->utc()->format('Y-m-d\\TH:i:s\\Z'),
            'location' => $log->location,
        ];
    }
}
