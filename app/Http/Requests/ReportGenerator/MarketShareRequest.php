<?php

namespace App\Http\Requests\ReportGenerator;

use Illuminate\Foundation\Http\FormRequest;

class MarketShareRequest extends FormRequest
{
  /**
   * Determine if the user is authorized to make this request.
   */
  public function authorize(): bool
  {
    return true;
  }

  /**
   * Get the validation rules that apply to the request.
   *
   * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
   */
  public function rules(): array
  {
    $current = date('Y');
    return [
      'origin'       => ['required', 'string'],
      'destination'  => ['required'],
      'sumber'       => ['required', 'integer'],
      'strategy1'    => ['required', 'string', 'in:EXPORT,IMPORT'],
      'top_n'        => ['required', 'integer', 'min:1'],
      'year'         => ['required', 'integer', "min:2020", "max:{$current}"],
      'format'       => ['sometimes', 'in:pdf,docx'],
    ];
  }
}
