<?php

namespace App\Http\Controllers\Api\V1\Indonesia;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Support\SideCacheKey;
use App\Services\Indonesia\EconomyDiplomationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KerjasamaBilateralSummaryController extends Controller
{
  public function __construct(protected EconomyDiplomationService $economyDiplomationService) {}

  private const DEFAULT_SOURCES = [
    'perdagangan' => 1,
    'pariwisata' => 1,
    'investasi' => 6,
    'bantuan' => 21,
    'jasa' => 1,
  ];

  public function nilaiPerdaganganSummaryPdf(Request $request)
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
    $ttl = $this->cacheTtl3Days();

    $cacheHit = Cache::has($cacheKey);
    $legacyHit = $legacyKey !== $cacheKey && Cache::has($legacyKey);
    if ($cacheHit) {
      $baseData = Cache::get($cacheKey);
    } elseif ($legacyHit) {
      $baseData = Cache::get($legacyKey);
      Cache::put($cacheKey, $baseData, $ttl);
    } else {
      $baseData = Cache::remember(
        $cacheKey,
        $ttl,
        fn () => $this->economyDiplomationService->getNilaiPerdagangan($filters, $sourceCode)
      );
    }

    if (empty($baseData) || empty($baseData['meta'])) {
      return ApiResponse::error('Tidak ada data untuk dibuatkan ringkasan.', null, 404);
    }

    $years = $baseData['meta']['years'] ?? ($baseData['meta']['available_years'] ?? []);
    sort($years);
    $latestYear = !empty($years) ? (int) end($years) : null;
    $prevYear = null;
    if (count($years) >= 2) {
      $prevYear = (int) $years[count($years) - 2];
    }

    if ($latestYear === null) {
      return ApiResponse::error('Data tidak tersedia untuk rentang tahun yang diminta.', null, 404);
    }

    $items = $baseData['items'] ?? [];
    $topProduk = $baseData['top_produk'] ?? [];

    $sortedPartners = $items;
    usort($sortedPartners, function ($a, $b) use ($latestYear) {
      return ($b['nilai_perdagangan'][$latestYear] ?? 0) <=> ($a['nilai_perdagangan'][$latestYear] ?? 0);
    });
    $topPartners = array_slice($sortedPartners, 0, 5);
    $topPartnersTable = array_slice($sortedPartners, 0, 10);

    $topProdukTable = array_slice($topProduk, 0, 10);

    $partnerRows = array_map(function ($row) use ($latestYear, $prevYear) {
      $latest = $latestYear !== null ? (int) ($row['nilai_perdagangan'][$latestYear] ?? 0) : 0;
      $prev = $prevYear !== null ? (int) ($row['nilai_perdagangan'][$prevYear] ?? 0) : null;
      $delta = $prevYear !== null ? $latest - (int) $prev : null;
      $pct = $prevYear !== null ? $this->pctChange($latest, (int) $prev) : null;
      return [
        'negara' => $row['negara'] ?? '-',
        'latest' => $latest,
        'prev' => $prev,
        'delta' => $delta,
        'pct' => $pct,
      ];
    }, $topPartnersTable);

    $produkRows = array_map(function ($row) use ($latestYear, $prevYear) {
      $latest = $latestYear !== null ? (int) ($row['nilai'][$latestYear] ?? 0) : 0;
      $prev = $prevYear !== null ? (int) ($row['nilai'][$prevYear] ?? 0) : null;
      $delta = $prevYear !== null ? $latest - (int) $prev : null;
      $pct = $prevYear !== null ? $this->pctChange($latest, (int) $prev) : null;
      return [
        'kode' => $row['kodeHS'] ?? '-',
        'nama' => $row['namaHS'] ?? '-',
        'latest' => $latest,
        'prev' => $prev,
        'delta' => $delta,
        'pct' => $pct,
      ];
    }, $topProdukTable);

    $tujuanEksporRows = $this->buildTujuanEksporRows($topProdukTable, $latestYear, 10);
    $tujuanImporRows = $this->buildTujuanImporRows($topProdukTable, $latestYear, 10);

    $kompetitorEksporRows = $this->buildKompetitorEksporRows($topProdukTable, 10);
    $kompetitorImporRows = $this->buildKompetitorImporRows($topProdukTable, 10);

    $tujuanEksporDesc = $this->buildTujuanListDescription($tujuanEksporRows, 'tujuan');
    $tujuanImporDesc = $this->buildTujuanListDescription($tujuanImporRows, 'tujuan');
    $kompetitorEksporDesc = $this->buildTujuanListDescription($kompetitorEksporRows, 'tujuan');
    $kompetitorImporDesc = $this->buildTujuanListDescription($kompetitorImporRows, 'tujuan');
    $headlineDesc = null;
    if (!empty($tujuanEksporDesc) || !empty($tujuanImporDesc)) {
      $combined = [];
      if (!empty($tujuanEksporDesc)) {
        $combined = array_merge($combined, array_map('trim', explode(',', $tujuanEksporDesc)));
      }
      if (!empty($tujuanImporDesc)) {
        $combined = array_merge($combined, array_map('trim', explode(',', $tujuanImporDesc)));
      }
      $combined = array_values(array_unique(array_filter($combined, fn ($v) => $v !== '')));
      if (count($combined)) {
        $headlineDesc = 'Tujuan ekspor/impor: ' . implode(', ', $combined);
      }
    }

    $totalTradeByYear = $baseData['meta']['total_world_per_year'] ?? [];
    $totalTradeLatest = (int) ($totalTradeByYear[$latestYear] ?? ($baseData['meta']['total_world'] ?? 0));
    $totalTradePrev = $prevYear !== null ? (int) ($totalTradeByYear[$prevYear] ?? 0) : null;

    $totalBalanceLatest = $this->sumYearValue($items, 'neraca', $latestYear);
    $totalBalancePrev = $prevYear !== null ? $this->sumYearValue($items, 'neraca', $prevYear) : null;

    $totalExportLatest = (int) round(($totalTradeLatest + $totalBalanceLatest) / 2);
    $totalImportLatest = $totalTradeLatest - $totalExportLatest;
    $totalExportPrev = $prevYear !== null ? (int) round((($totalTradePrev ?? 0) + ($totalBalancePrev ?? 0)) / 2) : null;
    $totalImportPrev = $prevYear !== null ? (int) (($totalTradePrev ?? 0) - ($totalExportPrev ?? 0)) : null;

    $totalSummaryRows = [
      $this->buildTotalSummaryRow('Total Perdagangan', $totalTradeLatest, $totalTradePrev),
      $this->buildTotalSummaryRow('Ekspor', $totalExportLatest, $totalExportPrev),
      $this->buildTotalSummaryRow('Impor', $totalImportLatest, $totalImportPrev),
      $this->buildTotalSummaryRow('Neraca', $totalBalanceLatest, $totalBalancePrev),
    ];

    $trendSeries = $this->buildTrendSeries($totalTradeByYear);
    $partnerSeries = $this->buildPartnerSeries($topPartners, $latestYear, 'nilai_perdagangan');
    $trendLineChart = $this->buildLineChartImageGd($trendSeries);
    $top5BarChart = $this->buildTop5BarChartImageGd($partnerSeries);

    $topPartnersDesc = $this->buildTopListDescription($topPartnersTable, $latestYear, 'negara', 'nilai_perdagangan', 3);
    $topKomoditasDesc = $this->buildTopListDescription($topProdukTable, $latestYear, 'namaHS', 'nilai', 3, 'kodeHS');

    $summaryNarrative = $this->buildSummaryNarrativeBilateral([
      'latestYear' => $latestYear,
      'prevYear' => $prevYear,
      'totalTradeLatest' => $totalTradeLatest,
      'totalTradePrev' => $totalTradePrev,
      'totalExportLatest' => $totalExportLatest,
      'totalExportPrev' => $totalExportPrev,
      'totalImportLatest' => $totalImportLatest,
      'totalImportPrev' => $totalImportPrev,
      'totalBalanceLatest' => $totalBalanceLatest,
      'totalBalancePrev' => $totalBalancePrev,
      'totalSummaryRows' => $totalSummaryRows,
      'topPartnersDesc' => $topPartnersDesc,
      'topKomoditasDesc' => $topKomoditasDesc,
    ]);

    $tanggalCetak = now()->translatedFormat('d F Y');
    $unit = $baseData['meta']['unit'] ?? 'Ribu US$';
    $sourceName = $baseData['meta']['sumber'] ?? 'Trademap';

    $pdf = Pdf::loadView('exports.nilai-perdagangan-bilateral-summary', [
      'tanggalCetak' => $tanggalCetak,
      'unit' => $unit,
      'sourceName' => $sourceName,
      'latestYear' => $latestYear,
      'prevYear' => $prevYear,
      'totalTradeLatest' => $totalTradeLatest,
      'totalTradePrev' => $totalTradePrev,
      'totalExportLatest' => $totalExportLatest,
      'totalExportPrev' => $totalExportPrev,
      'totalImportLatest' => $totalImportLatest,
      'totalImportPrev' => $totalImportPrev,
      'totalBalanceLatest' => $totalBalanceLatest,
      'totalBalancePrev' => $totalBalancePrev,
      'totalSummaryRows' => $totalSummaryRows,
      'summaryNarrative' => $summaryNarrative,
      'trendLineChart' => $trendLineChart,
      'top5BarChart' => $top5BarChart,
      'topPartnerCount' => count($topPartners),
      'partnerRows' => $partnerRows,
      'produkRows' => $produkRows,
      'tujuanEksporRows' => $tujuanEksporRows,
      'tujuanImporRows' => $tujuanImporRows,
      'kompetitorEksporRows' => $kompetitorEksporRows,
      'kompetitorImporRows' => $kompetitorImporRows,
      'tujuanEksporDesc' => $tujuanEksporDesc,
      'tujuanImporDesc' => $tujuanImporDesc,
      'kompetitorEksporDesc' => $kompetitorEksporDesc,
      'kompetitorImporDesc' => $kompetitorImporDesc,
      'headlineDesc' => $headlineDesc,
    ])->setPaper('a4', 'portrait');

    $filename = 'nilai-perdagangan-bilateral-summary-' . now()->format('Ymd_His') . '.pdf';

    return response($pdf->output(), 200)
      ->header('Content-Type', 'application/pdf')
      ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
  }

  public function nilaiPariwisataSummaryPdf(Request $request)
  {
    $filters = $this->normalizeFilters($request);
    $sources = $this->normalizeSources($request);
    $sourceCode = $this->sourceForSector($sources, 'pariwisata');
    $filters = $this->applyDefaultYearRange($filters, 'tbtourism', $sourceCode, 'Kode_Sumber');

    $requestedStatus = $this->canonStatus($request->input('status'));
    $filtersStrict = array_merge($filters, ['strict_source_years' => true]);
    $ttl = $this->cacheTtl3Days();

    $inbound = null;
    $outbound = null;
    $inMeta = null;
    $outMeta = null;

    if ($requestedStatus === null || $requestedStatus === 'inbound') {
      [$inbound, $inMeta] = $this->fetchTourismDirectional($filters, 'inbound', $ttl, $sourceCode);
    }
    if ($requestedStatus === null || $requestedStatus === 'outbound') {
      [$outbound, $outMeta] = $this->fetchTourismDirectional($filters, 'outbound', $ttl, $sourceCode);
    }

    if (!$inbound && !$outbound) {
      return ApiResponse::error('Tidak ada data untuk dibuatkan ringkasan.', null, 404);
    }

    $primary = $inbound ?: $outbound;
    $primaryMeta = $inMeta ?: $outMeta;

    $latestYear = $primaryMeta['latest_year'] ?? null;
    $prevYear = $primaryMeta['active_prev_year'] ?? ($primaryMeta['prev_year'] ?? null);
    $unit = $primaryMeta['unit'] ?? 'Orang';
    $sourceName = $primaryMeta['sumber'] ?? 'BPS';

    $summaryDataInbound = $inbound ? $this->buildTourismSummaryBlock($inbound, $inMeta, $unit) : null;
    if (is_array($summaryDataInbound) && empty($summaryDataInbound)) {
      $summaryDataInbound = null;
    }
    $summaryDataOutbound = $outbound ? $this->buildTourismSummaryBlock($outbound, $outMeta, $unit) : null;
    if (is_array($summaryDataOutbound) && empty($summaryDataOutbound)) {
      $summaryDataOutbound = null;
    }

    $totalInboundLatest = $summaryDataInbound['totalLatest'] ?? null;
    $totalOutboundLatest = $summaryDataOutbound['totalLatest'] ?? null;
    $periodStart = $summaryDataInbound['prevYear'] ?? $summaryDataOutbound['prevYear'] ?? ($primaryMeta['prev_year'] ?? null);
    $periodEnd = $summaryDataInbound['latestYear']
      ?? $summaryDataOutbound['latestYear']
      ?? $this->resolveLatestYearFromMeta($inMeta, $outMeta, $latestYear);

    $reportLatestYear = $summaryDataInbound['latestYear'] ?? $summaryDataOutbound['latestYear'] ?? $latestYear;
    $reportPrevYear = $summaryDataInbound['prevYear'] ?? $summaryDataOutbound['prevYear'] ?? $prevYear;
    $partnerMap = $this->collectPartnerMap($inbound, $outbound);
    $partnerNames = $this->buildPartnerHeadline($filters['partners'] ?? [], $partnerMap);
    $summaryNarrative = $this->buildSummaryNarrativePariwisata([
      'latestYear' => $latestYear,
      'prevYear' => $prevYear,
      'unit' => $unit,
      'inbound' => $summaryDataInbound,
      'outbound' => $summaryDataOutbound,
    ]);

    $tanggalCetak = now()->translatedFormat('d F Y');

    $pdf = Pdf::loadView('exports.nilai-pariwisata-bilateral-summary', [
      'tanggalCetak' => $tanggalCetak,
      'unit' => $unit,
      'sourceName' => $sourceName,
      'latestYear' => $latestYear,
      'prevYear' => $prevYear,
      'summaryNarrative' => $summaryNarrative,
      'partnerHeadline' => $partnerNames,
      'inbound' => $summaryDataInbound,
      'outbound' => $summaryDataOutbound,
      'totalInboundLatest' => $totalInboundLatest,
      'totalOutboundLatest' => $totalOutboundLatest,
      'periodStart' => $periodStart,
      'periodEnd' => $periodEnd,
    ])->setPaper('a4', 'portrait');

    $filename = 'nilai-pariwisata-bilateral-summary-' . now()->format('Ymd_His') . '.pdf';

    return response($pdf->output(), 200)
      ->header('Content-Type', 'application/pdf')
      ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
  }

  public function nilaiInvestasiSummaryPdf(Request $request)
  {
    $filters = $this->normalizeFilters($request);
    $sources = $this->normalizeSources($request);
    $sourceCode = $this->sourceForSector($sources, 'investasi');
    $filters = $this->applyDefaultYearRange($filters, 'tbinvestment', $sourceCode, 'Kode_Sumber');

    $requestedStatus = $this->canonStatus($request->input('status'));
    $filtersStrict = array_merge($filters, ['strict_source_years' => true]);
    $ttl = $this->cacheTtl3Days();

    $inbound = null;
    $outbound = null;
    $inMeta = null;
    $outMeta = null;

    if ($requestedStatus === null || $requestedStatus === 'inbound') {
      [$inbound, $inMeta] = $this->fetchInvestasiDirectional($filtersStrict, 'inbound', $ttl, $sourceCode);
    }
    if ($requestedStatus === null || $requestedStatus === 'outbound') {
      [$outbound, $outMeta] = $this->fetchInvestasiDirectional($filtersStrict, 'outbound', $ttl, $sourceCode);
    }

    if (!$inbound && !$outbound) {
      return ApiResponse::error('Tidak ada data untuk dibuatkan ringkasan.', null, 404);
    }

    $primaryMeta = $inMeta ?: $outMeta;
    $latestYear = $primaryMeta['latest_year'] ?? null;
    $prevYear = $primaryMeta['active_prev_year'] ?? ($primaryMeta['prev_year'] ?? null);
    $unit = $primaryMeta['unit'] ?? 'Ribu US$';
    $sourceName = $primaryMeta['sumber'] ?? 'BKPM';

    $summaryDataInbound = $inbound ? $this->buildInvestasiSummaryBlock($inbound, $inMeta, $unit, 'inbound') : null;
    if (is_array($summaryDataInbound) && empty($summaryDataInbound)) {
      $summaryDataInbound = null;
    }
    $summaryDataOutbound = $outbound ? $this->buildInvestasiSummaryBlock($outbound, $outMeta, $unit, 'outbound') : null;
    if (is_array($summaryDataOutbound) && empty($summaryDataOutbound)) {
      $summaryDataOutbound = null;
    }

    $totalInboundLatest = $summaryDataInbound['totalLatest'] ?? null;
    $totalOutboundLatest = $summaryDataOutbound['totalLatest'] ?? null;
    $periodStart = $summaryDataInbound['prevYear'] ?? $summaryDataOutbound['prevYear'] ?? ($primaryMeta['prev_year'] ?? null);
    $periodEnd = $summaryDataInbound['latestYear']
      ?? $summaryDataOutbound['latestYear']
      ?? $this->resolveLatestYearFromMeta($inMeta, $outMeta, $latestYear);

    $reportLatestYear = $summaryDataInbound['latestYear'] ?? $summaryDataOutbound['latestYear'] ?? $latestYear;
    $reportPrevYear = $summaryDataInbound['prevYear'] ?? $summaryDataOutbound['prevYear'] ?? $prevYear;

    $partnerMap = $this->collectPartnerMap($inbound, $outbound);
    $partnerHeadline = $this->buildPartnerHeadline($filters['partners'] ?? [], $partnerMap);

    $summaryNarrative = $this->buildSummaryNarrativeInvestasi([
      'latestYear' => $reportLatestYear,
      'prevYear' => $reportPrevYear,
      'unit' => $unit,
      'inbound' => $summaryDataInbound,
      'outbound' => $summaryDataOutbound,
    ]);

    $tanggalCetak = now()->translatedFormat('d F Y');

    $pdf = Pdf::loadView('exports.nilai-investasi-bilateral-summary', [
      'tanggalCetak' => $tanggalCetak,
      'unit' => $unit,
      'sourceName' => $sourceName,
      'latestYear' => $reportLatestYear,
      'prevYear' => $reportPrevYear,
      'summaryNarrative' => $summaryNarrative,
      'partnerHeadline' => $partnerHeadline,
      'inbound' => $summaryDataInbound,
      'outbound' => $summaryDataOutbound,
      'totalInboundLatest' => $totalInboundLatest,
      'totalOutboundLatest' => $totalOutboundLatest,
      'periodStart' => $periodStart,
      'periodEnd' => $periodEnd,
    ])->setPaper('a4', 'portrait');

    $filename = 'nilai-investasi-bilateral-summary-' . now()->format('Ymd_His') . '.pdf';

    return response($pdf->output(), 200)
      ->header('Content-Type', 'application/pdf')
      ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
  }

  public function nilaiBantuanSummaryPdf(Request $request)
  {
    $filters = $this->normalizeFilters($request);
    $sources = $this->normalizeSources($request);
    $sourceCode = $this->sourceForSector($sources, 'bantuan');
    $filters = $this->applyDefaultYearRange($filters, 'tbhibah', $sourceCode, 'Kode_Sumber');

    $cacheKey = $this->buildCacheKey('nilai-bantuan-kerjasama', array_merge($filters, ['source_code' => $sourceCode]));
    $ttl = $this->cacheTtl3Days();

    if (Cache::has($cacheKey)) {
      $baseData = Cache::get($cacheKey);
    } else {
      $baseData = Cache::remember(
        $cacheKey,
        $ttl,
        fn () => $this->economyDiplomationService->getNilaiBantuanKerjasama($filters, $sourceCode)
      );
    }

    if (empty($baseData) || empty($baseData['meta'])) {
      return ApiResponse::error('Tidak ada data untuk dibuatkan ringkasan.', null, 404);
    }

    $meta = $baseData['meta'] ?? [];
    $payload = $baseData;
    unset($payload['meta']);

    $summaryData = $this->buildBantuanSummaryBlock($payload, $meta);
    if (empty($summaryData)) {
      return ApiResponse::error('Tidak ada data untuk dibuatkan ringkasan.', null, 404);
    }

    $latestYear = $summaryData['latestYear'] ?? null;
    $prevYear = $summaryData['prevYear'] ?? null;
    $unit = $summaryData['unit'] ?? ($meta['unit'] ?? 'IDR Miliar');
    $sourceName = $meta['sumber'] ?? 'KSPI';

    $summaryNarrative = $this->buildSummaryNarrativeBantuan([
      'latestYear' => $latestYear,
      'prevYear' => $prevYear,
      'unit' => $unit,
      'totalLatest' => $summaryData['totalLatest'] ?? 0,
      'totalPrev' => $summaryData['totalPrev'] ?? null,
      'totalKegiatanLatest' => $summaryData['totalKegiatanLatest'] ?? null,
      'totalKegiatanPrev' => $summaryData['totalKegiatanPrev'] ?? null,
      'topPartnersDesc' => $summaryData['topPartnersDesc'] ?? null,
      'topKawasanDesc' => $summaryData['topKawasanDesc'] ?? null,
    ]);

    $tanggalCetak = now()->translatedFormat('d F Y');

    $pdf = Pdf::loadView('exports.nilai-bantuan-bilateral-summary', [
      'tanggalCetak' => $tanggalCetak,
      'unit' => $unit,
      'sourceName' => $sourceName,
      'latestYear' => $latestYear,
      'prevYear' => $prevYear,
      'summaryNarrative' => $summaryNarrative,
      'partnerHeadline' => $this->buildPartnerHeadline($filters['partners'] ?? [], $this->collectPartnerMap(null, $payload)),
      'totalLatest' => $summaryData['totalLatest'] ?? 0,
      'totalPrev' => $summaryData['totalPrev'] ?? null,
      'trendLineChart' => $summaryData['trendLineChart'] ?? null,
      'top5BarChart' => $summaryData['top5BarChart'] ?? null,
      'partnerRows' => $summaryData['partnerRows'] ?? [],
      'partnerCount' => $summaryData['partnerCount'] ?? 0,
      'kawasanRows' => $summaryData['kawasanRows'] ?? [],
      'kawasanCount' => $summaryData['kawasanCount'] ?? 0,
    ])->setPaper('a4', 'portrait');

    $filename = 'nilai-bantuan-bilateral-summary-' . now()->format('Ymd_His') . '.pdf';

    return response($pdf->output(), 200)
      ->header('Content-Type', 'application/pdf')
      ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
  }

  public function nilaiJasaSummaryPdf(Request $request)
  {
    $filters = $this->normalizeFilters($request);
    $sources = $this->normalizeSources($request);
    $sourceCode = $this->sourceForSector($sources, 'jasa');
    $filters = $this->applyDefaultYearRange($filters, 'tbservices', $sourceCode, 'KodeSumber');

    $profesiIds = $request->input('profesi_ids', $request->input('profesi', []));
    if (is_string($profesiIds)) $profesiIds = array_map('trim', explode(',', $profesiIds));
    if (is_array($profesiIds)) {
      $profesiIds = array_values(array_unique(array_filter(array_map(
        fn($v) => is_numeric($v) ? (int) $v : null,
        $profesiIds
      ))));
      if (count($profesiIds)) $filters['profesi_ids'] = $profesiIds;
    }

    // Outbound saja sesuai permintaan
    $filtersOut = array_merge($filters, ['status' => 'outbound']);

    $cacheKey = $this->buildCacheKey('nilai-jasa', array_merge($filtersOut, ['source_code' => $sourceCode]));
    $ttl = $this->cacheTtl3Days();

    if (Cache::has($cacheKey)) {
      $baseData = Cache::get($cacheKey);
    } else {
      $baseData = Cache::remember(
        $cacheKey,
        $ttl,
        fn () => $this->economyDiplomationService->getNilaiJasa($filtersOut, $sourceCode)
      );
    }

    if (empty($baseData) || empty($baseData['meta'])) {
      return ApiResponse::error('Tidak ada data untuk dibuatkan ringkasan.', null, 404);
    }

    $meta = $baseData['meta'] ?? [];
    $payload = $baseData;
    unset($payload['meta']);

    $summaryData = $this->buildJasaSummaryBlock($payload, $meta);
    if (empty($summaryData)) {
      return ApiResponse::error('Tidak ada data untuk dibuatkan ringkasan.', null, 404);
    }

    $latestYear = $summaryData['latestYear'] ?? null;
    $prevYear = $summaryData['prevYear'] ?? null;
    $unit = $summaryData['unit'] ?? ($meta['unit'] ?? 'Orang');
    $sourceName = $meta['sumber'] ?? 'SIP2MI';

    $summaryNarrative = $this->buildSummaryNarrativeJasa([
      'latestYear' => $latestYear,
      'prevYear' => $prevYear,
      'unit' => $unit,
      'totalLatest' => $summaryData['totalLatest'] ?? 0,
      'totalPrev' => $summaryData['totalPrev'] ?? null,
      'topPartnersDesc' => $summaryData['topPartnersDesc'] ?? null,
    ]);

    $tanggalCetak = now()->translatedFormat('d F Y');

    $pdf = Pdf::loadView('exports.nilai-jasa-bilateral-summary', [
      'tanggalCetak' => $tanggalCetak,
      'unit' => $unit,
      'sourceName' => $sourceName,
      'latestYear' => $latestYear,
      'prevYear' => $prevYear,
      'summaryNarrative' => $summaryNarrative,
      'partnerHeadline' => $this->buildPartnerHeadline($filtersOut['partners'] ?? [], $this->collectPartnerMap(null, $payload)),
      'totalLatest' => $summaryData['totalLatest'] ?? 0,
      'totalPrev' => $summaryData['totalPrev'] ?? null,
      'trendLineChart' => $summaryData['trendLineChart'] ?? null,
      'top5BarChart' => $summaryData['top5BarChart'] ?? null,
      'partnerRows' => $summaryData['partnerRows'] ?? [],
      'partnerCount' => $summaryData['partnerCount'] ?? 0,
      'perProfesiRows' => $summaryData['perProfesiRows'] ?? [],
    ])->setPaper('a4', 'portrait');

    $filename = 'nilai-jasa-bilateral-summary-' . now()->format('Ymd_His') . '.pdf';

    return response($pdf->output(), 200)
      ->header('Content-Type', 'application/pdf')
      ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
  }

  private function sumYearValue(array $items, string $key, int $year): int
  {
    $sum = 0;
    foreach ($items as $row) {
      $sum += (int) ($row[$key][$year] ?? 0);
    }
    return $sum;
  }

  private function buildJasaSummaryBlock(array $payload, array $meta): array
  {
    $totalByYear = $meta['total_world_per_year'] ?? [];
    $years = $meta['years'] ?? array_keys($totalByYear);
    $years = array_values(array_unique(array_map('intval', $years)));
    sort($years);

    if (!count($years)) {
      return [];
    }

    $latestYear = $meta['active_year'] ?? (int) end($years);
    $prevYear = $meta['active_prev_year'] ?? (count($years) > 1 ? (int) $years[count($years) - 2] : null);

    $items = $payload['items'] ?? [];
    if (empty($items)) {
      return [];
    }

    $totalLatest = (int) ($totalByYear[$latestYear] ?? ($meta['total_world'] ?? 0));
    $totalPrev = $prevYear !== null ? (int) ($totalByYear[$prevYear] ?? 0) : null;

    $sorted = $items;
    usort($sorted, function ($a, $b) use ($latestYear) {
      return ($b['Jumlah_Jasa'][$latestYear] ?? 0) <=> ($a['Jumlah_Jasa'][$latestYear] ?? 0);
    });
    $topPartners = array_slice($sorted, 0, 5);
    $topTable = array_slice($sorted, 0, 10);

    $partnerRows = array_map(function ($row) use ($latestYear, $prevYear) {
      $latest = $latestYear !== null ? (int) ($row['Jumlah_Jasa'][$latestYear] ?? 0) : 0;
      $prev = $prevYear !== null ? (int) ($row['Jumlah_Jasa'][$prevYear] ?? 0) : null;
      $delta = $prevYear !== null ? $latest - (int) $prev : null;
      $pct = $prevYear !== null ? $this->pctChange($latest, (int) $prev) : null;
      return [
        'negara' => $row['negara'] ?? '-',
        'latest' => $latest,
        'prev' => $prev,
        'delta' => $delta,
        'pct' => $pct,
      ];
    }, $topTable);

    $trendSeries = $this->buildTrendSeries($totalByYear);
    $partnerSeries = $this->buildPartnerSeries($topPartners, $latestYear, 'Jumlah_Jasa');
    $trendLineChart = $this->buildLineChartImageGd($trendSeries);
    $top5BarChart = $this->buildTop5BarChartImageGd($partnerSeries);

    $topPartnersDesc = $this->buildTopListDescriptionGeneric($topTable, $latestYear, 'negara', 'Jumlah_Jasa', 3, 'Orang');

    $perProfesi = $payload['per_profesi'] ?? [];
    usort($perProfesi, function ($a, $b) use ($latestYear) {
      return ($b['jumlah'][$latestYear] ?? 0) <=> ($a['jumlah'][$latestYear] ?? 0);
    });
    $perProfesiTop = array_slice($perProfesi, 0, 10);
    $perProfesiRows = array_map(function ($row) use ($latestYear, $prevYear) {
      $latest = $latestYear !== null ? (int) ($row['jumlah'][$latestYear] ?? 0) : 0;
      $prev = $prevYear !== null ? (int) ($row['jumlah'][$prevYear] ?? 0) : null;
      $delta = $prevYear !== null ? $latest - (int) $prev : null;
      $pct = $prevYear !== null ? $this->pctChange($latest, (int) $prev) : null;
      return [
        'nama' => $row['nama_profesi'] ?? '-',
        'latest' => $latest,
        'prev' => $prev,
        'delta' => $delta,
        'pct' => $pct,
      ];
    }, $perProfesiTop);

    return [
      'latestYear' => $latestYear,
      'prevYear' => $prevYear,
      'totalLatest' => $totalLatest,
      'totalPrev' => $totalPrev,
      'topPartnersDesc' => $topPartnersDesc,
      'partnerRows' => $partnerRows,
      'partnerCount' => count($items),
      'trendLineChart' => $trendLineChart,
      'top5BarChart' => $top5BarChart,
      'perProfesiRows' => $perProfesiRows,
      'unit' => $meta['unit'] ?? 'Orang',
    ];
  }

  private function buildBantuanSummaryBlock(array $payload, array $meta): array
  {
    $totalByYear = $meta['total_world_per_year'] ?? [];
    $years = $meta['years'] ?? array_keys($totalByYear);
    $years = array_values(array_unique(array_map('intval', $years)));
    sort($years);

    if (!count($years)) {
      return [];
    }

    $latestYear = $meta['active_year'] ?? (int) end($years);
    $prevYear = $meta['active_prev_year'] ?? (count($years) > 1 ? (int) $years[count($years) - 2] : null);

    $items = $payload['items'] ?? [];
    if (empty($items)) {
      return [];
    }

    $totalLatest = (int) ($totalByYear[$latestYear] ?? ($meta['total_world'] ?? 0));
    $totalPrev = $prevYear !== null ? (int) ($totalByYear[$prevYear] ?? 0) : null;

    $sorted = $items;
    usort($sorted, function ($a, $b) use ($latestYear) {
      return ($b['nilai_bantuan'][$latestYear] ?? 0) <=> ($a['nilai_bantuan'][$latestYear] ?? 0);
    });
    $topPartners = array_slice($sorted, 0, 5);
    $topTable = array_slice($sorted, 0, 10);

    $partnerRows = array_map(function ($row) use ($latestYear, $prevYear) {
      $latest = $latestYear !== null ? (float) ($row['nilai_bantuan'][$latestYear] ?? 0) : 0;
      $prev = $prevYear !== null ? (float) ($row['nilai_bantuan'][$prevYear] ?? 0) : null;
      $delta = $prevYear !== null ? $latest - (float) $prev : null;
      $pct = $prevYear !== null ? $this->pctChange((int) round($latest), (int) round((float) $prev)) : null;
      return [
        'negara' => $row['negara'] ?? '-',
        'latest' => $latest,
        'prev' => $prev,
        'delta' => $delta,
        'pct' => $pct,
      ];
    }, $topTable);

    $trendSeries = $this->buildTrendSeries($totalByYear);
    $partnerSeries = $this->buildPartnerSeries($topPartners, $latestYear, 'nilai_bantuan');
    $trendLineChart = $this->buildLineChartImageGd($trendSeries);
    $top5BarChart = $this->buildTop5BarChartImageGd($partnerSeries);

    $topPartnersDesc = $this->buildTopListDescriptionGeneric($topTable, $latestYear, 'negara', 'nilai_bantuan', 3, $meta['unit'] ?? 'IDR Miliar');

    $kawasanItems = $payload['per_kawasan']['items'] ?? [];
    $kawasanRows = [];
    if (!empty($kawasanItems)) {
      usort($kawasanItems, function ($a, $b) use ($latestYear) {
        return ($b['nilai_bantuan'][$latestYear] ?? 0) <=> ($a['nilai_bantuan'][$latestYear] ?? 0);
      });
      $kawasanTop = array_slice($kawasanItems, 0, 10);
      $kawasanRows = array_map(function ($row) use ($latestYear, $prevYear) {
        $latest = $latestYear !== null ? (float) ($row['nilai_bantuan'][$latestYear] ?? 0) : 0;
        $prev = $prevYear !== null ? (float) ($row['nilai_bantuan'][$prevYear] ?? 0) : null;
        $delta = $prevYear !== null ? $latest - (float) $prev : null;
        $pct = $prevYear !== null ? $this->pctChange((int) round($latest), (int) round((float) $prev)) : null;
        return [
          'kawasan' => $row['kawasan_nama'] ?? ($row['kawasan_kode'] ?? '-'),
          'latest' => $latest,
          'prev' => $prev,
          'delta' => $delta,
          'pct' => $pct,
        ];
      }, $kawasanTop);
    }

    $topKawasanDesc = null;
    if (!empty($kawasanItems) && $latestYear !== null) {
      $topKawasanDesc = $this->buildTopListDescriptionGeneric($kawasanItems, $latestYear, 'kawasan_nama', 'nilai_bantuan', 3, $meta['unit'] ?? 'IDR Miliar');
    }

    $totalKegiatanLatest = $this->sumYearValueFloat($items, 'total_kegiatan', $latestYear);
    $totalKegiatanPrev = $prevYear !== null ? $this->sumYearValueFloat($items, 'total_kegiatan', $prevYear) : null;

    return [
      'latestYear' => $latestYear,
      'prevYear' => $prevYear,
      'totalLatest' => $totalLatest,
      'totalPrev' => $totalPrev,
      'totalKegiatanLatest' => $totalKegiatanLatest,
      'totalKegiatanPrev' => $totalKegiatanPrev,
      'topPartnersDesc' => $topPartnersDesc,
      'topKawasanDesc' => $topKawasanDesc,
      'partnerRows' => $partnerRows,
      'partnerCount' => count($items),
      'trendLineChart' => $trendLineChart,
      'top5BarChart' => $top5BarChart,
      'kawasanRows' => $kawasanRows,
      'kawasanCount' => count($kawasanItems),
      'unit' => $meta['unit'] ?? 'IDR Miliar',
    ];
  }

  private function buildSummaryNarrativeBantuan(array $data): string
  {
    $latestYear = $data['latestYear'] ?? null;
    $prevYear = $data['prevYear'] ?? null;
    $unit = $data['unit'] ?? 'IDR Miliar';
    $totalLatest = $data['totalLatest'] ?? 0;
    $totalPrev = $data['totalPrev'] ?? null;
    $totalKegiatanLatest = $data['totalKegiatanLatest'] ?? null;
    $totalKegiatanPrev = $data['totalKegiatanPrev'] ?? null;
    $topPartnersDesc = $data['topPartnersDesc'] ?? null;
    $topKawasanDesc = $data['topKawasanDesc'] ?? null;

    $parts = [];
    if ($latestYear !== null) {
      if ($prevYear !== null) {
        $growth = $this->pctChange((int) round($totalLatest), (int) round((float) $totalPrev));
        $growthText = $growth === null ? '' : ' (perubahan ' . ($growth >= 0 ? '+' : '') . number_format($growth, 2, ',', '.') . '% dari ' . $prevYear . ')';
        $parts[] = "Pada {$latestYear}, total bantuan bilateral mencapai " . number_format((float) $totalLatest, 2, ',', '.') . " {$unit}" . $growthText . ".";
      } else {
        $parts[] = "Pada {$latestYear}, total bantuan bilateral mencapai " . number_format((float) $totalLatest, 2, ',', '.') . " {$unit}.";
      }
    }
    if (!empty($topPartnersDesc)) {
      $parts[] = "Negara mitra dengan kontribusi terbesar meliputi {$topPartnersDesc}.";
    }
    if (!empty($topKawasanDesc)) {
      $parts[] = "Pada tingkat kawasan, kontribusi tertinggi berasal dari {$topKawasanDesc}.";
    }
    if ($latestYear !== null && $totalKegiatanLatest !== null) {
      $kegiatanText = number_format((int) $totalKegiatanLatest, 0, ',', '.');
      if ($prevYear !== null && $totalKegiatanPrev !== null) {
        $delta = (int) $totalKegiatanLatest - (int) $totalKegiatanPrev;
        $deltaText = ($delta >= 0 ? '+' : '') . number_format($delta, 0, ',', '.');
        $parts[] = "Jumlah kegiatan pada {$latestYear} tercatat {$kegiatanText} kegiatan (perubahan {$deltaText} dari {$prevYear}).";
      } else {
        $parts[] = "Jumlah kegiatan pada {$latestYear} tercatat {$kegiatanText} kegiatan.";
      }
    }
    $parts[] = "Ringkasan ini memberikan gambaran arah fokus kerja sama bantuan dan menjadi dasar evaluasi program ke depan.";

    return implode(' ', $parts);
  }

  private function sumYearValueFloat(array $items, string $key, int $year): float
  {
    $sum = 0.0;
    foreach ($items as $row) {
      $sum += (float) ($row[$key][$year] ?? 0);
    }
    return $sum;
  }

  private function buildSummaryNarrativeJasa(array $data): string
  {
    $latestYear = $data['latestYear'] ?? null;
    $prevYear = $data['prevYear'] ?? null;
    $unit = $data['unit'] ?? 'Orang';
    $totalLatest = $data['totalLatest'] ?? 0;
    $totalPrev = $data['totalPrev'] ?? null;
    $topPartnersDesc = $data['topPartnersDesc'] ?? null;

    $parts = [];
    if ($latestYear !== null) {
      if ($prevYear !== null) {
        $growth = $this->pctChange((int) $totalLatest, (int) $totalPrev);
        $growthText = $growth === null ? '' : ' (perubahan ' . ($growth >= 0 ? '+' : '') . number_format($growth, 2, ',', '.') . '% dari ' . $prevYear . ')';
        $parts[] = "Pada {$latestYear}, total jasa Indonesia mencapai " . number_format((int) $totalLatest, 0, ',', '.') . " {$unit}" . $growthText . ".";
      } else {
        $parts[] = "Pada {$latestYear}, total jasa Indonesia mencapai " . number_format((int) $totalLatest, 0, ',', '.') . " {$unit}.";
      }
    }
    if (!empty($topPartnersDesc)) {
      $parts[] = "Negara mitra dengan kontribusi terbesar meliputi {$topPartnersDesc}.";
    }
    if ($latestYear !== null && $prevYear !== null) {
      $parts[] = "Perubahan dari {$prevYear} ke {$latestYear} mencerminkan dinamika permintaan jasa lintas negara yang dipengaruhi oleh kondisi pasar dan kebutuhan tenaga kerja.";
    }
    $parts[] = "Ringkasan ini membantu mengidentifikasi mitra utama dan perkembangan tahunan sebagai dasar perumusan kebijakan penempatan dan perlindungan pekerja.";
    return implode(' ', $parts);
  }

  private function buildTopListDescriptionGeneric(array $rows, ?int $year, string $nameKey, string $valueKey, int $limit, string $unit): ?string
  {
    if ($year === null) return null;
    $top = array_slice($rows, 0, $limit);
    $parts = [];
    foreach ($top as $row) {
      $name = $row[$nameKey] ?? '-';
      $value = $row[$valueKey][$year] ?? 0;
      $parts[] = $name . ' (' . number_format((int) $value, 0, ',', '.') . ' ' . $unit . ')';
    }
    return count($parts) ? implode(', ', $parts) : null;
  }

  private function fetchTourismDirectional(array $baseFilters, string $status, \DateTimeInterface $ttl, ?int $sourceCode): array
  {
    $filters = array_merge($baseFilters, ['status' => $status]);
    $cacheKey = $this->buildCacheKey('nilai-pariwisata-negara', array_merge($filters, ['source_code' => $sourceCode]));
    $legacyKey = $this->buildCacheKeyFromRequest('nilai-pariwisata-negara', request());

    if (Cache::has($cacheKey)) {
      $result = Cache::get($cacheKey);
    } elseif ($legacyKey !== $cacheKey && Cache::has($legacyKey)) {
      $result = Cache::get($legacyKey);
      Cache::put($cacheKey, $result, $ttl);
    } else {
      $result = Cache::remember($cacheKey, $ttl, fn () => $this->economyDiplomationService->getNilaiWisatawan($filters, $sourceCode));
    }

    if (empty($result)) return [null, null];

    $meta = $result['meta'] ?? [];
    $payload = $result;
    unset($payload['meta']);

    return [$payload, $meta];
  }

  private function fetchInvestasiDirectional(array $baseFilters, string $status, \DateTimeInterface $ttl, ?int $sourceCode): array
  {
    $filters = array_merge($baseFilters, ['status' => $status]);
    $cacheKey = $this->buildCacheKey('nilai-investasi', array_merge($filters, ['source_code' => $sourceCode]));
    $legacyKey = $this->buildCacheKeyFromRequest('nilai-investasi', request());

    if (Cache::has($cacheKey)) {
      $result = Cache::get($cacheKey);
    } elseif ($legacyKey !== $cacheKey && Cache::has($legacyKey)) {
      $result = Cache::get($legacyKey);
      Cache::put($cacheKey, $result, $ttl);
    } else {
      $result = Cache::remember($cacheKey, $ttl, fn () => $this->economyDiplomationService->getNilaiInvestasi($filters, $sourceCode));
    }

    if (empty($result)) return [null, null];

    $meta = $result['meta'] ?? [];
    $payload = $result;
    unset($payload['meta']);

    return [$payload, $meta];
  }

  private function buildInvestasiSummaryBlock(array $payload, array $meta, string $unit, ?string $status = null): array
  {
    if ($status && isset($meta[$status]) && is_array($meta[$status])) {
      $meta = $meta[$status];
    }
    $totalByYear = $meta['total_world_per_year'] ?? [];

    $itemYears = [];
    $items = $payload['items'] ?? [];
    foreach ($items as $row) {
      if (empty($row['nilai_investasi']) || !is_array($row['nilai_investasi'])) continue;
      foreach ($row['nilai_investasi'] as $year => $value) {
        $yearInt = (int) $year;
        $itemYears[] = $yearInt;
      }
    }
    $itemYears = array_values(array_unique($itemYears));
    sort($itemYears);

    $metaYears = [];
    if (!empty($meta['years']) && is_array($meta['years'])) {
      foreach ($meta['years'] as $year) {
        $metaYears[] = (int) $year;
      }
    } elseif (is_array($totalByYear) && count($totalByYear)) {
      foreach ($totalByYear as $year => $value) {
        $metaYears[] = (int) $year;
      }
    }
    $metaYears = array_values(array_unique($metaYears));
    sort($metaYears);

    $effectiveYears = $itemYears;
    if (count($metaYears)) {
      $effectiveYears = array_values(array_intersect($itemYears, $metaYears));
      sort($effectiveYears);
    }

    if (count($effectiveYears)) {
      $latestYear = (int) end($effectiveYears);
      $prevYear = count($effectiveYears) > 1 ? (int) $effectiveYears[count($effectiveYears) - 2] : null;
      // Batasi totalByYear ke tahun yang benar-benar ada di item (dan meta jika ada)
      $filteredTotal = [];
      foreach ($effectiveYears as $year) {
        if (array_key_exists($year, $totalByYear)) {
          $filteredTotal[$year] = $totalByYear[$year];
        } elseif (array_key_exists((string) $year, $totalByYear)) {
          $filteredTotal[$year] = $totalByYear[(string) $year];
        }
      }
      if (!empty($filteredTotal)) {
        $totalByYear = $filteredTotal;
      }
    } else {
      $yearKeys = [];
      if (is_array($totalByYear)) {
        foreach ($totalByYear as $year => $value) {
          $yearKeys[] = (int) $year;
        }
      }
      sort($yearKeys);
      $latestYear = !empty($yearKeys) ? (int) end($yearKeys) : ($meta['latest_year'] ?? null);
      $prevYear = count($yearKeys) > 1 ? (int) $yearKeys[count($yearKeys) - 2] : ($meta['active_prev_year'] ?? ($meta['prev_year'] ?? null));
    }

    if ($latestYear === null) {
      return [];
    }

    $totalLatest = (int) ($totalByYear[$latestYear] ?? ($meta['total_world'] ?? 0));
    $totalPrev = $prevYear !== null ? (int) ($totalByYear[$prevYear] ?? 0) : null;

    $items = $payload['items'] ?? [];
    $items = array_values(array_filter($items, function ($row) use ($latestYear) {
      $values = $row['nilai_investasi'] ?? null;
      return is_array($values) && array_key_exists($latestYear, $values);
    }));
    if (empty($items) && $totalLatest === 0) {
      return [];
    }

    $sorted = $items;
    usort($sorted, function ($a, $b) use ($latestYear) {
      return ($b['nilai_investasi'][$latestYear] ?? 0) <=> ($a['nilai_investasi'][$latestYear] ?? 0);
    });
    $topPartners = array_slice($sorted, 0, 5);
    $topTable = array_slice($sorted, 0, 10);

    $partnerRows = array_map(function ($row) use ($latestYear, $prevYear) {
      $latest = $latestYear !== null ? (int) ($row['nilai_investasi'][$latestYear] ?? 0) : 0;
      $prev = $prevYear !== null ? (int) ($row['nilai_investasi'][$prevYear] ?? 0) : null;
      $delta = $prevYear !== null ? $latest - (int) $prev : null;
      $pct = $prevYear !== null ? $this->pctChange($latest, (int) $prev) : null;
      return [
        'negara' => $row['negara'] ?? '-',
        'latest' => $latest,
        'prev' => $prev,
        'delta' => $delta,
        'pct' => $pct,
      ];
    }, $topTable);

    $partnerTotalsByYear = $this->buildInvestasiPartnerTotalsByYear($items, $effectiveYears);
    $trendSeries = $this->buildTrendSeries($partnerTotalsByYear);
    $partnerSeries = $this->buildPartnerSeries($topPartners, $latestYear, 'nilai_investasi');
    $trendLineChart = $this->buildLineChartImageGd($trendSeries);
    $top5BarChart = $this->buildTop5BarChartImageGd($partnerSeries);

    $topPartnersDesc = $this->buildTopListDescription($topTable, $latestYear, 'negara', 'nilai_investasi', 3);

    return [
      'latestYear' => $latestYear,
      'prevYear' => $prevYear,
      'totalLatest' => $totalLatest,
      'totalPrev' => $totalPrev,
      'topPartners' => $topPartners,
      'topPartnersDesc' => $topPartnersDesc,
      'partnerRows' => $partnerRows,
      'partnerCount' => count($items),
      'trendLineChart' => $trendLineChart,
      'top5BarChart' => $top5BarChart,
      'unit' => $unit,
    ];
  }

  private function extractInvestasiItemYears(?array $payload): array
  {
    if (!$payload || !is_array($payload)) return [];
    $years = [];
    $items = $payload['items'] ?? [];
    foreach ($items as $row) {
      if (empty($row['nilai_investasi']) || !is_array($row['nilai_investasi'])) continue;
      foreach ($row['nilai_investasi'] as $year => $value) {
        $years[] = (int) $year;
      }
    }
    $years = array_values(array_unique($years));
    sort($years);
    return $years;
  }

  private function buildInvestasiPartnerTotalsByYear(array $items, array $years): array
  {
    if (empty($years)) {
      $years = [];
      foreach ($items as $row) {
        $values = $row['nilai_investasi'] ?? null;
        if (!is_array($values)) continue;
        foreach ($values as $year => $value) {
          $years[] = (int) $year;
        }
      }
      $years = array_values(array_unique($years));
      sort($years);
    }

    $totals = array_fill_keys($years, 0);
    foreach ($items as $row) {
      $values = $row['nilai_investasi'] ?? null;
      if (!is_array($values)) continue;
      foreach ($years as $year) {
        if (array_key_exists($year, $values)) {
          $totals[$year] += (int) $values[$year];
        }
      }
    }

    return $totals;
  }

  private function buildSummaryNarrativeInvestasi(array $data): string
  {
    $parts = [];
    $latestYear = $data['latestYear'] ?? null;
    $prevYear = $data['prevYear'] ?? null;
    $unit = $data['unit'] ?? 'Ribu US$';
    $in = $data['inbound'] ?? null;
    $out = $data['outbound'] ?? null;

    if ($latestYear !== null && $in) {
      $growth = $prevYear !== null ? $this->pctChange($in['totalLatest'], (int) ($in['totalPrev'] ?? 0)) : null;
      $growthText = $growth === null ? '' : ' (perubahan ' . ($growth >= 0 ? '+' : '') . number_format($growth, 2, ',', '.') . '% dari ' . $prevYear . ')';
      $parts[] = "Tahun {$latestYear} mencatat investasi masuk sebesar " . number_format($in['totalLatest'], 0, ',', '.') . " {$unit}" . $growthText . ".";
      if (!empty($in['topPartnersDesc'])) {
        $parts[] = "Tiga negara asal investasi terbesar adalah {$in['topPartnersDesc']}.";
      }
      $parts[] = "Kinerja ini menunjukkan daya tarik Indonesia bagi investor utama serta mengindikasikan kebutuhan penguatan promosi dan kemudahan investasi.";
    }

    if ($latestYear !== null && $out && ($out['totalLatest'] ?? 0) > 0) {
      $growthOut = $prevYear !== null ? $this->pctChange($out['totalLatest'], (int) ($out['totalPrev'] ?? 0)) : null;
      $growthOutText = $growthOut === null ? '' : ' (perubahan ' . ($growthOut >= 0 ? '+' : '') . number_format($growthOut, 2, ',', '.') . '% dari ' . $prevYear . ')';
      $parts[] = "Pada periode yang sama, investasi keluar tercatat sebesar " . number_format($out['totalLatest'], 0, ',', '.') . " {$unit}" . $growthOutText . ".";
    }

    if (empty($parts)) {
      $parts[] = "Data investasi untuk periode ini belum tersedia secara lengkap.";
    }

    return implode(' ', $parts);
  }

  private function resolveLatestYearFromMeta(?array $inMeta, ?array $outMeta, ?int $fallback): ?int
  {
    $years = [];
    foreach ([$inMeta, $outMeta] as $meta) {
      if (!$meta) continue;
      $series = $meta['total_world_per_year'] ?? [];
      if (is_array($series) && count($series)) {
        foreach ($series as $year => $value) {
          $years[] = (int) $year;
        }
      } elseif (!empty($meta['years']) && is_array($meta['years'])) {
        foreach ($meta['years'] as $year) {
          $years[] = (int) $year;
        }
      } elseif (!empty($meta['latest_year'])) {
        $years[] = (int) $meta['latest_year'];
      }
    }
    if (count($years)) {
      return max($years);
    }
    return $fallback;
  }

  private function buildTourismSummaryBlock(array $payload, array $meta, string $unit): array
  {
    $years = $meta['years'] ?? [];
    sort($years);
    $latestYear = !empty($years) ? (int) end($years) : null;
    $prevYear = null;
    if (count($years) >= 2) {
      $prevYear = (int) $years[count($years) - 2];
    }

    $totalByYear = $meta['total_world_per_year'] ?? [];
    $totalLatest = $latestYear !== null ? (int) ($totalByYear[$latestYear] ?? ($meta['total_world'] ?? 0)) : 0;
    $totalPrev = $prevYear !== null ? (int) ($totalByYear[$prevYear] ?? 0) : null;

    $items = $payload['items'] ?? [];
    if (empty($items) && $totalLatest === 0) {
      return [];
    }
    $sorted = $items;
    usort($sorted, function ($a, $b) use ($latestYear) {
      return ($b['Jumlah_Wisatawan'][$latestYear] ?? 0) <=> ($a['Jumlah_Wisatawan'][$latestYear] ?? 0);
    });
    $topPartners = array_slice($sorted, 0, 5);
    $topTable = array_slice($sorted, 0, 10);

    $partnerRows = array_map(function ($row) use ($latestYear, $prevYear) {
      $latest = $latestYear !== null ? (int) ($row['Jumlah_Wisatawan'][$latestYear] ?? 0) : 0;
      $prev = $prevYear !== null ? (int) ($row['Jumlah_Wisatawan'][$prevYear] ?? 0) : null;
      $delta = $prevYear !== null ? $latest - (int) $prev : null;
      $pct = $prevYear !== null ? $this->pctChange($latest, (int) $prev) : null;
      return [
        'negara' => $row['negara'] ?? '-',
        'latest' => $latest,
        'prev' => $prev,
        'delta' => $delta,
        'pct' => $pct,
      ];
    }, $topTable);

    $trendSeries = $this->buildTrendSeries($totalByYear);
    $partnerSeries = $this->buildPartnerSeries($topPartners, $latestYear, 'Jumlah_Wisatawan');
    $trendLineChart = $this->buildLineChartImageGd($trendSeries);
    $top5BarChart = $this->buildTop5BarChartImageGd($partnerSeries);

    $topPartnersDesc = $this->buildTopListDescription($topTable, $latestYear, 'negara', 'Jumlah_Wisatawan', 3);

    return [
      'latestYear' => $latestYear,
      'prevYear' => $prevYear,
      'totalLatest' => $totalLatest,
      'totalPrev' => $totalPrev,
      'topPartners' => $topPartners,
      'topPartnersDesc' => $topPartnersDesc,
      'partnerRows' => $partnerRows,
      'partnerCount' => count($items),
      'trendLineChart' => $trendLineChart,
      'top5BarChart' => $top5BarChart,
      'unit' => $unit,
    ];
  }

  private function buildSummaryNarrativePariwisata(array $data): string
  {
    $parts = [];
    $latestYear = $data['latestYear'] ?? null;
    $prevYear = $data['prevYear'] ?? null;
    $unit = $data['unit'] ?? 'Orang';
    $in = $data['inbound'] ?? null;
    $out = $data['outbound'] ?? null;

    if ($latestYear !== null && $in) {
      $growth = $prevYear !== null ? $this->pctChange($in['totalLatest'], (int) ($in['totalPrev'] ?? 0)) : null;
      $growthText = $growth === null ? '' : ' (perubahan ' . ($growth >= 0 ? '+' : '') . number_format($growth, 2, ',', '.') . '% dari ' . $prevYear . ')';
      $parts[] = "Tahun {$latestYear} mencatat wisatawan masuk sebesar " . number_format($in['totalLatest'], 0, ',', '.') . " {$unit}" . $growthText . ".";
      if (!empty($in['topPartnersDesc'])) {
        $parts[] = "Tiga negara asal wisatawan terbesar adalah {$in['topPartnersDesc']}.";
      }
      $parts[] = "Komposisi ini menunjukkan konsentrasi kunjungan pada beberapa negara utama, sehingga strategi promosi dan konektivitas dapat difokuskan pada pasar yang berkontribusi terbesar.";
    }

    if ($latestYear !== null && $out && ($out['totalLatest'] ?? 0) > 0) {
      $growthOut = $prevYear !== null ? $this->pctChange($out['totalLatest'], (int) ($out['totalPrev'] ?? 0)) : null;
      $growthOutText = $growthOut === null ? '' : ' (perubahan ' . ($growthOut >= 0 ? '+' : '') . number_format($growthOut, 2, ',', '.') . '% dari ' . $prevYear . ')';
      $parts[] = "Pada periode yang sama, wisatawan keluar tercatat sebesar " . number_format($out['totalLatest'], 0, ',', '.') . " {$unit}" . $growthOutText . ".";
      $parts[] = "Perbandingan inbound dan outbound memberikan gambaran keseimbangan arus wisatawan serta potensi devisa dari sektor pariwisata.";
    }

    if (empty($parts)) {
      $parts[] = "Data pariwisata untuk periode ini belum tersedia secara lengkap.";
    }

    return implode(' ', $parts);
  }

  private function buildPartnerHeadline(array $partners, array $partnerMap): ?string
  {
    if (empty($partners)) return null;
    $labels = [];
    foreach ($partners as $p) {
      $key = strtoupper((string) $p);
      if ($key === '') continue;
      $labels[] = $partnerMap[$key] ?? $key;
    }
    $labels = array_values(array_unique($labels));
    if (!count($labels)) return null;
    return 'Tujuan negara: ' . implode(', ', $labels);
  }

  private function collectPartnerMap(?array $inbound, ?array $outbound): array
  {
    $map = [];
    foreach ([$inbound, $outbound] as $payload) {
      if (!$payload || empty($payload['items'])) continue;
      foreach ($payload['items'] as $row) {
        $name = $row['negara'] ?? null;
        $a3 = strtoupper((string) ($row['kode_alpha3'] ?? ''));
        $a2 = strtoupper((string) ($row['kode_alpha2'] ?? ''));
        if ($name) {
          if ($a3 !== '') $map[$a3] = $name;
          if ($a2 !== '') $map[$a2] = $name;
        }
      }
    }
    return $map;
  }

  private function buildTotalSummaryRow(string $label, int $latest, ?int $prev): array
  {
    $delta = $prev !== null ? $latest - $prev : null;
    $pct = $prev !== null ? $this->pctChange($latest, $prev) : null;
    return [
      'label' => $label,
      'latest' => $latest,
      'prev' => $prev,
      'delta' => $delta,
      'pct' => $pct,
    ];
  }

  private function buildTujuanListDescription(array $rows, string $key, int $limit = 6): ?string
  {
    if (empty($rows)) return null;
    $list = [];
    foreach ($rows as $row) {
      $raw = $row[$key] ?? '';
      if (!$raw) continue;
      $parts = array_map('trim', explode(',', (string) $raw));
      foreach ($parts as $p) {
        if ($p !== '') $list[] = $p;
      }
    }
    $list = array_values(array_unique($list));
    if (!count($list)) return null;
    $list = array_slice($list, 0, $limit);
    return implode(', ', $list);
  }

  private function buildSummaryNarrativeBilateral(array $data): string
  {
    $latestYear = $data['latestYear'] ?? null;
    $prevYear = $data['prevYear'] ?? null;
    $totalTradeLatest = $data['totalTradeLatest'] ?? 0;
    $totalTradePrev = $data['totalTradePrev'] ?? 0;
    $totalExportLatest = $data['totalExportLatest'] ?? 0;
    $totalExportPrev = $data['totalExportPrev'] ?? 0;
    $totalImportLatest = $data['totalImportLatest'] ?? 0;
    $totalImportPrev = $data['totalImportPrev'] ?? 0;
    $totalBalanceLatest = $data['totalBalanceLatest'] ?? 0;
    $totalBalancePrev = $data['totalBalancePrev'] ?? 0;
    $topPartnersDesc = $data['topPartnersDesc'] ?? null;
    $topKomoditasDesc = $data['topKomoditasDesc'] ?? null;

    $parts = [];
    if ($latestYear !== null) {
      if ($prevYear !== null) {
        $growth = $this->pctChange($totalTradeLatest, (int) $totalTradePrev);
        $growthText = $growth === null ? '' : ' (perubahan ' . ($growth >= 0 ? '+' : '') . number_format($growth, 2, ',', '.') . '% dari ' . $prevYear . ')';
        $parts[] = "Pada {$latestYear}, total nilai perdagangan bilateral mencapai " . number_format($totalTradeLatest, 0, ',', '.') . " Ribu US$" . $growthText . ".";
      } else {
        $parts[] = "Pada {$latestYear}, total nilai perdagangan bilateral mencapai " . number_format($totalTradeLatest, 0, ',', '.') . " Ribu US$.";
      }
    }
    if ($latestYear !== null) {
      $parts[] = "Nilai ekspor tercatat sebesar " . number_format($totalExportLatest, 0, ',', '.') . " Ribu US$, sementara impor sebesar " . number_format($totalImportLatest, 0, ',', '.') . " Ribu US$, dengan neraca perdagangan " . number_format($totalBalanceLatest, 0, ',', '.') . " Ribu US$.";
      if ($prevYear !== null) {
        $parts[] = "Dibanding {$prevYear}, ekspor berubah " . $this->formatSignedPct($totalExportLatest, (int) $totalExportPrev) . ", impor berubah " . $this->formatSignedPct($totalImportLatest, (int) $totalImportPrev) . ", dan neraca berubah " . $this->formatSignedPct($totalBalanceLatest, (int) $totalBalancePrev) . ".";
      }
    }
    if ($topPartnersDesc) {
      $parts[] = "Tiga mitra dagang dengan nilai terbesar adalah {$topPartnersDesc}.";
    }
    if ($topKomoditasDesc) {
      $parts[] = "Komoditas utama yang mendominasi perdagangan meliputi {$topKomoditasDesc}.";
    }
    return implode(' ', $parts);
  }

  private function formatSignedPct(int $latest, int $prev): string
  {
    $pct = $this->pctChange($latest, $prev);
    if ($pct === null) return 'n/a';
    return ($pct >= 0 ? '+' : '') . number_format($pct, 2, ',', '.') . '%';
  }

  private function buildTopListDescription(array $rows, ?int $year, string $nameKey, string $valueKey, int $limit = 3, ?string $codeKey = null): ?string
  {
    if ($year === null) return null;
    $top = array_slice($rows, 0, $limit);
    $parts = [];
    foreach ($top as $row) {
      $name = $row[$nameKey] ?? '-';
      if ($codeKey && isset($row[$codeKey])) {
        $name = ($row[$codeKey] ?? '-') . ' - ' . $name;
      }
      $value = $row[$valueKey][$year] ?? 0;
      $parts[] = $name . ' (' . number_format((int) $value, 0, ',', '.') . ' Ribu US$)';
    }
    return count($parts) ? implode(', ', $parts) : null;
  }

  private function buildTrendSeries(array $series): array
  {
    $years = array_keys($series);
    sort($years);
    $values = [];
    foreach ($years as $y) {
      $values[] = (int) ($series[$y] ?? 0);
    }
    return ['years' => $years, 'values' => $values];
  }

  private function buildPartnerSeries(array $rows, ?int $year, string $valueKey = 'nilai_perdagangan'): array
  {
    $labels = [];
    $values = [];
    foreach ($rows as $row) {
      $labels[] = $row['negara'] ?? '-';
      $values[] = $year !== null ? (int) ($row[$valueKey][$year] ?? 0) : 0;
    }
    return ['labels' => $labels, 'values' => $values];
  }

  private function buildLineChartImageGd(array $series): ?string
  {
    $years = $series['years'] ?? [];
    $values = $series['values'] ?? [];
    if (count($years) < 2 || count($values) < 2) return null;

    $width = 560;
    $height = 220;
    $paddingLeft = 50;
    $paddingRight = 14;
    $paddingTop = 18;
    $paddingBottom = 34;

    $img = imagecreatetruecolor($width, $height);
    imagealphablending($img, true);
    imagesavealpha($img, true);

    $white = imagecolorallocate($img, 255, 255, 255);
    $bg = imagecolorallocate($img, 248, 250, 252);
    $grid = imagecolorallocate($img, 226, 232, 240);
    $line = imagecolorallocate($img, 37, 99, 235);
    $dot = imagecolorallocate($img, 29, 78, 216);
    $text = imagecolorallocate($img, 100, 116, 139);
    $fill = imagecolorallocatealpha($img, 59, 130, 246, 88);

    imagefill($img, 0, 0, $white);
    imagefilledrectangle($img, 0, 0, $width, $height, $bg);

    $max = max($values);
    $min = 0;
    $range = max(1, $max - $min);

    $plotWidth = $width - $paddingLeft - $paddingRight;
    $plotHeight = $height - $paddingTop - $paddingBottom;

    for ($i = 0; $i <= 4; $i++) {
      $y = $paddingTop + (int) ($plotHeight * $i / 4);
      imageline($img, $paddingLeft, $y, $width - $paddingRight, $y, $grid);
      $labelVal = (int) round($max - ($range * $i / 4));
      imagestring($img, 2, 6, $y - 6, number_format($labelVal, 0, ',', '.'), $text);
    }

    $points = [];
    $count = count($values);
    for ($i = 0; $i < $count; $i++) {
      $x = $paddingLeft + (int) ($plotWidth * ($count === 1 ? 0 : $i / ($count - 1)));
      $val = $values[$i];
      if ($val < 0) $val = 0;
      $y = $paddingTop + (int) ($plotHeight * (1 - (($val - $min) / $range)));
      $points[] = [$x, $y];
      imagestring($img, 2, $x - 10, $height - 18, (string) $years[$i], $text);
    }

    $polygon = [$paddingLeft, $paddingTop + $plotHeight];
    foreach ($points as $pt) {
      $polygon[] = $pt[0];
      $polygon[] = $pt[1];
    }
    $polygon[] = $paddingLeft + $plotWidth;
    $polygon[] = $paddingTop + $plotHeight;
    imagefilledpolygon($img, $polygon, count($polygon) / 2, $fill);

    for ($i = 1; $i < count($points); $i++) {
      imageline($img, $points[$i - 1][0], $points[$i - 1][1], $points[$i][0], $points[$i][1], $line);
    }

    foreach ($points as $pt) {
      imagefilledellipse($img, $pt[0], $pt[1], 8, 8, $dot);
      imageellipse($img, $pt[0], $pt[1], 8, 8, $white);
    }

    ob_start();
    imagepng($img);
    $data = ob_get_clean();
    $img = null;

    return 'data:image/png;base64,' . base64_encode($data);
  }

  private function buildTop5BarChartImageGd(array $series): ?string
  {
    $labels = $series['labels'] ?? [];
    $values = $series['values'] ?? [];
    if (count($labels) === 0 || count($values) === 0) return null;

    $width = 560;
    $height = 220;
    $paddingLeft = 110;
    $paddingRight = 28;
    $paddingTop = 16;
    $paddingBottom = 16;

    $img = imagecreatetruecolor($width, $height);
    imagealphablending($img, true);
    imagesavealpha($img, true);

    $white = imagecolorallocate($img, 255, 255, 255);
    $bg = imagecolorallocate($img, 248, 250, 252);
    $bar = imagecolorallocate($img, 245, 158, 11);
    $track = imagecolorallocate($img, 226, 232, 240);
    $text = imagecolorallocate($img, 15, 23, 42);
    $muted = imagecolorallocate($img, 100, 116, 139);

    imagefill($img, 0, 0, $white);
    imagefilledrectangle($img, 0, 0, $width, $height, $bg);

    $max = max($values);
    $max = max(1, $max);
    $count = count($values);
    $rowHeight = (int) (($height - $paddingTop - $paddingBottom) / max(1, $count));
    $barHeight = max(8, $rowHeight - 12);

    for ($i = 0; $i < $count; $i++) {
      $y = $paddingTop + $i * $rowHeight + 6;
      $label = mb_strtoupper((string) $labels[$i]);
      imagestring($img, 2, 8, $y, $label, $muted);
      $trackWidth = $width - $paddingLeft - $paddingRight;
      imagefilledrectangle($img, $paddingLeft, $y + 2, $paddingLeft + $trackWidth, $y + 2 + $barHeight, $track);
      $value = $values[$i];
      if ($value < 0) $value = 0;
      $barWidth = (int) (($trackWidth * $value) / $max);
      imagefilledrectangle($img, $paddingLeft, $y + 2, $paddingLeft + $barWidth, $y + 2 + $barHeight, $bar);
      $valueText = number_format((int) $values[$i], 0, ',', '.');
      $valueX = $paddingLeft + $barWidth - 4 - (strlen($valueText) * 6);
      $valueY = $y + 2;
      if ($barWidth > 50 && $valueX > $paddingLeft + 4) {
        imagestring($img, 2, $valueX, $valueY, $valueText, $white);
      } else {
        $rightX = min($paddingLeft + $trackWidth + 4, $width - 6 - (strlen($valueText) * 6));
        imagestring($img, 2, $rightX, $valueY, $valueText, $text);
      }
    }

    ob_start();
    imagepng($img);
    $data = ob_get_clean();
    $img = null;

    return 'data:image/png;base64,' . base64_encode($data);
  }

  private function pctChange(int $current, int $previous): ?float
  {
    if ($previous === 0) return null;
    return (($current - $previous) / $previous) * 100;
  }

  private function buildTujuanEksporRows(array $topProdukTable, ?int $latestYear, int $limitProduk = 10): array
  {
    if ($latestYear === null) return [];
    $rows = [];
    foreach (array_slice($topProdukTable, 0, $limitProduk) as $prod) {
      $tujuan = $prod['tujuan_ekspor'] ?? [];
      if (!is_array($tujuan) || empty($tujuan)) continue;
      $entries = array_map(function ($t) {
        return [
          'label' => $this->resolveCountryLabel(is_array($t) ? $t : []),
          'rank' => isset($t['rank']) && is_numeric($t['rank']) ? (int) $t['rank'] : null,
        ];
      }, $tujuan);
      $rows[] = [
        'kode' => $prod['kodeHS'] ?? '-',
        'nama' => $prod['namaHS'] ?? '-',
        'tujuan' => $this->formatNumberedList($entries, ', '),
      ];
    }
    return $rows;
  }

  private function buildTujuanImporRows(array $topProdukTable, ?int $latestYear, int $limitProduk = 10): array
  {
    if ($latestYear === null) return [];
    $rows = [];
    foreach (array_slice($topProdukTable, 0, $limitProduk) as $prod) {
      $tujuan = $prod['tujuan_impor'] ?? [];
      if (!is_array($tujuan) || empty($tujuan)) continue;
      $entries = array_map(function ($t) {
        return [
          'label' => $this->resolveCountryLabel(is_array($t) ? $t : []),
          'rank' => isset($t['rank']) && is_numeric($t['rank']) ? (int) $t['rank'] : null,
        ];
      }, $tujuan);
      $rows[] = [
        'kode' => $prod['kodeHS'] ?? '-',
        'nama' => $prod['namaHS'] ?? '-',
        'tujuan' => $this->formatNumberedList($entries, ', '),
      ];
    }
    return $rows;
  }

  private function buildKompetitorImporRows(array $topProdukTable, int $limitProduk = 10): array
  {
    $rows = [];
    foreach (array_slice($topProdukTable, 0, $limitProduk) as $prod) {
      $topTujuanImpor = $prod['tujuan_impor'][0] ?? null;
      $tujuanName = is_array($topTujuanImpor)
        ? $this->resolveCountryLabel($topTujuanImpor)
        : null;
      $kompetitor = $prod['kompetitor_global_top_tujuan_impor'] ?? [];
      if (!is_array($kompetitor) || empty($kompetitor)) continue;
      $rankIndonesia = null;
      foreach ($kompetitor as $k) {
        $a3 = strtoupper((string) ($k['kode_alpha3'] ?? ''));
        $name = strtoupper((string) ($k['negara'] ?? ''));
        if ($a3 === 'IDN' || $name === 'INDONESIA') {
          $rankIndonesia = $k['rank'] ?? null;
          break;
        }
      }
      $filtered = array_values(array_filter($kompetitor, function ($k) {
        $a3 = strtoupper((string) ($k['kode_alpha3'] ?? ''));
        $name = strtoupper((string) ($k['negara'] ?? ''));
        return $a3 !== 'IDN' && $name !== 'INDONESIA';
      }));
      if (empty($filtered)) continue;
      $top = $filtered[0];
      $rows[] = [
        'kode' => $prod['kodeHS'] ?? '-',
        'nama' => $prod['namaHS'] ?? '-',
        'tujuan' => $tujuanName ?? '-',
        'negara' => $this->resolveCountryLabel($top),
        'rank' => $rankIndonesia ?? '-',
        'nilai' => number_format((int) ($top['nilai'] ?? 0), 0, ',', '.'),
      ];
    }
    return $rows;
  }

  private function buildKompetitorEksporRows(array $topProdukTable, int $limitProduk = 10): array
  {
    $rows = [];
    foreach (array_slice($topProdukTable, 0, $limitProduk) as $prod) {
      $topTujuanEkspor = $prod['tujuan_ekspor'][0] ?? null;
      $tujuanName = is_array($topTujuanEkspor)
        ? $this->resolveCountryLabel($topTujuanEkspor)
        : null;
      $kompetitor = $prod['kompetitor_global_top_tujuan_ekspor'] ?? [];
      if (!is_array($kompetitor) || empty($kompetitor)) continue;
      $rankIndonesia = null;
      foreach ($kompetitor as $k) {
        $a3 = strtoupper((string) ($k['kode_alpha3'] ?? ''));
        $name = strtoupper((string) ($k['negara'] ?? ''));
        if ($a3 === 'IDN' || $name === 'INDONESIA') {
          $rankIndonesia = $k['rank'] ?? null;
          break;
        }
      }
      $filtered = array_values(array_filter($kompetitor, function ($k) {
        $a3 = strtoupper((string) ($k['kode_alpha3'] ?? ''));
        $name = strtoupper((string) ($k['negara'] ?? ''));
        return $a3 !== 'IDN' && $name !== 'INDONESIA';
      }));
      if (empty($filtered)) continue;
      $top = $filtered[0];
      $rows[] = [
        'kode' => $prod['kodeHS'] ?? '-',
        'nama' => $prod['namaHS'] ?? '-',
        'tujuan' => $tujuanName ?? '-',
        'negara' => $this->resolveCountryLabel($top),
        'rank' => $rankIndonesia ?? '-',
        'nilai' => number_format((int) ($top['nilai'] ?? 0), 0, ',', '.'),
      ];
    }
    return $rows;
  }

  private function resolveCountryLabel(array $row): string
  {
    $name = trim((string) ($row['negara'] ?? ''));
    if ($name !== '') return $name;

    $a3 = strtoupper(trim((string) ($row['kode_alpha3'] ?? '')));
    if ($a3 !== '') return $a3;

    return '-';
  }

  private function formatNumberedList(array $items, string $separator = ', '): string
  {
    $clean = [];
    foreach ($items as $i => $item) {
      if (is_array($item)) {
        $label = trim((string) ($item['label'] ?? ''));
        $rank = isset($item['rank']) && is_numeric($item['rank']) ? (int) $item['rank'] : null;
      } else {
        $label = trim((string) $item);
        $rank = null;
      }
      if ($label === '') continue;
      $clean[] = [
        'label' => $label,
        'rank' => $rank,
        'index' => $i + 1,
      ];
    }

    if (empty($clean)) {
      return '-';
    }

    if (count($clean) === 1) {
      $single = $clean[0]['label'];
      return function_exists('mb_strtoupper') ? mb_strtoupper($single, 'UTF-8') : strtoupper($single);
    }

    $out = [];
    foreach ($clean as $row) {
      $label = function_exists('mb_strtoupper') ? mb_strtoupper($row['label'], 'UTF-8') : strtoupper($row['label']);
      $no = $row['rank'] ?? $row['index'];
      $out[] = $no . ') ' . $label;
    }

    return implode($separator, $out);
  }

  /** TTL cache 3 hari */
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
    $key = strtolower(trim((string) $raw));
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

  private function normalizeSourceCode($value): ?int
  {
    if (is_numeric($value)) {
      return (int) $value;
    }
    if (is_string($value)) {
      $digits = preg_replace('/\\D+/', '', $value);
      if ($digits !== '') {
        return (int) $digits;
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

  private function normalizeFilters(Request $request): array
  {
    $ys = $request->input('year_start');
    $ye = $request->input('year_end');
    $yearStart = is_numeric($ys) ? (int) $ys : null;
    $yearEnd   = is_numeric($ye) ? (int) $ye : null;

    $hsIn = $request->input('hs');
    $hs   = is_numeric($hsIn) ? (int) $hsIn : null;

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
        $d = preg_replace('/\D+/', '', (string) $v);
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
    $s = strtolower(trim((string) $v));
    if (in_array($s, ['inbound', 'masuk'], true)) return 'inbound';
    if (in_array($s, ['outbound', 'keluar'], true)) return 'outbound';
    return null;
  }

  private function canonTradeStatus($v): ?string
  {
    if (is_array($v)) {
      $v = $v[0] ?? null;
    }
    $s = strtolower(trim((string) $v));
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
    $dest = $this->csvToUpperArray($request->input('dest', $request->input('tujuan', [])));
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
    $hasDestIdn = in_array('IDN', $dests, true);

    if ($hasOriginIdn || $hasDestIdn) {
      $reverse = $this->reverseTradeStatus($tradeStatus);
      if ($reverse !== null) {
        $tradeFilters['status'] = $reverse;
      }
      $tradeFilters['origin'] = $dests;
      $tradeFilters['dest'] = $origins;
    }

    return $tradeFilters;
  }

  private function buildCacheKeyFromRequest(string $prefix, Request $request): string
  {
    $filters = $this->sortRecursive($request->all());
    return SideCacheKey::pairs(['indonesia', 'kerjasama-bilateral-summary', $prefix], $filters);
  }

  private function buildCacheKey(string $prefix, array $filters): string
  {
    $filters = $this->sortRecursive($filters);
    return SideCacheKey::pairs(['indonesia', 'kerjasama-bilateral-summary', $prefix], $filters);
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

  private function isAssocArray(array $arr): bool
  {
    return array_keys($arr) !== range(0, count($arr) - 1);
  }

  private function csvToUpperArray($raw): array
  {
    if (is_array($raw)) {
      $arr = $raw;
    } elseif (is_string($raw)) {
      $arr = array_map('trim', explode(',', $raw));
    } else {
      $arr = [];
    }

    $arr = array_map(fn ($v) => strtoupper((string) $v), $arr);
    $arr = array_values(array_filter($arr, fn ($v) => $v !== ''));
    return array_values(array_unique($arr));
  }
}
