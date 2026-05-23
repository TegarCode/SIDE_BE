<?php

namespace App\Http\Controllers\Api\V1\SektorPrioritas;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Support\SideCacheKey;
use App\Services\SektorPrioritas\EconomyDigitalService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EkonomiDigitalSummaryController extends Controller
{
  public function __construct(protected EconomyDigitalService $EconomyDigitalService) {}

  protected string $conn = 'server_mysql';
  protected string $TB_SEKTOR = 'tbsektor_hscode';
  protected int $ID_Sektor = 16;

  /** Memo per-request */
  private ?array $allHsMemo = null;

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
    $filters = [];

    $partner  = $this->csvToUpperArray($input['partner'] ?? []);
    $reporter = $this->csvToUpperArray($input['reporter'] ?? []);
    if (!empty($partner))  $filters['partner']  = $partner;
    if (!empty($reporter)) $filters['reporter'] = $reporter;

    $ys = $input['year_start'] ?? null;
    $ye = $input['year_end'] ?? null;
    if (is_numeric($ys)) $filters['year_start'] = (int)$ys;
    if (is_numeric($ye)) $filters['year_end']   = (int)$ye;

    $status = $input['status'] ?? null;
    $canon  = function ($v) {
      $s = strtolower(trim((string)$v));
      if (in_array($s, ['export', 'ekspor'], true)) return 'Export';
      if (in_array($s, ['import', 'impor'], true))  return 'Import';
      return null;
    };
    if (is_array($status)) {
      $st = array_values(array_filter(array_unique(array_map($canon, $status))));
      if ($st) $filters['status'] = $st;
    } elseif (is_string($status)) {
      $st = $canon($status);
      if ($st) $filters['status'] = $st;
    }

    $dirjen = $this->csvToUpperArray($input['dirjen'] ?? []);
    if (!empty($dirjen)) $filters['dirjen'] = $dirjen;

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
    if (!empty($hsList)) $filters['hs_list'] = $hsList;

    return array_filter(
      $filters,
      fn($v) => is_array($v) ? count($v) > 0 : !is_null($v) && $v !== ''
    );
  }

  private function allHsCodes(): array
  {
    if ($this->allHsMemo !== null) {
      return $this->allHsMemo;
    }

    $codes = Cache::remember(
      'side_cache:sektor-prioritas:ekonomi-digital:all-hs-codes',
      now()->addDays(3),
      function () {
        $rows = DB::connection($this->conn)
          ->table("{$this->TB_SEKTOR} as s")
          ->where('s.ID_Sektor', $this->ID_Sektor)
          ->distinct()
          ->pluck('s.hscode')
          ->toArray();

        $clean = array_values(array_unique(array_filter(array_map(
          fn ($v) => trim((string) $v),
          $rows
        ))));
        sort($clean, SORT_STRING);
        return $clean;
      }
    );

    $this->allHsMemo = $codes;
    return $codes;
  }

  private function buildCacheKey(string $prefix, array $filters): string
  {
    $stable = $filters;
    if (isset($stable['hs_list']) && is_array($stable['hs_list'])) {
      $tmp = array_values(array_unique(array_map('strval', $stable['hs_list'])));
      sort($tmp, SORT_STRING);
      $stable['hs_list'] = $tmp;
    }
    ksort($stable);
    return SideCacheKey::pairs(['sektor-prioritas', 'ekonomi-digital-summary', $prefix], $stable);
  }

  private function buildCacheKeyByOriginsDests(
    string $prefix,
    array $origins,
    array $dests,
    array $filters,
    int $kodeSumber,
    int $limit
  ): string {
    $o = $origins; sort($o, SORT_STRING);
    $d = $dests;   sort($d, SORT_STRING);

    $rest = $filters;
    unset($rest['reporter'], $rest['partner']);

    if (isset($rest['hs_list']) && is_array($rest['hs_list'])) {
      $tmp = array_values(array_unique(array_map('strval', $rest['hs_list'])));
      sort($tmp, SORT_STRING);
      $rest['hs_list'] = $tmp;
    }

    $rest['_sumber'] = $kodeSumber;
    $rest['_limit']  = $limit;

    return SideCacheKey::pairs(
      ['sektor-prioritas', 'ekonomi-digital-summary', $prefix],
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
      fn($v) => strtoupper((string)$v),
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
      $digits = preg_replace('/\D+/', '', (string)$v);
      if ($digits !== '') $out[] = $digits;
    }
    $out = array_values(array_unique($out));
    sort($out, SORT_STRING);
    return $out;
  }

  private function buildBarChartImageGd(array $labels, array $values, array $rgb): ?string
  {
    if (empty($values)) return null;
    $max = max($values);
    $min = min($values);
    if ($max == 0 && $min == 0) return null;

    $count = count($values);
    $width = 560;
    $height = 240;
    $paddingLeft = 40;
    $paddingRight = 14;
    $paddingTop = 18;
    $paddingBottom = 50;

    $img = imagecreatetruecolor($width, $height);
    imagealphablending($img, true);
    imagesavealpha($img, true);

    $white = imagecolorallocate($img, 255, 255, 255);
    $bg = imagecolorallocate($img, 248, 250, 252);
    $grid = imagecolorallocate($img, 226, 232, 240);
    $bar = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
    $text = imagecolorallocate($img, 100, 116, 139);

    imagefill($img, 0, 0, $white);
    imagefilledrectangle($img, 0, 0, $width, $height, $bg);

    $plotWidth = $width - $paddingLeft - $paddingRight;
    $plotHeight = $height - $paddingTop - $paddingBottom;

    $range = max(1, $max - $min);
    $zeroY = $paddingTop + (int) ($plotHeight * (1 - ((0 - $min) / $range)));
    $zeroY = max($paddingTop, min($paddingTop + $plotHeight, $zeroY));

    for ($i = 0; $i <= 4; $i++) {
      $y = $paddingTop + (int) ($plotHeight * $i / 4);
      imageline($img, $paddingLeft, $y, $width - $paddingRight, $y, $grid);
    }

    $gap = 8;
    $barWidth = (int) floor(($plotWidth - ($count - 1) * $gap) / max(1, $count));
    for ($i = 0; $i < $count; $i++) {
      $val = $values[$i];
      $x1 = $paddingLeft + $i * ($barWidth + $gap);
      $x2 = $x1 + $barWidth;
      $barHeight = (int) round(($plotHeight * (abs($val)) / $range));
      if ($val >= 0) {
        $y1 = $zeroY - $barHeight;
        $y2 = $zeroY;
      } else {
        $y1 = $zeroY;
        $y2 = $zeroY + $barHeight;
      }
      imagefilledrectangle($img, $x1, $y1, $x2, $y2, $bar);
      $label = $labels[$i] ?? '';
      imagestring($img, 2, $x1, $height - 18, $label, $text);
    }

    ob_start();
    imagepng($img);
    $data = ob_get_clean();
    $img = null;

    return 'data:image/png;base64,' . base64_encode($data);
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

      $allHs = $this->allHsCodes();
      $reqHs = $filtersNegara['hs_list'] ?? [];
      if (empty($reqHs)) {
        $reqHs = $allHs;
        $filtersNegara['hs_list'] = $reqHs;
      }

      $cacheKey = $this->buildCacheKey('nilai-tik', $filtersNegara);
      $ttl = now()->addDays(3);
      $negaraCacheHit = Cache::has($cacheKey);
      $perNegara = $negaraCacheHit
        ? Cache::get($cacheKey)
        : Cache::remember(
            $cacheKey,
            $ttl,
            fn() => $this->EconomyDigitalService->getNilaiPerdaganganTIK($filtersNegara)
          );
      [$negaraPayload, $negaraMeta] = $this->splitPayloadAndMeta($perNegara);

      $kodeSumber = (int) ($produkInput['sumber'] ?? $request->input('sumber', 5));
      $limit = (int) ($produkInput['limit'] ?? $request->input('limit', 50));
      $reporters = $this->csvToUpperArray($produkInput['origin'] ?? $request->input('origin', []));
      $partners  = $this->csvToUpperArray($produkInput['dest'] ?? $request->input('dest', []));
      if (!empty($reporters)) $filtersProduk['reporter'] = $reporters;
      if (!empty($partners))  $filtersProduk['partner']  = $partners;
      if (empty($filtersProduk['hs_list'])) $filtersProduk['hs_list'] = $this->allHsCodes();

      $cacheKeyProduk = $this->buildCacheKeyByOriginsDests(
        'tik-produk',
        $reporters,
        $partners,
        $filtersProduk,
        $kodeSumber,
        $limit
      );
      $produkCacheHit = Cache::has($cacheKeyProduk);
      $perProduk = $produkCacheHit
        ? Cache::get($cacheKeyProduk)
        : Cache::remember(
            $cacheKeyProduk,
            $ttl,
            fn() => $this->EconomyDigitalService->nilaiPerdaganganPerProduk($filtersProduk)
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

      $topRows = [];
      foreach ($items as $row) {
        $total = (float) ($row['nilai_perdagangan'][$latestYear] ?? 0);
        $neraca = (float) ($row['neraca'][$latestYear] ?? 0);
        $export = ($total + $neraca) / 2;
        $import = ($total - $neraca) / 2;
        $topRows[] = [
          'negara' => $row['negara'] ?? '-',
          'proporsi' => (float) ($row['proporsi'][$latestYear] ?? 0),
          'export' => $export,
          'import' => $import,
          'total' => $total,
          'neraca' => $neraca,
          'prev_total' => (float) ($prevYear ? ($row['nilai_perdagangan'][$prevYear] ?? 0) : 0),
          'prev_neraca' => (float) ($prevYear ? ($row['neraca'][$prevYear] ?? 0) : 0),
          'prev_proporsi' => (float) ($prevYear ? ($row['proporsi'][$prevYear] ?? 0) : 0),
          'prev_export' => (float) ($prevYear ? ((($row['nilai_perdagangan'][$prevYear] ?? 0) + ($row['neraca'][$prevYear] ?? 0)) / 2) : 0),
          'prev_import' => (float) ($prevYear ? ((($row['nilai_perdagangan'][$prevYear] ?? 0) - ($row['neraca'][$prevYear] ?? 0)) / 2) : 0),
        ];
      }

      $byExport = $topRows;
      usort($byExport, fn($a, $b) => $b['export'] <=> $a['export']);
      $byImport = $topRows;
      usort($byImport, fn($a, $b) => $b['import'] <=> $a['import']);
      $byTotal = $topRows;
      usort($byTotal, fn($a, $b) => $b['total'] <=> $a['total']);
      $byNeraca = $topRows;
      usort($byNeraca, fn($a, $b) => $b['neraca'] <=> $a['neraca']);

      $chartExport = $this->buildLineChartImageGd($years, array_values($exportByYear), [14, 116, 144]);
      $chartImport = $this->buildLineChartImageGd($years, array_values($importByYear), [245, 158, 11]);
      $chartTotal = $this->buildLineChartImageGd($years, array_values($totalByYear), [59, 130, 246]);
      $chartNeraca = $this->buildLineChartImageGd($years, array_values($neracaByYear), [168, 85, 247]);

      $produk = $produkPayload['produk'] ?? [];
      $latestProdukYear = $produkMeta['latest_year'] ?? ($produkMeta['tahun'] ? max($produkMeta['tahun']) : null);
      $prevProdukYear = $latestProdukYear ? ($latestProdukYear - 1) : ($produkMeta['prev_year'] ?? ($produkMeta['tahun'] ? min($produkMeta['tahun']) : null));
      $topProduk = $produk;
      usort($topProduk, function ($a, $b) use ($latestProdukYear) {
        $va = (float) ($a['total'][$latestProdukYear] ?? 0);
        $vb = (float) ($b['total'][$latestProdukYear] ?? 0);
        return $vb <=> $va;
      });
      $topProduk = array_slice($topProduk, 0, 20);

      $tanggalCetak = now()->translatedFormat('d F Y');
      $pdf = Pdf::loadView('exports.ekonomi-digital-summary', [
        'tanggalCetak' => $tanggalCetak,
        'negaraMeta' => $negaraMeta,
        'produkMeta' => $produkMeta,
        'latestYear' => $latestYear,
        'latestProdukYear' => $latestProdukYear,
        'items' => $items,
        'topProduk' => $topProduk,
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
        'byExport' => $byExport,
      ])->setPaper('a4', 'portrait');

      $filename = 'sektor-tik-summary-' . now()->format('Ymd_His') . '.pdf';

      return response($pdf->output(), 200)
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    } catch (\Throwable $e) {
      report($e);
      return ApiResponse::error('Gagal memuat ringkasan ekonomi digital');
    }
  }
}
