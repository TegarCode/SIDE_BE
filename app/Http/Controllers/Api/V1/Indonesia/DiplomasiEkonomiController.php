<?php

namespace App\Http\Controllers\Api\V1\Indonesia;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Support\SideCacheKey;
use App\Services\Indonesia\EconomyDiplomationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DiplomasiEkonomiController extends Controller
{
  public function __construct(protected EconomyDiplomationService $economyDiplomationService) {}

  private const DEFAULT_SOURCES = [
    'perdagangan' => 1,
    'pariwisata'  => 1,
    'investasi'   => 6,
    'bantuan'     => 21,
  ];

  /** TTL cache 3 hari */
  private function cacheTtl3Days(): \DateTimeInterface
  {
    return now()->addDays(3);
  }

  private function buildSectorCacheKey(string $prefix, array $filters, ?int $sourceCode): string
  {
    $status = $filters['status'] ?? 'all';
    $ys = $filters['year_start'] ?? 'null';
    $ye = $filters['year_end'] ?? 'null';
    $hs = $filters['hs'] ?? 'all';

    $dirjen = $filters['dirjen'] ?? [];
    if (is_string($dirjen)) {
      $dirjen = array_map('trim', explode(',', $dirjen));
    }
    if (is_array($dirjen)) {
      sort($dirjen, SORT_STRING);
    } else {
      $dirjen = [];
    }
    return SideCacheKey::pairs(
      ['indonesia', 'diplomasi-ekonomi', $prefix],
      [
        'status' => $status,
        'tahun' => "{$ys}-{$ye}",
        'hs' => $hs,
        'src' => $sourceCode ?? 'all',
        'dirjen' => $dirjen ?: 'all',
      ]
    );
  }

  public function stats(Request $request): JsonResponse
  {
    $stopProfile = $this->startProfiling('stats');
    try {
      $filters = $this->normalizeFilters($request);
      $filters['strict_source_years'] = true;
      $sources = $this->normalizeSources($request);
      $statSources = $this->normalizeStatSources($sources);

      // Key utama berdasarkan rentang tahun/hs/dirjen/status
      $cacheKey = $this->buildStatsCacheKey($filters, $statSources);

      $fetch = function () use ($filters, $statSources) {
        return $this->economyDiplomationService->getComputeStats($filters, $statSources);
      };

      // Simpan/ambil dari cache 3 hari (per kombinasi filter)
      $stats = Cache::remember($cacheKey, $this->cacheTtl3Days(), $fetch);

      // Optional warming: cache tiap antar-tahun di dalam rentang (juga 1 bulan)
      if (isset($filters['year_start'], $filters['year_end']) && is_numeric($filters['year_start']) && is_numeric($filters['year_end'])) {
        $ys = (int) $filters['year_start'];
        $ye = (int) $filters['year_end'];
        if ($ys <= $ye) {
          for ($y = $ys; $y < $ye; $y++) {
            $pairFilters = $filters;
            $pairFilters['year_start'] = $y;
            $pairFilters['year_end']   = $y + 1;

            $pairKey = $this->buildStatsCacheKey($pairFilters, $statSources);

            Cache::remember($pairKey, $this->cacheTtl3Days(), function () use ($pairFilters, $statSources) {
              return $this->economyDiplomationService->getComputeStats($pairFilters, $statSources);
            });
          }
        }
      }

      if (empty($stats)) {
        return ApiResponse::success(
          [],
          'Tidak ada data.',
          ['filters' => $filters]
        );
      }

      $repoMeta = $stats['meta'] ?? [];
      $payload  = $stats;
      unset($payload['meta']);

      if (isset($repoMeta['applied_filters'])) {
        unset($repoMeta['applied_filters']);
      }
      $meta = array_merge($repoMeta, [
        'filters' => $filters,
        'sources' => $sources,
      ]);

      return ApiResponse::success(
        $payload,
        'Stats berhasil diambil',
        $meta
      );
    } catch (\Throwable $e) {
      $errors = app()->environment('local') ? ['exception' => $e->getMessage()] : null;
      return ApiResponse::error('Terjadi kesalahan saat mengambil stats.', $errors, 500);
    } finally {
      if (isset($stopProfile)) $stopProfile();
    }
  }

  public function nilaiPerdagangan(Request $request): JsonResponse
  {
    $stopProfile = $this->startProfiling('nilai_perdagangan');
    try {
      $filters = $this->normalizeFilters($request);
      $filters['strict_source_years'] = true;
      $sources = $this->normalizeSources($request);
      $sourceCode = $this->sourceForSector($sources, 'perdagangan');

      $fetch = fn() => $this->economyDiplomationService->getNilaiPerdagangan($filters, $sourceCode);
      $cacheKey = $this->buildSectorCacheKey('nilai-perdagangan', $filters, $sourceCode);
      $data = Cache::remember($cacheKey, $this->cacheTtl3Days(), $fetch);

      if (empty($data)) {
        return ApiResponse::success(
          [],
          'Tidak ada data.',
          ['filters' => $filters]
        );
      }

      $repoMeta = $data['meta'] ?? [];
      $payload  = $data;
      unset($payload['meta']);

      // Nilai perdagangan: hapus field kompetitor/top tujuan
      if (!empty($payload['top_produk']) && is_array($payload['top_produk'])) {
        foreach ($payload['top_produk'] as &$row) {
          if (!is_array($row)) continue;
          unset(
            $row['kompetitor_global_top_tujuan_ekspor'],
            $row['kode_alpha3_top_tujuan_ekspor'],
            $row['kode_alpha2_top_tujuan_ekspor'],
            $row['negara_top_tujuan_ekspor'],
            $row['kompetitor_global_top_tujuan_impor'],
            $row['kode_alpha3_top_tujuan_impor'],
            $row['kode_alpha2_top_tujuan_impor'],
            $row['negara_top_tujuan_impor']
          );
        }
        unset($row);
      }

      if (isset($repoMeta['applied_filters'])) {
        unset($repoMeta['applied_filters']);
      }
      $meta = array_merge($repoMeta, [
        'filters' => $filters,
        'sources' => $sources,
      ]);

      return ApiResponse::success(
        $payload,
        'Nilai perdagangan berhasil diambil',
        $meta
      );
    } finally {
      if (isset($stopProfile)) $stopProfile();
    }
  }

  public function totalEkspor(Request $request): JsonResponse
  {
    $stopProfile = $this->startProfiling('total_ekspor');
    try {
      // Ambil & normalisasi filter, lalu paksa status=export
      $filters = $this->normalizeFilters($request);
      $filters['strict_source_years'] = true;
      $sources = $this->normalizeSources($request);
      $sourceCode = $this->sourceForSector($sources, 'perdagangan');
      $filters['status'] = 'export';

      $fetch = fn() => $this->economyDiplomationService->getNilaiPerdagangan($filters, $sourceCode);
      $cacheKey = $this->buildSectorCacheKey('total-ekspor', $filters, $sourceCode);
      $data = Cache::remember($cacheKey, $this->cacheTtl3Days(), $fetch);

      if (empty($data)) {
        return ApiResponse::success(
          [],
          'Tidak ada data.',
          ['filters' => $filters]
        );
      }

      $repoMeta = $data['meta'] ?? [];
      $payload  = $data;
      unset($payload['meta']);

      // Total Ekspor: hanya tampilkan data ekspor
      if (!empty($payload['top_produk']) && is_array($payload['top_produk'])) {
        foreach ($payload['top_produk'] as &$row) {
          if (!is_array($row)) continue;
          unset(
            $row['import'],
            $row['import_reverse'],
            $row['tujuan_impor'],
            $row['kompetitor_global_top_tujuan_impor'],
            $row['kompetitor_asean_top_tujuan_impor'],
            $row['kode_alpha3_top_tujuan_impor'],
            $row['kode_alpha2_top_tujuan_impor'],
            $row['negara_top_tujuan_impor']
          );
        }
        unset($row);
      }

      if (isset($repoMeta['applied_filters'])) {
        unset($repoMeta['applied_filters']);
      }

      $meta = array_merge($repoMeta, [
        'filters' => $filters,
        'sources' => $sources,
      ]);

      return ApiResponse::success(
        $payload,
        'Total Ekspor berhasil diambil',
        $meta
      );
    } finally {
      if (isset($stopProfile)) $stopProfile();
    }
  }

  public function totalImpor(Request $request): JsonResponse
  {
    $stopProfile = $this->startProfiling('total_impor');
    try {
      // Ambil & normalisasi filter, lalu paksa status=import
      $filters = $this->normalizeFilters($request);
      $filters['strict_source_years'] = true;
      $sources = $this->normalizeSources($request);
      $sourceCode = $this->sourceForSector($sources, 'perdagangan');
      $filters['status'] = 'import';

      $fetch = fn() => $this->economyDiplomationService->getNilaiPerdagangan($filters, $sourceCode);
      $cacheKey = $this->buildSectorCacheKey('total-impor', $filters, $sourceCode);
      $data = Cache::remember($cacheKey, $this->cacheTtl3Days(), $fetch);

      if (empty($data)) {
        return ApiResponse::success(
          [],
          'Tidak ada data.',
          ['filters' => $filters]
        );
      }

      $repoMeta = $data['meta'] ?? [];
      $payload  = $data;
      unset($payload['meta']);

      // Total Impor: hanya tampilkan data impor
      if (!empty($payload['top_produk']) && is_array($payload['top_produk'])) {
        foreach ($payload['top_produk'] as &$row) {
          if (!is_array($row)) continue;
          unset(
            $row['export'],
            $row['export_reverse'],
            $row['tujuan_ekspor'],
            $row['kompetitor_global_top_tujuan_ekspor'],
            $row['kompetitor_asean_top_tujuan_ekspor'],
            $row['kode_alpha3_top_tujuan_ekspor'],
            $row['kode_alpha2_top_tujuan_ekspor'],
            $row['negara_top_tujuan_ekspor']
          );
        }
        unset($row);
      }

      if (isset($repoMeta['applied_filters'])) {
        unset($repoMeta['applied_filters']);
      }

      $meta = array_merge($repoMeta, [
        'filters' => $filters,
        'sources' => $sources,
      ]);

      return ApiResponse::success(
        $payload,
        'Total Impor berhasil diambil',
        $meta
      );
    } finally {
      if (isset($stopProfile)) $stopProfile();
    }
  }

  public function totalInboundInvestasi(Request $request): JsonResponse
  {
    $stopProfile = $this->startProfiling('total_inbound_investasi');
    try {
      $filters = $this->normalizeFilters($request);
      $filters['strict_source_years'] = true;
      $sources = $this->normalizeSources($request);
      $sourceCode = $this->sourceForSector($sources, 'investasi');
      $filters['status'] = 'INBOUND';

      $fetch = fn() => $this->economyDiplomationService->getNilaiInvestasi($filters, $sourceCode);
      $cacheKey = $this->buildSectorCacheKey('total-inbound-investasi', $filters, $sourceCode);
      $data = Cache::remember($cacheKey, $this->cacheTtl3Days(), $fetch);

      if (empty($data)) {
        return ApiResponse::success(
          [],
          'Tidak ada data.',
          ['filters' => $filters]
        );
      }

      $repoMeta = $data['meta'] ?? [];
      $payload  = $data;
      unset($payload['meta']);

      if (isset($repoMeta['applied_filters'])) {
        unset($repoMeta['applied_filters']);
      }

      $meta = array_merge($repoMeta, [
        'filters' => $filters,
        'sources' => $sources,
      ]);

      return ApiResponse::success(
        $payload,
        'Total Inbound Investasi berhasil diambil',
        $meta
      );
    } finally {
      if (isset($stopProfile)) $stopProfile();
    }
  }

  public function totalOutboundInvestasi(Request $request): JsonResponse
  {
    $stopProfile = $this->startProfiling('total_outbound_investasi');
    try {
      $filters = $this->normalizeFilters($request);
      $filters['strict_source_years'] = true;
      $sources = $this->normalizeSources($request);
      $sourceCode = $this->sourceForSector($sources, 'investasi');
      $filters['status'] = 'OUTBOUND';

      $fetch = fn() => $this->economyDiplomationService->getNilaiInvestasi($filters, $sourceCode);
      $cacheKey = $this->buildSectorCacheKey('total-outbound-investasi', $filters, $sourceCode);
      $data = Cache::remember($cacheKey, $this->cacheTtl3Days(), $fetch);

      if (empty($data)) {
        return ApiResponse::success(
          [],
          'Tidak ada data.',
          ['filters' => $filters]
        );
      }

      $repoMeta = $data['meta'] ?? [];
      $payload  = $data;
      unset($payload['meta']);

      if (isset($repoMeta['applied_filters'])) {
        unset($repoMeta['applied_filters']);
      }

      $meta = array_merge($repoMeta, [
        'filters' => $filters,
        'sources' => $sources,
      ]);

      return ApiResponse::success(
        $payload,
        'Total Outbound Investasi berhasil diambil',
        $meta
      );
    } finally {
      if (isset($stopProfile)) $stopProfile();
    }
  }

  public function totalInboundTourism(Request $request): JsonResponse
  {
    $stopProfile = $this->startProfiling('total_inbound_tourism');
    try {
      $filters = $this->normalizeFilters($request);
      $filters['strict_source_years'] = true;
      $sources = $this->normalizeSources($request);
      $sourceCode = $this->sourceForSector($sources, 'pariwisata');
      $filters['status'] = 'INBOUND';

      $fetch = fn() => $this->economyDiplomationService->getNilaiWisatawan($filters, $sourceCode);
      $cacheKey = $this->buildSectorCacheKey('total-inbound-wisatawan', $filters, $sourceCode);
      $data = Cache::remember($cacheKey, $this->cacheTtl3Days(), $fetch);

      if (empty($data)) {
        return ApiResponse::success(
          [],
          'Tidak ada data.',
          ['filters' => $filters]
        );
      }

      $repoMeta = $data['meta'] ?? [];
      $payload  = $data;
      unset($payload['meta']);

      if (isset($repoMeta['applied_filters'])) {
        unset($repoMeta['applied_filters']);
      }

      $meta = array_merge($repoMeta, [
        'filters' => $filters,
        'sources' => $sources,
      ]);

      return ApiResponse::success(
        $payload,
        'Total Wisatawan Masuk berhasil diambil',
        $meta
      );
    } finally {
      if (isset($stopProfile)) $stopProfile();
    }
  }

  public function totalBantuanKerjasama(Request $request): JsonResponse
  {
    $stopProfile = $this->startProfiling('total_bantuan_kerjasama');
    try {
      $filters = $this->normalizeFilters($request);
      $filters['strict_source_years'] = true;
      $sources = $this->normalizeSources($request);
      $sourceCode = $this->sourceForSector($sources, 'bantuan');

      $fetch = fn() => $this->economyDiplomationService->getNilaiBantuanKerjasama($filters, $sourceCode);
      $cacheKey = $this->buildSectorCacheKey('total-bantuan-kerjasama', $filters, $sourceCode);
      $data = Cache::remember($cacheKey, $this->cacheTtl3Days(), $fetch);

      if (empty($data)) {
        return ApiResponse::success(
          [],
          'Tidak ada data.',
          ['filters' => $filters]
        );
      }

      $repoMeta = $data['meta'] ?? [];
      $payload  = $data;
      unset($payload['meta']);

      if (isset($repoMeta['applied_filters'])) {
        unset($repoMeta['applied_filters']);
      }

      $meta = array_merge($repoMeta, [
        'filters' => $filters,
        'sources' => $sources,
      ]);

      return ApiResponse::success(
        $payload,
        'Total Bantuan Kerjasama berhasil diambil',
        $meta
      );
    } finally {
      if (isset($stopProfile)) $stopProfile();
    }
  }

  /* =======================value: = Helpers ======================== */

  private function normalizeFilters(Request $request): array
  {
    $ys = $request->input('year_start');
    $ye = $request->input('year_end');
    $hs = $request->input('hs');

    $missing = [];
    if ($ys === null || $ys === '') $missing[] = 'year_start';
    if ($ye === null || $ye === '') $missing[] = 'year_end';
    if ($hs === null || $hs === '') $missing[] = 'hs';
    if (!empty($missing)) {
      throw new HttpResponseException(
        ApiResponse::error('Filter wajib diisi.', ['missing' => $missing], 400)
      );
    }

    $yearStart = is_numeric($ys) ? (int) $ys : null;
    $yearEnd   = is_numeric($ye) ? (int) $ye : null;
    $hs        = is_numeric($hs) ? (int) $hs : null;

    $invalid = [];
    if ($yearStart === null) $invalid[] = 'year_start';
    if ($yearEnd === null) $invalid[] = 'year_end';
    if ($hs === null) $invalid[] = 'hs';
    if (!empty($invalid)) {
      throw new HttpResponseException(
        ApiResponse::error('Filter tidak valid.', ['invalid' => $invalid], 400)
      );
    }

    // Dirjen: array atau csv → uppercase unik + di-sort
    $djIn = $request->input('dirjen', []);
    if ($djIn === null || $djIn === '' || (is_array($djIn) && count($djIn) === 0)) {
      throw new HttpResponseException(
        ApiResponse::error('Filter wajib diisi.', ['missing' => ['dirjen']], 400)
      );
    }
    if (is_string($djIn))      $dirjen = array_map('trim', explode(',', $djIn));
    elseif (is_array($djIn))   $dirjen = $djIn;
    else                       $dirjen = [];

    $dirjen = array_values(array_unique(array_map(fn($v) => strtoupper((string) $v), $dirjen)));
    $dirjen = array_values(array_filter($dirjen, fn($v) => $v !== ''));
    if (count($dirjen) === 0) {
      throw new HttpResponseException(
        ApiResponse::error('Filter wajib diisi.', ['missing' => ['dirjen']], 400)
      );
    }
    sort($dirjen, SORT_STRING);

    $filters = [
      'year_start' => $yearStart,
      'year_end'   => $yearEnd,
      'hs'         => $hs,
      'dirjen'     => $dirjen,
    ];

    return array_filter($filters, function ($v) {
      if (is_array($v)) return count($v) > 0;
      return !is_null($v) && $v !== '';
    });
  }

  private function buildStatsCacheKey(array $filters, array $sources = []): string
  {
    $status = $filters['status'] ?? 'inbound';
    $ys = $filters['year_start'] ?? 'null';
    $ye = $filters['year_end'] ?? 'null';
    $hs = $filters['hs'] ?? 'all';

    $dirjen = $filters['dirjen'] ?? [];
    if (is_string($dirjen)) {
      $dirjen = array_map('trim', explode(',', $dirjen));
    }
    if (is_array($dirjen)) {
      sort($dirjen, SORT_STRING);
    } else {
      $dirjen = [];
    }
    return SideCacheKey::pairs(
      ['indonesia', 'diplomasi-ekonomi', 'stats'],
      [
        'status' => $status,
        'tahun' => "{$ys}-{$ye}",
        'hs' => $hs,
        'dirjen' => $dirjen ?: 'all',
        'sources' => $sources ?: 'default',
      ]
    );
  }

  private function normalizeSources(Request $request): array
  {
    $raw = $request->input('sumber', []);
    if (is_string($raw)) {
      $decoded = json_decode($raw, true);
      if (json_last_error() === JSON_ERROR_NONE) {
        $raw = $decoded;
      }
    }

    if (!is_array($raw)) {
      return self::DEFAULT_SOURCES;
    }

    $sources = [];
    $isAssoc = array_keys($raw) !== range(0, count($raw) - 1);

    if ($isAssoc) {
      foreach ($raw as $k => $v) {
        $sector = $this->normalizeSectorKey($k);
        $code = $this->normalizeSourceCode($v);
        if ($sector && $code !== null) {
          $sources[$sector] = $code;
        }
      }
      return !empty($sources) ? $sources : self::DEFAULT_SOURCES;
    }

    foreach ($raw as $row) {
      if (!is_array($row)) {
        continue;
      }
      $sector = $this->normalizeSectorKey($row['sektor'] ?? $row['sector'] ?? $row['type'] ?? null);
      $code = $this->normalizeSourceCode($row['sumber'] ?? $row['kode_sumber'] ?? $row['kodeSumber'] ?? null);
      if ($sector && $code !== null) {
        $sources[$sector] = $code;
      }
    }

    return !empty($sources) ? $sources : self::DEFAULT_SOURCES;
  }

  private function normalizeStatSources(array $sources): array
  {
    $out = [];
    if (isset($sources['perdagangan'])) {
      $code = $sources['perdagangan'];
      $out['trade_total'] = $code;
      $out['trade_balance'] = $code;
      $out['export'] = $code;
      $out['import'] = $code;
      $out['top_partner'] = $code;
    }
    if (isset($sources['pariwisata'])) {
      $out['tourism'] = $sources['pariwisata'];
    }
    if (isset($sources['investasi'])) {
      $out['fdi_in'] = $sources['investasi'];
      $out['fdi_out'] = $sources['investasi'];
    }
    if (isset($sources['bantuan'])) {
      $out['aid'] = $sources['bantuan'];
    }
    return $out;
  }

  private function normalizeSectorKey($raw): ?string
  {
    if ($raw === null) {
      return null;
    }
    $key = strtolower(trim((string)$raw));
    if ($key === '') {
      return null;
    }

    return match ($key) {
      'perdagangan', 'trade' => 'perdagangan',
      'investasi', 'investment', 'fdi' => 'investasi',
      'pariwisata', 'tourism', 'wisata' => 'pariwisata',
      'bantuan', 'hibah', 'aid', 'kerjasama' => 'bantuan',
      default => null,
    };
  }

  private function normalizeSourceCode($value): ?int
  {
    if (is_numeric($value)) {
      return (int)$value;
    }
    if (is_string($value)) {
      $v = trim($value);
      if ($v === '') {
        return null;
      }
      if (ctype_digit($v)) {
        return (int)$v;
      }
    }
    return null;
  }

  private function sourceForSector(array $sources, string $sector): ?int
  {
    return $sources[$sector] ?? null;
  }

  private function startProfiling(string $label): \Closure
  {
    return function () {
    };
  }

}
