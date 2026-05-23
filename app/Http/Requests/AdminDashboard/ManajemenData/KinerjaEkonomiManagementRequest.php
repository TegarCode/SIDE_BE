<?php

namespace App\Http\Requests\AdminDashboard\ManajemenData;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class KinerjaEkonomiManagementRequest extends FormRequest
{
    private const MAX_PER_PAGE = 100;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'index' => $this->indexRules(),
            'currentIndex' => $this->currentIndexRules(),
            'show' => $this->detailRules(),
            'previewUpload' => $this->previewRules(),
            'store' => $this->storeRules(),
            'updateRow' => $this->rowRules(),
            'updateCurrentRow' => $this->rowRules(),
            'reject' => ['note' => ['nullable', 'string']],
            default => [],
        };
    }

    private function currentIndexRules(): array
    {
        return [
            'country_code' => ['nullable', 'string', 'size:3'],
            'indicator_id' => ['nullable', 'string', 'max:5'],
            'source_code' => ['nullable', 'string', 'max:2'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_PER_PAGE],
            'sort_by' => ['nullable', Rule::in(['ID', 'Kode_Alpha3', 'Bulan', 'Tahun', 'Nilai', 'Unit', 'Satuan', 'ID_Indikator', 'Komponen_Indikator', 'KodeSumber'])],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    private function indexRules(): array
    {
        return [
            'search' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_PER_PAGE],
            'status' => ['nullable', Rule::in(['draft', 'validating', 'valid', 'invalid', 'approved', 'rejected', 'published', 'failed'])],
            'source_type' => ['nullable', Rule::in(['manual', 'upload'])],
            'sort_by' => ['nullable', Rule::in(['created_at', 'updated_at', 'uploaded_at', 'validated_at', 'approved_at', 'published_at', 'source_type', 'original_filename', 'status', 'total_rows', 'valid_rows', 'invalid_rows'])],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    private function detailRules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_PER_PAGE],
            'sort_by' => ['nullable', Rule::in(['id', 'Kode_Alpha3', 'Bulan', 'Tahun', 'Nilai', 'Unit', 'Satuan', 'ID_Indikator', 'Komponen_Indikator', 'KodeSumber', 'row_status', 'created_at', 'updated_at'])],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }
    private function storeRules(): array
    {
        return [
            'source_type' => ['required', Rule::in(['manual', 'upload'])],
            'original_filename' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'rows' => ['required_without:file', 'array', 'min:1'],
            'rows.*' => ['required', 'array'],
            'rows.*.Kode_Alpha3' => ['nullable', 'string', 'size:3'],
            'rows.*.kode_alpha3' => ['nullable', 'string', 'size:3'],
            'rows.*.Bulan' => ['nullable', 'integer', 'between:1,12'],
            'rows.*.bulan' => ['nullable', 'integer', 'between:1,12'],
            'rows.*.Tahun' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'rows.*.tahun' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'rows.*.Nilai' => ['nullable', 'numeric'],
            'rows.*.nilai' => ['nullable', 'numeric'],
            'rows.*.Unit' => ['nullable', 'string', 'max:45'],
            'rows.*.unit' => ['nullable', 'string', 'max:45'],
            'rows.*.Satuan' => ['nullable', 'string', 'max:45'],
            'rows.*.satuan' => ['nullable', 'string', 'max:45'],
            'rows.*.ID_Indikator' => ['nullable', 'string', 'max:5'],
            'rows.*.id_indikator' => ['nullable', 'string', 'max:5'],
            'rows.*.indicator_id' => ['nullable', 'string', 'max:5'],
            'rows.*.Komponen_Indikator' => ['nullable', 'string', 'max:100'],
            'rows.*.komponen_indikator' => ['nullable', 'string', 'max:100'],
            'rows.*.KodeSumber' => ['nullable', 'string', 'max:2'],
            'rows.*.kode_sumber' => ['nullable', 'string', 'max:2'],
            'file' => ['nullable', 'file', 'mimes:csv,xlsx,xls', 'max:1024000'],
            'column_mapping' => ['nullable', 'array'],
            'column_mapping.Kode_Alpha3' => ['nullable', 'string'],
            'column_mapping.Bulan' => ['nullable', 'string'],
            'column_mapping.Tahun' => ['nullable', 'string'],
            'column_mapping.Nilai' => ['nullable', 'string'],
            'column_mapping.Unit' => ['nullable', 'string'],
            'column_mapping.Satuan' => ['nullable', 'string'],
            'column_mapping.ID_Indikator' => ['nullable', 'string'],
            'column_mapping.Komponen_Indikator' => ['nullable', 'string'],
            'column_mapping.KodeSumber' => ['nullable', 'string'],
        ];
    }

    private function previewRules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls', 'max:1024000'],
            'sample_size' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    private function rowRules(): array
    {
        return [
            'Kode_Alpha3' => ['required_without:kode_alpha3', 'string', 'size:3'],
            'kode_alpha3' => ['required_without:Kode_Alpha3', 'string', 'size:3'],
            'Bulan' => ['nullable', 'integer', 'between:1,12'],
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'Tahun' => ['required_without:tahun', 'integer', 'min:1900', 'max:2100'],
            'tahun' => ['required_without:Tahun', 'integer', 'min:1900', 'max:2100'],
            'Nilai' => ['required_without:nilai', 'numeric'],
            'nilai' => ['required_without:Nilai', 'numeric'],
            'Unit' => ['nullable', 'string', 'max:45'],
            'unit' => ['nullable', 'string', 'max:45'],
            'Satuan' => ['nullable', 'string', 'max:45'],
            'satuan' => ['nullable', 'string', 'max:45'],
            'ID_Indikator' => ['required_without_all:id_indikator,indicator_id', 'string', 'max:5'],
            'id_indikator' => ['required_without_all:ID_Indikator,indicator_id', 'string', 'max:5'],
            'indicator_id' => ['required_without_all:ID_Indikator,id_indikator', 'string', 'max:5'],
            'Komponen_Indikator' => ['nullable', 'string', 'max:100'],
            'komponen_indikator' => ['nullable', 'string', 'max:100'],
            'KodeSumber' => ['nullable', 'string', 'max:2'],
            'kode_sumber' => ['nullable', 'string', 'max:2'],
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


