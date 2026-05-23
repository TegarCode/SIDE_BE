<?php

namespace App\Http\Controllers\Api\V1\ReportGenerator\RCACMSA;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReportGenerator\RCACMSADownloadRequest;
use App\Http\Requests\ReportGenerator\RCACMSARequest;
use App\Repositories\ReportGenerator\RCACMSA\RCACMSARepositoryInterface;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use Throwable;

Carbon::setLocale('id');

class RCACMSAController extends Controller
{
    protected RCACMSARepositoryInterface $RCACMSAService;

    public function __construct(RCACMSARepositoryInterface $RCACMSAService)
    {
        $this->RCACMSAService = $RCACMSAService;
    }

    public function filter(RCACMSARequest $request): JsonResponse
    {
        try {
            $filters = $request->validated();
            $results = $this->RCACMSAService->getTableFilterData($filters);

            // Format angka
            $formatted = array_map(function ($row) {
                $arr = (array) $row;
                foreach ($arr as $key => $val) {
                    if (! in_array($key, ['HsCode', 'NamaProduk']) && is_numeric($val)) {
                        // ribuan pakai titik, desimal koma (silakan sesuaikan)
                        $arr[$key] = number_format($val, 2, ',', '.');
                    }
                }

                return $arr;
            }, $results);

            // Meta info
            $meta = [
                'origin' => $filters['origin'],
                'destination' => $filters['destination'],
                'strategy1' => $filters['strategy1'],
            ];

            return ApiResponse::success($formatted, 'Data RCACMSA berhasil diambil', $meta);
        } catch (Throwable $e) {
            return ApiResponse::error('Gagal mengambil data RCACMSA', [
                'exception' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
        }
    }

    public function snapshotWord(RCACMSADownloadRequest $request)
    {
        try {
            $validated = $request->validated();

            $origin = $validated['origin'];
            $destination = $validated['destination'];

            $originName = $this->RCACMSAService->getCountryName($origin);
            $destinationName = $this->RCACMSAService->getCountryName($destination);

            $strategies = ['EXPORT', 'IMPORT', 'FDI INBOUND', 'FDI OUTBOUND'];
            $phpWord = new PhpWord;

            $sectionStyle = ['orientation' => 'landscape', 'marginLeft' => 800, 'marginRight' => 800];
            $fontTitle = ['bold' => true, 'size' => 13];
            $centerAlign = ['alignment' => Jc::CENTER];

            foreach ($strategies as $strategy) {
                $section = $phpWord->addSection($sectionStyle);

                $footer = $section->addFooter();
                $footerStyle = ['alignment' => Jc::CENTER, 'lineHeight' => 1.0];
                $footer->addText('Portfolio Demo Team', ['size' => 8], $footerStyle);
                $footer->addText('Strategy & Analytics Showcase', ['size' => 8], $footerStyle);
                $footer->addText('Sample Repository', ['size' => 8], $footerStyle);

                $imagePath = public_path('assets/img/report-generator/stat-snapshots.png');
                if (is_file($imagePath)) {
                    $section->addImage($imagePath, ['width' => 760, 'wrappingStyle' => 'inline']);
                } else {
                    Log::warning('RCACMSA snapshotWord image missing', ['path' => $imagePath]);
                }

                $section->addText('Portfolio Demo: '.now()->translatedFormat('d F Y'), $fontTitle);
                $section->addText('STAT-SNAPSHOTS ANALISIS INTELIJEN PASAR :', $fontTitle, $centerAlign);
                $section->addText('HASIL RCA-CMSA PEMETAAN DAN IDENTIFIKASI PRODUK POTENSIAL', $fontTitle, $centerAlign);
                $section->addText("{$originName} - {$destinationName}", $fontTitle, $centerAlign);
                $section->addText("STRATEGY : {$strategy}", ['size' => 9, 'bold' => true]);

                $data = $this->RCACMSAService->getSnapshotData($origin, $destination, $strategy);
                $this->buildSnapshotTable($section, $data, $origin, $destination, $strategy);
            }

            $fileName = 'stat_snapshots_'.now()->format('Ymd_His').'.docx';
            $directory = storage_path('app/public');
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            if (! is_writable($directory)) {
                throw new \RuntimeException("Storage directory not writable: {$directory}");
            }
            $path = $directory.DIRECTORY_SEPARATOR.$fileName;

            IOFactory::createWriter($phpWord, 'Word2007')->save($path);
        } catch (Throwable $e) {
            Log::error('RCACMSA snapshotWord failed', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            return response()->json(['error' => 'Gagal membuat dokumen Word'], 500);
        }

        return response()->download($path)->deleteFileAfterSend(true);
    }

    private function buildSnapshotTable($section, $data, $alpha_origin, $alpha_destination, $strategy)
    {
        $headers = [
            'No',
            'Kode Produk',
            'Deskripsi Produk',
            "RCA {$alpha_origin}",
            "CMSA {$alpha_origin}",
            "Class {$alpha_origin}",
            "RCA {$alpha_destination}",
            "CMSA {$alpha_destination}",
            "Kelas {$alpha_destination}",
            'Strategi',
            "{$alpha_origin} {$strategy} to Dunia",
            "{$alpha_origin} {$strategy} to {$alpha_destination}",
        ];

        $columnWidths = [
            500,   // No
            1200,  // Kode Produk
            5000,  // Deskripsi Produk
            1000,  // RCA IDN
            1500,  // CMSA IDN
            1200,  // Class IDN
            1000,  // RCA USA
            1500,  // CMSA USA
            1200,  // Class USA
            1200,  // Strategi
            1800,  // IDN Export to World
            1800,  // IDN Export to Partner
        ];

        $fontHeader = ['bold' => true, 'size' => 10];
        $fontBody = ['size' => 9];
        $centerAlign = ['alignment' => Jc::CENTER];
        $cellStyle = ['borderSize' => 6, 'borderColor' => '000000', 'valign' => 'center'];

        $table = $section->addTable($cellStyle);
        $table->addRow();
        $paragraphStyle = [
            'alignment' => Jc::CENTER,
            'spaceBefore' => 2,
            'spaceAfter' => 2,
            'spacing' => 0,
            'lineHeight' => 1.0,
        ];

        $fontHeader = [
            'bold' => true,
            'size' => 11,
            'color' => '000000',
        ];

        $cellStyle = [
            'bgColor' => 'D3D3D3',
        ];

        foreach ($headers as $i => $header) {
            $width = $columnWidths[$i] ?? 2200;
            $cell = $table->addCell($width, [
                'valign' => 'center',
                'bgColor' => 'D3D3D3',
                'borderSize' => 6,
                'borderColor' => '000000',
            ]);
            $cell->addText(htmlspecialchars($header), $fontHeader, $paragraphStyle);
        }

        foreach ($data as $i => $row) {
            $cells = [
                $i + 1,
                $row->HsCode ?? '-',
                $row->NamaProduk ?? '-',
                number_format((float) ($row->RCA_Asal ?? 0), 2, ',', '.'),
                number_format((float) ($row->CMSA_Asal ?? 0), 2, ',', '.'),
                $row->Class_Asal ?? '-',
                number_format((float) ($row->RCA_Tujuan ?? 0), 2, ',', '.'),
                number_format((float) ($row->CMSA_Tujuan ?? 0), 2, ',', '.'),
                $row->Class_Tujuan ?? '-',
                $row->Strategy ?? '-',
                number_format((float) ($row->Asal_World ?? 0), 2, ',', '.'),
                number_format((float) ($row->Ekspor_RI_To_Partner ?? 0), 2, ',', '.'),
            ];

            $table->addRow(400);
            foreach ($cells as $j => $val) {
                $width = $columnWidths[$j] ?? 2200;
                $table->addCell($width)->addText(htmlspecialchars((string) $val), $fontBody, $centerAlign);
            }
        }
    }

    public function snapshotPdf(RCACMSADownloadRequest $request)
    {
        $validated = $request->validated();

        $origin = $validated['origin'];
        $destination = $validated['destination'];

        $originName = $this->RCACMSAService->getCountryName($origin);
        $destinationName = $this->RCACMSAService->getCountryName($destination);

        $strategies = ['EXPORT', 'IMPORT', 'FDI INBOUND', 'FDI OUTBOUND'];
        $dataPerStrategy = [];

        foreach ($strategies as $strategy) {
            $data = $this->RCACMSAService->getSnapshotData($origin, $destination, $strategy);

            $dataPerStrategy[] = [
                'strategy' => $strategy,
                'data' => $data,
            ];
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.snapshot', [
            'originName' => $originName,
            'destinationName' => $destinationName,
            'dataPerStrategy' => $dataPerStrategy,
            'tanggalCetak' => now()->translatedFormat('d F Y'),
        ])->setPaper('a4', 'landscape');

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="stat_snapshot.pdf"');
    }

    public function summaryWord(RCACMSADownloadRequest $request)
    {
        $validated = $request->validated();

        $origin = $validated['origin'];
        $destination = $validated['destination'];

        $originName = $this->RCACMSAService->getCountryName($origin);
        $destinationName = $this->RCACMSAService->getCountryName($destination);

        $strategies = ['EXPORT', 'IMPORT', 'FDI INBOUND', 'FDI OUTBOUND'];

        $phpWord = new \PhpOffice\PhpWord\PhpWord;
        $section = $phpWord->addSection();
        $center = ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER];
        $justify = ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::BOTH];
        $fontTitle = ['bold' => true, 'size' => 13];

        // Styling
        $phpWord->addTitleStyle(1, ['bold' => true, 'size' => 16, 'color' => '1F4E79'], $center);
        $phpWord->addTitleStyle(2, ['bold' => true, 'size' => 14], $center);

        // Header Section
        $section->addImage(public_path('assets/img/report-generator/summary.png'), ['width' => 450]);
        $section->addText('Portfolio Demo: '.now()->translatedFormat('d F Y'), $fontTitle);
        $section->addTitle('ANALISIS INTELIJEN PASAR :', 2);
        $section->addTitle('PEMETAAN DAN IDENTIFIKASI PRODUK POTENSIAL', 2);
        $section->addTitle("{$originName} - {$destinationName}", 2);
        $section->addImage(public_path('assets/img/report-generator/insights.png'), ['width' => 450]);

        $section->addText(
            "Data Summary analisis intelijen pasar dalam pemetaan dan identifikasi produk potensial {$originName} - {$destinationName} menggunakan perangkat analisis RCA dan CMSA oleh Kiki Verico (2020). Rekomendasi kebijakan sebagai berikut:",
            ['size' => 11],
            $justify
        );

        // Summary List
        $counter = 1;
        foreach ($strategies as $strategy) {
            $judul = match ($strategy) {
                'EXPORT' => "Strategi Ekspor Produk {$originName} ke {$destinationName}",
                'IMPORT' => "Strategi Impor Produk {$originName} ke {$destinationName}",
                'FDI INBOUND' => "Strategi {$originName} menarik FDI INBOUND dari {$destinationName}",
                'FDI OUTBOUND' => "Strategi {$originName} FDI OUTBOUND ke {$destinationName}",
            };

            $data = $this->RCACMSAService->getSummaryListData($origin, $destination, $strategy, 10);

            $list = count($data)
              ? implode(', ', array_map(fn ($d) => "{$d->HsCode} ({$d->NamaProduk})", $data)).'.'
              : '– Tidak ada data tersedia.';

            $section->addText("{$counter}. {$judul} yaitu: {$list}", ['size' => 11], $justify);
            $counter++;
        }

        // Tabel RCA CMSA tiap strategi
        foreach ($strategies as $strategy) {
            $section = $phpWord->addSection();
            $section->addImage(public_path('assets/img/report-generator/hasil-rca-cmsa.png'), ['width' => 450]);
            $section->addText("HASIL RCA-CMSA PRODUK POTENSIAL {$originName} - {$destinationName}", ['bold' => true, 'size' => 11], $center);
            $section->addText("REKOMENDASI STRATEGI : {$strategy}", ['bold' => true, 'size' => 11]);

            $data = $this->RCACMSAService->getSummaryTableData($origin, $destination, $strategy);
            if (count($data)) {
                $this->buildSummaryTable($phpWord, $section, $data, $originName, $destinationName);
            } else {
                $section->addText('Tidak ada data tersedia untuk strategi ini.', ['italic' => true]);
            }
        }

        $fileName = 'data_summary_'.now()->format('Ymd_His').'.docx';
        $path = storage_path("app/public/{$fileName}");
        \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return response()->download($path)->deleteFileAfterSend(true);
    }

    private function buildSummaryTable($phpWord, $section, array $data, $originName, $destinationName): void
    {
        $headers = [
            'No',
            'Kode Produk',
            'Deskripsi Produk',
            "RCA\n{$originName}",
            "CMSA\n{$originName}",
            "Class\n{$originName}",
            "RCA\n{$destinationName}",
            "CMSA\n{$destinationName}",
            "Class\n{$destinationName}",
            'Strategi',
            "{$originName}\nke Dunia",
            "{$originName}\nke {$destinationName}",
        ];

        $widths = [800, 1200, 5000, 1200, 1200, 1200, 1200, 1200, 1200, 1500, 1500, 1500];

        $fontHeader = ['bold' => true, 'size' => 9];
        $fontBody = ['size' => 9];
        $alignCenter = ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER];
        $alignLeft = ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::START];
        $alignRight = ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END];

        // ✅ Tambahkan style ke PhpWord, bukan ke section
        $phpWord->addTableStyle('CustomTable', [
            'borderSize' => 6,
            'borderColor' => '000000',
            'cellMargin' => 80,
        ], [
            'bgColor' => 'EEEEEE',
        ]);

        $table = $section->addTable('CustomTable');

        // Header
        $table->addRow();
        foreach ($headers as $i => $header) {
            $table->addCell($widths[$i])->addText($header, $fontHeader, $alignCenter);
        }

        foreach ($data as $i => $row) {
            $table->addRow();

            $cells = [
                $i + 1,
                $row->HsCode ?? '-',
                htmlspecialchars($row->NamaProduk ?? '-'),
                number_format((float) ($row->RCA_Asal ?? 0), 2, ',', '.'),
                number_format((float) ($row->CMSA_Asal ?? 0), 2, ',', '.'),
                $row->Class_Asal ?? '-',
                number_format((float) ($row->RCA_Tujuan ?? 0), 2, ',', '.'),
                number_format((float) ($row->CMSA_Tujuan ?? 0), 2, ',', '.'),
                $row->Class_Tujuan ?? '-',
                $row->Strategy ?? '-',
                number_format((float) ($row->Asal_World ?? 0), 2, ',', '.'),
                number_format((float) ($row->Ekspor_RI_To_Partner ?? 0), 2, ',', '.'),
            ];

            foreach ($cells as $j => $val) {
                $style = in_array($j, [3, 4, 6, 7, 10, 11]) ? $alignRight : ($j === 2 ? $alignLeft : $alignCenter);
                $table->addCell($widths[$j])->addText($val, $fontBody, $style);
            }
        }
    }

    public function summaryPdf(RCACMSADownloadRequest $request)
    {
        $validated = $request->validated();

        $origin = $validated['origin'];
        $destination = $validated['destination'];

        $originName = $this->RCACMSAService->getCountryName($origin);
        $destinationName = $this->RCACMSAService->getCountryName($destination);

        $strategies = ['EXPORT', 'IMPORT', 'FDI INBOUND', 'FDI OUTBOUND'];

        $summaryList = [];
        $tablesData = [];

        foreach ($strategies as $strategy) {
            $produkData = $this->RCACMSAService->getSummaryDataWithMetrics($origin, $destination, $strategy);

            $produkList = collect($produkData)->take(10)->map(function ($item) {
                return "{$item->HsCode} ({$item->NamaProduk})";
            })->implode(', ');

            $judul = match ($strategy) {
                'EXPORT' => "Strategi Ekspor Produk {$originName} ke {$destinationName} yaitu:",
                'IMPORT' => "Strategi Impor Produk {$originName} ke {$destinationName} yaitu:",
                'FDI INBOUND' => "Strategi {$originName} menarik FDI INBOUND dari {$destinationName} untuk produk (sektor) yaitu:",
                'FDI OUTBOUND' => "Strategi {$originName} FDI OUTBOUND ke {$destinationName} untuk produk (sektor) yaitu:",
            };

            $summaryList[] = [
                'judul' => $judul,
                'produk' => $produkList,
            ];

            $tablesData[] = [
                'strategy' => $strategy,
                'data' => $produkData,
            ];
        }

        $tanggalCetak = now()->translatedFormat('d F Y');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.summary', [
            'originName' => $originName,
            'destinationName' => $destinationName,
            'summaryList' => $summaryList,
            'tablesData' => $tablesData,
            'tanggalCetak' => $tanggalCetak,
        ])->setPaper('a4', 'portrait');

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="data_summary.pdf"');
    }
}
