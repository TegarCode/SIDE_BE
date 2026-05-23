<?php

namespace App\Http\Requests\DataGenerator;

use Illuminate\Foundation\Http\FormRequest;

class PerdaganganRequest extends FormRequest
{
  public function rules(): array
  {
    return [
      'origins'           => ['nullable', 'array'],
      'destinations'      => ['nullable', 'array'],
      'originGroups'      => ['nullable', 'array'],
      'destinationGroups' => ['nullable', 'array'],
      'tradeType'         => ['required', 'in:Export,Import,Neraca,Total'],
      'hsLevel'           => ['required', 'integer', 'in:2,4,6'],
      'product'           => ['nullable'],
      'yearFrom'          => ['required', 'integer'],
      'yearTo'            => ['required', 'integer', 'gte:yearFrom'],
      'source'            => ['required', 'integer'],

      'page'              => ['nullable', 'integer', 'min:1'],
      'perPage'           => ['nullable', 'integer', 'min:1', 'max:500'],
    ];
  }

  public function withValidator($validator): void
  {
    $validator->after(function ($validator) {
      $originGroups = $this->input('originGroups', []);
      $destinationGroups = $this->input('destinationGroups', []);

      $originGroups = is_array($originGroups) ? array_values(array_filter($originGroups, fn($v) => $v !== null && $v !== '')) : [];
      $destinationGroups = is_array($destinationGroups) ? array_values(array_filter($destinationGroups, fn($v) => $v !== null && $v !== '')) : [];

      if (count($originGroups) > 0 && count($destinationGroups) > 0) {
        $validator->errors()->add(
          'groups',
          'Request antar grup penuh tidak didukung. Gunakan group hanya di salah satu sisi: originGroups atau destinationGroups.'
        );
      }
    });
  }

  public function authorize(): bool
  {
    return true;
  }

  protected function prepareForValidation(): void
  {
    if ($this->has('tradeType')) {
      $raw = trim((string) $this->input('tradeType'));
      if ($raw === '') {
        return;
      }

      $map = [
        'export' => 'Export',
        'import' => 'Import',
        'neraca' => 'Neraca',
        'total perdagangan' => 'Total',
        'total_perdagangan' => 'Total',
        'totalperdagangan' => 'Total',
        'total' => 'Total',
      ];

      $normalized = $map[strtolower($raw)] ?? $raw;
      $this->merge(['tradeType' => $normalized]);
    }
  }
}
