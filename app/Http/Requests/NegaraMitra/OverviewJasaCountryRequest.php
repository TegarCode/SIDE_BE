<?php

namespace App\Http\Requests\NegaraMitra;

use Illuminate\Foundation\Http\FormRequest;

class OverviewJasaCountryRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'filters'              => ['array'],
      'filters.country'      => ['nullable', 'string'],
      'filters.year'         => ['nullable', 'integer', 'min:1900', 'max:2100'],
      'filters.limit'        => ['nullable', 'integer', 'min:1', 'max:1000'],
      'filters.source'       => ['nullable'],

      // flat (opsional)
      'country'              => ['nullable', 'string'],
      'year'                 => ['nullable', 'integer', 'min:1900', 'max:2100'],
      'limit'                => ['nullable', 'integer', 'min:1', 'max:1000'],
      'source'               => ['nullable'],

      'include'              => ['nullable', 'array'],
      'include.*'            => ['string', 'in:summary,top_countries_inbound,top_countries_outbound'],
    ];
  }

  /** Normalisasi input sebelum validasi */
  protected function prepareForValidation(): void
  {
    $inc = $this->input('include', []);
    if (is_array($inc)) {
      $norm = array_map('strtolower', $inc);

      // expand "top_countries" -> keduanya
      if (in_array('top_countries', $norm, true)) {
        $norm[] = 'top_countries_inbound';
        $norm[] = 'top_countries_outbound';
      }

      $allowed = ['summary', 'top_countries_inbound', 'top_countries_outbound'];
      $final = array_values(array_unique(array_intersect($norm, $allowed)));

      $this->merge(['include' => $final]);
    }
  }

  public function sanitizedFilters(): array
  {
    $f = $this->input('filters', []);
    $countryIn = $f['country'] ?? $this->input('country');

    $year   = $f['year']   ?? $this->input('year');
    $limit  = $f['limit']  ?? $this->input('limit');
    $source = $f['source'] ?? $this->input('source');

    return [
      'country' => $this->normalizeCountry($countryIn),
      'year'    => isset($year) ? (int) $year : null,
      'limit'   => isset($limit) ? (int) $limit : null,
      'source'  => $source,
    ];
  }

  public function sanitizedInclude(): array
  {
    return $this->input('include', [
      'summary',
      'top_countries_inbound',
      'top_countries_outbound',
    ]);
  }

  private function normalizeCountry($val): ?string
  {
    if ($val === null) return null;
    if (is_string($val)) {
      $s = trim($val);
      if ($s === '' || strtolower($s) === 'all') return null;
      return strtoupper($s);
    }
    return null;
  }
}
