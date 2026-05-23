<?php

namespace App\Http\Controllers\Api\V1\NegaraMitra;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\NegaraMitra\InvestmentOverviewService;
use App\Support\SideCacheKey;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class InvestmentOverviewSummaryController extends Controller
{
  public function __construct(private InvestmentOverviewService $service) {}

  private function splitMeta(array $arr): array
  {
    $meta = $arr['meta'] ?? [];
    unset($arr['meta']);
    return [$meta, $arr];
  }

  private function resolveTtl(?int $year)
  {
    $nowY = (int) date('Y');
    if (!$year) {
      return now()->addHours(6);
    }
    return $year >= $nowY ? now()->addHours(6) : now()->addDays(7);
  }

  private function makeSingleCacheKey(array $filters, array $include = []): string
  {
    $country = strtoupper((string) ($filters['country'] ?? 'IDN'));
    $year = (int) ($filters['year'] ?? 0);
    $limit = (int) ($filters['limit'] ?? 20);
    $source = $filters['source'] ?? 16;
    return SideCacheKey::pairs(
      ['negara-mitra', 'investasi', 'single'],
      [
        'country' => $country,
        'year' => $year,
        'source' => $source,
        'limit' => $limit,
        'include' => $include,
      ]
    );
  }

  private function makeMultiCacheKey(array $filters, array $include = []): string
  {
    $origin = $filters['origin'] ?? null;
    $dest = $filters['dest'] ?? null;
    $year = (int) ($filters['year'] ?? 0);
    $limit = (int) ($filters['limit'] ?? 20);
    $source = $filters['source'] ?? 16;
    return SideCacheKey::pairs(
      ['negara-mitra', 'investasi', 'multi'],
      [
        'origin' => $origin ?: 'all',
        'dest' => $dest ?: 'all',
        'year' => $year,
        'limit' => $limit,
        'source' => $source,
        'include' => $include,
      ]
    );
  }

  private function normalizeSource(mixed $source): int|array
  {
    if (is_array($source)) {
      return array_values(array_filter(array_map(fn ($v) => (int) $v, $source)));
    }
    return (int) $source;
  }

  private function normalizeOD($val): array|null
  {
    if ($val === null) return null;

    if (is_string($val) && strtolower(trim($val)) === 'all') {
      return null;
    }

    if (is_string($val)) {
      $s = trim($val);
      $decoded = null;
      if ((str_starts_with($s, '[') && str_ends_with($s, ']')) ||
        (str_starts_with($s, '{') && str_ends_with($s, '}'))
      ) {
        $decoded = json_decode($s, true);
      }
      if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
        $val = $decoded;
      } else {
        $clean = preg_replace(['/^\[|\]$/', '/[\'"]/'], ['', ''], $s);
        $parts = preg_split('/[,\s]+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
        $parts = array_map(fn ($x) => strtoupper(trim($x)), $parts);
        $parts = array_values(array_unique($parts));
        return count($parts) ? $parts : null;
      }
    }

    if (is_array($val)) {
      $arr = [];
      foreach ($val as $item) {
        if (is_string($item) && $item !== '') {
          $arr[] = strtoupper(trim($item));
        } elseif (is_array($item)) {
          $code = $item['value'] ?? $item['code'] ?? $item['alpha3'] ?? null;
          if (is_string($code) && $code !== '') {
            $arr[] = strtoupper(trim($code));
          }
        }
      }
      $arr = array_values(array_unique(array_filter($arr, fn ($x) => $x !== '')));
      return count($arr) ? $arr : null;
    }

    return null;
  }

  private function sanitizeSingleFilters(Request $request): array
  {
    $f = (array) $request->input('single.filters', $request->input('filters', []));

    $country = $f['country'] ?? $request->input('single.country', $request->input('country', 'IDN'));
    $year = $f['year'] ?? $request->input('single.year', $request->input('year'));
    $source = $f['source'] ?? $request->input('single.source', $request->input('source', 16));
    $limit = $f['limit'] ?? $request->input('single.limit', $request->input('limit', 20));

    $country = is_string($country) ? strtoupper(trim($country)) : 'IDN';
    $source = $this->normalizeSource($source);

    return [
      'country' => $country,
      'year' => isset($year) ? (int) $year : null,
      'source' => $source,
      'limit' => isset($limit) ? (int) $limit : 20,
    ];
  }

  private function sanitizeMultiFilters(Request $request): array
  {
    $f = (array) $request->input('multi.filters', $request->input('filters_multi', []));

    $originIn = $f['origin'] ?? $request->input('multi.origin', $request->input('origin'));
    $destIn = $f['dest']
      ?? $f['destination']
      ?? $request->input('multi.dest', $request->input('dest', $request->input('multi.destination', $request->input('destination'))));

    $origin = $this->normalizeOD($originIn);
    $dest = $this->normalizeOD($destIn);

    $year = $f['year'] ?? $request->input('multi.year', $request->input('year'));
    $limit = $f['limit'] ?? $request->input('multi.limit', $request->input('limit'));

    return [
      'origin' => $origin,
      'dest' => $dest,
      'year' => isset($year) ? (int) $year : null,
      'limit' => isset($limit) ? (int) $limit : 20,
    ];
  }

  private function buildInvestmentSeries(array $rows, string $key): array
  {
    $years = [];
    $values = [];
    foreach ($rows as $row) {
      $year = (int) ($row['year'] ?? 0);
      if (!$year) continue;
      $years[] = $year;

      if ($key === 'volume') {
        $values[] = (float) ($row['volume'] ?? (($row['inbound_value'] ?? 0) + ($row['outbound_value'] ?? 0)));
      } elseif ($key === 'balance') {
        $values[] = (float) ($row['balance'] ?? (($row['inbound_value'] ?? 0) - ($row['outbound_value'] ?? 0)));
      } else {
        $values[] = (float) ($row[$key] ?? 0);
      }
    }

    return ['years' => $years, 'values' => $values];
  }

  private function buildLineChartImageGd(array $series, array $rgb): ?string
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
    $line = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
    $dot = imagecolorallocate($img, max(0, $rgb[0] - 20), max(0, $rgb[1] - 20), max(0, $rgb[2] - 20));
    $text = imagecolorallocate($img, 100, 116, 139);
    $fill = imagecolorallocatealpha($img, $rgb[0], $rgb[1], $rgb[2], 88);

    imagefill($img, 0, 0, $white);
    imagefilledrectangle($img, 0, 0, $width, $height, $bg);

    $max = max($values);
    $min = min($values);
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

    $zeroY = $paddingTop + (int) ($plotHeight * (1 - ((0 - $min) / $range)));
    $baselineY = max($paddingTop, min($paddingTop + $plotHeight, $zeroY));

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

  private function buildOverviewNarrative(
    string $countryName,
    ?int $year,
    ?int $prevYear,
    float $inNow,
    float $inPrev,
    float $outNow,
    float $outPrev,
    string $unit,
    ?int $yearFrom,
    ?int $yearTo
  ): string {
    $parts = [];
    $labelYear = $year ? (string) $year : 'tahun terbaru';
    $parts[] = "Ringkasan investasi untuk {$countryName} pada {$labelYear} menunjukkan nilai investasi masuk sebesar " . number_format($inNow, 0, ',', '.') . " {$unit} dan investasi keluar sebesar " . number_format($outNow, 0, ',', '.') . " {$unit}.";

    if ($prevYear) {
      $parts[] = "Dibanding {$prevYear}, perubahan nilai investasi masuk mencapai " . ($inPrev > 0 ? number_format((($inNow - $inPrev) / $inPrev) * 100, 2, ',', '.') . '%' : '0%') . ", sedangkan investasi keluar berubah " . ($outPrev > 0 ? number_format((($outNow - $outPrev) / $outPrev) * 100, 2, ',', '.') . '%' : '0%') . ".";
    }

    $totalNow = $inNow + $outNow;
    $balance = $inNow - $outNow;
    $parts[] = "Total nilai investasi pada {$labelYear} tercatat " . number_format($totalNow, 0, ',', '.') . " {$unit}, dengan saldo investasi " . number_format($balance, 0, ',', '.') . " {$unit} yang menunjukkan posisi " . ($balance >= 0 ? 'surplus' : 'defisit') . " pada periode ini.";

    if ($yearFrom && $yearTo) {
      $parts[] = "Tren historis pada rentang {$yearFrom}–{$yearTo} memberikan konteks perubahan siklus investasi, baik dari sisi aliran masuk maupun keluar, sehingga membantu membaca pola jangka menengah.";
    }
    $parts[] = "Informasi mitra terbesar pada tabel inbound dan outbound menegaskan konsentrasi investasi serta peluang penguatan kerja sama dengan negara mitra utama.";
    $parts[] = "Ringkasan ini dapat dimanfaatkan sebagai bahan evaluasi dan perencanaan promosi investasi berbasis data.";

    return implode(' ', $parts);
  }

  public function overviewSummaryPdf(Request $request)
  {
    try {
      $singleFilters = $this->sanitizeSingleFilters($request);
      $multiFilters = $this->sanitizeMultiFilters($request);

      $singleInclude = $request->input('single.include', $request->input('include', [
        'summary',
        'table_inbound',
        'table_outbound',
      ]));
      $multiInclude = $request->input('multi.include', $request->input('include_multi', ['timeseries']));

      $ttlSingle = $this->resolveTtl(isset($singleFilters['year']) ? (int) $singleFilters['year'] : null);
      $ttlMulti = $this->resolveTtl(isset($multiFilters['year']) ? (int) $multiFilters['year'] : null);

      $singleKey = $this->makeSingleCacheKey($singleFilters, $singleInclude);
      $multiKey = $this->makeMultiCacheKey($multiFilters, $multiInclude);

      $singleData = Cache::remember($singleKey, $ttlSingle, function () use ($singleFilters, $singleInclude) {
        return $this->service->getComposite($singleFilters, $singleInclude);
      });
      $multiData = Cache::remember($multiKey, $ttlMulti, function () use ($multiFilters) {
        return $this->service->getTimeseries($multiFilters);
      });

      [$singleMeta, $singlePayload] = $this->splitMeta(is_array($singleData) ? $singleData : []);
      [$multiMeta, $multiPayload] = $this->splitMeta(is_array($multiData) ? $multiData : []);

      $summary = $singlePayload['summary'] ?? null;
      $tableInbound = $singlePayload['table_inbound'] ?? [];
      $tableOutbound = $singlePayload['table_outbound'] ?? [];
      $timeseries = $multiPayload['timeseries']['data'] ?? ($multiData['timeseries']['data'] ?? []);

      if (empty($summary) && empty($tableInbound) && empty($tableOutbound) && empty($timeseries)) {
        return ApiResponse::error('Tidak ada data untuk dibuatkan ringkasan.', null, 404);
      }

      $unit = $singleMeta['unit'] ?? 'Ribu US$';
      $countryName = $singleMeta['country_name'] ?? ($singleMeta['country'] ?? '-');

      $year = isset($singleMeta['year']) ? (int) $singleMeta['year'] : null;
      $prevYear = isset($singleMeta['prevYear']) ? (int) $singleMeta['prevYear'] : null;

      $inNow = (float) ($summary['inbound']['value_now'] ?? 0);
      $inPrev = (float) ($summary['inbound']['value_prev'] ?? 0);
      $inProjectsNow = (int) ($summary['inbound']['projects_now'] ?? 0);
      $inProjectsPrev = (int) ($summary['inbound']['projects_prev'] ?? 0);
      $outNow = (float) ($summary['outbound']['value_now'] ?? 0);
      $outPrev = (float) ($summary['outbound']['value_prev'] ?? 0);

      $totalNow = $inNow + $outNow;
      $balanceNow = $inNow - $outNow;

      $yearFrom = isset($multiMeta['year_from']) ? (int) $multiMeta['year_from'] : null;
      $yearTo = isset($multiMeta['year_to']) ? (int) $multiMeta['year_to'] : null;

      $summaryNarrative = $this->buildOverviewNarrative(
        $countryName,
        $year,
        $prevYear,
        $inNow,
        $inPrev,
        $outNow,
        $outPrev,
        $unit,
        $yearFrom,
        $yearTo
      );

      $inboundSeries = $this->buildInvestmentSeries($timeseries, 'inbound_value');
      $outboundSeries = $this->buildInvestmentSeries($timeseries, 'outbound_value');
      $volumeSeries = $this->buildInvestmentSeries($timeseries, 'volume');
      $balanceSeries = $this->buildInvestmentSeries($timeseries, 'balance');

      $inboundChart = $this->buildLineChartImageGd($inboundSeries, [59, 130, 246]);
      $outboundChart = $this->buildLineChartImageGd($outboundSeries, [245, 158, 11]);
      $volumeChart = $this->buildLineChartImageGd($volumeSeries, [14, 116, 144]);
      $balanceChart = $this->buildLineChartImageGd($balanceSeries, [168, 85, 247]);

      $tanggalCetak = now()->translatedFormat('d F Y');
      $pdf = Pdf::loadView('exports.overview-investasi-mitra-summary', [
        'tanggalCetak' => $tanggalCetak,
        'singleMeta' => $singleMeta,
        'multiMeta' => $multiMeta,
        'unit' => $unit,
        'countryName' => $countryName,
        'summaryNarrative' => $summaryNarrative,
        'year' => $year,
        'prevYear' => $prevYear,
        'inNow' => $inNow,
        'outNow' => $outNow,
        'totalNow' => $totalNow,
        'balanceNow' => $balanceNow,
        'inProjectsNow' => $inProjectsNow,
        'tableInbound' => $tableInbound,
        'tableOutbound' => $tableOutbound,
        'timeseries' => $timeseries,
        'inboundChart' => $inboundChart,
        'outboundChart' => $outboundChart,
        'volumeChart' => $volumeChart,
        'balanceChart' => $balanceChart,
      ])->setPaper('a4', 'portrait');

      $filename = 'overview-investasi-summary-' . now()->format('Ymd_His') . '.pdf';

      return response($pdf->output(), 200)
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    } catch (\Throwable $e) {
      report($e);
      return ApiResponse::error('Gagal memuat ringkasan overview investasi');
    }
  }
}
