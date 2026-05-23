<?php

namespace App\Http\Requests\DataGenerator;

use Illuminate\Foundation\Http\FormRequest;

class InvestasiRequest extends FormRequest
{
  public function rules(): array
  {
    return [
      'origins' => 'array',
      'destinations' => 'array',
      'originGroups' => 'array',
      'destinationGroups' => 'array',
      'investmentType' => 'required|string',
      'sourceCode' => 'required|integer',
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
