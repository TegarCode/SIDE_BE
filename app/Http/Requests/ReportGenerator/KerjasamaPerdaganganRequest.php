<?php

namespace App\Http\Requests\ReportGenerator;

use Illuminate\Foundation\Http\FormRequest;

class KerjasamaPerdaganganRequest extends FormRequest
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
      'destinations' => ['required', 'array'],
      'sumber'       => ['required', 'integer'],
      'year_start'   => ['required', 'integer', "min:2000", "max:{$current}"],
      'year_end'     => ['required', 'integer', "min:2000", "max:{$current}"],
      'format'       => ['sometimes', 'in:pdf,docx'],
    ];
  }
}
