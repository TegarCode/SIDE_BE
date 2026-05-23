<?php

namespace App\Http\Requests\NegaraMitra;

use Illuminate\Foundation\Http\FormRequest;

class OverviewJasaRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'filters'        => ['array'],
      'filters.origin' => ['nullable'],
      'filters.dest'   => ['nullable'],
      'filters.year'   => ['nullable', 'integer', 'min:1900', 'max:2100'],
      'filters.year_from' => ['nullable', 'integer', 'min:1900', 'max:2100'],
      'filters.year_to'   => ['nullable', 'integer', 'min:1900', 'max:2100'],
      'filters.limit'  => ['nullable', 'integer', 'min:1', 'max:1000'],
      'filters.source' => ['nullable'],   // "all" | int | int[]

      // flat (opsional)
      'origin' => ['nullable'],
      'dest'   => ['nullable'],
      'year'   => ['nullable', 'integer', 'min:1900', 'max:2100'],
      'limit'  => ['nullable', 'integer', 'min:1', 'max:1000'],
      'source' => ['nullable'],

      'include'   => ['nullable', 'array'],
      'include.*' => ['string', 'in:summary,timeseries,top_services_inbound,top_services_outbound'],
    ];
  }

  /** Normalisasi input sebelum validasi */
  protected function prepareForValidation(): void
  {
    $inc = $this->input('include', []);
    if (is_array($inc)) {
      // lower, map legacy → baru, expand "top_services"
      $norm = array_map('strtolower', $inc);
      $mapped = array_map(function ($k) {
        return match ($k) {
          'top_services_export' => 'top_services_inbound',   // export → inbound (tujuan = country)
          'top_services_import' => 'top_services_outbound',  // import → outbound (asal = country)
          default               => $k,
        };
      }, $norm);

      // expand "top_services" → keduanya
      if (in_array('top_services', $mapped, true)) {
        $mapped[] = 'top_services_inbound';
        $mapped[] = 'top_services_outbound';
      }

      // filter hanya yang diizinkan & unik
      $allowed = ['summary', 'timeseries', 'top_services_inbound', 'top_services_outbound'];
      $final = array_values(array_unique(array_intersect($mapped, $allowed)));

      $this->merge(['include' => $final]);
    }
  }

  public function sanitizedFilters(): array
  {
    $f = $this->input('filters', []);
    $originIn = $f['origin'] ?? $this->input('origin');
    $destIn   = $f['dest']   ?? $this->input('dest');

    $origin = $this->normalizeOD($originIn);
    $dest   = $this->normalizeOD($destIn);

    $year      = $f['year']       ?? $this->input('year');
    $yearFrom  = $f['year_from']  ?? $this->input('year_from');
    $yearTo    = $f['year_to']    ?? $this->input('year_to');
    $limit     = $f['limit']      ?? $this->input('limit');
    $source    = $f['source']     ?? $this->input('source');

    return [
      'origin'    => $origin,
      'dest'      => $dest,
      'year'      => isset($year) ? (int)$year : null,
      'year_from' => isset($yearFrom) ? (int)$yearFrom : null,
      'year_to'   => isset($yearTo) ? (int)$yearTo : null,
      'limit'     => isset($limit) ? (int)$limit : null,
      'source'    => $source, // biarkan apa adanya (null|"all"|int|int[])
    ];
  }

  public function sanitizedInclude(): array
  {
    // default jika kosong
    return $this->input('include', [
      'summary',
      'timeseries',
      'top_services_inbound',
      'top_services_outbound',
    ]);
  }

  private function normalizeOD($val): ?array
  {
    if ($val === null) return null;
    if (is_string($val)) {
      $s = trim($val);
      if (strtolower($s) === 'all' || $s === '') return null;
      return [strtoupper($s)];
    }
    if (is_array($val)) {
      $arr = [];
      foreach ($val as $item) {
        if (is_string($item) && $item !== '') {
          $arr[] = strtoupper(trim($item));
        } elseif (is_array($item)) {
          $code = $item['value'] ?? $item['code'] ?? $item['alpha3'] ?? null;
          if (is_string($code) && $code !== '') $arr[] = strtoupper(trim($code));
        }
      }
      $arr = array_values(array_unique(array_filter($arr)));
      return $arr ?: null;
    }
    return null;
  }
}
