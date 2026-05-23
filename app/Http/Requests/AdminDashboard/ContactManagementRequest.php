<?php

namespace App\Http\Requests\AdminDashboard;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ContactManagementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'index' => $this->indexRules(),
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
            'jenis' => ['nullable', Rule::in(['PERTANYAAN', 'MASUKAN', 'SARAN'])],
            'sort_by' => ['nullable', Rule::in(['nama', 'email', 'jenis', 'created_at', 'updated_at'])],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    private function updateRules(): array
    {
        $identifier = (string) $this->route('id');
        $contactId = $this->resolveContactId($identifier);

        return [
            'nama' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191'],
            'jenis' => ['required', Rule::in(['PERTANYAAN', 'MASUKAN', 'SARAN'])],
            'pesan' => ['required', 'string', 'min:6', 'max:5000'],
        ];
    }

    private function resolveContactId(string $identifier): ?int
    {
        if (!Str::isUuid($identifier)) {
            return null;
        }

        return DB::table('contacts')
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
