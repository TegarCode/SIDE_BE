<?php

namespace App\Http\Controllers\Api\V1\NegaraMitra;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\NegaraMitra\TourismOverviewService;
use App\Support\SideCacheKey;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TourismOverviewSummaryController extends Controller
{
  public function __construct(private TourismOverviewService $service) {}

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
    $source = $filters['source'] ?? 1;
    return SideCacheKey::pairs(
      ['negara-mitra', 'pariwisata', 'single'],
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
    $source = $filters['source'] ?? 1;
    return SideCacheKey::pairs(
      ['negara-mitra', 'pariwisata', 'multi'],
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
    $source = $f['source'] ?? $request->input('single.source', $request->input('source', 1));
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
    $destIn = $f['dest'] ?? $request->input('multi.dest', $request->input('dest'));

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

  private function buildTourismSeries(array $rows, string $key): array
  {
    $years = [];
    $values = [];
    foreach ($rows as $row) {
      $year = (int) ($row['year'] ?? 0);
      if (!$year) continue;
      $years[] = $year;
      if ($key === 'total') {
        $values[] = (int) (($row['inbound_count'] ?? 0) + ($row['outbound_count'] ?? 0));
      } else {
        $values[] = (int) ($row[$key] ?? 0);
      }
    }
    return ['years' => $years, 'values' => $values];
  }

  private function shouldShowOutbound(array $rows): bool
  {
    if (empty($rows)) return false;
    $hasNonZero = false;
    $sameAsInbound = true;
    foreach ($rows as $row) {
      $in = (int) ($row['inbound_count'] ?? 0);
      $out = (int) ($row['outbound_count'] ?? 0);
      if ($out !== 0) $hasNonZero = true;
      if ($out !== $in) $sameAsInbound = false;
    }
    return $hasNonZero && !$sameAsInbound;
  }

  private function shouldShowInbound(array $rows): bool
  {
    if (empty($rows)) return false;
    $hasNonZero = false;
    $sameAsOutbound = true;
    foreach ($rows as $row) {
      $in = (int) ($row['inbound_count'] ?? 0);
      $out = (int) ($row['outbound_count'] ?? 0);
      if ($in !== 0) $hasNonZero = true;
      if ($in !== $out) $sameAsOutbound = false;
    }
    return $hasNonZero && !$sameAsOutbound;
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
    if ($max == 0 && $min == 0) {
      return null;
    }
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

  private function buildOverviewNarrative(
    string $countryName,
    ?int $year,
    ?int $prevYear,
    int $inNow,
    int $inPrev,
    int $outNow,
    int $outPrev,
    ?int $yearFrom,
    ?int $yearTo
  ): string {
    $parts = [];
    $labelYear = $year ? (string) $year : 'tahun terbaru';
    if ($inNow > 0 || $outNow > 0) {
      $inText = $inNow > 0 ? number_format($inNow, 0, ',', '.') . " orang" : 'N/A';
      $outText = $outNow > 0 ? number_format($outNow, 0, ',', '.') . " orang" : 'N/A';
      $parts[] = "Ringkasan pariwisata untuk {$countryName} pada {$labelYear} menunjukkan jumlah wisatawan masuk sebanyak {$inText} dan wisatawan keluar sebanyak {$outText}.";
    } else {
      $parts[] = "Data jumlah wisatawan masuk dan keluar untuk {$countryName} pada {$labelYear} belum tersedia (N/A).";
    }

    if ($prevYear) {
      if ($inPrev > 0 && $inNow > 0) {
        $parts[] = "Dibanding {$prevYear}, perubahan wisatawan masuk sebesar " . number_format((($inNow - $inPrev) / $inPrev) * 100, 2, ',', '.') . "%.";
      }
      if ($outPrev > 0 && $outNow > 0) {
        $parts[] = "Perubahan wisatawan keluar sebesar " . number_format((($outNow - $outPrev) / $outPrev) * 100, 2, ',', '.') . "%.";
      }
    }

    $totalNow = $inNow + $outNow;
    if ($totalNow > 0) {
      $parts[] = "Total pergerakan wisatawan pada {$labelYear} tercatat " . number_format($totalNow, 0, ',', '.') . " orang.";
    }

    if ($yearFrom && $yearTo) {
      $parts[] = "Tren historis pada rentang {$yearFrom}–{$yearTo} memberikan konteks perubahan arus wisatawan antar negara dan membantu membaca dinamika permintaan jangka menengah.";
    }
    $parts[] = "Daftar mitra terbesar pada tabel inbound dan outbound memperlihatkan konsentrasi asal dan tujuan utama wisatawan serta peluang penguatan kerja sama pariwisata.";

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

      $unit = 'Orang';
      $countryName = $singleMeta['country_name'] ?? ($singleMeta['country'] ?? '-');

      $year = isset($singleMeta['year']) ? (int) $singleMeta['year'] : null;
      $prevYear = isset($singleMeta['prevYear']) ? (int) $singleMeta['prevYear'] : null;

      $inNow = (int) ($summary['inbound']['count_now'] ?? 0);
      $inPrev = (int) ($summary['inbound']['count_prev'] ?? 0);
      $outNow = (int) ($summary['outbound']['count_now'] ?? 0);
      $outPrev = (int) ($summary['outbound']['count_prev'] ?? 0);

      $totalNow = $inNow + $outNow;

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
        $yearFrom,
        $yearTo
      );

      $inboundSeries = $this->buildTourismSeries($timeseries, 'inbound_count');
      $outboundSeries = $this->buildTourismSeries($timeseries, 'outbound_count');
      $showInboundSingle = (!empty($tableInbound) || $inNow > 0 || $inPrev > 0);
      $showOutboundSingle = (!empty($tableOutbound) || $outNow > 0 || $outPrev > 0);
      $showInboundMulti = $showInboundSingle && $this->shouldShowInbound($timeseries);
      $showOutboundMulti = $showOutboundSingle && $this->shouldShowOutbound($timeseries);

      $inboundChart = $showInboundSingle
        ? $this->buildLineChartImageGd($inboundSeries, [59, 130, 246])
        : null;
      $outboundChart = $showOutboundSingle
        ? $this->buildLineChartImageGd($outboundSeries, [245, 158, 11])
        : null;

      $tanggalCetak = now()->translatedFormat('d F Y');
      $pdf = Pdf::loadView('exports.overview-pariwisata-mitra-summary', [
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
        'tableInbound' => $tableInbound,
        'tableOutbound' => $tableOutbound,
        'timeseries' => $timeseries,
        'inboundChart' => $inboundChart,
        'outboundChart' => $outboundChart,
        'showInboundMulti' => $showInboundMulti,
        'showOutboundMulti' => $showOutboundMulti,
        'showInboundSingle' => $showInboundSingle,
        'showOutboundSingle' => $showOutboundSingle,
      ])->setPaper('a4', 'portrait');

      $filename = 'overview-pariwisata-summary-' . now()->format('Ymd_His') . '.pdf';

      return response($pdf->output(), 200)
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    } catch (\Throwable $e) {
      report($e);
      return ApiResponse::error('Gagal memuat ringkasan overview pariwisata');
    }
  }
}
