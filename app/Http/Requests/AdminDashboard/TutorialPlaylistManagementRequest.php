<?php

namespace App\Http\Requests\AdminDashboard;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class TutorialPlaylistManagementRequest extends FormRequest
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
            'sort_by' => ['nullable', Rule::in(['title', 'slug', 'created_at', 'updated_at'])],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    private function storeRules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:191'],
            'slug' => ['required', 'string', 'min:3', 'max:191', 'unique:tutorial_playlists,slug'],
            'desc' => ['required', 'string'],
            'url' => ['required', 'url', 'max:2048'],
            'thumbnail' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    private function updateRules(): array
    {
        $identifier = (string) $this->route('id');

        return [
            'title' => ['required', 'string', 'min:3', 'max:191'],
            'slug' => ['required', 'string', 'min:3', 'max:191', Rule::unique('tutorial_playlists', 'slug')->ignore($identifier, 'id')],
            'desc' => ['required', 'string'],
            'url' => ['required', 'url', 'max:2048'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
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
