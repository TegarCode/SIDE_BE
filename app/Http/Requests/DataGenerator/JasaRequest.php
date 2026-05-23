<?php

namespace App\Http\Requests\DataGenerator;

use Illuminate\Foundation\Http\FormRequest;

class JasaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'origins'           => 'array',
            'origins.*'         => 'string',
            'destinations'      => 'array',
            'destinations.*'    => 'string',
            'originGroups'      => 'array',
            'destinationGroups' => 'array',

            'yearFrom'          => 'required|integer',
            'yearTo'            => 'required|integer',

            'gender'            => 'required|string',   // 'all' | 'L' | 'P'
            'idProfesi'         => 'required|array',    // ['all'] atau [1,2,...]
            'idProfesi.*'       => 'string',

            'sourceCode'        => 'required|integer',

            'viewType'          => 'required|string',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
