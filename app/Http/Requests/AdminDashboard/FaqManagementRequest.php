<?php

namespace App\Http\Requests\AdminDashboard;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FaqManagementRequest extends FormRequest
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
            'is_featured' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', Rule::in(['topic', 'order', 'created_at', 'updated_at'])],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    private function storeRules(): array
    {
        return [
            'topic' => ['required', 'string', 'min:3', 'max:255'],
            'summary' => ['nullable', 'string'],
            'is_featured' => ['required', 'boolean'],
            'order' => ['required', 'integer', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.question' => ['required', 'string', 'min:3', 'max:255'],
            'items.*.answer' => ['required', 'string'],
            'items.*.order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    private function updateRules(): array
    {
        return $this->storeRules();
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_featured')) {
            $this->merge([
                'is_featured' => filter_var($this->input('is_featured'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
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
