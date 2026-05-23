<?php

namespace App\Http\Requests\DataGenerator;

use Illuminate\Foundation\Http\FormRequest;

class KinerjaEkonomiRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'indicator_id' => 'required|integer',
            'yearFrom'     => 'required|integer',
            'yearTo'       => 'required|integer',
            'viewType'     => 'required|string|in:table,chart',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
