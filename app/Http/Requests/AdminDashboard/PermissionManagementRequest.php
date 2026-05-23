<?php

namespace App\Http\Requests\AdminDashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PermissionManagementRequest extends FormRequest
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
            'per_page' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'category' => ['nullable', 'string', 'max:255'],
            'sort_by' => ['nullable', Rule::in(['name', 'category', 'created_at', 'updated_at'])],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    private function storeRules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:3',
                'max:255',
                Rule::unique('permissions', 'name')->where('guard_name', 'web'),
            ],
            'category' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }

    private function updateRules(): array
    {
        $identifier = (string) $this->route('id');
        $permissionId = $this->resolvePermissionId($identifier);

        return [
            'name' => [
                'required',
                'string',
                'min:3',
                'max:255',
                Rule::unique('permissions', 'name')
                    ->where('guard_name', 'web')
                    ->ignore($permissionId),
            ],
            'category' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }

    private function resolvePermissionId(string $identifier): ?int
    {
        if (!Str::isUuid($identifier)) {
            return null;
        }

        return DB::table('permissions')
            ->where('uuid', $identifier)
            ->value('id');
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'The given data was invalid.',
            'errors' => $validator->errors(),
            'data' => null,
        ], 422));
    }
}
