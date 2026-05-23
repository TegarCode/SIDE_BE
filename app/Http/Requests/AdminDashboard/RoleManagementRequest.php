<?php

namespace App\Http\Requests\AdminDashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleManagementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'index' => $this->indexRules(),
            'store' => $this->storeRules(),
            'update' => $this->updateRules(),
            default => [],
        };
    }

    private function indexRules(): array
    {
        return [
            'search' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }

    private function storeRules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255', 'unique:roles,name'],
            'slug' => ['required', 'string', 'max:255', 'unique:roles,slug'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'permissions' => ['required', 'array'],
            'permissions.*' => [
                'string',
                'distinct',
                Rule::exists('permissions', 'name')->where('guard_name', 'web'),
            ],
        ];
    }

    private function updateRules(): array
    {
        $identifier = (string) $this->route('id');
        $roleId = $this->resolveRoleId($identifier);

        return [
            'name' => [
                'required',
                'string',
                'min:3',
                'max:255',
                Rule::unique('roles', 'name')->ignore($roleId),
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'slug')->ignore($roleId),
            ],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'permissions' => ['required', 'array'],
            'permissions.*' => [
                'string',
                'distinct',
                Rule::exists('permissions', 'name')->where('guard_name', 'web'),
            ],
        ];
    }

    private function resolveRoleId(string $identifier): ?int
    {
        if (!Str::isUuid($identifier)) {
            return null;
        }

        return DB::table('roles')
            ->where('uuid', $identifier)
            ->value('id');
    }
}
