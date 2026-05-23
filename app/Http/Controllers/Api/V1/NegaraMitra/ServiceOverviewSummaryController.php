<?php

namespace App\Http\Controllers\Api\V1\NegaraMitra;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\NegaraMitra\ServiceOverviewService;
use App\Support\SideCacheKey;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ServiceOverviewSummaryController extends Controller
{
  public function __construct(private ServiceOverviewService $service) {}

  private function splitMeta(array $arr): array
  {
    $meta = $arr['meta'] ?? [];
    unset($arr['meta']);
    return [$meta, $arr];
  }

  private function makeOverviewCacheKey(array $filters, array $include): string
  {
    return SideCacheKey::pairs(
      ['negara-mitra', 'jasa', 'overview'],
      [
        'version' => 1,
        'filters' => $filters,
        'include' => $include,
      ]
    );
  }

  private function makeCountryCacheKey(array $filters, array $include): string
  {
    return SideCacheKey::pairs(
      ['negara-mitra', 'jasa', 'country'],
      [
        'version' => 1,
        'filters' => $filters,
        'include' => $include,
      ]
    );
  }

  private function normalizeSource(mixed $source): int|array|null
  {
    if (is_array($source)) {
      return array_values(array_filter(array_map(fn ($v) => (int) $v, $source)));
    }
    return isset($source) ? (int) $source : null;
  }

  private function normalizeOD($val): array|null
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

  private function sanitizeCountryFilters(Request $request): array
  {
    $f = (array) $request->input('single.filters', $request->input('filters', []));
    $country = $f['country'] ?? $request->input('single.country', $request->input('country'));
    $year = $f['year'] ?? $request->input('single.year', $request->input('year'));
    $limit = $f['limit'] ?? $request->input('single.limit', $request->input('limit'));
    $source = $f['source'] ?? $request->input('single.source', $request->input('source'));

    return [
      'country' => is_string($country) ? strtoupper(trim($country)) : null,
      'year' => isset($year) ? (int) $year : null,
      'limit' => isset($limit) ? (int) $limit : null,
      'source' => $this->normalizeSource($source),
    ];
  }

  private function sanitizeOverviewFilters(Request $request): array
  {
    $f = (array) $request->input('multi.filters', $request->input('filters_multi', []));
    $originIn = $f['origin'] ?? $request->input('multi.origin', $request->input('origin'));
    $destIn = $f['dest'] ?? $request->input('multi.dest', $request->input('dest'));
    $year = $f['year'] ?? $request->input('multi.year', $request->input('year'));
    $yearFrom = $f['year_from'] ?? $request->input('multi.year_from', $request->input('year_from'));
    $yearTo = $f['year_to'] ?? $request->input('multi.year_to', $request->input('year_to'));
    $limit = $f['limit'] ?? $request->input('multi.limit', $request->input('limit'));
    $source = $f['source'] ?? $request->input('multi.source', $request->input('source'));

    return [
      'origin' => $this->normalizeOD($originIn),
      'dest' => $this->normalizeOD($destIn),
      'year' => isset($year) ? (int) $year : null,
      'year_from' => isset($yearFrom) ? (int) $yearFrom : null,
      'year_to' => isset($yearTo) ? (int) $yearTo : null,
      'limit' => isset($limit) ? (int) $limit : null,
      'source' => $source,
    ];
  }

  private function buildSeries(array $rows, string $key): array
  {
    $years = [];
    $values = [];
    foreach ($rows as $row) {
      $year = (int) ($row['year'] ?? 0);
      if (!$year) continue;
      $years[] = $year;
      $values[] = (float) ($row[$key] ?? 0);
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

  public function overviewSummaryPdf(Request $request)
  {
    try {
      $countryFilters = $this->sanitizeCountryFilters($request);
      $overviewFilters = $this->sanitizeOverviewFilters($request);

      $countryInclude = $request->input('single.include', $request->input('include_country', [
        'summary',
        'top_countries_inbound',
        'top_countries_outbound',
      ]));
      $overviewInclude = $request->input('multi.include', $request->input('include', [
        'summary',
        'timeseries',
        'top_services_inbound',
        'top_services_outbound',
      ]));

      $countryKey = $this->makeCountryCacheKey($countryFilters, $countryInclude);
      $overviewKey = $this->makeOverviewCacheKey($overviewFilters, $overviewInclude);
      $ttl = now()->endOfDay();

      $countryData = Cache::remember($countryKey, $ttl, function () use ($countryFilters, $countryInclude) {
        return $this->service->getCountryComposite($countryFilters, $countryInclude);
      });
      $overviewData = Cache::remember($overviewKey, $ttl, function () use ($overviewFilters, $overviewInclude) {
        return $this->service->getComposite($overviewFilters, $overviewInclude);
      });

      [$countryMeta, $countryPayload] = $this->splitMeta(is_array($countryData) ? $countryData : []);
      [$overviewMeta, $overviewPayload] = $this->splitMeta(is_array($overviewData) ? $overviewData : []);

      $countrySummary = $countryPayload['summary'] ?? null;
      $topCountriesInbound = $countryPayload['top_countries_inbound'] ?? [];
      $topCountriesOutbound = $countryPayload['top_countries_outbound'] ?? [];

      $overviewSummary = $overviewPayload['summary'] ?? null;
      $timeseries = $overviewPayload['timeseries']['data'] ?? [];
      $topServicesInbound = $overviewPayload['top_services_inbound'] ?? [];
      $topServicesOutbound = $overviewPayload['top_services_outbound'] ?? [];

      if (empty($countrySummary) && empty($overviewSummary) && empty($timeseries)) {
        return ApiResponse::error('Tidak ada data untuk dibuatkan ringkasan.', null, 404);
      }

      $unit = $overviewMeta['unit'] ?? 'Orang';
      $year = isset($countryMeta['year']) ? (int) $countryMeta['year'] : ($overviewMeta['year'] ?? null);
      $prevYear = isset($countryMeta['prevYear']) ? (int) $countryMeta['prevYear'] : ($overviewMeta['prevYear'] ?? null);

      $countryInNow = (float) ($countrySummary['inbound']['value_now'] ?? 0);
      $countryInPrev = (float) ($countrySummary['inbound']['value_prev'] ?? 0);
      $countryOutNow = (float) ($countrySummary['outbound']['value_now'] ?? 0);
      $countryOutPrev = (float) ($countrySummary['outbound']['value_prev'] ?? 0);

      $showInboundSingle = ($countryInNow > 0 || $countryInPrev > 0 || !empty($topCountriesInbound));
      $showOutboundSingle = ($countryOutNow > 0 || $countryOutPrev > 0 || !empty($topCountriesOutbound));

      $bilatInNow = (float) ($overviewSummary['inbound']['value_now'] ?? 0);
      $bilatInPrev = (float) ($overviewSummary['inbound']['value_prev'] ?? 0);
      $bilatOutNow = (float) ($overviewSummary['outbound']['value_now'] ?? 0);
      $bilatOutPrev = (float) ($overviewSummary['outbound']['value_prev'] ?? 0);
      $showInboundBilateral = ($bilatInNow > 0 || $bilatInPrev > 0 || !empty($topServicesInbound));
      $showOutboundBilateral = ($bilatOutNow > 0 || $bilatOutPrev > 0 || !empty($topServicesOutbound));

      $inboundSeries = $this->buildSeries($timeseries, 'inbound_value');
      $outboundSeries = $this->buildSeries($timeseries, 'outbound_value');

      $inboundChart = ($showInboundSingle || $showInboundBilateral)
        ? $this->buildLineChartImageGd($inboundSeries, [59, 130, 246])
        : null;
      $outboundChart = ($showOutboundSingle || $showOutboundBilateral)
        ? $this->buildLineChartImageGd($outboundSeries, [245, 158, 11])
        : null;

      $originName = is_array($overviewMeta['origin_names'] ?? null)
        ? implode(', ', array_values($overviewMeta['origin_names']))
        : ($overviewMeta['origin_names'] ?? ($overviewMeta['origin'][0] ?? null));
      if (!$originName) {
        $originCodes = $overviewMeta['origin'] ?? ($overviewFilters['origin'] ?? null);
        if (is_array($originCodes)) {
          $originName = implode(', ', array_values($originCodes));
        } elseif (is_string($originCodes)) {
          $originName = $originCodes;
        } else {
          $originName = '-';
        }
      }
      $destNames = $overviewMeta['dest_names'] ?? null;
      if (is_object($destNames)) {
        $destNames = (array) $destNames;
      }
      if (is_array($destNames)) {
        $vals = array_values(array_filter($destNames, fn ($v) => (string) $v !== ''));
        $destName = $vals ? implode(', ', $vals) : null;
      } else {
        $destName = $destNames ?? null;
      }
      if (!$destName) {
        $destName = '-';
      }

      $tanggalCetak = now()->translatedFormat('d F Y');
      $pdf = Pdf::loadView('exports.overview-jasa-mitra-summary', [
        'tanggalCetak' => $tanggalCetak,
        'countryMeta' => $countryMeta,
        'overviewMeta' => $overviewMeta,
        'unit' => $unit,
        'year' => $year,
        'prevYear' => $prevYear,
        'countryInNow' => $countryInNow,
        'countryOutNow' => $countryOutNow,
        'topCountriesInbound' => $topCountriesInbound,
        'topCountriesOutbound' => $topCountriesOutbound,
        'topServicesInbound' => $topServicesInbound,
        'topServicesOutbound' => $topServicesOutbound,
        'timeseries' => $timeseries,
        'inboundChart' => $inboundChart,
        'outboundChart' => $outboundChart,
        'showInboundSingle' => $showInboundSingle,
        'showOutboundSingle' => $showOutboundSingle,
        'showInboundBilateral' => $showInboundBilateral,
        'showOutboundBilateral' => $showOutboundBilateral,
        'originName' => $originName,
        'destName' => $destName,
      ])->setPaper('a4', 'portrait');

      $filename = 'overview-jasa-summary-' . now()->format('Ymd_His') . '.pdf';

      return response($pdf->output(), 200)
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    } catch (\Throwable $e) {
      report($e);
      return ApiResponse::error('Gagal memuat ringkasan overview jasa');
    }
  }
}
