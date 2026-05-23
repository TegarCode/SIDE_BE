<?php

namespace App\Http\Requests\NegaraMitra\Pariwisata;

use Illuminate\Foundation\Http\FormRequest;

class OverviewPariwisataSingleRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'filters' => ['array'],

      // Single "home" country (default IDN)
      'filters.country' => ['nullable', 'string', 'size:3'],
      'country'         => ['nullable', 'string', 'size:3'],

      'filters.year'  => ['nullable', 'integer', 'min:1900', 'max:2100'],
      'year'          => ['nullable', 'integer', 'min:1900', 'max:2100'],

      'filters.limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
      'limit'         => ['nullable', 'integer', 'min:1', 'max:1000'],

      // Kode_Sumber: integer atau array integer, default 5
      'filters.source' => ['nullable'], // int|array<int>
      'source'         => ['nullable'],

      'include'   => ['nullable', 'array'],
      'include.*' => ['string', 'in:summary,table_inbound,table_outbound'],
    ];
  }

  public function sanitizedFilters(): array
  {
    $f = $this->input('filters', []);

    $country = $f['country'] ?? $this->input('country') ?? 'IDN';
    $year    = $f['year']    ?? $this->input('year');
    $source  = $f['source']  ?? $this->input('source', 1);

    // upper-case ISO alpha-3
    $country = is_string($country) ? strtoupper(trim($country)) : 'IDN';

    // source: bisa int tunggal atau array int
    $source = is_array($source)
      ? array_values(array_filter(array_map(fn($v) => (int)$v, $source)))
      : (int)$source;

    return [
      'country' => $country,
      'year'    => isset($year) ? (int)$year : null,
      'source'  => $source, // int|int[]
    ];
  }

  public function sanitizedInclude(): array
  {
    // default: cards + tabel inbound
    return $this->input('include', [
      'summary',
      'table_inbound',
      'table_outbound',
    ]);
  }
}
