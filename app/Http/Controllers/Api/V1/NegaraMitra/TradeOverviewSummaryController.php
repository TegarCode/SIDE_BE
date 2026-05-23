<?php

namespace App\Http\Controllers\Api\V1\NegaraMitra;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\NegaraMitra\OverviewPerdaganganRequest;
use App\Repositories\NegaraMitra\Perdagangan\TradeRepositoryInterface;
use App\Support\SideCacheKey;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;

class TradeOverviewSummaryController extends Controller
{
  public function __construct(private TradeRepositoryInterface $service) {}

  private function splitMeta(array $arr): array
  {
    $meta = $arr['meta'] ?? [];
    unset($arr['meta']);
    return [$meta, $arr];
  }

  private function normalizeLimit(mixed $v): ?int
  {
    if (is_string($v)) {
      $s = strtolower(trim($v));
      if ($s === 'all') return null;
      if (is_numeric($s)) $v = (int) $s;
    }
    if ($v === 0 || $v === '0') return null;
    $allowed = [10, 25, 50];
    return in_array((int) $v, $allowed, true) ? (int) $v : 50;
  }

  private function makeTradeCacheKey(array $filters, array $include = []): string
  {
    $origin = $filters['origin'] ?? null;
    $dest   = $filters['dest']   ?? null;
    $hs     = $filters['hsCode'] ?? null;

    return SideCacheKey::pairs(
      ['negara-mitra', 'trade', 'overview'],
      [
        'origin' => $origin ?: 'all',
        'dest' => $dest ?: 'all',
        'hs' => $hs === 'ALL' ? 'all' : ($hs ?: 'all'),
        'year' => (int) ($filters['year'] ?? 0),
        'source' => $filters['source'] ?? 5,
        'limit' => $filters['limit'] ?? 'all',
        'include' => $include,
      ]
    );
  }

  public function overviewSummaryPdf(OverviewPerdaganganRequest $request)
  {
    try {
      $filters = $request->sanitizedFilters();
      $filters['limit'] = $this->normalizeLimit($filters['limit'] ?? null);
      $include = $request->sanitizedInclude();

      $isIdnAll = (function ($f) {
        $o = $f['origin'] ?? null;
        $d = $f['dest']   ?? null;
        $isOriginIdn = (is_array($o) && count($o) === 1 && strtoupper($o[0]) === 'IDN')
          || (is_string($o) && strtoupper($o) === 'IDN');
        $isDestAll = !isset($d) || $d === null
          || (is_string($d) && strtolower($d) === 'all')
          || (is_array($d) && count($d) === 0);
        return $isOriginIdn && $isDestAll;
      })($filters);

      $isChnToIdn = (function ($f) {
        $o = $f['origin'] ?? null;
        $d = $f['dest']   ?? null;
        $isOriginChn = (is_array($o) && count($o) === 1 && strtoupper($o[0]) === 'CHN')
          || (is_string($o) && strtoupper($o) === 'CHN');
        $isDestIdn = (is_array($d) && count($d) === 1 && strtoupper($d[0]) === 'IDN')
          || (is_string($d) && strtoupper($d) === 'IDN');
        return $isOriginChn && $isDestIdn;
      })($filters);

      $year = (int)($filters['year'] ?? 0);
      $nowY = (int) date('Y');
      $ttl  = $year >= $nowY ? now()->addHours(6) : now()->addDays(7);

      if ($isIdnAll || $isChnToIdn) {
        $cacheKey = $this->makeTradeCacheKey($filters, $include);
        $data = Cache::remember($cacheKey, $ttl, function () use ($filters, $include) {
          return $this->service->getComposite($filters, $include);
        });
      } else {
        $data = $this->service->getComposite($filters, $include);
      }

      [$meta, $payload] = $this->splitMeta(is_array($data) ? $data : []);
      if (empty($payload)) {
        return ApiResponse::error('Tidak ada data untuk dibuatkan ringkasan.', null, 404);
      }

      $summary = $payload['summary'] ?? [];
      $timeseries = $payload['timeseries']['data'] ?? [];
      $topExport = array_slice($payload['top_products_export'] ?? [], 0, 20);
      $topImport = array_slice($payload['top_products_import'] ?? [], 0, 20);

      $exportNow = (int) ($summary['export']['value_now'] ?? 0);
      $exportPrev = (int) ($summary['export']['value_prev'] ?? 0);
      $importNow = (int) ($summary['import']['value_now'] ?? 0);
      $importPrev = (int) ($summary['import']['value_prev'] ?? 0);
      $totalNow = $exportNow + $importNow;
      $totalPrev = $exportPrev + $importPrev;

      $unit = $meta['unit'] ?? 'Ribu US$';
      $originName = is_array($meta['origin_name'] ?? null)
        ? implode(', ', array_values($meta['origin_name']))
        : ($meta['origin_name'] ?? ($meta['origin'][0] ?? '-'));
      $destName = is_array($meta['dest_name'] ?? null)
        ? implode(', ', array_values($meta['dest_name']))
        : ($meta['dest_name'] ?? ($meta['dest'][0] ?? '-'));

      $summaryNarrative = $this->buildOverviewNarrative(
        $originName,
        $destName,
        $meta['year'] ?? null,
        $exportNow,
        $exportPrev,
        $importNow,
        $importPrev,
        $unit
      );

      $exportSeries = $this->buildTrendSeriesFromTimeseries($timeseries, 'export');
      $importSeries = $this->buildTrendSeriesFromTimeseries($timeseries, 'import');
      $totalSeries = $this->buildTrendSeriesFromTimeseries($timeseries, 'total');
      $balanceSeries = $this->buildTrendSeriesFromTimeseries($timeseries, 'balance');
      $exportChart = $this->buildLineChartImageGd($exportSeries, [239, 68, 68]);
      $importChart = $this->buildLineChartImageGd($importSeries, [16, 185, 129]);
      $totalChart = $this->buildLineChartImageGd($totalSeries, [14, 116, 144]);
      $balanceChart = $this->buildLineChartImageGd($balanceSeries, [168, 85, 247]);

      $tanggalCetak = now()->translatedFormat('d F Y');
      $pdf = Pdf::loadView('exports.overview-perdagangan-mitra-summary', [
        'tanggalCetak' => $tanggalCetak,
        'meta' => $meta,
        'unit' => $unit,
        'originName' => $originName,
        'destName' => $destName,
        'summaryNarrative' => $summaryNarrative,
        'exportNow' => $exportNow,
        'exportPrev' => $exportPrev,
        'importNow' => $importNow,
        'importPrev' => $importPrev,
        'totalNow' => $totalNow,
        'totalPrev' => $totalPrev,
        'timeseries' => $timeseries,
        'exportChart' => $exportChart,
        'importChart' => $importChart,
        'totalChart' => $totalChart,
        'balanceChart' => $balanceChart,
        'topExport' => $topExport,
        'topImport' => $topImport,
      ])->setPaper('a4', 'portrait');

      $filename = 'overview-perdagangan-summary-' . now()->format('Ymd_His') . '.pdf';

      return response($pdf->output(), 200)
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    } catch (\Throwable $e) {
      report($e);
      return ApiResponse::error('Gagal memuat ringkasan overview perdagangan');
    }
  }

  private function buildOverviewNarrative(
    string $origin,
    string $dest,
    ?int $year,
    int $exportNow,
    int $exportPrev,
    int $importNow,
    int $importPrev,
    string $unit
  ): string {
    $parts = [];
    $labelYear = $year ? (string) $year : 'tahun terbaru';
    $parts[] = "Ringkasan perdagangan antara {$origin} dan {$dest} pada {$labelYear} menunjukkan total ekspor sebesar " . number_format($exportNow, 0, ',', '.') . " {$unit} dan impor sebesar " . number_format($importNow, 0, ',', '.') . " {$unit}.";

    if ($exportPrev > 0) {
      $pctExp = (($exportNow - $exportPrev) / $exportPrev) * 100;
      $parts[] = "Dibanding tahun sebelumnya, ekspor berubah " . ($pctExp >= 0 ? '+' : '') . number_format($pctExp, 2, ',', '.') . "%.";
    }
    if ($importPrev > 0) {
      $pctImp = (($importNow - $importPrev) / $importPrev) * 100;
      $parts[] = "Impor berubah " . ($pctImp >= 0 ? '+' : '') . number_format($pctImp, 2, ',', '.') . "%.";
    }

    $totalNow = $exportNow + $importNow;
    $totalPrev = $exportPrev + $importPrev;
    if ($totalPrev > 0) {
      $pctTotal = (($totalNow - $totalPrev) / $totalPrev) * 100;
      $parts[] = "Secara keseluruhan, total perdagangan mencapai " . number_format($totalNow, 0, ',', '.') . " {$unit} dengan perubahan " . ($pctTotal >= 0 ? '+' : '') . number_format($pctTotal, 2, ',', '.') . "% dibanding periode sebelumnya.";
    } else {
      $parts[] = "Secara keseluruhan, total perdagangan mencapai " . number_format($totalNow, 0, ',', '.') . " {$unit}.";
    }

    $neraca = $exportNow - $importNow;
    $parts[] = "Neraca perdagangan pada periode ini berada di angka " . number_format($neraca, 0, ',', '.') . " {$unit}, yang menunjukkan posisi " . ($neraca >= 0 ? 'surplus' : 'defisit') . ".";

    $parts[] = "Komposisi produk utama memberikan gambaran sektor yang paling berkontribusi dalam perdagangan bilateral serta perubahan nilainya dari periode sebelumnya.";
    $parts[] = "Informasi ini penting untuk mengidentifikasi komoditas unggulan, ketergantungan impor, serta peluang diversifikasi pasar pada tahun berikutnya.";

    return implode(' ', $parts);
  }

  private function buildTrendSeriesFromTimeseries(array $rows, string $key): array
  {
    $years = [];
    $values = [];
    foreach ($rows as $row) {
      $year = (int) ($row['year'] ?? 0);
      if (!$year) continue;
      $years[] = $year;
      if ($key === 'total') {
        $values[] = (int) (($row['export'] ?? 0) + ($row['import'] ?? 0));
      } elseif ($key === 'balance') {
        $values[] = (int) (($row['export'] ?? 0) - ($row['import'] ?? 0));
      } else {
        $values[] = (int) ($row[$key] ?? 0);
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
}
