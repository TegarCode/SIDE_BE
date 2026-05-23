<?php

namespace App\Http\Requests\NegaraMitra;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class OverviewPerdaganganRequest extends FormRequest
{
  public function authorize(): bool { return true; }

  public function rules(): array
  {
    return [
      'filters'         => ['array'],
      'filters.origin'  => ['nullable'],
      'filters.dest'    => ['nullable'],
      'filters.year'    => ['nullable','integer','min:1900','max:2100'],
      'filters.limit'   => ['nullable','integer','min:1','max:1000'],
      'filters.hsCode'  => ['nullable'],
      'filters.hs'      => ['nullable'],

      'origin' => ['nullable'],
      'dest'   => ['nullable'],
      'year'   => ['nullable','integer','min:1900','max:2100'],
      'limit'  => ['nullable','integer','min:1','max:1000'],
      'hsCode' => ['nullable'],
      'hs'     => ['nullable'],

      'include'   => ['nullable','array'],
      'include.*' => ['string','in:summary,timeseries,top_products_export,top_products_import'],
    ];
  }

  public function sanitizedFilters(): array
  {
    $f        = $this->input('filters', []);
    $originIn = $f['origin'] ?? $this->input('origin');
    $destIn   = $f['dest']   ?? $this->input('dest');
    $year     = $f['year']   ?? $this->input('year');
    $limit    = $f['limit']  ?? $this->input('limit');
    $hsIn     = $f['hsCode'] ?? $f['hs'] ?? $this->input('hsCode') ?? $this->input('hs');

    $origin = $this->normalizeOD($originIn);
    $dest   = $this->normalizeOD($destIn);
    $hs     = $this->normalizeHs($hsIn);

    $out = [
      'origin' => $origin,
      'dest'   => $dest,
      'year'   => isset($year)  ? (int)$year  : null,
      'limit'  => isset($limit) ? (int)$limit : 20,
    ];
    if ($hs !== null) $out['hsCode'] = $hs;

    return $out;
  }

  public function sanitizedInclude(): array
  {
    return $this->input('include', [
      'summary','timeseries','top_products_export','top_products_import'
    ]);
  }

  private function normalizeOD($val): ?array
  {
    if ($val === null) return null;
    if (is_string($val) && strtolower(trim($val)) === 'all') return null;

    if (is_string($val)) {
      $s = trim($val);
      $decoded = null;
      if ((str_starts_with($s,'[') && str_ends_with($s,']')) ||
          (str_starts_with($s,'{') && str_ends_with($s,'}'))) {
        $decoded = json_decode($s, true);
      }
      if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
        $val = $decoded;
      } else {
        $clean = preg_replace(['/^\[|\]$/','/[\'"]/'], ['',''], $s);
        $parts = preg_split('/[,\s]+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
        $parts = array_map(fn($x)=>strtoupper(trim($x)), $parts);
        $parts = array_values(array_unique($parts));
        return count($parts) ? $parts : null;
      }
    }

    if (is_array($val)) {
      $arr = [];
      foreach ($val as $item) {
        if (is_string($item) && $item !== '') $arr[] = strtoupper(trim($item));
        elseif (is_array($item)) {
          $code = $item['value'] ?? $item['code'] ?? $item['alpha3'] ?? null;
          if (is_string($code) && $code !== '') $arr[] = strtoupper(trim($code));
        }
      }
      $arr = array_values(array_unique(array_filter($arr, fn($x)=>$x!=='')));
      return count($arr) ? $arr : null;
    }
    return null;
  }

  private function normalizeHs($val): array|string|null
  {
    if ($val === null) return null;

    if (is_string($val)) {
      $s = trim($val);
      if (strtoupper($s) === 'ALL') return 'ALL';

      if ((str_starts_with($s,'[') && str_ends_with($s,']')) ||
          (str_starts_with($s,'{') && str_ends_with($s,'}'))) {
        $decoded = json_decode($s, true);
        if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
          $val = $decoded;
        } else {
          $clean = preg_replace(['/^\[|\]$/','/[\'"]/'], ['',''], $s);
          $parts = preg_split('/[,\s]+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
          $parts = array_map(fn($x)=>strtoupper(trim($x)), $parts);
          $parts = array_values(array_unique($parts));
          return count($parts) ? $parts : null;
        }
      } else {
        return [strtoupper($s)];
      }
    }

    if (is_array($val)) {
      $arr = [];
      foreach ($val as $item) {
        if (is_string($item) && $item !== '') {
          $arr[] = strtoupper(trim($item));
        } elseif (is_array($item)) {
          $code = $item['value'] ?? $item['hs_code'] ?? $item['code'] ?? $item['kode_hs'] ?? $item['id'] ?? null;
          if (is_string($code) && $code !== '') $arr[] = strtoupper(trim($code));
        }
      }
      $arr = array_values(array_unique(array_filter($arr, fn($x)=>$x!=='')));
      return count($arr) ? $arr : null;
    }

    return null;
  }
}
