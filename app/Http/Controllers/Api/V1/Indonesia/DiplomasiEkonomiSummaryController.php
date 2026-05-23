<?php

namespace App\Http\Controllers\Api\V1\Indonesia;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Support\SideCacheKey;
use App\Services\Indonesia\EconomyDiplomationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DiplomasiEkonomiSummaryController extends Controller
{
  public function __construct(protected EconomyDiplomationService $economyDiplomationService) {}

  private const DEFAULT_SOURCES = [
    'perdagangan' => 1,
    'pariwisata'  => 1,
    'investasi'   => 6,
    'bantuan'     => 21,
  ];

  public function pdf(Request $request)
  {
    $stopProfile = $this->startProfiling('nilai_perdagangan_summary_pdf');
    try {
      $filters = $this->normalizeFilters($request);
      $filters['strict_source_years'] = true;
      $sources = $this->normalizeSources($request);
      $sourceCode = $this->sourceForSector($sources, 'perdagangan');

      $baseKey = $this->buildSectorCacheKey('nilai-perdagangan', $filters, $sourceCode);
      $baseData = Cache::remember($baseKey, $this->cacheTtl3Days(), fn () => $this->economyDiplomationService->getNilaiPerdagangan($filters, $sourceCode));

      if (empty($baseData) || empty($baseData['meta'])) {
        return ApiResponse::error('Tidak ada data untuk dibuatkan ringkasan.', null, 404);
      }

      $years = $baseData['meta']['years'] ?? [];
      sort($years);
      $latestYear = !empty($years) ? (int) end($years) : null;
      $prevYear = null;
      if (count($years) >= 2) {
        $prevYear = (int) $years[count($years) - 2];
      }

      if ($latestYear === null) {
        return ApiResponse::error('Data tidak tersedia untuk rentang tahun yang diminta.', null, 404);
      }

      $totalTradeByYear = $baseData['meta']['total_world_per_year'] ?? [];
      $totalTradeLatest = $latestYear !== null ? (int) ($totalTradeByYear[$latestYear] ?? ($baseData['meta']['total_world'] ?? 0)) : 0;
      $totalTradePrev = $prevYear !== null ? (int) ($totalTradeByYear[$prevYear] ?? 0) : null;

      $exportFilters = array_merge($filters, ['status' => 'Export']);
      $importFilters = array_merge($filters, ['status' => 'Import']);

      $exportKey = $this->buildSectorCacheKey('nilai-perdagangan-export', $exportFilters, $sourceCode);
      $importKey = $this->buildSectorCacheKey('nilai-perdagangan-import', $importFilters, $sourceCode);

      $exportData = Cache::remember($exportKey, $this->cacheTtl3Days(), fn () => $this->economyDiplomationService->getNilaiPerdagangan($exportFilters, $sourceCode));
      $importData = Cache::remember($importKey, $this->cacheTtl3Days(), fn () => $this->economyDiplomationService->getNilaiPerdagangan($importFilters, $sourceCode));

      $exportByYear = $exportData['meta']['total_world_per_year'] ?? [];
      $importByYear = $importData['meta']['total_world_per_year'] ?? [];
      $totalExportLatest = $latestYear !== null ? (int) ($exportByYear[$latestYear] ?? ($exportData['meta']['total_world'] ?? 0)) : 0;
      $totalImportLatest = $latestYear !== null ? (int) ($importByYear[$latestYear] ?? ($importData['meta']['total_world'] ?? 0)) : 0;

      $items = $baseData['items'] ?? [];
      $topPartners = array_slice($items, 0, 5);
      $topPartnersTable = array_slice($items, 0, 10);

      $topProduk = $baseData['top_produk'] ?? [];
      $topProdukTable = array_slice($topProduk, 0, 10);

      $topPartnersDesc = $this->buildTopListDescription($topPartnersTable, $latestYear, 'negara', 'nilai_perdagangan', 3);
      $topKomoditasDesc = $this->buildTopListDescription($topProdukTable, $latestYear, 'namaHS', 'nilai', 3, 'kodeHS');

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

      $trendSeries = $this->buildTrendSeries($totalTradeByYear);
      $partnerSeries = $this->buildPartnerSeries($topPartners, $latestYear);
      $trendLineChart = $this->buildLineChartImageGd($trendSeries);
      $top5BarChart = $this->buildTop5BarChartImageGd($partnerSeries);

      $topPartnerName = null;
      $topPartnerValue = 0;
      if (!empty($topPartners)) {
        $topPartnerName = $topPartners[0]['negara'] ?? null;
        $topPartnerValue = $latestYear !== null ? (int) ($topPartners[0]['nilai_perdagangan'][$latestYear] ?? 0) : 0;
      }

      $topCommodity = $topProdukTable[0] ?? null;
      $topCommodityName = $topCommodity['namaHS'] ?? null;
      $topCommodityValue = $latestYear !== null ? (int) ($topCommodity['nilai'][$latestYear] ?? 0) : 0;

      $summaryNarrative = $this->buildSummaryNarrative([
        'latestYear' => $latestYear,
        'prevYear' => $prevYear,
        'totalTradeLatest' => $totalTradeLatest,
        'totalTradePrev' => $totalTradePrev,
        'totalExportLatest' => $totalExportLatest,
        'totalImportLatest' => $totalImportLatest,
        'topPartnerName' => $topPartnerName,
        'topPartnerValue' => $topPartnerValue,
        'topCommodityName' => $topCommodityName,
        'topCommodityValue' => $topCommodityValue,
        'topPartnersDesc' => $topPartnersDesc,
        'topKomoditasDesc' => $topKomoditasDesc,
      ]);

      $tanggalCetak = now()->translatedFormat('d F Y');
      $unit = $baseData['meta']['unit'] ?? 'Ribu US$';
      $sourceName = $baseData['meta']['sumber'] ?? 'BPS';

      $pdf = Pdf::loadView('exports.nilai-perdagangan-summary', [
        'tanggalCetak' => $tanggalCetak,
        'unit' => $unit,
        'sourceName' => $sourceName,
        'latestYear' => $latestYear,
        'prevYear' => $prevYear,
        'totalTradeLatest' => $totalTradeLatest,
        'totalExportLatest' => $totalExportLatest,
        'totalImportLatest' => $totalImportLatest,
        'summaryNarrative' => $summaryNarrative,
        'trendSeries' => $trendSeries,
        'trendLineChart' => $trendLineChart,
        'top5BarChart' => $top5BarChart,
        'partnerSeries' => $partnerSeries,
        'partnerRows' => $partnerRows,
        'produkRows' => $produkRows,
      ])->setPaper('a4', 'portrait');

      $filename = 'nilai-perdagangan-summary-' . now()->format('Ymd_His') . '.pdf';

      return response($pdf->output(), 200)
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    } finally {
      if (isset($stopProfile)) $stopProfile();
    }
  }

  public function totalEksporPdf(Request $request)
  {
    $stopProfile = $this->startProfiling('total_ekspor_summary_pdf');
    try {
      $filters = $this->normalizeFilters($request);
      $filters['strict_source_years'] = true;
      $filters['status'] = 'export';
      $sources = $this->normalizeSources($request);
      $sourceCode = $this->sourceForSector($sources, 'perdagangan');

      $baseKey = $this->buildSectorCacheKey('total-ekspor', $filters, $sourceCode);
      $baseData = Cache::remember($baseKey, $this->cacheTtl3Days(), fn () => $this->economyDiplomationService->getNilaiPerdagangan($filters, $sourceCode));

      if (empty($baseData) || empty($baseData['meta'])) {
        return ApiResponse::error('Tidak ada data untuk dibuatkan ringkasan.', null, 404);
      }

      $years = $baseData['meta']['years'] ?? [];
      sort($years);
      $latestYear = !empty($years) ? (int) end($years) : null;
      $prevYear = null;
      if (count($years) >= 2) {
        $prevYear = (int) $years[count($years) - 2];
      }

      if ($latestYear === null) {
        return ApiResponse::error('Data tidak tersedia untuk rentang tahun yang diminta.', null, 404);
      }

      $totalTradeByYear = $baseData['meta']['total_world_per_year'] ?? [];
      $totalExportLatest = (int) ($totalTradeByYear[$latestYear] ?? ($baseData['meta']['total_world'] ?? 0));
      $totalExportPrev = $prevYear !== null ? (int) ($totalTradeByYear[$prevYear] ?? 0) : null;

      $items = $baseData['items'] ?? [];
      $topPartners = array_slice($items, 0, 5);
      $topPartnersTable = array_slice($items, 0, 10);

      $topProduk = $baseData['top_produk'] ?? [];
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

      $trendSeries = $this->buildTrendSeries($totalTradeByYear);
      $partnerSeries = $this->buildPartnerSeries($topPartners, $latestYear);
      $trendLineChart = $this->buildLineChartImageGd($trendSeries);
      $top5BarChart = $this->buildTop5BarChartImageGd($partnerSeries);

      $topPartnerName = null;
      $topPartnerValue = 0;
      if (!empty($topPartners)) {
        $topPartnerName = $topPartners[0]['negara'] ?? null;
        $topPartnerValue = $latestYear !== null ? (int) ($topPartners[0]['nilai_perdagangan'][$latestYear] ?? 0) : 0;
      }

      $topCommodity = $topProdukTable[0] ?? null;
      $topCommodityName = $topCommodity['namaHS'] ?? null;
      $topCommodityValue = $latestYear !== null ? (int) ($topCommodity['nilai'][$latestYear] ?? 0) : 0;

      $topPartnersDesc = $this->buildTopListDescription($topPartnersTable, $latestYear, 'negara', 'nilai_perdagangan', 3);
      $topKomoditasDesc = $this->buildTopListDescription($topProdukTable, $latestYear, 'namaHS', 'nilai', 3, 'kodeHS');

      $summaryNarrative = $this->buildSummaryNarrativeExport([
        'latestYear' => $latestYear,
        'prevYear' => $prevYear,
        'totalExportLatest' => $totalExportLatest,
        'totalExportPrev' => $totalExportPrev,
        'topPartnerName' => $topPartnerName,
        'topPartnerValue' => $topPartnerValue,
        'topCommodityName' => $topCommodityName,
        'topCommodityValue' => $topCommodityValue,
        'topPartnersDesc' => $topPartnersDesc,
        'topKomoditasDesc' => $topKomoditasDesc,
      ]);

      $tujuanEksporRows = $this->buildTujuanEksporRows($topProdukTable, $latestYear, 10);
      $kompetitorRows = $this->buildKompetitorEksporRows($topProdukTable, 10, 1);

      $tanggalCetak = now()->translatedFormat('d F Y');
      $unit = $baseData['meta']['unit'] ?? 'Ribu US$';
      $sourceName = $baseData['meta']['sumber'] ?? 'BPS';

      $pdf = Pdf::loadView('exports.total-ekspor-summary', [
        'tanggalCetak' => $tanggalCetak,
        'unit' => $unit,
        'sourceName' => $sourceName,
        'latestYear' => $latestYear,
        'prevYear' => $prevYear,
        'totalExportLatest' => $totalExportLatest,
        'summaryNarrative' => $summaryNarrative,
        'trendLineChart' => $trendLineChart,
        'top5BarChart' => $top5BarChart,
        'partnerRows' => $partnerRows,
        'produkRows' => $produkRows,
        'tujuanEksporRows' => $tujuanEksporRows,
        'kompetitorRows' => $kompetitorRows,
      ])->setPaper('a4', 'portrait');

      $filename = 'total-ekspor-summary-' . now()->format('Ymd_His') . '.pdf';

      return response($pdf->output(), 200)
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    } finally {
      if (isset($stopProfile)) $stopProfile();
    }
  }

  public function totalImporPdf(Request $request)
  {
    $stopProfile = $this->startProfiling('total_impor_summary_pdf');
    try {
      $filters = $this->normalizeFilters($request);
      $filters['strict_source_years'] = true;
      $filters['status'] = 'import';
      $sources = $this->normalizeSources($request);
      $sourceCode = $this->sourceForSector($sources, 'perdagangan');

      $baseKey = $this->buildSectorCacheKey('total-impor', $filters, $sourceCode);
      $baseData = Cache::remember($baseKey, $this->cacheTtl3Days(), fn () => $this->economyDiplomationService->getNilaiPerdagangan($filters, $sourceCode));

      if (empty($baseData) || empty($baseData['meta'])) {
        return ApiResponse::error('Tidak ada data untuk dibuatkan ringkasan.', null, 404);
      }

      $years = $baseData['meta']['years'] ?? [];
      sort($years);
      $latestYear = !empty($years) ? (int) end($years) : null;
      $prevYear = null;
      if (count($years) >= 2) {
        $prevYear = (int) $years[count($years) - 2];
      }

      if ($latestYear === null) {
        return ApiResponse::error('Data tidak tersedia untuk rentang tahun yang diminta.', null, 404);
      }

      $totalTradeByYear = $baseData['meta']['total_world_per_year'] ?? [];
      $totalImportLatest = (int) ($totalTradeByYear[$latestYear] ?? ($baseData['meta']['total_world'] ?? 0));
      $totalImportPrev = $prevYear !== null ? (int) ($totalTradeByYear[$prevYear] ?? 0) : null;

      $items = $baseData['items'] ?? [];
      $topPartners = array_slice($items, 0, 5);
      $topPartnersTable = array_slice($items, 0, 10);

      $topProduk = $baseData['top_produk'] ?? [];
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

      $trendSeries = $this->buildTrendSeries($totalTradeByYear);
      $partnerSeries = $this->buildPartnerSeries($topPartners, $latestYear);
      $trendLineChart = $this->buildLineChartImageGd($trendSeries);
      $top5BarChart = $this->buildTop5BarChartImageGd($partnerSeries);

      $topPartnerName = null;
      $topPartnerValue = 0;
      if (!empty($topPartners)) {
        $topPartnerName = $topPartners[0]['negara'] ?? null;
        $topPartnerValue = $latestYear !== null ? (int) ($topPartners[0]['nilai_perdagangan'][$latestYear] ?? 0) : 0;
      }

      $topCommodity = $topProdukTable[0] ?? null;
      $topCommodityName = $topCommodity['namaHS'] ?? null;
      $topCommodityValue = $latestYear !== null ? (int) ($topCommodity['nilai'][$latestYear] ?? 0) : 0;

      $topPartnersDesc = $this->buildTopListDescription($topPartnersTable, $latestYear, 'negara', 'nilai_perdagangan', 3);
      $topKomoditasDesc = $this->buildTopListDescription($topProdukTable, $latestYear, 'namaHS', 'nilai', 3, 'kodeHS');

      $summaryNarrative = $this->buildSummaryNarrativeImport([
        'latestYear' => $latestYear,
        'prevYear' => $prevYear,
        'totalImportLatest' => $totalImportLatest,
        'totalImportPrev' => $totalImportPrev,
        'topPartnerName' => $topPartnerName,
        'topPartnerValue' => $topPartnerValue,
        'topCommodityName' => $topCommodityName,
        'topCommodityValue' => $topCommodityValue,
        'topPartnersDesc' => $topPartnersDesc,
        'topKomoditasDesc' => $topKomoditasDesc,
      ]);

      $asalImporRows = $this->buildTujuanImporRows($topProdukTable, $latestYear, 10);
      $kompetitorRows = $this->buildKompetitorImporRows($topProdukTable, 10, 1);

      $tanggalCetak = now()->translatedFormat('d F Y');
      $unit = $baseData['meta']['unit'] ?? 'Ribu US$';
      $sourceName = $baseData['meta']['sumber'] ?? 'BPS';

      $pdf = Pdf::loadView('exports.total-impor-summary', [
        'tanggalCetak' => $tanggalCetak,
        'unit' => $unit,
        'sourceName' => $sourceName,
        'latestYear' => $latestYear,
        'prevYear' => $prevYear,
        'totalImportLatest' => $totalImportLatest,
        'summaryNarrative' => $summaryNarrative,
        'trendLineChart' => $trendLineChart,
        'top5BarChart' => $top5BarChart,
        'partnerRows' => $partnerRows,
        'produkRows' => $produkRows,
        'tujuanImporRows' => $asalImporRows,
        'kompetitorRows' => $kompetitorRows,
      ])->setPaper('a4', 'portrait');

      $filename = 'total-impor-summary-' . now()->format('Ymd_His') . '.pdf';

      return response($pdf->output(), 200)
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    } finally {
      if (isset($stopProfile)) $stopProfile();
    }
  }

  public function neracaPerdaganganPdf(Request $request)
  {
    $stopProfile = $this->startProfiling('neraca_perdagangan_summary_pdf');
    try {
      $filters = $this->normalizeFilters($request);
      $filters['strict_source_years'] = true;
      $sources = $this->normalizeSources($request);
      $sourceCode = $this->sourceForSector($sources, 'perdagangan');

      $baseKey = $this->buildSectorCacheKey('nilai-perdagangan', $filters, $sourceCode);
      $baseData = Cache::remember($baseKey, $this->cacheTtl3Days(), fn () => $this->economyDiplomationService->getNilaiPerdagangan($filters, $sourceCode));

      if (empty($baseData) || empty($baseData['meta'])) {
        return ApiResponse::error('Tidak ada data untuk dibuatkan ringkasan.', null, 404);
      }

      $years = $baseData['meta']['years'] ?? [];
      sort($years);
      $latestYear = !empty($years) ? (int) end($years) : null;
      $prevYear = null;
      if (count($years) >= 2) {
        $prevYear = (int) $years[count($years) - 2];
      }
      if ($latestYear === null) {
        return ApiResponse::error('Data tidak tersedia untuk rentang tahun yang diminta.', null, 404);
      }

      $exportFilters = array_merge($filters, ['status' => 'export']);
      $importFilters = array_merge($filters, ['status' => 'import']);

      $exportKey = $this->buildSectorCacheKey('total-ekspor', $exportFilters, $sourceCode);
      $importKey = $this->buildSectorCacheKey('total-impor', $importFilters, $sourceCode);
      $exportData = Cache::remember($exportKey, $this->cacheTtl3Days(), fn () => $this->economyDiplomationService->getNilaiPerdagangan($exportFilters, $sourceCode));
      $importData = Cache::remember($importKey, $this->cacheTtl3Days(), fn () => $this->economyDiplomationService->getNilaiPerdagangan($importFilters, $sourceCode));

      $exportByYear = $exportData['meta']['total_world_per_year'] ?? [];
      $importByYear = $importData['meta']['total_world_per_year'] ?? [];

      $balanceSeries = [];
      foreach ($years as $yr) {
        $exp = (int) ($exportByYear[$yr] ?? 0);
        $imp = (int) ($importByYear[$yr] ?? 0);
        $balanceSeries[$yr] = $exp - $imp;
      }

      $balanceLatest = (int) ($balanceSeries[$latestYear] ?? 0);
      $balancePrev = $prevYear !== null ? (int) ($balanceSeries[$prevYear] ?? 0) : null;

      $items = $baseData['items'] ?? [];
      $sortedPartners = $items;
      usort($sortedPartners, function ($a, $b) use ($latestYear) {
        return ($b['neraca'][$latestYear] ?? 0) <=> ($a['neraca'][$latestYear] ?? 0);
      });
      $topPartners = array_slice($sortedPartners, 0, 5);
      $topPartnersTable = array_slice($sortedPartners, 0, 10);

      $topProduk = $baseData['top_produk'] ?? [];
      usort($topProduk, function ($a, $b) use ($latestYear) {
        return ($b['neraca'][$latestYear] ?? 0) <=> ($a['neraca'][$latestYear] ?? 0);
      });
      $topProdukTable = array_slice($topProduk, 0, 10);

      $partnerRows = array_map(function ($row) use ($latestYear, $prevYear) {
        $latest = $latestYear !== null ? (int) ($row['neraca'][$latestYear] ?? 0) : 0;
        $prev = $prevYear !== null ? (int) ($row['neraca'][$prevYear] ?? 0) : null;
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
        $latest = $latestYear !== null ? (int) ($row['neraca'][$latestYear] ?? 0) : 0;
        $prev = $prevYear !== null ? (int) ($row['neraca'][$prevYear] ?? 0) : null;
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

      $trendSeries = $this->buildTrendSeries($balanceSeries);
      $partnerSeries = $this->buildPartnerSeries($topPartners, $latestYear);
      $trendLineChart = $this->buildLineChartImageGd($trendSeries);
      $top5BarChart = $this->buildTop5BarChartImageGd($partnerSeries);

      $tanggalCetak = now()->translatedFormat('d F Y');
      $unit = $baseData['meta']['unit'] ?? 'Ribu US$';
      $sourceName = $baseData['meta']['sumber'] ?? 'BPS';

      $summaryNarrative = $this->buildSummaryNarrativeBalance([
        'latestYear' => $latestYear,
        'prevYear' => $prevYear,
        'balanceLatest' => $balanceLatest,
        'balancePrev' => $balancePrev,
        'topPartnersDesc' => $this->buildTopListDescription($topPartnersTable, $latestYear, 'negara', 'neraca', 3),
        'topKomoditasDesc' => $this->buildTopListDescription($topProdukTable, $latestYear, 'namaHS', 'neraca', 3, 'kodeHS'),
      ]);

      $pdf = Pdf::loadView('exports.neraca-summary', [
        'tanggalCetak' => $tanggalCetak,
        'unit' => $unit,
        'sourceName' => $sourceName,
        'latestYear' => $latestYear,
        'prevYear' => $prevYear,
        'totalTradeLatest' => $balanceLatest,
        'totalExportLatest' => (int) ($exportByYear[$latestYear] ?? 0),
        'totalImportLatest' => (int) ($importByYear[$latestYear] ?? 0),
        'summaryNarrative' => $summaryNarrative,
        'trendLineChart' => $trendLineChart,
        'top5BarChart' => $top5BarChart,
        'partnerRows' => $partnerRows,
        'produkRows' => $produkRows,
      ])->setPaper('a4', 'portrait');

      $filename = 'neraca-perdagangan-summary-' . now()->format('Ymd_His') . '.pdf';

      return response($pdf->output(), 200)
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    } finally {
      if (isset($stopProfile)) $stopProfile();
    }
  }

  public function totalInboundInvestasiPdf(Request $request)
  {
    $stopProfile = $this->startProfiling('total_inbound_investasi_summary_pdf');
    try {
      $filters = $this->normalizeFilters($request);
      $filters['strict_source_years'] = true;
      $filters['status'] = 'INBOUND';
      $sources = $this->normalizeSources($request);
      $sourceCode = $this->sourceForSector($sources, 'investasi');

      $baseKey = $this->buildSectorCacheKey('total-inbound-investasi', $filters, $sourceCode);
      $baseData = Cache::remember($baseKey, $this->cacheTtl3Days(), fn () => $this->economyDiplomationService->getNilaiInvestasi($filters, $sourceCode));

      if (empty($baseData) || empty($baseData['meta'])) {
        return ApiResponse::error('Tidak ada data untuk dibuatkan ringkasan.', null, 404);
      }

      $years = $baseData['meta']['years'] ?? [];
      sort($years);
      $latestYear = !empty($years) ? (int) end($years) : null;
      $prevYear = null;
      if (count($years) >= 2) {
        $prevYear = (int) $years[count($years) - 2];
      }

      if ($latestYear === null) {
        return ApiResponse::error('Data tidak tersedia untuk rentang tahun yang diminta.', null, 404);
      }

      $totalByYear = $baseData['meta']['total_world_per_year'] ?? [];
      $totalLatest = (int) ($totalByYear[$latestYear] ?? ($baseData['meta']['total_world'] ?? 0));
      $totalPrev = $prevYear !== null ? (int) ($totalByYear[$prevYear] ?? 0) : null;

      $items = $baseData['items'] ?? [];
      $sortedPartners = $items;
      usort($sortedPartners, function ($a, $b) use ($latestYear) {
        return ($b['nilai_investasi'][$latestYear] ?? 0) <=> ($a['nilai_investasi'][$latestYear] ?? 0);
      });

      $topPartners = array_slice($sortedPartners, 0, 5);
      $topPartnersTable = array_slice($sortedPartners, 0, 10);

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
      }, $topPartnersTable);

      $trendSeries = $this->buildTrendSeries($totalByYear);
      $partnerSeries = $this->buildPartnerSeries($topPartners, $latestYear, 'nilai_investasi');
      $trendLineChart = $this->buildLineChartImageGd($trendSeries);
      $top5BarChart = $this->buildTop5BarChartImageGd($partnerSeries);

      $trenItems = $baseData['tren_investasi_masuk']['items'] ?? [];
      $trenRows = array_slice(array_map(function ($row) {
        return [
          'negara' => $row['negara'] ?? '-',
          'nilai_prev' => $row['nilai_prev'] ?? null,
          'nilai_curr' => $row['nilai_curr'] ?? 0,
          'delta' => $row['delta'] ?? null,
          'delta_pct' => $row['delta_pct'] ?? null,
        ];
      }, $trenItems), 0, 10);

      $topPartnersDesc = $this->buildTopListDescription($topPartnersTable, $latestYear, 'negara', 'nilai_investasi', 3);

      $summaryNarrative = $this->buildSummaryNarrativeInboundInvestasi([
        'latestYear' => $latestYear,
        'prevYear' => $prevYear,
        'totalLatest' => $totalLatest,
        'totalPrev' => $totalPrev,
        'topPartnersDesc' => $topPartnersDesc,
      ]);

      $tanggalCetak = now()->translatedFormat('d F Y');
      $unit = $baseData['meta']['unit'] ?? 'Ribu US$';
      $sourceName = $baseData['meta']['sumber'] ?? 'BPS';

      $pdf = Pdf::loadView('exports.total-inbound-investasi-summary', [
        'tanggalCetak' => $tanggalCetak,
        'unit' => $unit,
        'sourceName' => $sourceName,
        'latestYear' => $latestYear,
        'prevYear' => $prevYear,
        'totalInvestasiLatest' => $totalLatest,
        'summaryNarrative' => $summaryNarrative,
        'trendLineChart' => $trendLineChart,
        'top5BarChart' => $top5BarChart,
        'partnerRows' => $partnerRows,
        'trenRows' => $trenRows,
      ])->setPaper('a4', 'portrait');

      $filename = 'total-inbound-investasi-summary-' . now()->format('Ymd_His') . '.pdf';

      return response($pdf->output(), 200)
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    } finally {
      if (isset($stopProfile)) $stopProfile();
    }
  }

  public function totalInboundTourismPdf(Request $request)
  {
    $stopProfile = $this->startProfiling('total_inbound_tourism_summary_pdf');
    try {
      $filters = $this->normalizeFilters($request);
      $filters['strict_source_years'] = true;
      $filters['status'] = 'INBOUND';
      $sources = $this->normalizeSources($request);
      $sourceCode = $this->sourceForSector($sources, 'pariwisata');

      $baseKey = $this->buildSectorCacheKey('total-inbound-wisatawan', $filters, $sourceCode);
      $baseData = Cache::remember($baseKey, $this->cacheTtl3Days(), fn () => $this->economyDiplomationService->getNilaiWisatawan($filters, $sourceCode));

      if (empty($baseData) || empty($baseData['meta'])) {
        return ApiResponse::error('Tidak ada data untuk dibuatkan ringkasan.', null, 404);
      }

      $years = $baseData['meta']['years'] ?? [];
      sort($years);
      $latestYear = !empty($years) ? (int) end($years) : null;
      $prevYear = null;
      if (count($years) >= 2) {
        $prevYear = (int) $years[count($years) - 2];
      }

      if ($latestYear === null) {
        return ApiResponse::error('Data tidak tersedia untuk rentang tahun yang diminta.', null, 404);
      }

      $totalByYear = $baseData['meta']['total_world_per_year'] ?? [];
      $totalLatest = (int) ($totalByYear[$latestYear] ?? ($baseData['meta']['total_world'] ?? 0));
      $totalPrev = $prevYear !== null ? (int) ($totalByYear[$prevYear] ?? 0) : null;

      $items = $baseData['items'] ?? [];
      $sortedPartners = $items;
      usort($sortedPartners, function ($a, $b) use ($latestYear) {
        return ($b['Jumlah_Wisatawan'][$latestYear] ?? 0) <=> ($a['Jumlah_Wisatawan'][$latestYear] ?? 0);
      });

      $topPartners = array_slice($sortedPartners, 0, 5);
      $topPartnersTable = array_slice($sortedPartners, 0, 10);

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
      }, $topPartnersTable);

      $trendSeries = $this->buildTrendSeries($totalByYear);
      $partnerSeries = $this->buildPartnerSeries($topPartners, $latestYear, 'Jumlah_Wisatawan');
      $trendLineChart = $this->buildLineChartImageGd($trendSeries);
      $top5BarChart = $this->buildTop5BarChartImageGd($partnerSeries);

      $topPartnersDesc = $this->buildTopListDescription($topPartnersTable, $latestYear, 'negara', 'Jumlah_Wisatawan', 3);

        $summaryNarrative = $this->buildSummaryNarrativeInboundTourism([
          'latestYear' => $latestYear,
          'prevYear' => $prevYear,
          'totalLatest' => $totalLatest,
          'totalPrev' => $totalPrev,
          'topPartnersDesc' => $topPartnersDesc,
          'unit' => $baseData['meta']['unit'] ?? 'Orang',
        ]);

      $tanggalCetak = now()->translatedFormat('d F Y');
        $unit = $baseData['meta']['unit'] ?? 'Orang';
      $sourceName = $baseData['meta']['sumber'] ?? 'BPS';

      $pdf = Pdf::loadView('exports.total-inbound-tourism-summary', [
        'tanggalCetak' => $tanggalCetak,
        'unit' => $unit,
        'sourceName' => $sourceName,
        'latestYear' => $latestYear,
        'prevYear' => $prevYear,
        'totalWisatawanLatest' => $totalLatest,
        'summaryNarrative' => $summaryNarrative,
        'trendLineChart' => $trendLineChart,
        'top5BarChart' => $top5BarChart,
        'partnerRows' => $partnerRows,
      ])->setPaper('a4', 'portrait');

      $filename = 'total-inbound-wisatawan-summary-' . now()->format('Ymd_His') . '.pdf';

      return response($pdf->output(), 200)
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    } finally {
      if (isset($stopProfile)) $stopProfile();
    }
  }

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
      ['indonesia', 'diplomasi-ekonomi-summary', $prefix],
      [
        'status' => $status,
        'tahun' => "{$ys}-{$ye}",
        'hs' => $hs,
        'src' => $sourceCode ?? 'all',
        'dirjen' => $dirjen ?: 'all',
      ]
    );
  }

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
      default => null,
    };
  }

  private function normalizeSourceCode($value): ?int
  {
    if (is_numeric($value)) {
      return (int) $value;
    }
    if (is_string($value)) {
      $v = trim($value);
      if ($v === '') {
        return null;
      }
      if (ctype_digit($v)) {
        return (int) $v;
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

  private function pctChange(int $curr, int $prev): ?float
  {
    if ($prev === 0) return $curr === 0 ? 0.0 : 100.0;
    return round((($curr - $prev) / abs($prev)) * 100.0, 2);
  }

  private function buildTrendSeries(array $totalByYear): array
  {
    if (empty($totalByYear)) return [];
    ksort($totalByYear);
    $max = max(array_values($totalByYear));
    $max = $max > 0 ? $max : 1;

    $series = [];
    foreach ($totalByYear as $year => $value) {
      $val = (int) $value;
      $series[] = [
        'year' => (int) $year,
        'value' => $val,
        'pct' => round(($val / $max) * 100, 2),
      ];
    }
    return $series;
  }

  private function buildPartnerSeries(array $topPartners, ?int $latestYear, string $valueKey = 'nilai_perdagangan'): array
  {
    if (empty($topPartners) || $latestYear === null) return [];
    $values = array_map(function ($row) use ($latestYear, $valueKey) {
      return (int) ($row[$valueKey][$latestYear] ?? 0);
    }, $topPartners);
    $max = max($values) > 0 ? max($values) : 1;

    $series = [];
    foreach ($topPartners as $row) {
      $val = (int) ($row[$valueKey][$latestYear] ?? 0);
      $series[] = [
        'label' => $row['negara'] ?? '-',
        'value' => $val,
        'pct' => round(($val / $max) * 100, 2),
      ];
    }
    return $series;
  }

  private function buildSummaryNarrative(array $data): string
  {
    $latestYear = $data['latestYear'] ?? null;
    $prevYear = $data['prevYear'] ?? null;
    $totalTradeLatest = $data['totalTradeLatest'] ?? 0;
    $totalTradePrev = $data['totalTradePrev'] ?? 0;
    $totalExportLatest = $data['totalExportLatest'] ?? 0;
    $totalImportLatest = $data['totalImportLatest'] ?? 0;
    $topPartnerName = $data['topPartnerName'] ?? null;
    $topPartnerValue = $data['topPartnerValue'] ?? 0;
    $topCommodityName = $data['topCommodityName'] ?? null;
    $topCommodityValue = $data['topCommodityValue'] ?? 0;
    $topPartnersDesc = $data['topPartnersDesc'] ?? null;
    $unit = $data['unit'] ?? 'Orang';
    $unit = $data['unit'] ?? 'Orang';
    $unit = $data['unit'] ?? 'Orang';
    $unit = $data['unit'] ?? 'Orang';
    $unit = $data['unit'] ?? 'Orang';
    $topKomoditasDesc = $data['topKomoditasDesc'] ?? null;

    $parts = [];
    if ($latestYear !== null) {
      if ($prevYear !== null) {
        $growth = $this->pctChange($totalTradeLatest, (int) $totalTradePrev);
        $growthText = $growth === null ? '' : ' (perubahan ' . ($growth >= 0 ? '+' : '') . number_format($growth, 2, ',', '.') . '% dari ' . $prevYear . ')';
        $parts[] = "Tahun {$latestYear} menunjukkan kinerja perdagangan Indonesia dengan total nilai perdagangan sebesar " . number_format($totalTradeLatest, 0, ',', '.') . " Ribu US\\$" . $growthText . ".";
      } else {
        $parts[] = "Tahun {$latestYear} menampilkan total nilai perdagangan Indonesia sebesar " . number_format($totalTradeLatest, 0, ',', '.') . " Ribu US\\$.";
      }
    }
    if ($totalExportLatest > 0 || $totalImportLatest > 0) {
      $parts[] = "Ekspor tercatat " . number_format($totalExportLatest, 0, ',', '.') . " Ribu US\\$ dan impor " . number_format($totalImportLatest, 0, ',', '.') . " Ribu US\\$.";
    }
    if ($topPartnerName) {
      $parts[] = "{$topPartnerName} menjadi mitra dagang terbesar dengan nilai " . number_format($topPartnerValue, 0, ',', '.') . " Ribu US$.";
    }
    if ($topCommodityName) {
      $parts[] = "Komoditas utama didominasi {$topCommodityName} dengan nilai " . number_format($topCommodityValue, 0, ',', '.') . " Ribu US$.";
    }
    if ($topPartnersDesc) {
      $parts[] = "Tiga mitra dagang utama adalah {$topPartnersDesc}.";
    }
    if ($topKomoditasDesc) {
      $parts[] = "Tiga komoditas teratas meliputi {$topKomoditasDesc}.";
    }

    return implode(' ', $parts);
  }

  private function buildSummaryNarrativeExport(array $data): string
  {
    $latestYear = $data['latestYear'] ?? null;
    $prevYear = $data['prevYear'] ?? null;
    $totalExportLatest = $data['totalExportLatest'] ?? 0;
    $totalExportPrev = $data['totalExportPrev'] ?? 0;
    $topPartnerName = $data['topPartnerName'] ?? null;
    $topPartnerValue = $data['topPartnerValue'] ?? 0;
    $topCommodityName = $data['topCommodityName'] ?? null;
    $topCommodityValue = $data['topCommodityValue'] ?? 0;
    $topPartnersDesc = $data['topPartnersDesc'] ?? null;
    $topKomoditasDesc = $data['topKomoditasDesc'] ?? null;

    $parts = [];
    if ($latestYear !== null) {
      if ($prevYear !== null) {
        $growth = $this->pctChange($totalExportLatest, (int) $totalExportPrev);
        $growthText = $growth === null ? '' : ' (perubahan ' . ($growth >= 0 ? '+' : '') . number_format($growth, 2, ',', '.') . '% dari ' . $prevYear . ')';
        $parts[] = "Tahun {$latestYear} menunjukkan total nilai ekspor Indonesia sebesar " . number_format($totalExportLatest, 0, ',', '.') . " Ribu US\\$" . $growthText . ".";
      } else {
        $parts[] = "Tahun {$latestYear} menampilkan total nilai ekspor Indonesia sebesar " . number_format($totalExportLatest, 0, ',', '.') . " Ribu US\\$.";
      }
    }
    if ($topPartnerName) {
      $parts[] = "{$topPartnerName} menjadi tujuan ekspor terbesar dengan nilai " . number_format($topPartnerValue, 0, ',', '.') . " Ribu US\\$.";
    }
    if ($topCommodityName) {
      $parts[] = "Komoditas ekspor utama didominasi {$topCommodityName} dengan nilai " . number_format($topCommodityValue, 0, ',', '.') . " Ribu US\\$.";
    }
    if ($topPartnersDesc) {
      $parts[] = "Tiga tujuan ekspor utama adalah {$topPartnersDesc}.";
    }
    if ($topKomoditasDesc) {
      $parts[] = "Tiga komoditas ekspor teratas meliputi {$topKomoditasDesc}.";
    }

    return implode(' ', $parts);
  }

  private function buildSummaryNarrativeImport(array $data): string
  {
    $latestYear = $data['latestYear'] ?? null;
    $prevYear = $data['prevYear'] ?? null;
    $totalImportLatest = $data['totalImportLatest'] ?? 0;
    $totalImportPrev = $data['totalImportPrev'] ?? 0;
    $topPartnerName = $data['topPartnerName'] ?? null;
    $topPartnerValue = $data['topPartnerValue'] ?? 0;
    $topCommodityName = $data['topCommodityName'] ?? null;
    $topCommodityValue = $data['topCommodityValue'] ?? 0;
    $topPartnersDesc = $data['topPartnersDesc'] ?? null;
    $topKomoditasDesc = $data['topKomoditasDesc'] ?? null;

    $parts = [];
    if ($latestYear !== null) {
      if ($prevYear !== null) {
        $growth = $this->pctChange($totalImportLatest, (int) $totalImportPrev);
        $growthText = $growth === null ? '' : ' (perubahan ' . ($growth >= 0 ? '+' : '') . number_format($growth, 2, ',', '.') . '% dari ' . $prevYear . ')';
        $parts[] = "Tahun {$latestYear} menunjukkan total nilai impor Indonesia sebesar " . number_format($totalImportLatest, 0, ',', '.') . " Ribu US\\$" . $growthText . ".";
      } else {
        $parts[] = "Tahun {$latestYear} menampilkan total nilai impor Indonesia sebesar " . number_format($totalImportLatest, 0, ',', '.') . " Ribu US\\$.";
      }
    }
    if ($topPartnerName) {
      $parts[] = "{$topPartnerName} menjadi asal impor terbesar dengan nilai " . number_format($topPartnerValue, 0, ',', '.') . " Ribu US\\$.";
    }
    if ($topCommodityName) {
      $parts[] = "Komoditas impor utama didominasi {$topCommodityName} dengan nilai " . number_format($topCommodityValue, 0, ',', '.') . " Ribu US\\$.";
    }
    if ($topPartnersDesc) {
      $parts[] = "Tiga asal impor utama adalah {$topPartnersDesc}.";
    }
    if ($topKomoditasDesc) {
      $parts[] = "Tiga komoditas impor teratas meliputi {$topKomoditasDesc}.";
    }

    return implode(' ', $parts);
  }

  private function buildSummaryNarrativeBalance(array $data): string
  {
    $latestYear = $data['latestYear'] ?? null;
    $prevYear = $data['prevYear'] ?? null;
    $balanceLatest = $data['balanceLatest'] ?? 0;
    $balancePrev = $data['balancePrev'] ?? 0;
    $topPartnersDesc = $data['topPartnersDesc'] ?? null;
    $topKomoditasDesc = $data['topKomoditasDesc'] ?? null;

    $parts = [];
    if ($latestYear !== null) {
      if ($prevYear !== null) {
        $growth = $this->pctChange($balanceLatest, (int) $balancePrev);
        $growthText = $growth === null ? '' : ' (perubahan ' . ($growth >= 0 ? '+' : '') . number_format($growth, 2, ',', '.') . '% dari ' . $prevYear . ')';
        $parts[] = "Tahun {$latestYear} menunjukkan neraca perdagangan sebesar " . number_format($balanceLatest, 0, ',', '.') . " Ribu US\\$" . $growthText . ".";
      } else {
        $parts[] = "Tahun {$latestYear} menampilkan neraca perdagangan sebesar " . number_format($balanceLatest, 0, ',', '.') . " Ribu US\\$.";
      }
    }

    // Tambahkan konteks surplus/defisit dan implikasi singkat
    if ($latestYear !== null) {
      if ($balanceLatest > 0) {
        $parts[] = "Neraca perdagangan berada pada posisi surplus, menunjukkan ekspor lebih besar daripada impor pada periode tersebut.";
      } elseif ($balanceLatest < 0) {
        $parts[] = "Neraca perdagangan berada pada posisi defisit, menunjukkan impor lebih besar daripada ekspor pada periode tersebut.";
      } else {
        $parts[] = "Neraca perdagangan berada pada posisi seimbang antara ekspor dan impor.";
      }
    }

    if ($prevYear !== null) {
      $direction = $balanceLatest >= $balancePrev ? 'menguat' : 'melemah';
      $parts[] = "Pergerakan neraca perdagangan dibandingkan tahun sebelumnya cenderung {$direction}, mengindikasikan perubahan kinerja perdagangan luar negeri.";
    }

    if ($topPartnersDesc) {
      $parts[] = "Tiga mitra dengan kontribusi neraca terbesar adalah {$topPartnersDesc}.";
    }
    if ($topKomoditasDesc) {
      $parts[] = "Tiga komoditas dengan kontribusi neraca terbesar meliputi {$topKomoditasDesc}.";
    }

    return implode(' ', $parts);
  }

  private function buildSummaryNarrativeInboundInvestasi(array $data): string
  {
    $latestYear = $data['latestYear'] ?? null;
    $prevYear = $data['prevYear'] ?? null;
    $totalLatest = $data['totalLatest'] ?? 0;
    $totalPrev = $data['totalPrev'] ?? 0;
    $topPartnersDesc = $data['topPartnersDesc'] ?? null;

    $parts = [];
    if ($latestYear !== null) {
      if ($prevYear !== null) {
        $growth = $this->pctChange($totalLatest, (int) $totalPrev);
        $growthText = $growth === null ? '' : ' (perubahan ' . ($growth >= 0 ? '+' : '') . number_format($growth, 2, ',', '.') . '% dari ' . $prevYear . ')';
        $parts[] = "Tahun {$latestYear} menunjukkan total investasi masuk sebesar " . number_format($totalLatest, 0, ',', '.') . " Ribu US\\$" . $growthText . ".";
      } else {
        $parts[] = "Tahun {$latestYear} menampilkan total investasi masuk sebesar " . number_format($totalLatest, 0, ',', '.') . " Ribu US\\$.";
      }
    }
    if ($latestYear !== null) {
      if ($totalLatest > 0) {
        $parts[] = "Capaian ini mencerminkan minat investor asing terhadap iklim investasi Indonesia pada periode tersebut, dengan kontribusi terbesar berasal dari negara-negara utama mitra investasi.";
      } else {
        $parts[] = "Realisasi investasi masuk pada periode ini masih terbatas, menandakan perlunya penguatan promosi dan iklim investasi.";
      }
    }
    if ($topPartnersDesc) {
      $parts[] = "Tiga negara asal investasi terbesar adalah {$topPartnersDesc}.";
    }
    if ($prevYear !== null) {
      $parts[] = "Dibandingkan tahun sebelumnya, perubahan nilai investasi masuk memberikan sinyal dinamika arus modal yang perlu diantisipasi dalam kebijakan diplomasi ekonomi.";
    }
    return implode(' ', $parts);
  }

  private function buildSummaryNarrativeInboundTourism(array $data): string
  {
    $latestYear = $data['latestYear'] ?? null;
    $prevYear = $data['prevYear'] ?? null;
    $totalLatest = $data['totalLatest'] ?? 0;
    $totalPrev = $data['totalPrev'] ?? 0;
    $topPartnersDesc = $data['topPartnersDesc'] ?? null;
    $unit = $data['unit'] ?? 'Orang';

    $parts = [];
    if ($latestYear !== null) {
      if ($prevYear !== null) {
        $growth = $this->pctChange($totalLatest, (int) $totalPrev);
        $growthText = $growth === null ? '' : ' (perubahan ' . ($growth >= 0 ? '+' : '') . number_format($growth, 2, ',', '.') . '% dari ' . $prevYear . ')';
        $parts[] = "Tahun {$latestYear} mencatat jumlah wisatawan masuk sebesar " . number_format($totalLatest, 0, ',', '.') . " {$unit}" . $growthText . ". Kinerja ini mencerminkan dinamika permintaan perjalanan ke Indonesia dan efektivitas pemulihan sektor pariwisata dibanding tahun sebelumnya.";
      } else {
        $parts[] = "Tahun {$latestYear} mencatat jumlah wisatawan masuk sebesar " . number_format($totalLatest, 0, ',', '.') . " {$unit}. Angka ini menjadi baseline kinerja pariwisata Indonesia pada periode yang tersedia.";
      }
    }
    if ($topPartnersDesc) {
      $parts[] = "Tiga negara asal wisatawan terbesar adalah {$topPartnersDesc}, menunjukkan konsentrasi pasar utama yang perlu dijaga melalui penguatan konektivitas, promosi, dan kemudahan perjalanan.";
    }
    if ($prevYear !== null) {
      $parts[] = "Perubahan jumlah wisatawan masuk dibandingkan tahun sebelumnya memberikan sinyal pemulihan dan daya tarik destinasi, sekaligus menjadi masukan bagi strategi diplomasi pariwisata dan penargetan pasar prioritas.";
    }
    return implode(' ', $parts);
  }

  private function buildTujuanEksporRows(array $topProdukTable, ?int $latestYear, int $limitProduk = 5): array
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

  private function buildTujuanImporRows(array $topProdukTable, ?int $latestYear, int $limitProduk = 5): array
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

  private function buildKompetitorImporRows(array $topProdukTable, int $limitProduk = 5, int $limitKompetitor = 3): array
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
      $negaraList = [];
      $rankList = [];
      $nilaiList = [];
      foreach (array_slice($filtered, 0, $limitKompetitor) as $k) {
        $negaraList[] = [
          'label' => $this->resolveCountryLabel($k),
          'rank' => isset($k['rank']) && is_numeric($k['rank']) ? (int) $k['rank'] : null,
        ];
        $rankList[] = $k['rank'] ?? '-';
        $nilaiList[] = number_format((int) ($k['nilai'] ?? 0), 0, ',', '.');
      }
      $rows[] = [
        'kode' => $prod['kodeHS'] ?? '-',
        'nama' => $prod['namaHS'] ?? '-',
        'tujuan' => $tujuanName ?? '-',
        'negara' => $this->formatNumberedList($negaraList, '<br>'),
        'rank' => $rankIndonesia ?? '-',
        'nilai' => implode('<br>', $nilaiList),
      ];
    }
    return $rows;
  }

  private function buildKompetitorEksporRows(array $topProdukTable, int $limitProduk = 5, int $limitKompetitor = 3): array
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
      $negaraList = [];
      $nilaiList = [];
      foreach (array_slice($filtered, 0, $limitKompetitor) as $k) {
        $negaraList[] = [
          'label' => $this->resolveCountryLabel($k),
          'rank' => isset($k['rank']) && is_numeric($k['rank']) ? (int) $k['rank'] : null,
        ];
        $nilaiList[] = number_format((int) ($k['nilai'] ?? 0), 0, ',', '.');
      }
      $rows[] = [
        'kode' => $prod['kodeHS'] ?? '-',
        'nama' => $prod['namaHS'] ?? '-',
        'tujuan' => $tujuanName ?? '-',
        'negara' => $this->formatNumberedList($negaraList, '<br>'),
        'rank' => $rankIndonesia ?? '-',
        'nilai' => implode('<br>', $nilaiList),
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

  private function buildTopListDescription(array $rows, ?int $latestYear, string $nameKey, string $valueKey, int $limit = 3, ?string $codeKey = null): ?string
  {
    if (empty($rows) || $latestYear === null) return null;
    $items = [];
    foreach (array_slice($rows, 0, $limit) as $row) {
      $name = $row[$nameKey] ?? '-';
      $value = 0;
      if (isset($row[$valueKey]) && is_array($row[$valueKey])) {
        $value = (int) ($row[$valueKey][$latestYear] ?? 0);
      } elseif (isset($row[$valueKey]) && is_numeric($row[$valueKey])) {
        $value = (int) $row[$valueKey];
      }
      $code = $codeKey ? ($row[$codeKey] ?? null) : null;
      $label = $code ? "{$name} (HS{$code})" : $name;
      $items[] = $label . ' (' . number_format($value, 0, ',', '.') . ' Ribu US$)';
    }
    return !empty($items) ? implode(', ', $items) : null;
  }

  private function buildLineChartImageGd(array $trendSeries): ?string
  {
    if (empty($trendSeries) || !function_exists('imagecreatetruecolor')) {
      return null;
    }

    $width = 520;
    $height = 230;
    $paddingX = 50;
    $paddingY = 34;

    $img = imagecreatetruecolor($width, $height);
    if (!$img) return null;

    $white = imagecolorallocate($img, 255, 255, 255);
    $axis = imagecolorallocate($img, 203, 213, 225);
    $grid = imagecolorallocate($img, 241, 245, 249);
    $line = imagecolorallocate($img, 37, 99, 235);
    $lineSoft = imagecolorallocate($img, 59, 130, 246);
    $fill = imagecolorallocatealpha($img, 219, 234, 254, 70);
    $dot = imagecolorallocate($img, 37, 99, 235);
    $dotRing = imagecolorallocate($img, 255, 255, 255);
    $text = imagecolorallocate($img, 71, 85, 105);
    imagefilledrectangle($img, 0, 0, $width, $height, $white);

    $values = array_map(fn ($r) => (int) $r['value'], $trendSeries);
    $labels = array_map(fn ($r) => (string) $r['year'], $trendSeries);
    $max = max($values);
    $min = 0;
    $range = max(1, $max - $min);
    $count = count($values);

    $gridCount = 4;
    for ($i = 0; $i <= $gridCount; $i++) {
      $y = $paddingY + (($height - 2 * $paddingY) * ($i / $gridCount));
      imageline($img, $paddingX, (int) $y, $width - $paddingX, (int) $y, $grid);
    }

    imageline($img, $paddingX, $height - $paddingY, $width - $paddingX, $height - $paddingY, $axis);
    imageline($img, $paddingX, $paddingY, $paddingX, $height - $paddingY, $axis);

    $points = [];
    foreach ($values as $i => $val) {
      $x = $paddingX + ($count === 1 ? 0 : ($width - 2 * $paddingX) * ($i / ($count - 1)));
      $y = $height - $paddingY - (($height - 2 * $paddingY) * (($val - $min) / $range));
      $points[] = ['x' => (int) $x, 'y' => (int) $y];
    }
    // Area fill
    if (count($points) >= 2) {
      $poly = [];
      foreach ($points as $pt) {
        $poly[] = $pt['x'];
        $poly[] = $pt['y'];
      }
      $poly[] = $points[count($points) - 1]['x'];
      $poly[] = $height - $paddingY;
      $poly[] = $points[0]['x'];
      $poly[] = $height - $paddingY;
      imagefilledpolygon($img, $poly, count($poly) / 2, $fill);
    }

    // Line (double stroke)
    for ($i = 0; $i < count($points) - 1; $i++) {
      imageline($img, $points[$i]['x'], $points[$i]['y'], $points[$i + 1]['x'], $points[$i + 1]['y'], $lineSoft);
    }
    imagesetthickness($img, 2);
    for ($i = 0; $i < count($points) - 1; $i++) {
      imageline($img, $points[$i]['x'], $points[$i]['y'], $points[$i + 1]['x'], $points[$i + 1]['y'], $line);
    }
    imagesetthickness($img, 1);
    foreach ($points as $pt) {
      imagefilledellipse($img, $pt['x'], $pt['y'], 8, 8, $dot);
      imagefilledellipse($img, $pt['x'], $pt['y'], 4, 4, $dotRing);
    }

    // Y-axis labels aligned to grid
    for ($i = 0; $i <= $gridCount; $i++) {
      $value = $max - (($max - $min) * ($i / $gridCount));
      $y = $paddingY + (($height - 2 * $paddingY) * ($i / $gridCount));
      imagestring($img, 2, 6, (int) $y - 6, number_format((int) round($value), 0, ',', '.'), $text);
    }

    // X-axis labels for each year
    if (!empty($labels)) {
      foreach ($labels as $i => $label) {
        $x = $paddingX + ($count === 1 ? 0 : ($width - 2 * $paddingX) * ($i / ($count - 1)));
        imagestring($img, 2, (int) $x - 8, $height - 16, $label, $text);
      }
    }

    ob_start();
    imagepng($img);
    $pngData = ob_get_clean();
    $img = null;

    if ($pngData === false) return null;
    return 'data:image/png;base64,' . base64_encode($pngData);
  }

  private function buildTop5BarChartImageGd(array $partnerSeries): ?string
  {
    if (empty($partnerSeries) || !function_exists('imagecreatetruecolor')) {
      return null;
    }

    $width = 520;
    $height = 230;
    $paddingX = 120;
    $paddingY = 22;

    $img = imagecreatetruecolor($width, $height);
    if (!$img) return null;

    $white = imagecolorallocate($img, 255, 255, 255);
    $bar = imagecolorallocate($img, 245, 158, 11);
    $track = imagecolorallocate($img, 253, 230, 138);
    $text = imagecolorallocate($img, 15, 23, 42);
    $muted = imagecolorallocate($img, 100, 116, 139);
    imagefilledrectangle($img, 0, 0, $width, $height, $white);

    $values = array_map(fn ($r) => (int) $r['value'], $partnerSeries);
    $labels = array_map(fn ($r) => (string) $r['label'], $partnerSeries);
    $max = max($values) ?: 1;

    $rowHeight = 30;
    foreach ($values as $i => $val) {
      $y = $paddingY + $i * $rowHeight;
      $trackWidth = $width - $paddingX - 70;
      $barWidth = (int) ($trackWidth * ($val / $max));
      imagestring($img, 2, 10, $y + 5, $labels[$i] ?? '', $text);
      // track
      imagefilledrectangle($img, $paddingX, $y + 8, $paddingX + $trackWidth, $y + 16, $track);
      // bar with rounded ends
      imagefilledrectangle($img, $paddingX, $y + 8, $paddingX + $barWidth, $y + 16, $bar);
      imagefilledellipse($img, $paddingX, $y + 12, 8, 8, $bar);
      imagefilledellipse($img, $paddingX + $barWidth, $y + 12, 8, 8, $bar);
      imagestring($img, 2, $paddingX + $trackWidth + 8, $y + 5, number_format($val, 0, ',', '.'), $muted);
    }

    ob_start();
    imagepng($img);
    $pngData = ob_get_clean();
    $img = null;

    if ($pngData === false) return null;
    return 'data:image/png;base64,' . base64_encode($pngData);
  }
}
