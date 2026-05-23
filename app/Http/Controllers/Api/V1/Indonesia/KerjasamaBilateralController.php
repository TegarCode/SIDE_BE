<?php

namespace App\Http\Controllers\Api\V1\Indonesia;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Repositories\Indonesia\EconomyDiplomation\InsightKompetitorPerdaganganRepositoryInterface;
use App\Support\SideCacheKey;
use App\Services\Indonesia\EconomyDiplomationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class KerjasamaBilateralController extends Controller
{
  public function __construct(
    protected EconomyDiplomationService $economyDiplomationService,
    protected InsightKompetitorPerdaganganRepositoryInterface $insightKompetitorRepository
  ) {}

  private const DEFAULT_SOURCES = [
    'perdagangan' => 1,
    'pariwisata' => 1,
    'investasi' => 6,
    'bantuan' => 21,
    'jasa' => 136,
  ];

  public function nilaiPerdagangan(Request $request): JsonResponse
  {
    if ($validation = $this->validateNilaiPerdaganganFilters($request)) {
      return $validation;
    }

    $filters = $this->normalizeFilters($request);
    if (!array_key_exists('hs', $filters) || is_null($filters['hs'])) {
      $filters['hs'] = 4;
    }

    $filters = $this->applyTradeReverseFilters($filters, $request);

    $sources = $this->normalizeSources($request);
    $sourceCode = $this->sourceForSector($sources, 'perdagangan');
    $filters = $this->applyDefaultYearRange($filters, 'tbtrade', $sourceCode, 'Kode_Sumber');
      $cacheKey = $this->buildCacheKey('nilai-perdagangan-negara', array_merge($filters, [
        'sources' => $sources,
      ]));
      $legacyKey = $this->buildCacheKeyFromRequest('nilai-perdagangan-negara', $request);
      $ttl      = $this->cacheTtl3Days();

      $cacheHit = Cache::has($cacheKey);
      $legacyHit = $legacyKey !== $cacheKey && Cache::has($legacyKey);
      if ($cacheHit) {
        $data = Cache::get($cacheKey);
      } elseif ($legacyHit) {
        $data = Cache::get($legacyKey);
        Cache::put($cacheKey, $data, $ttl);
      } else {
        $data = Cache::remember(
          $cacheKey,
          $ttl,
          fn() => $this->economyDiplomationService->getNilaiPerdagangan($filters, $sourceCode)
        );
      }

    if (empty($data)) {
      return ApiResponse::success([], 'Tidak ada data.', ['filters' => $filters]);
    }

    $data = $this->ensureNilaiPerdaganganCardsInData($data, $filters, $sourceCode);
    Cache::put($cacheKey, $data, $ttl);

    [$payload, $meta] = $this->splitPayloadAndMeta($data);
    $meta = array_merge($meta, ['filters' => $filters, 'sources' => $sources]);

    return ApiResponse::success($payload, 'Nilai perdagangan berhasil diambil', $meta);
  }

  public function insightKompetitor(Request $request): JsonResponse 
  {
    $rawHs = (string) $request->input('hsCode', $request->input('hs_code', ''));
    $hsCode = preg_replace('/\D+/', '', $rawHs);
    $negaraInput = strtoupper(trim((string) $request->input('negara', '')));
    $reporter = $negaraInput !== '' ? $negaraInput : 'IDN';
    $negara = $reporter;

    $errors = [];
    if (strlen($hsCode) !== 4) {
      $errors['hsCode'] = ['hsCode wajib 4 digit'];
    }
    if ($negaraInput !== '' && strlen($negaraInput) !== 3) {
      $errors['negara'] = ['negara harus kode alpha3 (3 huruf)'];
    }
    if (!empty($errors)) {
      return ApiResponse::validation($errors);
    }

    $filters = $this->normalizeFilters($request);
    $filters['hs'] = 4;
    $filters['hscodes'] = [$hsCode];
    $filters['reporter'] = $reporter;

    $year = $request->input('year', $request->input('tahun'));
    if (is_numeric($year)) {
      $filters['year_start'] = (int) $year;
      $filters['year_end'] = (int) $year;
    }

    $sources = $this->normalizeSources($request);
    $sourceCode = $this->requestedSourceForSector($request, 'perdagangan') ?? 5;
    $sources['perdagangan'] = $sourceCode;
    $filters = $this->applyDefaultYearRange($filters, 'tbtrade', $sourceCode, 'Kode_Sumber');
    if (!is_numeric($year) && !empty($filters['year_end'])) {
      $filters['year_start'] = (int) $filters['year_end'];
    }
    $activeYear = isset($filters['year_end']) ? (int) $filters['year_end'] : null;
    $ttl = $this->cacheTtl3Days();

    $cacheKey = $this->buildCacheKey('insight-kompetitor-perdagangan', array_merge($filters, [
      'sources' => $sources,
      'source_code' => $sourceCode,
      'year' => $activeYear,
    ]));
    $data = Cache::remember(
      $cacheKey,
      $ttl,
      fn() => $this->economyDiplomationService->getNilaiPerdagangan($filters, $sourceCode)
    );

    if (empty($data)) {
      return ApiResponse::success([], 'Tidak ada data.', [
        'hsCode' => $hsCode,
        'negara' => $negara,
        'year' => $activeYear,
      ]);
    }

    $insight = $this->insightKompetitorRepository->buildInsightResponse($data, $hsCode, $negara, $filters);
    $payload = $insight['data'] ?? [];
    $meta = $insight['meta'] ?? [];
    if (empty($payload)) {
      return ApiResponse::success([], 'Tidak ada data.', $meta);
    }
    return ApiResponse::success($payload, 'Insight kompetitor berhasil diambil', $meta);
  }

  public function nilaiPariwisata(Request $request): JsonResponse
  {
    $baseFilters     = $this->normalizeFilters($request);
    $sources         = $this->normalizeSources($request);
    $sourceCode      = $this->sourceForSector($sources, 'pariwisata');
    $baseFilters     = $this->applyDefaultYearRange($baseFilters, 'tbtourism', $sourceCode, 'Kode_Sumber');
    $requestedStatus = $this->canonStatus($request->input('status'));
    $ttl             = $this->cacheTtl3Days();

    $in = $inMeta = $out = $outMeta = null;

    if ($requestedStatus === null || $requestedStatus === 'inbound') {
      [$in, $inMeta] = $this->fetchDirectional(
        cachePrefix: 'nilai-pariwisata-negara',
        baseFilters: $baseFilters,
        status: 'inbound',
        ttl: $ttl,
        fetcher: fn($f) => $this->economyDiplomationService->getNilaiWisatawan($f, $sourceCode),
        extraCache: ['source_code' => $sourceCode],
        extraMeta: ['sources' => $sources]
      );
    }

    if ($requestedStatus === null || $requestedStatus === 'outbound') {
      [$out, $outMeta] = $this->fetchDirectional(
        cachePrefix: 'nilai-pariwisata-negara',
        baseFilters: $baseFilters,
        status: 'outbound',
        ttl: $ttl,
        fetcher: fn($f) => $this->economyDiplomationService->getNilaiWisatawan($f, $sourceCode),
        extraCache: ['source_code' => $sourceCode],
        extraMeta: ['sources' => $sources],
        trenAliasFrom: 'tren_wisatawan_masuk',
        trenAliasTo: 'tren_wisatawan_keluar'
      );
    }

    if ($requestedStatus === 'inbound') {
      if ($in) {
        $inMeta['filters']['partners_name'] = $this->mapPartnerNames($in, $inMeta);
        return ApiResponse::success($in, 'Nilai pariwisata (inbound) berhasil diambil', $inMeta);
      }
      return ApiResponse::success([], 'Tidak ada data.', ['filters' => array_merge($baseFilters, ['status' => 'inbound'])]);
    }
    if ($requestedStatus === 'outbound') {
      if ($out) {
        $outMeta['filters']['partners_name'] = $this->mapPartnerNames($out, $outMeta);
        return ApiResponse::success($out, 'Nilai pariwisata (outbound) berhasil diambil', $outMeta);
      }
      return ApiResponse::success([], 'Tidak ada data.', ['filters' => array_merge($baseFilters, ['status' => 'outbound'])]);
    }

    if (!$in && !$out) {
      return ApiResponse::success(['inbound' => [], 'outbound' => []], 'Tidak ada data.', ['inbound' => $inMeta, 'outbound' => $outMeta]);
    }
    if ($inMeta) {
      $inMeta['filters']['partners_name'] = $this->mapPartnerNames($in ?? [], $inMeta);
    }
    if ($outMeta) {
      $outMeta['filters']['partners_name'] = $this->mapPartnerNames($out ?? [], $outMeta);
    }
    return ApiResponse::success(['inbound' => $in ?? [], 'outbound' => $out ?? []], 'Nilai pariwisata (inbound & outbound) berhasil diambil', ['inbound' => $inMeta, 'outbound' => $outMeta]);
  }

  public function nilaiInvestasi(Request $request): JsonResponse
  {
    $baseFilters     = $this->normalizeFilters($request);
    $sources         = $this->normalizeSources($request);
    $sourceCode      = $this->sourceForSector($sources, 'investasi');
    $baseFilters     = $this->applyDefaultYearRange($baseFilters, 'tbinvestment', $sourceCode, 'Kode_Sumber');
    $requestedStatus = $this->canonStatus($request->input('status'));
    $ttl             = $this->cacheTtl3Days();

    $in = $inMeta = $out = $outMeta = null;

    if ($requestedStatus === null || $requestedStatus === 'inbound') {
      [$in, $inMeta] = $this->fetchDirectional(
        cachePrefix: 'nilai-investasi',
        baseFilters: $baseFilters,
        status: 'inbound',
        ttl: $ttl,
        fetcher: fn($f) => $this->economyDiplomationService->getNilaiInvestasi($f, $sourceCode),
        extraCache: ['source_code' => $sourceCode],
        extraMeta: ['sources' => $sources]
      );
    }

    if ($requestedStatus === null || $requestedStatus === 'outbound') {
      [$out, $outMeta] = $this->fetchDirectional(
        cachePrefix: 'nilai-investasi',
        baseFilters: $baseFilters,
        status: 'outbound',
        ttl: $ttl,
        fetcher: fn($f) => $this->economyDiplomationService->getNilaiInvestasi($f, $sourceCode),
        extraCache: ['source_code' => $sourceCode],
        extraMeta: ['sources' => $sources],
        trenAliasFrom: 'tren_investasi_masuk',
        trenAliasTo: 'tren_investasi_keluar'
      );
    }

    if ($requestedStatus === 'inbound') {
      if ($in) {
        $this->filterYearsWithData($in, $inMeta);
        $inMeta['filters']['partners_name'] = $this->mapPartnerNames($in, $inMeta);
        return ApiResponse::success($in, 'Nilai investasi (inbound) berhasil diambil', $inMeta);
      }
      return ApiResponse::success([], 'Tidak ada data.', ['filters' => array_merge($baseFilters, ['status' => 'inbound'])]);
    }
    if ($requestedStatus === 'outbound') {
      if ($out) {
        $this->filterYearsWithData($out, $outMeta);
        $outMeta['filters']['partners_name'] = $this->mapPartnerNames($out, $outMeta);
        return ApiResponse::success($out, 'Nilai investasi (outbound) berhasil diambil', $outMeta);
      }
      return ApiResponse::success([], 'Tidak ada data.', ['filters' => array_merge($baseFilters, ['status' => 'outbound'])]);
    }

    if (!$in && !$out) {
      return ApiResponse::success(['inbound' => [], 'outbound' => []], 'Tidak ada data.', ['inbound' => $inMeta, 'outbound' => $outMeta]);
    }
    if ($inMeta) {
      $inPayload = $in ?? [];
      $this->filterYearsWithData($inPayload, $inMeta);
      $in = $inPayload;
      $inMeta['filters']['partners_name'] = $this->mapPartnerNames($inPayload, $inMeta);
    }
    if ($outMeta) {
      $outPayload = $out ?? [];
      $this->filterYearsWithData($outPayload, $outMeta);
      $out = $outPayload;
      $outMeta['filters']['partners_name'] = $this->mapPartnerNames($outPayload, $outMeta);
    }
    return ApiResponse::success(['inbound' => $in ?? [], 'outbound' => $out ?? []], 'Nilai investasi (inbound & outbound) berhasil diambil', ['inbound' => $inMeta, 'outbound' => $outMeta]);
  }

  public function nilaiJasa(Request $request): JsonResponse
  {
    $baseFilters = $this->normalizeFilters($request);
    $sources     = $this->normalizeSources($request);
    $sourceCode  = $this->sourceForSector($sources, 'jasa');
    $baseFilters = $this->applyDefaultYearRange($baseFilters, 'tbservices', $sourceCode, 'KodeSumber');
    $requestedStatus = $this->canonStatus($request->input('status'));
    $ttlUntilEod = $this->cacheTtl3Days();

    $profesiIds = $request->input('profesi_ids', $request->input('profesi', []));
    if (is_string($profesiIds)) $profesiIds = array_map('trim', explode(',', $profesiIds));
    if (is_array($profesiIds)) {
      $profesiIds = array_values(array_unique(array_filter(array_map(
        fn($v) => is_numeric($v) ? (int)$v : null,
        $profesiIds
      ))));
      if (count($profesiIds)) $baseFilters['profesi_ids'] = $profesiIds;
    }

    $in = $inMeta = $out = $outMeta = null;

    if ($requestedStatus === null || $requestedStatus === 'inbound') {
      [$in, $inMeta] = $this->fetchDirectional(
        cachePrefix: 'nilai-jasa',
        baseFilters: $baseFilters,
        status: 'inbound',
        ttl: $ttlUntilEod,
        fetcher: fn($f) => $this->economyDiplomationService->getNilaiJasa($f, $sourceCode),
        extraCache: ['source_code' => $sourceCode],
        extraMeta: ['sources' => $sources]
      );
    }

    if ($requestedStatus === null || $requestedStatus === 'outbound') {
      [$out, $outMeta] = $this->fetchDirectional(
        cachePrefix: 'nilai-jasa',
        baseFilters: $baseFilters,
        status: 'outbound',
        ttl: $ttlUntilEod,
        fetcher: fn($f) => $this->economyDiplomationService->getNilaiJasa($f, $sourceCode),
        extraCache: ['source_code' => $sourceCode],
        extraMeta: ['sources' => $sources],
        trenAliasFrom: 'tren_jasa_masuk',
        trenAliasTo: 'tren_jasa_keluar'
      );
    }

    if ($requestedStatus === 'inbound') {
      if ($in) {
        $inMeta['filters']['partners_name'] = $this->mapPartnerNames($in, $inMeta);
        return ApiResponse::success($in, 'Nilai jasa (inbound) berhasil diambil', $inMeta);
      }
      return ApiResponse::success([], 'Tidak ada data.', ['filters' => array_merge($baseFilters, ['status' => 'inbound'])]);
    }
    if ($requestedStatus === 'outbound') {
      if ($out) {
        $outMeta['filters']['partners_name'] = $this->mapPartnerNames($out, $outMeta);
        return ApiResponse::success($out, 'Nilai jasa (outbound) berhasil diambil', $outMeta);
      }
      return ApiResponse::success([], 'Tidak ada data.', ['filters' => array_merge($baseFilters, ['status' => 'outbound'])]);
    }

    if (!$in && !$out) {
      return ApiResponse::success(['inbound' => [], 'outbound' => []], 'Tidak ada data.', ['inbound' => $inMeta, 'outbound' => $outMeta]);
    }
    if ($inMeta) {
      $inMeta['filters']['partners_name'] = $this->mapPartnerNames($in ?? [], $inMeta);
    }
    if ($outMeta) {
      $outMeta['filters']['partners_name'] = $this->mapPartnerNames($out ?? [], $outMeta);
    }
    return ApiResponse::success(['inbound' => $in ?? [], 'outbound' => $out ?? []], 'Nilai jasa (inbound & outbound) berhasil diambil', ['inbound' => $inMeta, 'outbound' => $outMeta]);
  }

  public function nilaiBantuan(Request $request): JsonResponse
  {
    $baseFilters = $this->normalizeFilters($request);
    $sources     = $this->normalizeSources($request);
    $sourceCode  = $this->sourceForSector($sources, 'bantuan');
    $baseFilters = $this->applyDefaultYearRange($baseFilters, 'tbhibah', $sourceCode, 'Kode_Sumber');

    $ttlUntilEod = $this->cacheTtl3Days();
    $cacheKey    = $this->buildCacheKey('nilai-bantuan-kerjasama', array_merge($baseFilters, ['source_code' => $sourceCode]));

    $result = Cache::remember($cacheKey, $ttlUntilEod, function () use ($baseFilters, $sourceCode) {
      return $this->economyDiplomationService->getNilaiBantuanKerjasama($baseFilters, $sourceCode);
    });

    if (empty($result)) return ApiResponse::success([], 'Tidak ada data.', ['filters' => $baseFilters]);

    [$payload, $meta] = $this->splitPayloadAndMeta($result);
    $meta = array_merge($meta ?? [], ['filters' => $baseFilters, 'sources' => $sources]);

    return ApiResponse::success($payload, 'Nilai bantuan berhasil diambil', $meta);
  }

  private function fetchDirectional(
    string $cachePrefix,
    array $baseFilters,
    string $status,
    \DateTimeInterface $ttl,
    callable $fetcher,
    array $extraCache = [],
    array $extraMeta = [],
    ?string $trenAliasFrom = null,
    ?string $trenAliasTo   = null
  ): array {
    $filters  = array_merge($baseFilters, ['status' => $status]);
    $cacheKey = $this->buildCacheKey($cachePrefix, array_merge($filters, $extraCache));
    $result   = Cache::remember($cacheKey, $ttl, fn() => $fetcher($filters));

    if (empty($result)) return [null, null];

    [$payload, $meta] = $this->splitPayloadAndMeta($result);

    if ($trenAliasFrom && $trenAliasTo && isset($payload[$trenAliasFrom])) {
      $payload[$trenAliasTo] = $payload[$trenAliasFrom];
      unset($payload[$trenAliasFrom]);
    }

    if (isset($meta['applied_filters'])) unset($meta['applied_filters']);
    $meta = array_merge($meta, ['filters' => $filters], $extraMeta);

    return [$payload, $meta];
  }

  private function normalizeFilters(Request $request): array
  {
    $ys = $request->input('year_start');
    $ye = $request->input('year_end');
    $yearStart = is_numeric($ys) ? (int)$ys : null;
    $yearEnd   = is_numeric($ye) ? (int)$ye : null;

    $hsIn = $request->input('hs');
    $hs   = is_numeric($hsIn) ? (int)$hsIn : null;

    $dirjen = $this->csvToUpperArray($request->input('dirjen', []));
    $partners = $this->csvToUpperArray($request->input('partners', []));
    $status = $this->canonStatus($request->input('status'));

    $hsCodeRaw = $request->input('hsCode', $request->input('hs_code', $request->input('hsCodes', $request->input('hscodes'))));
    $hsCodeAll = false;
    $hscodes   = [];

    if (is_string($hsCodeRaw)) {
      $s = trim($hsCodeRaw);
      if ($s === '' || strtoupper($s) === 'ALL') {
        $hsCodeAll = true;
      } else {
        $hscodes = array_map('trim', explode(',', $s));
      }
    } elseif (is_array($hsCodeRaw)) {
      $hscodes = $hsCodeRaw;
    }

    if (!$hsCodeAll && is_array($hscodes)) {
      $hscodes = array_values(array_unique(array_filter(array_map(function ($v) {
        $d = preg_replace('/\D+/', '', (string)$v);
        return strlen($d) === 4 ? $d : null;
      }, $hscodes))));
      if (!count($hscodes)) {
        $hsCodeAll = true;
      }
    }

    $filters = [
      'year_start' => $yearStart,
      'year_end'   => $yearEnd,
      'hs'         => $hs,
      'dirjen'     => $dirjen,
      'partners'   => $partners,
      'status'     => $status,
    ];

    if (!$hsCodeAll && !empty($hscodes)) {
      $filters['hscodes'] = $hscodes;
    }
    $filters['hsCode_all'] = $hsCodeAll;

    return array_filter($filters, function ($v, $k) {
      if ($k === 'hsCode_all') return true;
      return is_array($v) ? count($v) > 0 : !is_null($v) && $v !== '';
    }, ARRAY_FILTER_USE_BOTH);
  }

  private function validateNilaiPerdaganganFilters(Request $request): ?JsonResponse
  {
    $errors = [];

    $partners = $this->csvToUpperArray($request->input('partners', []));
    if (!count($partners)) {
      $errors['partners'] = ['partners wajib diisi'];
    }

    $rawSumber = $request->input('sumber', null);
    if ($rawSumber === null || $rawSumber === '' || (is_array($rawSumber) && !count($rawSumber))) {
      $errors['sumber'] = ['sumber wajib diisi'];
    }

    $sources = $this->normalizeSources($request);
    if (!array_key_exists('perdagangan', $sources)) {
      $errors['sumber.perdagangan'] = ['sumber sektor perdagangan wajib diisi'];
    }

    $hsCodeRaw = $request->input('hsCode', $request->input('hs_code', $request->input('hsCodes', $request->input('hscodes'))));
    $hsCodeMissing = $hsCodeRaw === null
      || (is_string($hsCodeRaw) && trim($hsCodeRaw) === '')
      || (is_array($hsCodeRaw) && !count($hsCodeRaw));
    if ($hsCodeMissing) {
      $errors['hsCode'] = ['hsCode wajib diisi (boleh ALL)'];
    }

    if (count($errors)) {
      return ApiResponse::validation($errors);
    }

    return null;
  }

  private function canonStatus($v): ?string
  {
    $s = strtolower(trim((string)$v));
    if (in_array($s, ['inbound', 'masuk'], true))  return 'inbound';
    if (in_array($s, ['outbound', 'keluar'], true)) return 'outbound';
    return null;
  }

  private function canonTradeStatus($v): ?string
  {
    if (is_array($v)) {
      $v = $v[0] ?? null;
    }
    $s = strtolower(trim((string)$v));
    if (in_array($s, ['export', 'ekspor'], true)) return 'Export';
    if (in_array($s, ['import', 'impor'], true)) return 'Import';
    return null;
  }

  private function reverseTradeStatus(?string $status): ?string
  {
    if ($status === 'Export') return 'Import';
    if ($status === 'Import') return 'Export';
    return null;
  }

  private function parseOriginDest(Request $request): array
  {
    $origin = $this->csvToUpperArray($request->input('origin', $request->input('asal', [])));
    $dest   = $this->csvToUpperArray($request->input('dest', $request->input('tujuan', [])));
    return [$origin, $dest];
  }

  private function applyTradeReverseFilters(array $filters, Request $request): array
  {
    $tradeFilters = $filters;

    if (isset($tradeFilters['status'])) unset($tradeFilters['status']);

    $tradeStatus = $this->canonTradeStatus($request->input('status'));
    if ($tradeStatus !== null) $tradeFilters['status'] = $tradeStatus;

    [$origins, $dests] = $this->parseOriginDest($request);

    $hasOriginIdn = in_array('IDN', $origins, true);
    $hasDestIdn   = in_array('IDN', $dests, true);

    if ($hasOriginIdn && !$hasDestIdn && !empty($dests)) {
      $tradeFilters['partners'] = $dests;
      return $tradeFilters;
    }

    if ($hasDestIdn && !$hasOriginIdn && !empty($origins)) {
      $tradeFilters['partners'] = $origins;
      if ($tradeStatus !== null) $tradeFilters['status'] = $this->reverseTradeStatus($tradeStatus);
      return $tradeFilters;
    }

    return $tradeFilters;
  }

  protected function splitPayloadAndMeta(?array $data): array
  {
    $data = is_array($data) ? $data : [];
    $meta = Arr::get($data, 'meta', []);
    $payload = $data;
    unset($payload['meta']);
    if (isset($meta['applied_filters'])) unset($meta['applied_filters']);
    return [$payload, $meta];
  }

  private function csvToUpperArray($val): array
  {
    $arr = is_string($val) ? array_map('trim', explode(',', $val)) : (is_array($val) ? $val : []);
    return array_values(array_unique(array_filter(array_map(
      fn($v) => strtoupper((string)$v),
      $arr
    ))));
  }

  private function mapPartnerNames(array $payload, ?array $meta): array
  {
    $partners = $meta['filters']['partners'] ?? [];
    if (!is_array($partners) || !count($partners)) return [];

    $items = $payload['items'] ?? [];
    $map = [];
    foreach ($items as $row) {
      $name = $row['negara'] ?? null;
      $a3 = strtoupper((string) ($row['kode_alpha3'] ?? ''));
      $a2 = strtoupper((string) ($row['kode_alpha2'] ?? ''));
      if ($name) {
        if ($a3 !== '') $map[$a3] = $name;
        if ($a2 !== '') $map[$a2] = $name;
      }
    }

    $result = [];
    foreach ($partners as $code) {
      $key = strtoupper((string) $code);
      $result[] = $map[$key] ?? $key;
    }
    return $result;
  }

  private function filterYearsWithData(array &$payload, array &$meta): void
  {
    $series = $meta['total_world_per_year'] ?? [];
    if (!is_array($series) || empty($series)) {
      return;
    }

    $validYears = [];
    foreach ($series as $year => $value) {
      if ((int) $value > 0) {
        $validYears[] = (int) $year;
      }
    }
    sort($validYears);
    if (!count($validYears)) {
      return;
    }

    $meta['years'] = $validYears;
    $meta['latest_year'] = end($validYears);
    $meta['active_year'] = $meta['latest_year'];
    $meta['active_prev_year'] = count($validYears) > 1 ? $validYears[count($validYears) - 2] : null;
    $meta['prev_year'] = $validYears[0] ?? null;

    $filteredSeries = [];
    foreach ($validYears as $year) {
      if (array_key_exists($year, $series)) {
        $filteredSeries[$year] = $series[$year];
      } elseif (array_key_exists((string) $year, $series)) {
        $filteredSeries[$year] = $series[(string) $year];
      }
    }
    $meta['total_world_per_year'] = $filteredSeries;
    $meta['total_world'] = $filteredSeries[$meta['latest_year']] ?? ($meta['total_world'] ?? 0);

    if (!empty($meta['filters']) && is_array($meta['filters'])) {
      $meta['filters']['year_start'] = $validYears[0] ?? ($meta['filters']['year_start'] ?? null);
      $meta['filters']['year_end'] = $meta['latest_year'] ?? ($meta['filters']['year_end'] ?? null);
    }

    if (!empty($payload['items'])) {
      foreach ($payload['items'] as &$row) {
        if (!empty($row['nilai_investasi']) && is_array($row['nilai_investasi'])) {
          $row['nilai_investasi'] = array_intersect_key($row['nilai_investasi'], array_flip($validYears));
        }
        if (!empty($row['share']) && is_array($row['share'])) {
          $row['share'] = array_intersect_key($row['share'], array_flip($validYears));
        }
      }
      unset($row);
    }
  }

  /** (BARU) Jika hsCode ALL/empty → hilangkan 'hscodes' dari kunci cache */
  private function collapseHsCodeForCache(array $filters): array
  {
    $f = $filters;
    if (($f['hsCode_all'] ?? false) === true) {
      unset($f['hscodes']);
      $f['hsCode_all'] = true;
    }
    return $f;
  }

  private function buildCacheKey(string $prefix, array $filters): string
  {
    $filters = $this->sortRecursive($filters);
    return SideCacheKey::pairs(['indonesia', 'kerjasama-bilateral', $prefix], $filters);
  }

  private function buildCacheKeyFromRequest(string $prefix, Request $request): string
  {
    $filters = $this->sortRecursive($request->all());
    return SideCacheKey::pairs(['indonesia', 'kerjasama-bilateral', $prefix], $filters);
  }

  private function sortRecursive($value)
  {
    if (!is_array($value)) {
      return $value;
    }

    foreach ($value as $k => $v) {
      $value[$k] = $this->sortRecursive($v);
    }

    if ($this->isAssocArray($value)) {
      ksort($value);
      return $value;
    }

    $sortable = true;
    foreach ($value as $v) {
      if (!is_scalar($v) && $v !== null) {
        $sortable = false;
        break;
      }
    }
    if ($sortable) {
      sort($value);
    }

    return $value;
  }

  private function isAssocArray(array $value): bool
  {
    return array_keys($value) !== range(0, count($value) - 1);
  }

  private function cacheTtl3Days(): \DateTimeInterface
  {
    return now()->addDays(3);
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
      'jasa', 'services', 'service' => 'jasa',
      default => null,
    };
  }

  private function requestedSourceForSector(Request $request, string $targetSector): ?int
  {
    $raw = $request->input('sumber', null);
    if ($raw === null || $raw === '' || (is_array($raw) && !count($raw))) {
      return null;
    }

    if (is_string($raw)) {
      $decoded = json_decode($raw, true);
      if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
      }
      $raw = $decoded;
    }

    if (!is_array($raw)) {
      return null;
    }

    $isAssoc = array_keys($raw) !== range(0, count($raw) - 1);
    if ($isAssoc) {
      foreach ($raw as $k => $v) {
        $sector = $this->normalizeSectorKey($k);
        if ($sector !== $targetSector) {
          continue;
        }
        return $this->normalizeSourceCode($v);
      }
      return null;
    }

    foreach ($raw as $row) {
      if (!is_array($row)) {
        continue;
      }
      $sector = $this->normalizeSectorKey($row['sektor'] ?? $row['sector'] ?? $row['type'] ?? null);
      if ($sector !== $targetSector) {
        continue;
      }
      return $this->normalizeSourceCode($row['sumber'] ?? $row['kode_sumber'] ?? $row['kodeSumber'] ?? null);
    }

    return null;
  }

  private function normalizeSourceCode($value): ?int
  {
    if (is_numeric($value)) {
      return (int)$value;
    }
    if (is_string($value)) {
      $digits = preg_replace('/\D+/', '', $value);
      if ($digits !== '') {
        return (int)$digits;
      }
    }
    return null;
  }

  private function sourceForSector(array $sources, string $sector): ?int
  {
    return $sources[$sector] ?? (self::DEFAULT_SOURCES[$sector] ?? null);
  }

  private function applyDefaultYearRange(array $filters, string $table, ?int $sourceCode, string $sourceCol): array
  {
    if (!empty($filters['year_start']) && !empty($filters['year_end'])) {
      return $filters;
    }

    $cacheKey = $this->buildCacheKey('latest-year', [
      'table' => $table,
      'source_col' => $sourceCol,
      'source_code' => $sourceCode,
    ]);
    $latest = Cache::remember($cacheKey, now()->addDay(), function () use ($table, $sourceCode, $sourceCol) {
      $q = DB::connection('server_mysql')->table($table);
      if ($sourceCode !== null && $sourceCol !== '') {
        $q->where($sourceCol, $sourceCode);
      }
      return $q->max('Tahun');
    });
    if (!$latest) {
      return $filters;
    }

    $filters['year_end'] = (int) $latest;
    $filters['year_start'] = (int) $latest - 4;
    return $filters;
  }

  private function buildNilaiPerdaganganCards(array $filters, ?int $sourceCode, array $meta = []): array
  {
    if ((int) $sourceCode !== 1) {
      return [];
    }

    $years = $meta['years'] ?? [];
    if (!is_array($years) || !count($years)) {
      return [];
    }
    $years = array_values(array_unique(array_map(fn($y) => (int)$y, $years)));
    sort($years);

    $latestYear = (int) end($years);
    $prevYear = $latestYear - 1;

    $base = DB::connection('server_mysql')
      ->table(DB::raw('tbtrade as t FORCE INDEX (idx_trade_filter_partner)'))
      ->where('t.Kode_Alpha3_Reporter', 'IDN')
      ->where('t.Kode_Alpha3_Partner', '!=', 'IDN');

    if ($sourceCode !== null) {
      $base->where('t.Kode_Sumber', (int) $sourceCode);
    }

    if (isset($filters['year_start']) && isset($filters['year_end'])) {
      $a = (int) min($filters['year_start'], $filters['year_end']);
      $b = (int) max($filters['year_start'], $filters['year_end']);
      $base->whereBetween('t.Tahun', [$a, $b]);
    } elseif (isset($filters['year_start'])) {
      $base->where('t.Tahun', '>=', (int) $filters['year_start']);
    } elseif (isset($filters['year_end'])) {
      $base->where('t.Tahun', '<=', (int) $filters['year_end']);
    }

    if (array_key_exists('status', $filters)) {
      $status = $filters['status'];
      if (is_array($status) && count($status) > 0) {
        $base->whereIn('t.Status', $status);
      } elseif (is_string($status) && $status !== '') {
        $base->where('t.Status', $status);
      }
    }

    if (isset($filters['hs']) && is_numeric($filters['hs'])) {
      $hs = max(2, min(10, (int) $filters['hs']));
      $base->where('t.hs_len', $hs);
    }

    if (!empty($filters['dirjen']) && is_array($filters['dirjen'])) {
      $base->join('tbnegara as n_dirjen', 'n_dirjen.Kode_Alpha3', '=', 't.Kode_Alpha3_Partner')
        ->whereIn('n_dirjen.ID_WIl_Kemlu', $filters['dirjen']);
    }

    if (!empty($filters['partners']) && is_array($filters['partners'])) {
      $base->whereIn('t.Kode_Alpha3_Partner', $filters['partners']);
    }

    if (!empty($filters['hscodes']) && is_array($filters['hscodes'])) {
      $base->whereIn('t.HsCode', $filters['hscodes']);
    }

    $months = $this->resolveTradeCardMonths((clone $base), $latestYear);

    $now = $this->tradeCardTotals((clone $base), $latestYear, $months);
    $prev = $this->tradeCardTotals((clone $base), $prevYear, $months);
    $topNow = $this->tradeCardTopPartner((clone $base), $latestYear, $months);
    $topPrev = $this->tradeCardTopPartner((clone $base), $prevYear, $months);

    return [
      'trade_total' => [
        'value' => $now['total'],
        'prevValue' => $prev['total'],
        'year' => $latestYear,
        'prevYear' => $prevYear,
        'note' => 'Nilai Perdagangan Indonesia ke Mitra Tujuan',
      ],
      'top_partner' => [
        'value' => $topNow['value'],
        'prevValue' => $topPrev['value'],
        'year' => $latestYear,
        'prevYear' => $prevYear,
        'country' => $topNow['country'],
        'prevCountry' => $topPrev['country'],
        'note' => 'Mitra Dagang Utama',
      ],
      'export_total' => [
        'value' => $now['export'],
        'prevValue' => $prev['export'],
        'year' => $latestYear,
        'prevYear' => $prevYear,
        'note' => 'Total Ekspor Indonesia ke Mitra Tujuan',
      ],
      'import_total' => [
        'value' => $now['import'],
        'prevValue' => $prev['import'],
        'year' => $latestYear,
        'prevYear' => $prevYear,
        'note' => 'Total Impor Indonesia ke Mitra Tujuan',
      ],
    ];
  }

  private function ensureNilaiPerdaganganCardsInData($data, array $filters, ?int $sourceCode): array
  {
    $data = is_array($data) ? $data : [];
    if (empty($data)) {
      return $data;
    }
    if (array_key_exists('cards', $data)) {
      return $data;
    }

    [$payload, $meta] = $this->splitPayloadAndMeta($data);
    $payload['cards'] = $this->buildNilaiPerdaganganCards($filters, $sourceCode, $meta);
    $payload['meta'] = $meta;

    return $payload;
  }

  private function resolveTradeCardMonths($base, int $year): array
  {
    try {
      $raw = $base->where('t.Tahun', $year)->distinct()->pluck('t.Bulan')->all();
    } catch (\Throwable $e) {
      return [];
    }

    $set = [];
    foreach ($raw as $m) {
      if (!is_numeric($m)) continue;
      $mi = (int) $m;
      if ($mi >= 1 && $mi <= 12) $set[$mi] = true;
    }
    if (!count($set)) return [];
    ksort($set);
    return array_keys($set);
  }

  private function tradeCardTotals($base, int $year, array $months): array
  {
    try {
      $q = $base->where('t.Tahun', $year);
      if (!empty($months)) {
        $q->whereIn('t.Bulan', $months);
      }
      $row = $q->selectRaw("
        SUM(t.Nilai) as total,
        SUM(CASE WHEN t.Status = 'Export' THEN t.Nilai ELSE 0 END) as exp_total,
        SUM(CASE WHEN t.Status = 'Import' THEN t.Nilai ELSE 0 END) as imp_total
      ")->first();

      return [
        'total' => isset($row->total) ? (float) $row->total : null,
        'export' => isset($row->exp_total) ? (float) $row->exp_total : null,
        'import' => isset($row->imp_total) ? (float) $row->imp_total : null,
      ];
    } catch (\Throwable $e) {
      return ['total' => null, 'export' => null, 'import' => null];
    }
  }

  private function tradeCardTopPartner($base, int $year, array $months): array
  {
    try {
      $q = $base->where('t.Tahun', $year);
      if (!empty($months)) {
        $q->whereIn('t.Bulan', $months);
      }
      $row = $q->selectRaw('t.Kode_Alpha3_Partner as a3, SUM(t.Nilai) as total')
        ->groupBy('t.Kode_Alpha3_Partner')
        ->orderByDesc('total')
        ->limit(1)
        ->first();

      if (!$row) {
        return ['country' => '-', 'value' => null];
      }

      $name = DB::connection('server_mysql')
        ->table('tbnegara')
        ->where('Kode_Alpha3', (string) $row->a3)
        ->value('Negara_IDN');

      return [
        'country' => is_string($name) && $name !== '' ? strtoupper($name) : strtoupper((string) $row->a3),
        'value' => isset($row->total) ? (float) $row->total : null,
      ];
    } catch (\Throwable $e) {
      return ['country' => '-', 'value' => null];
    }
  }
}
