<?php

namespace App\Http\Requests\DataGenerator;

use Illuminate\Foundation\Http\FormRequest;

class PariwisataRequest extends FormRequest
{
  public function rules(): array
  {
    return [
      'origins' => 'array',
      'destinations' => 'array',
      'originGroups' => 'array',
      'destinationGroups' => 'array',
      'sourceCode' => 'required|integer',
      'typeData' => 'required|string',
      'yearFrom' => 'required|integer',
      'yearTo' => 'required|integer',
      'viewType' => 'required|string',
    ];
  }

  public function authorize(): bool
  {
    return true;
  }
}
