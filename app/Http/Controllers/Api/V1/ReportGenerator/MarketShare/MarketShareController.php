<?php

namespace App\Http\Controllers\Api\V1\ReportGenerator\MarketShare;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportGenerator\MarketShareRequest;
use App\Services\ReportGenerator\MarketShareService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\TemplateProcessor;
use App\Helpers\ApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Throwable;

class MarketShareController extends Controller
{
  protected MarketShareService $MarketShareService;

  public function __construct(MarketShareService $MarketShareService)
  {
    $this->MarketShareService = $MarketShareService;
  }

  public function filter(MarketShareRequest $request): JsonResponse
  {
    try {
      $filters = $request->validated();

      $results = $this->MarketShareService->getFilteredMarketShareData($filters);

      $formatted = array_map(function ($row) {
        $arr = (array) $row;
        foreach ($arr as $key => $val) {
          if (! in_array($key, ['HsCode', 'NamaProduk', 'Tahun'], true) && is_numeric($val)) {
            $arr[$key] = number_format($val, 2, ',', '.');
          }
        }
        return $arr;
      }, $results);

      $originName = $this->MarketShareService->getCountryName($filters['origin']);

      $meta = [
        'origin'      => $filters['origin'],
        'origin_name' => $originName,
        'destination' => $filters['destination'],
        'sumber'      => $this->MarketShareService->getSourceName($filters['sumber']),
        'strategy1'   => $filters['strategy1'],
        'top_n'       => $filters['top_n'],
        'year'        => $filters['year'],
      ];

      return ApiResponse::success(
        $formatted,
        'Data market share berhasil diambil',
        $meta
      );
    } catch (Throwable $e) {
      return ApiResponse::error(
        'Gagal mengambil data market share',
        ['exception' => $e->getMessage()]
      );
    }
  }

  public function snapshotWord(MarketShareRequest $request)
  {
    $filters = $request->validated();
    $data    = $this->MarketShareService->getFilteredMarketShareData($filters);

    $regionLabel   = '';
    $destinationId = $filters['destination'];

    // 🔹 Khusus Dunia (ALL)
    if ($destinationId === 'ALL') {
      $regionLabel = 'Dunia';
    } elseif (is_numeric($destinationId)) {
      // 🔹 Group organisasi (tborgjenis)
      $regionLabel = DB::connection('server_mysql')
        ->table('tborgjenis')
        ->where('ID_Org', $destinationId)
        ->value('Organization') ?? '';
    } else {
      // 🔹 Kawasan/Benua (tbbenua)
      $regionLabel = DB::connection('server_mysql')
        ->table('tbbenua')
        ->where('ID_Benua', $destinationId)
        ->value('Benua') ?? '';
    }

    $countries = array_map(function ($c) {
      return [
        'name'     => $c['NegaraTujuan'],
        'total'    => (int) str_replace(['.', ','], '', $c['TotalNilai']),
        'products' => array_map(function ($p) {
          return [
            'hs4'         => $p['hs4'],
            'nama_produk' => $p['nama_produk'],
            'nilai'       => (int) str_replace(['.', ','], '', $p['nilai']),
            'pangsa'      => (float) str_replace(',', '.', str_replace('%', '', $p['pangsa'])),
          ];
        }, $c['products']),
      ];
    }, $data);

    $files = File::glob(resource_path('templates') . '/Market-Share.docx');
    if (empty($files)) {
      abort(500, 'Template Word tidak ditemukan di resources/templates');
    }
    $templatePath = collect($files)
      ->first(fn($p) => str_contains(basename($p), 'report-template'))
      ?? $files[0];

    $template = new TemplateProcessor($templatePath);

    $template->setValue('DATE', now()->locale('id')->isoFormat('D MMMM YYYY'));
    $template->setValue('TOP_N', $filters['top_n']);
    $template->setValue('STATUS', $filters['strategy1']);
    $template->setValue('SUMBER', $this->MarketShareService->getSourceName($filters['sumber']));
    $template->setValue('TAHUN', $filters['year']);
    $template->setValue('REGION', $regionLabel);

    $template->cloneBlock('country_block', count($countries), true, true);

    foreach ($countries as $i => $country) {
      $block = $i + 1;

      $template->setValue("country_name#{$block}", $country['name']);
      $template->setValue("total_ekspor#{$block}", number_format($country['total'], 0, ',', '.'));

      $template->cloneRow("no#{$block}", count($country['products']));

      foreach ($country['products'] as $j => $p) {
        $row = $j + 1;
        $template->setValue("no#{$block}#{$row}", $row);
        $template->setValue("hs4#{$block}#{$row}", $p['hs4']);
        $template->setValue("nama_produk#{$block}#{$row}", $p['nama_produk']);
        $template->setValue("nilai#{$block}#{$row}", number_format($p['nilai'], 0, ',', '.'));
        $template->setValue("pangsa#{$block}#{$row}", number_format($p['pangsa'], 1, ',', '.') . '%');
      }

      if ($block < count($countries)) {
        $template->setValue("pagebreak#{$block}", '</w:t></w:r></w:p><w:p><w:r><w:br w:type="page"/></w:r></w:p><w:p><w:r><w:t>');
      } else {
        $template->setValue("pagebreak#{$block}", '');
      }
    }

    $fileName = 'snapshot-' . now()->format('YmdHis') . '.docx';
    $savePath = storage_path('app/public/' . $fileName);
    $template->saveAs($savePath);

    return response()
      ->download($savePath, $fileName, [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      ])
      ->deleteFileAfterSend(true);
  }

  public function snapshotPdf(MarketShareRequest $request)
  {
    $filters = $request->validated();
    $data    = $this->MarketShareService->getFilteredMarketShareData($filters);

    $regionLabel   = '';
    $destinationId = $filters['destination'];

    // 🔹 Khusus Dunia (ALL)
    if ($destinationId === 'ALL') {
      $regionLabel = 'Dunia';
    } elseif (is_numeric($destinationId)) {
      $regionLabel = DB::connection('server_mysql')
        ->table('tborgjenis')
        ->where('ID_Org', $destinationId)
        ->value('Organization') ?? '';
    } else {
      $regionLabel = DB::connection('server_mysql')
        ->table('tbbenua')
        ->where('ID_Benua', $destinationId)
        ->value('Benua') ?? '';
    }

    $countries = array_map(function ($c) {
      return [
        'name'     => $c['NegaraTujuan'],
        'total'    => (int) str_replace(['.', ','], '', $c['TotalNilai']),
        'products' => array_map(function ($p) {
          return [
            'hs4'         => $p['hs4'],
            'nama_produk' => $p['nama_produk'],
            'nilai'       => (int) str_replace(['.', ','], '', $p['nilai']),
            'pangsa'      => (float) str_replace(',', '.', str_replace('%', '', $p['pangsa'])),
          ];
        }, $c['products']),
      ];
    }, $data);

    $html = View::make('templates.Market-Share', [
      'countries' => $countries,
      'region'    => $regionLabel,
      'status'    => $filters['strategy1'],
      'top_n'     => $filters['top_n'],
      'year'      => $filters['year'],
      'sumber'    => $this->MarketShareService->getSourceName($filters['sumber']),
    ])->render();

    $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');

    $filename = 'snapshot-' . now()->format('YmdHis') . '.pdf';

    return $pdf->download($filename);
  }
}
