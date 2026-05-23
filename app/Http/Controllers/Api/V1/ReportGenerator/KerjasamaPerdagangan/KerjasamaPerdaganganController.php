<?php

namespace App\Http\Controllers\Api\V1\ReportGenerator\KerjasamaPerdagangan;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportGenerator\KerjasamaPerdaganganRequest;
use App\Services\ReportGenerator\KerjasamaPerdaganganService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\TemplateProcessor;
use App\Helpers\ApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Symfony\Component\Process\Process;
use Illuminate\Support\Arr;
use Throwable;
use Illuminate\Http\Response;

class KerjasamaPerdaganganController extends Controller
{
  protected KerjasamaPerdaganganService $KerjasamaPerdaganganService;

  public function __construct(KerjasamaPerdaganganService $KerjasamaPerdaganganService)
  {
    $this->KerjasamaPerdaganganService = $KerjasamaPerdaganganService;
  }
  public function filter(KerjasamaPerdaganganRequest $request): JsonResponse
  {
    try {
      // 1. Ambil semua filter yang sudah tervalidasi
      $filters = $request->validated();

      // 2. Panggil service untuk menghasilkan data tren (EXPORT/IMPORT per tahun)
      //    atau market share tergantung implementasi service Anda
      $data = $this->KerjasamaPerdaganganService->getFilteredKerjasamaPerdaganganData($filters);
      $destCodes = (array)$filters['destinations'];
      $destNames = array_map(
        fn(string $code) => $this->KerjasamaPerdaganganService->getCountryName($code),
        $destCodes
      );

      // 3. Siapkan meta tambahan
      $meta = [
        'origin'        => $filters['origin'],
        'origin_name'   => $this->KerjasamaPerdaganganService->getCountryName($filters['origin']),
        'destinations'  => $destNames,
        'sumber'        => $this->KerjasamaPerdaganganService->getSourceName($filters['sumber']),
        'year_start'    => $filters['year_start'],
        'year_end'      => $filters['year_end'],
      ];

      // 4. Kembalikan respons sesuai spec
      return ApiResponse::success(
        $data,
        'Data kerjasama perdagangan berhasil diambil',
        $meta
      );
    } catch (Throwable $e) {

      return ApiResponse::error(
        'Gagal mengambil data kerjasama perdagangan',
        ['exception' => $e->getMessage()]
      );
    }
  }
  public function snapshotWord(KerjasamaPerdaganganRequest $request)
  {
    $filters = $request->validated();
    $rawData = $this->KerjasamaPerdaganganService->getFilteredKerjasamaPerdaganganData($filters);

    // 1. Transformasi data
    $countries = array_map(function ($item) {
      return [
        'destination' => $item['NegaraTujuan'],
        'origin'      => $item['NegaraAsal'],
        'periods'     => array_map(function ($p) {
          $d = $p['detail'][0];
          return [
            'tahun'  => $p['tahun'],
            'ekspor' => (int) str_replace(['.', ','], '', $d['ekspor']),
            'impor'  => (int) str_replace(['.', ','], '', $d['impor']),
            'neraca' => (int) str_replace(['.', ',', '−'], ['', '', '-'], $d['neraca']),
            'total'  => (int) str_replace(['.', ','], '', $d['total']),
          ];
        }, $item['per']),
      ];
    }, $rawData);

    $origins = collect($countries)->pluck('origin')->unique()->values()->all();
    $destinations = collect($countries)->pluck('destination')->unique()->values()->all();

    // 2. Load template
    $files = File::glob(resource_path('templates') . '/Kerjasama-Perdagangan.docx');
    if (empty($files)) {
      abort(500, 'Template Word tidak ditemukan di resources/templates');
    }
    $templatePath = collect($files)
      ->first(fn($p) => str_contains(basename($p), 'report-template'))
      ?? $files[0];
    $template = new TemplateProcessor($templatePath);

    // 3. Set placeholder umum
    $template->setValue('DATE',       now()->locale('id')->isoFormat('D MMMM YYYY'));
    $template->setValue('ORIGIN',     count($origins) === 1 ? $origins[0] : implode(', ', $origins));
    $template->setValue('DESTINATION',     count($destinations) === 1 ? $destinations[0] : implode(', ', $destinations));
    $template->setValue('SUMBER', $this->KerjasamaPerdaganganService->getSourceName($filters['sumber']));
    $template->setValue('YEAR_START', $filters['year_start']);
    $template->setValue('YEAR_END',   $filters['year_end']);

    // 4a. Clone country_block sebanyak jumlah negara
    $template->cloneBlock('country_block', count($countries), true, true);

    foreach ($countries as $i => $country) {
      $idx = $i + 1;

      // 4b. Isi placeholder country-level
      $template->setValue("country_name#{$idx}", $country['destination']);
      $template->setValue("origin#{$idx}",       $country['origin']);

      // 4c. **Clone row_block#X** sebanyak periode yang ada
      $template->cloneRow("no#{$idx}", count($country['periods']));

      // 4d. Isi masing-masing baris periode
      foreach ($country['periods'] as $j => $period) {
        $row = $j + 1;
        $template->setValue("no#{$idx}#{$row}",     $row);
        $template->setValue("tahun#{$idx}#{$row}",  $period['tahun']);
        $template->setValue("ekspor#{$idx}#{$row}", number_format($period['ekspor'], 0, ',', '.'));
        $template->setValue("impor#{$idx}#{$row}",  number_format($period['impor'],  0, ',', '.'));
        $template->setValue("neraca#{$idx}#{$row}", number_format($period['neraca'], 0, ',', '.'));
        $template->setValue("total#{$idx}#{$row}",  number_format($period['total'],  0, ',', '.'));
      }
    }

    // 5. Simpan & kirim
    $fileName = 'snapshot-' . now()->format('YmdHis') . '.docx';
    $savePath = storage_path('app/public/' . $fileName);
    $template->saveAs($savePath);

    return response()
      ->download($savePath, $fileName, [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      ])->deleteFileAfterSend(true);
  }

  public function snapshotPdf(KerjasamaPerdaganganRequest $request)
  {
    $filters = $request->validated();
    $rawData = $this->KerjasamaPerdaganganService->getFilteredKerjasamaPerdaganganData($filters);

    $countries = array_map(function ($item) {
      return [
        'destination' => $item['NegaraTujuan'],
        'origin'      => $item['NegaraAsal'],
        'periods'     => array_map(function ($p) {
          $d = $p['detail'][0];
          return [
            'tahun'  => $p['tahun'],
            'ekspor' => (int) str_replace(['.', ','], '', $d['ekspor']),
            'impor'  => (int) str_replace(['.', ','], '', $d['impor']),
            'neraca' => (int) str_replace(['.', ',', '−'], ['', '', '-'], $d['neraca']),
            'total'  => (int) str_replace(['.', ','], '', $d['total']),
          ];
        }, $item['per']),
      ];
    }, $rawData);

    $origins = collect($countries)->pluck('origin')->unique()->values()->all();
    $destinations = collect($countries)->pluck('destination')->unique()->values()->all();

    $html = view('templates.Kerjasama-Perdagangan', [
      'countries'    => $countries,
      'originLabel'  => count($origins) === 1 ? $origins[0] : implode(', ', $origins),
      'destLabel'    => count($destinations) === 1 ? $destinations[0] : implode(', ', $destinations),
      'date'         => now()->locale('id')->isoFormat('D MMMM YYYY'),
      'year_start'   => $filters['year_start'],
      'year_end'     => $filters['year_end'],
      'sumber'       => $this->KerjasamaPerdaganganService->getSourceName($filters['sumber']),
    ])->render();

    return \PDF::loadHTML($html)->setPaper('a4', 'portrait')
      ->download('snapshot-' . now()->format('YmdHis') . '.pdf');
  }
}
