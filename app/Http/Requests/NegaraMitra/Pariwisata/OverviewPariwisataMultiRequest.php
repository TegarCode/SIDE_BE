<?php

namespace App\Http\Requests\NegaraMitra\Pariwisata;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class OverviewPariwisataMultiRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  // Longgarkan tipe: boleh array ATAU string "all"
  public function rules(): array
  {
    return [
      // nested
      'filters'        => ['array'],
      'filters.origin' => ['nullable'], // <- boleh array/string
      'filters.dest'   => ['nullable'],
      'filters.year'   => ['nullable', 'integer', 'min:1900', 'max:2100'],

      // flat (opsional)
      'origin' => ['nullable'], // <- boleh array/string
      'dest'   => ['nullable'],
      'year'   => ['nullable', 'integer', 'min:1900', 'max:2100'],

      'include'   => ['nullable', 'array'],
      'include.*' => ['string', 'in:timeseries'],
    ];
  }

  public function sanitizedFilters(): array
  {
    $f = $this->input('filters', []);
    $originIn = $f['origin'] ?? $this->input('origin');
    $destIn   = $f['dest']   ?? $this->input('dest');

    $origin = $this->normalizeOD($originIn);
    $dest   = $this->normalizeOD($destIn);

    $year  = $f['year']  ?? $this->input('year');
    $limit = $f['limit'] ?? $this->input('limit');

    $out = [
      'origin' => $origin,
      'dest'   => $dest,
      'year'   => isset($year)  ? (int)$year  : null,
      'limit'  => isset($limit) ? (int)$limit : 20,
    ];

    return $out;
  }


  public function sanitizedInclude(): array
  {
    return $this->input('include', [
      'timeseries',
    ]);
  }

  private function normalizeOD($val): array|null
  {
    if ($val === null) return null;

    // Jika frontend kirim "all" (string) → anggap tanpa filter
    if (is_string($val) && strtolower(trim($val)) === 'all') {
      return null;
    }

    // contoh: "['IDN']" atau '["IDN","USA"]' atau 'IDN, USA'
    if (is_string($val)) {
      $s = trim($val);

      // Coba decode JSON terlebih dulu: ["IDN","USA"] atau [{"value":"IDN"}]
      $decoded = null;
      if ((str_starts_with($s, '[') && str_ends_with($s, ']')) ||
        (str_starts_with($s, '{') && str_ends_with($s, '}'))
      ) {
        $decoded = json_decode($s, true);
      }
      if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
        $val = $decoded; // lanjut ke blok array di bawah
      } else {
        // fallback: perlakukan sebagai CSV (hapus bracket & quote dulu)
        $clean = preg_replace(['/^\[|\]$/', '/[\'"]/'], ['', ''], $s);
        $parts = preg_split('/[,\s]+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
        $parts = array_map(fn($x) => strtoupper(trim($x)), $parts);
        $parts = array_values(array_unique($parts));
        return count($parts) ? $parts : null;
      }
    }

    // Jika array: boleh ["IDN","USA"] atau [{value:"IDN"}]
    if (is_array($val)) {
      $arr = [];
      foreach ($val as $item) {
        if (is_string($item) && $item !== '') {
          $arr[] = strtoupper(trim($item));
        } elseif (is_array($item)) {
          // dukung banyak variasi key
          $code = $item['value'] ?? $item['code'] ?? $item['alpha3'] ?? null;
          if (is_string($code) && $code !== '') {
            $arr[] = strtoupper(trim($code));
          }
        }
      }
      $arr = array_values(array_unique(array_filter($arr, fn($x) => $x !== '')));
      return count($arr) ? $arr : null;
    }

    // tipe lain → kosong
    return null;
  }
}
