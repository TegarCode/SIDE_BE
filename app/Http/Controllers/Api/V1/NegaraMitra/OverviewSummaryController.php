<?php

namespace App\Http\Controllers\Api\V1\NegaraMitra;

use App\Http\Controllers\Controller;
use App\Support\SideCacheKey;
use App\Services\NegaraMitra\OverviewService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class OverviewSummaryController extends Controller
{
  public function __construct(protected OverviewService $overviewService) {}

  private function buildCacheKey(Request $request, string $prefix): string
  {
    $filters = $request->only(['negara', 'year', 'tahun']);
    $filters = array_filter($filters, static fn ($value) => $value !== null && $value !== '');
    $segments = array_values(array_filter(explode(':', trim($prefix, ':'))));

    return SideCacheKey::filters(
      array_merge(['negara-mitra'], $segments),
      $filters,
      ['negara', 'year', 'tahun']
    );
  }

  public function topPerdaganganSummaryPdf(Request $request)
  {
    $negara = strtoupper((string) $request->input('negara', $request->query('negara', '')));
    if (strlen($negara) !== 3) {
      return response()->json(
        [
          'success' => false,
          'message' => 'Parameter negara (alpha-3) wajib diisi, contoh: CHN',
          'data'    => (object) [],
        ],
        422
      );
    }

    $cacheKey = $this->buildCacheKey($request, 'top-perdagangan:');
    $ttl = now()->addDays(3);

    $data = Cache::remember($cacheKey, $ttl, function () use ($negara) {
      return $this->overviewService->getTopPerdagangan($negara);
    });

    if (empty($data) || empty($data['meta'])) {
      return response()->json([
        'success' => true,
        'message' => "Tidak ada data untuk {$negara}",
        'data'    => (object) [],
      ]);
    }

    $meta = $data['meta'] ?? [];
    $items = $data['items'] ?? [];
    $topProduk = $this->normalizeTopPerdaganganProduk($data['top_produk'] ?? ['ekspor' => [], 'impor' => []]);

    $latestYear = $meta['latest_year'] ?? null;
    $prevYear = $meta['prev_year'] ?? null;
    $unit = $meta['unit'] ?? 'Ribu US$';

    $summaryNarrative = $this->buildTopPerdaganganNarrative($meta, $items);

    $tanggalCetak = now()->translatedFormat('d F Y');

    $pdf = Pdf::loadView('exports.top-perdagangan-mitra-summary', [
      'tanggalCetak' => $tanggalCetak,
      'meta' => $meta,
      'unit' => $unit,
      'latestYear' => $latestYear,
      'prevYear' => $prevYear,
      'summaryNarrative' => $summaryNarrative,
      'partnerRows' => $items,
      'topProdukEkspor' => $topProduk['ekspor'] ?? [],
      'topProdukImpor' => $topProduk['impor'] ?? [],
    ])->setPaper('a4', 'portrait');

    $filename = 'top-perdagangan-mitra-summary-' . now()->format('Ymd_His') . '.pdf';

    return response($pdf->output(), 200)
      ->header('Content-Type', 'application/pdf')
      ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
  }

  public function topInvestasiSummaryPdf(Request $request)
  {
    $negara = strtoupper((string) $request->input('negara', $request->query('negara', '')));
    if (strlen($negara) !== 3) {
      return response()->json(
        [
          'success' => false,
          'message' => 'Parameter negara (alpha-3) wajib diisi, contoh: CHN',
          'data'    => (object) [],
        ],
        422
      );
    }

    $cacheKey = $this->buildCacheKey($request, 'top-investasi:');
    $ttl = now()->addDays(3);

    $data = Cache::remember($cacheKey, $ttl, function () use ($negara) {
      return $this->overviewService->getTopInvestasi($negara);
    });

    if (empty($data)) {
      return response()->json([
        'success' => true,
        'message' => "Tidak ada data untuk {$negara}",
        'data'    => (object) [],
      ]);
    }

    $payload = $data['data'] ?? $data;
    $meta = $payload['meta'] ?? [];
    $items = $payload['items'] ?? ['inbound' => [], 'outbound' => []];

    if (empty($meta)) {
      return response()->json([
        'success' => true,
        'message' => "Tidak ada data untuk {$negara}",
        'data'    => (object) [],
      ]);
    }

    $inbound = $items['inbound'] ?? [];
    $outbound = $items['outbound'] ?? [];

    $unit = $meta['unit'] ?? 'Ribu US$';
    $summaryNarrative = $this->buildTopInvestasiNarrative($meta, $inbound, $outbound, $unit);

    $tanggalCetak = now()->translatedFormat('d F Y');

    $pdf = Pdf::loadView('exports.top-investasi-mitra-summary', [
      'tanggalCetak' => $tanggalCetak,
      'meta' => $meta,
      'unit' => $unit,
      'summaryNarrative' => $summaryNarrative,
      'inboundRows' => $inbound,
      'outboundRows' => $outbound,
    ])->setPaper('a4', 'portrait');

    $filename = 'top-investasi-mitra-summary-' . now()->format('Ymd_His') . '.pdf';

    return response($pdf->output(), 200)
      ->header('Content-Type', 'application/pdf')
      ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
  }

  public function topPariwisataSummaryPdf(Request $request)
  {
    $negara = strtoupper((string) $request->input('negara', $request->query('negara', '')));
    if (strlen($negara) !== 3) {
      return response()->json(
        [
          'success' => false,
          'message' => 'Parameter negara (alpha-3) wajib diisi, contoh: CHN',
          'data'    => (object) [],
        ],
        422
      );
    }

    $cacheKey = $this->buildCacheKey($request, 'top-pariwisata:');
    $ttl = now()->addDays(3);

    $data = Cache::remember($cacheKey, $ttl, function () use ($negara) {
      return $this->overviewService->getTopPariwisata($negara);
    });

    if (empty($data)) {
      return response()->json([
        'success' => true,
        'message' => "Tidak ada data untuk {$negara}",
        'data'    => (object) [],
      ]);
    }

    $payload = $data['data'] ?? $data;
    $meta = $payload['meta'] ?? [];
    $items = $payload['items'] ?? ['inbound' => [], 'outbound' => []];

    if (empty($meta)) {
      return response()->json([
        'success' => true,
        'message' => "Tidak ada data untuk {$negara}",
        'data'    => (object) [],
      ]);
    }

    $inbound = $items['inbound'] ?? [];
    $outbound = $items['outbound'] ?? [];

    $summaryNarrative = $this->buildTopPariwisataNarrative($meta, $inbound, $outbound);
    $tanggalCetak = now()->translatedFormat('d F Y');

    $pdf = Pdf::loadView('exports.top-pariwisata-mitra-summary', [
      'tanggalCetak' => $tanggalCetak,
      'meta' => $meta,
      'summaryNarrative' => $summaryNarrative,
      'inboundRows' => $inbound,
      'outboundRows' => $outbound,
    ])->setPaper('a4', 'portrait');

    $filename = 'top-pariwisata-mitra-summary-' . now()->format('Ymd_His') . '.pdf';

    return response($pdf->output(), 200)
      ->header('Content-Type', 'application/pdf')
      ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
  }

  public function topJasaSummaryPdf(Request $request)
  {
    $negara = strtoupper((string) $request->input('negara', $request->query('negara', '')));
    if (strlen($negara) !== 3) {
      return response()->json(
        [
          'success' => false,
          'message' => 'Parameter negara (alpha-3) wajib diisi, contoh: CHN',
          'data'    => (object) [],
        ],
        422
      );
    }

    $cacheKey = $this->buildCacheKey($request, 'top-jasa:');
    $ttl = now()->addDays(3);

    $data = Cache::remember($cacheKey, $ttl, function () use ($negara) {
      return $this->overviewService->getTopJasa($negara);
    });

    if (empty($data)) {
      return response()->json([
        'success' => true,
        'message' => "Tidak ada data untuk {$negara}",
        'data'    => (object) [],
      ]);
    }

    $payload = $data['data'] ?? $data;
    $meta = $payload['meta'] ?? [];
    $items = $payload['items']['bothYears'] ?? [];

    if (empty($meta)) {
      return response()->json([
        'success' => true,
        'message' => "Tidak ada data untuk {$negara}",
        'data'    => (object) [],
      ]);
    }

    $summaryNarrative = $this->buildTopJasaNarrative($meta);
    $tanggalCetak = now()->translatedFormat('d F Y');

    $pdf = Pdf::loadView('exports.top-jasa-mitra-summary', [
      'tanggalCetak' => $tanggalCetak,
      'meta' => $meta,
      'summaryNarrative' => $summaryNarrative,
      'rows' => $items,
    ])->setPaper('a4', 'portrait');

    $filename = 'top-jasa-mitra-summary-' . now()->format('Ymd_His') . '.pdf';

    return response($pdf->output(), 200)
      ->header('Content-Type', 'application/pdf')
      ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
  }

  private function buildTopPerdaganganNarrative(array $meta, array $items): string
  {
    $latestYear = $meta['latest_year'] ?? null;
    $prevYear = $meta['prev_year'] ?? null;
    $unit = $meta['unit'] ?? 'Ribu US$';

    $totalWorld = (int) ($meta['total_world'] ?? 0);
    $totalExport = (int) ($meta['total_export_y2'] ?? 0);
    $totalImport = (int) ($meta['total_import_y2'] ?? 0);
    $totalExportPrev = (int) ($meta['total_export_y1'] ?? 0);
    $totalImportPrev = (int) ($meta['total_import_y1'] ?? 0);

    $parts = [];
    if ($latestYear !== null) {
      $parts[] = "Pada {$latestYear}, total nilai perdagangan mencapai " . number_format($totalWorld, 0, ',', '.') . " {$unit}.";
      $parts[] = "Ekspor tercatat sebesar " . number_format($totalExport, 0, ',', '.') . " {$unit}, sementara impor sebesar " . number_format($totalImport, 0, ',', '.') . " {$unit}.";
      if ($prevYear !== null) {
        $parts[] = "Dibanding {$prevYear}, ekspor berubah " . $this->formatSignedPct($totalExport, $totalExportPrev) . " dan impor berubah " . $this->formatSignedPct($totalImport, $totalImportPrev) . ".";
      }
    }

    $topNames = [];
    foreach (array_slice($items, 0, 3) as $row) {
      if (!empty($row['negara'])) {
        $topNames[] = $row['negara'];
      }
    }
    if (count($topNames)) {
      $parts[] = "Mitra dagang terbesar meliputi " . implode(', ', $topNames) . ".";
    }

    if (!empty($items) && $latestYear !== null) {
      $top = $items[0] ?? null;
      if ($top) {
        $topName = $top['negara'] ?? '-';
        $topShare = number_format((float) ($top['proporsi_y2'] ?? 0), 2, ',', '.');
        $parts[] = "Kontribusi terbesar pada {$latestYear} berasal dari {$topName} dengan pangsa sekitar {$topShare}% dari total perdagangan tahun tersebut.";
      }
    }

    $parts[] = "Ringkasan ini memberikan gambaran struktur mitra utama dan arah dinamika ekspor-impor, sekaligus membantu mengidentifikasi pasar prioritas serta evaluasi kinerja perdagangan tahunan.";

    return implode(' ', $parts);
  }

  private function buildTopInvestasiNarrative(array $meta, array $inbound, array $outbound, string $unit): string
  {
    $parts = [];

    $asal = $meta['asal'] ?? ($meta['asal_alpha3'] ?? '-');
    $latestYear = $meta['latest_year'] ?? null;
    $prevYear = $meta['prev_year'] ?? null;

    $inLatest = $meta['inbound_latest_year'] ?? null;
    $inPrev = $meta['inbound_prev_year'] ?? null;
    $outLatest = $meta['outbound_latest_year'] ?? null;
    $outPrev = $meta['outbound_prev_year'] ?? null;

    if ($latestYear !== null) {
      $parts[] = "Ringkasan ini menampilkan posisi top investasi masuk dan keluar untuk {$asal} dengan referensi tahun terbaru {$latestYear}" . ($prevYear ? " dibanding {$prevYear}" : "") . ".";
      $parts[] = "Data disusun berdasarkan mitra tujuan terbesar pada masing-masing arah investasi sehingga memberikan gambaran struktur pasar dan konsentrasi mitra utama.";
    }

    if ($inLatest !== null) {
      $topInbound = [];
      foreach (array_slice($inbound, 0, 3) as $row) {
        if (!empty($row['negara'])) $topInbound[] = $row['negara'];
      }
      if (count($topInbound)) {
        $parts[] = "Pada investasi masuk tahun {$inLatest}" . ($inPrev ? " dibanding {$inPrev}" : "") . ", mitra terbesar meliputi " . implode(', ', $topInbound) . ".";
        $parts[] = "Perubahan persentase mencerminkan dinamika aliran investasi masuk dari mitra utama dan dapat digunakan untuk membaca pergeseran preferensi investor.";
      }
    }

    if ($outLatest !== null) {
      $topOutbound = [];
      foreach (array_slice($outbound, 0, 3) as $row) {
        if (!empty($row['negara'])) $topOutbound[] = $row['negara'];
      }
      if (count($topOutbound)) {
        $parts[] = "Untuk investasi keluar tahun {$outLatest}" . ($outPrev ? " dibanding {$outPrev}" : "") . ", mitra utama mencakup " . implode(', ', $topOutbound) . ".";
        $parts[] = "Informasi ini membantu mengidentifikasi arah ekspansi perusahaan dan fokus geografis investasi keluar pada periode terkini.";
      }
    }

    $parts[] = "Nilai pada tabel disajikan dalam {$unit} dan dapat digunakan untuk melihat dinamika per mitra beserta perubahan persentasenya.";
    $parts[] = "Secara keseluruhan, ringkasan ini dapat menjadi bahan evaluasi dan masukan untuk strategi promosi investasi serta penguatan kerja sama ekonomi bilateral.";

    return implode(' ', $parts);
  }

  private function buildTopPariwisataNarrative(array $meta, array $inbound, array $outbound): string
  {
    $parts = [];
    $latestYear = $meta['latest_year'] ?? null;
    $prevYear = $meta['prev_year'] ?? null;
    $tujuan = $meta['tujuan'] ?? '-';

    if ($latestYear !== null) {
      $parts[] = "Ringkasan ini menampilkan pergerakan pariwisata untuk {$tujuan} pada tahun {$latestYear}" . ($prevYear ? " dibanding {$prevYear}" : "") . ".";
    }

    if (!empty($inbound)) {
      $topInbound = [];
      foreach (array_slice($inbound, 0, 3) as $row) {
        if (!empty($row['country'])) $topInbound[] = $row['country'];
      }
      if (count($topInbound)) {
        $parts[] = "Pada pariwisata masuk, negara asal utama meliputi " . implode(', ', $topInbound) . ".";
      }
    } else {
      $parts[] = "Data pariwisata masuk tidak tersedia pada periode ini.";
    }

    if (!empty($outbound)) {
      $topOutbound = [];
      foreach (array_slice($outbound, 0, 3) as $row) {
        if (!empty($row['country'])) $topOutbound[] = $row['country'];
      }
      if (count($topOutbound)) {
        $parts[] = "Pada pariwisata keluar, tujuan utama meliputi " . implode(', ', $topOutbound) . ".";
      }
    } else {
      $parts[] = "Data pariwisata keluar tidak tersedia pada periode ini.";
    }

    $parts[] = "Ringkasan ini membantu melihat konsentrasi tujuan/asalan wisatawan dan perubahan antar tahun pada negara mitra.";

    return implode(' ', $parts);
  }

  private function buildTopJasaNarrative(array $meta): string
  {
    $parts = [];
    $latestYear = $meta['latest_year'] ?? null;
    $prevYear = $meta['prev_year'] ?? null;
    $asal = $meta['asal'] ?? 'IDN';
    $tujuan = $meta['tujuan'] ?? '-';

    if ($latestYear !== null) {
      $parts[] = "Ringkasan ini menampilkan Tenaga Kerja Indonesia di {$tujuan} pada tahun {$latestYear}" . ($prevYear ? " dibanding {$prevYear}" : "") . ".";
    }

    $totalLatest = (int) ($meta['total_latest'] ?? 0);
    $totalPrev = (int) ($meta['total_prev'] ?? 0);
    if ($latestYear !== null) {
      $parts[] = "Total tenaga kerja pada {$latestYear} tercatat " . number_format($totalLatest, 0, ',', '.') . " orang, sedangkan pada {$prevYear} sebesar " . number_format($totalPrev, 0, ',', '.') . " orang.";
      if ($prevYear !== null && $totalPrev > 0) {
        $pct = (($totalLatest - $totalPrev) / $totalPrev) * 100;
        $parts[] = "Perubahan dari {$prevYear} ke {$latestYear} sebesar " . ($pct >= 0 ? '+' : '') . number_format($pct, 2, ',', '.') . "%.";
      }
    }

    $parts[] = "Data ini menggambarkan konsentrasi tenaga kerja Indonesia berdasarkan jenis profesi dan membantu memantau dinamika penempatan di negara mitra.";

    return implode(' ', $parts);
  }

  private function formatSignedPct(int $latest, int $prev): string
  {
    if ($prev === 0) return 'n/a';
    $pct = (($latest - $prev) / $prev) * 100;
    return ($pct >= 0 ? '+' : '') . number_format($pct, 2, ',', '.') . '%';
  }

  private function normalizeTopPerdaganganProduk(array $topProduk): array
  {
    $normalizeList = function (array $rows, string $globalKeyNew, string $aseanKeyNew): array {
      return array_map(function ($row) use ($globalKeyNew, $aseanKeyNew) {
        if (!is_array($row)) return $row;
        $globalRows = $row[$globalKeyNew] ?? ($row['kompetitor_global'] ?? []);
        $aseanRows = $row[$aseanKeyNew] ?? ($row['kompetitor_asean'] ?? []);

        $row['kompetitor_global'] = $globalRows;
        $row['kompetitor_asean'] = $aseanRows;
        $row['kompetitor_global_display'] = $this->formatCompetitorList($globalRows);
        $row['kompetitor_asean_display'] = $this->formatCompetitorList($aseanRows);
        $row['rank_indonesia'] = $this->extractIndonesiaRank($globalRows);
        return $row;
      }, $rows);
    };

    $ekspor = $topProduk['ekspor'] ?? [];
    $impor = $topProduk['impor'] ?? [];

    return [
      'ekspor' => is_array($ekspor) ? $normalizeList($ekspor, 'kompetitor_global_top_tujuan_ekspor', 'kompetitor_asean_top_tujuan_ekspor') : [],
      'impor' => is_array($impor) ? $normalizeList($impor, 'kompetitor_global_top_tujuan_impor', 'kompetitor_asean_top_tujuan_impor') : [],
    ];
  }

  private function formatCompetitorList(array $rows): string
  {
    $clean = [];
    foreach ($rows as $i => $row) {
      if (!is_array($row)) continue;
      $label = trim((string) ($row['negara'] ?? ($row['kode_alpha3'] ?? ($row['alpha3'] ?? ''))));
      if ($label === '') continue;
      $rank = null;
      if (isset($row['rank']) && is_numeric($row['rank'])) {
        $rank = (int) $row['rank'];
      } elseif (isset($row['rank_global']) && is_numeric($row['rank_global'])) {
        $rank = (int) $row['rank_global'];
      }
      $clean[] = [
        'label' => $label,
        'rank' => $rank,
        'index' => $i + 1,
      ];
    }

    if (empty($clean)) return '-';
    if (count($clean) === 1) {
      $single = $clean[0]['label'];
      return function_exists('mb_strtoupper') ? mb_strtoupper($single, 'UTF-8') : strtoupper($single);
    }

    $out = [];
    foreach ($clean as $row) {
      $no = $row['rank'] ?? $row['index'];
      $label = function_exists('mb_strtoupper') ? mb_strtoupper($row['label'], 'UTF-8') : strtoupper($row['label']);
      $out[] = $no . ') ' . $label;
    }
    return implode(', ', $out);
  }

  private function extractIndonesiaRank(array $rows): string
  {
    foreach ($rows as $row) {
      if (!is_array($row)) continue;
      $a3 = strtoupper((string) ($row['kode_alpha3'] ?? ($row['alpha3'] ?? '')));
      $name = strtoupper((string) ($row['negara'] ?? ''));
      if ($a3 === 'IDN' || $name === 'INDONESIA') {
        if (isset($row['rank']) && is_numeric($row['rank'])) return (string) ((int) $row['rank']);
        if (isset($row['rank_global']) && is_numeric($row['rank_global'])) return (string) ((int) $row['rank_global']);
        break;
      }
    }
    return '-';
  }
}
