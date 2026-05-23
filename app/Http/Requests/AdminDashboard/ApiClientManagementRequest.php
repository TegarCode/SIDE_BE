<?php

namespace App\Http\Requests\AdminDashboard;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ApiClientManagementRequest extends FormRequest
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
            'regenerateKey' => $this->regenerateKeyRules(),
            default => [],
        };
    }

    private function indexRules(): array
    {
        return [
            'search' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'active' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', Rule::in(['name', 'active', 'created_at', 'updated_at'])],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    private function storeRules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255', 'unique:api_clients,name'],
            'description' => ['nullable', 'string'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['required', 'string', 'distinct', Rule::in($this->availableAbilities())],
            'allowed_domains' => ['nullable', 'array'],
            'allowed_domains.*' => ['required', 'string', 'max:255'],
            'active' => ['required', 'boolean'],
        ];
    }

    private function updateRules(): array
    {
        $identifier = (string) $this->route('id');
        $apiClientId = $this->resolveApiClientId($identifier);

        return [
            'name' => ['required', 'string', 'min:3', 'max:255', Rule::unique('api_clients', 'name')->ignore($apiClientId)],
            'description' => ['nullable', 'string'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['required', 'string', 'distinct', Rule::in($this->availableAbilities())],
            'allowed_domains' => ['nullable', 'array'],
            'allowed_domains.*' => ['required', 'string', 'max:255'],
            'active' => ['required', 'boolean'],
        ];
    }

    private function regenerateKeyRules(): array
    {
        return [
            'current_password' => ['required', 'string'],
        ];
    }

    private function availableAbilities(): array
    {
        return DB::table('permissions')
            ->where('guard_name', 'web')
            ->pluck('name')
            ->push('*')
            ->all();
    }

    private function resolveApiClientId(string $identifier): ?int
    {
        if (!Str::isUuid($identifier)) {
            return null;
        }

        return DB::table('api_clients')
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
