<?php

namespace App\Http\Controllers\Api\V1\SektorPrioritas;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Support\SideCacheKey;
use App\Services\SektorPrioritas\HilirisasiService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class HilirisasiSummaryController extends Controller
{
  public function __construct(protected HilirisasiService $hilirisasiService) {}

  private function splitPayloadAndMeta(?array $data): array
  {
    $data    = is_array($data) ? $data : [];
    $meta    = Arr::get($data, 'meta', []);
    $payload = $data;
    unset($payload['meta']);
    if (isset($meta['applied_filters'])) unset($meta['applied_filters']);
    return [$payload, $meta];
  }

  private function normalizeFiltersFrom(array $input): array
  {
    $ys = $input['year_start'] ?? null;
    $ye = $input['year_end'] ?? null;
    $yearStart = is_numeric($ys) ? (int) $ys : null;
    $yearEnd = is_numeric($ye) ? (int) $ye : null;

    $hsIn = $input['hs'] ?? null;
    $hs = is_numeric($hsIn) ? (int) $hsIn : null;

    $dirjen = $this->csvToUpperArray($input['dirjen'] ?? []);
    sort($dirjen, SORT_STRING);

    $partner  = $this->csvToUpperArray($input['partner'] ?? []);
    $reporter = $this->csvToUpperArray($input['reporter'] ?? []);

    $rawHs = $input['hscode']
      ?? ($input['hs_list'] ?? ($input['hs_codes'] ?? ($input['hsCodes'] ?? [])));
    if (is_string($rawHs) && strtolower(trim($rawHs)) === 'all') {
      $rawHs = [];
    } elseif (is_array($rawHs)) {
      $hasAll = false;
      foreach ($rawHs as $v) {
        if (is_string($v) && strtolower(trim($v)) === 'all') {
          $hasAll = true;
          break;
        }
      }
      if ($hasAll) $rawHs = [];
    }
    $hsList = $this->csvToDigitsArray($rawHs);

    $filters = [
      'year_start' => $yearStart,
      'year_end'   => $yearEnd,
      'hs'         => $hs,
      'dirjen'     => $dirjen,
    ];
    if (!empty($partner)) $filters['partner'] = $partner;
    if (!empty($reporter)) $filters['reporter'] = $reporter;
    if (!empty($hsList)) $filters['hs_list'] = $hsList;

    return array_filter(
      $filters,
      fn ($v) => is_array($v) ? count($v) > 0 : !is_null($v) && $v !== ''
    );
  }

  private function buildCacheKey(string $prefix, array $filters): string
  {
    ksort($filters);
    return SideCacheKey::pairs(['sektor-prioritas', 'hilirisasi-summary', $prefix], $filters);
  }

  private function buildCacheKeyByOriginsDests(string $prefix, array $origins, array $dests, array $filters): string
  {
    $o = $origins; sort($o, SORT_STRING);
    $d = $dests;   sort($d, SORT_STRING);

    $rest = $filters;
    unset($rest['reporter'], $rest['partner']);

    return SideCacheKey::pairs(
      ['sektor-prioritas', 'hilirisasi-summary', $prefix],
      array_merge(
        [
          'origin' => $o ?: 'all',
          'dest' => $d ?: 'all',
        ],
        $rest
      )
    );
  }

  private function csvToUpperArray($val): array
  {
    $arr = is_string($val) ? array_map('trim', explode(',', $val)) : (is_array($val) ? $val : []);
    $out = array_values(array_unique(array_filter(array_map(
      fn ($v) => strtoupper((string) $v),
      $arr
    ))));
    sort($out, SORT_STRING);
    return $out;
  }

  private function csvToDigitsArray($val): array
  {
    $arr = is_string($val) ? array_map('trim', explode(',', $val)) : (is_array($val) ? $val : []);
    $out = [];
    foreach ($arr as $v) {
      $digits = preg_replace('/\D+/', '', (string) $v);
      if ($digits !== '') $out[] = $digits;
    }
    $out = array_values(array_unique($out));
    sort($out, SORT_STRING);
    return $out;
  }

  private function buildLineChartImageGd(array $years, array $values, array $rgb): ?string
  {
    if (count($years) < 2 || count($values) < 2) return null;
    $max = max($values);
    $min = min($values);
    if ($max == 0 && $min == 0) return null;

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
    $line = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
    $dot = imagecolorallocate($img, max(0, $rgb[0] - 20), max(0, $rgb[1] - 20), max(0, $rgb[2] - 20));
    $text = imagecolorallocate($img, 100, 116, 139);
    $fill = imagecolorallocatealpha($img, $rgb[0], $rgb[1], $rgb[2], 88);

    imagefill($img, 0, 0, $white);
    imagefilledrectangle($img, 0, 0, $width, $height, $bg);

    if ($max === $min) {
      $max += 1;
      $min -= 1;
    }
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
      $y = $paddingTop + (int) ($plotHeight * (1 - (($val - $min) / $range)));
      $points[] = [$x, $y];
      imagestring($img, 2, $x - 10, $height - 18, (string) $years[$i], $text);
    }

    $baselineY = $paddingTop + $plotHeight;
    $polygon = [$paddingLeft, $baselineY];
    foreach ($points as $pt) {
      $polygon[] = $pt[0];
      $polygon[] = $pt[1];
    }
    $polygon[] = $paddingLeft + $plotWidth;
    $polygon[] = $baselineY;
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

  public function summaryPdf(Request $request)
  {
    try {
      $negaraInput = (array) $request->input('negara', []);
      $produkInput = (array) $request->input('produk', []);

      $filtersNegara = $this->normalizeFiltersFrom($negaraInput);
      unset($filtersNegara['partner'], $filtersNegara['reporter']);
      $filtersProduk = $this->normalizeFiltersFrom($produkInput);

      $cacheKey = $this->buildCacheKey('nilai-hilirisasi', $filtersNegara);
      $ttl = now()->addDays(3);
      $perNegara = Cache::remember(
        $cacheKey,
        $ttl,
        fn() => $this->hilirisasiService->getNilaiPerdaganganHilirisasi($filtersNegara)
      );
      [$negaraPayload, $negaraMeta] = $this->splitPayloadAndMeta($perNegara);

      $reporters = $this->csvToUpperArray($produkInput['origin'] ?? $request->input('origin', []));
      $partners  = $this->csvToUpperArray($produkInput['dest'] ?? $request->input('dest', []));
      if (!empty($reporters)) $filtersProduk['reporter'] = $reporters;
      if (!empty($partners))  $filtersProduk['partner']  = $partners;

      $cacheKeyProduk = $this->buildCacheKeyByOriginsDests('sektor-hilirisasi-produk', $reporters, $partners, $filtersProduk);
      $perProduk = Cache::remember(
        $cacheKeyProduk,
        $ttl,
        fn() => $this->hilirisasiService->getNilaiPerdaganganHilirisasiProduk($filtersProduk)
      );
      [$produkPayload, $produkMeta] = $this->splitPayloadAndMeta($perProduk);

      $items = $negaraPayload['items'] ?? [];
      $years = $negaraMeta['years'] ?? [];
      $latestYear = $years ? max($years) : null;
      $prevYear = $years ? (count($years) > 1 ? $years[count($years) - 2] : null) : null;

      $exportByYear = array_fill_keys($years, 0.0);
      $importByYear = array_fill_keys($years, 0.0);
      $totalByYear = array_fill_keys($years, 0.0);
      $neracaByYear = array_fill_keys($years, 0.0);
      foreach ($items as $row) {
        foreach ($years as $yr) {
          $total = (float) ($row['nilai_perdagangan'][$yr] ?? 0);
          $neraca = (float) ($row['neraca'][$yr] ?? 0);
          $export = ($total + $neraca) / 2;
          $import = ($total - $neraca) / 2;
          $exportByYear[$yr] += $export;
          $importByYear[$yr] += $import;
          $totalByYear[$yr] += $total;
          $neracaByYear[$yr] += $neraca;
        }
      }

      $chartExport = $this->buildLineChartImageGd($years, array_values($exportByYear), [14, 116, 144]);
      $chartImport = $this->buildLineChartImageGd($years, array_values($importByYear), [245, 158, 11]);
      $chartTotal = $this->buildLineChartImageGd($years, array_values($totalByYear), [59, 130, 246]);
      $chartNeraca = $this->buildLineChartImageGd($years, array_values($neracaByYear), [168, 85, 247]);

      $sektorProduk = $produkPayload['sektor_produk'] ?? [];
      $produkYears = $produkMeta['years'] ?? ($produkMeta['tahun'] ?? []);
      $latestProdukYear = $produkMeta['latest_year'] ?? ($produkYears ? max($produkYears) : null);
      $prevProdukYear = $latestProdukYear ? ($latestProdukYear - 1) : ($produkMeta['prev_year'] ?? ($produkYears ? min($produkYears) : null));

      $sectorTrends = [];
      $sectorProducts = [];
      foreach ($sektorProduk as $sec) {
        $sectorName = $sec['sektor'] ?? '-';
        $products = $sec['produk'] ?? [];

        $expBy = array_fill_keys($produkYears, 0.0);
        $impBy = array_fill_keys($produkYears, 0.0);
        $totBy = array_fill_keys($produkYears, 0.0);
        $nerBy = array_fill_keys($produkYears, 0.0);

        foreach ($products as $prod) {
          foreach ($produkYears as $yr) {
            $exp = (float) ($prod['ekspor'][$yr] ?? 0);
            $imp = (float) ($prod['impor'][$yr] ?? 0);
            $tot = (float) ($prod['total'][$yr] ?? 0);
            $expBy[$yr] += $exp;
            $impBy[$yr] += $imp;
            $totBy[$yr] += $tot;
            $nerBy[$yr] += ($exp - $imp);
          }
        }

        $chartSector = $this->buildLineChartImageGd($produkYears, array_values($totBy), [59, 130, 246]);

        $sectorTrends[] = [
          'sektor' => $sectorName,
          'years' => $produkYears,
          'export' => $expBy,
          'import' => $impBy,
          'total' => $totBy,
          'neraca' => $nerBy,
          'chart' => $chartSector,
        ];

        $sorted = $products;
        usort($sorted, function ($a, $b) use ($latestProdukYear) {
          $va = (float) ($a['total'][$latestProdukYear] ?? 0);
          $vb = (float) ($b['total'][$latestProdukYear] ?? 0);
          return $vb <=> $va;
        });

        $sectorProducts[] = [
          'sektor' => $sectorName,
          'produk' => array_slice($sorted, 0, 20),
        ];
      }

      $tanggalCetak = now()->translatedFormat('d F Y');
      $pdf = Pdf::loadView('exports.sektor-hilirisasi-summary', [
        'tanggalCetak' => $tanggalCetak,
        'negaraMeta' => $negaraMeta,
        'produkMeta' => $produkMeta,
        'latestYear' => $latestYear,
        'latestProdukYear' => $latestProdukYear,
        'prevYear' => $prevYear,
        'prevProdukYear' => $prevProdukYear,
        'exportByYear' => $exportByYear,
        'importByYear' => $importByYear,
        'totalByYear' => $totalByYear,
        'neracaByYear' => $neracaByYear,
        'chartExport' => $chartExport,
        'chartImport' => $chartImport,
        'chartTotal' => $chartTotal,
        'chartNeraca' => $chartNeraca,
        'items' => $items,
        'sektorProduk' => $sektorProduk,
        'sectorTrends' => $sectorTrends,
        'sectorProducts' => $sectorProducts,
      ])->setPaper('a4', 'portrait');

      $filename = 'sektor-hilirisasi-summary-' . now()->format('Ymd_His') . '.pdf';

      return response($pdf->output(), 200)
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    } catch (\Throwable $e) {
      report($e);
      return ApiResponse::error('Gagal memuat ringkasan sektor hilirisasi');
    }
  }
}
