<?php

namespace App\Http\Controllers\Api\V1\Analisis;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Analisis\AnalisisRCAEPDService;
use Illuminate\Http\Request;

class AnalisisRCAEPDController extends Controller
{
    public function __construct(
        private readonly AnalisisRCAEPDService $service
    ) {
    }

    public function data(Request $request)
    {
        try {
            return ApiResponse::success(
                $this->service->getData($this->validateFilter($request)),
                'Data RCA EPD berhasil dimuat'
            );
        } catch (\Throwable $th) {
            report($th);
            return ApiResponse::error('Gagal memuat data RCA EPD');
        }
    }

    public function calculation(Request $request)
    {
        try {
            return ApiResponse::success(
                $this->service->getCalculation($this->validateFilter($request)),
                'Data kalkulasi RCA EPD berhasil dimuat'
            );
        } catch (\Throwable $th) {
            report($th);
            return ApiResponse::error('Gagal memuat kalkulasi RCA EPD');
        }
    }

    public function comparison(Request $request)
    {
        try {
            return ApiResponse::success(
                $this->service->getComparison($this->validateFilter($request)),
                'Data comparison RCA EPD berhasil dimuat'
            );
        } catch (\Throwable $th) {
            report($th);
            return ApiResponse::error('Gagal memuat comparison RCA EPD');
        }
    }

    public function xModelOptions(Request $request)
    {
        try {
            return ApiResponse::success(
                $this->service->getXModelOptions($this->validateFilter($request)),
                'Opsi jenis pasar RCA EPD berhasil dimuat'
            );
        } catch (\Throwable $th) {
            report($th);
            return ApiResponse::error('Gagal memuat opsi jenis pasar RCA EPD');
        }
    }

    private function validateFilter(Request $request): array
    {
        $xModel = $request->input('x_model');

        if (is_string($xModel)) {
            $xModel = trim($xModel);
            $xModel = $xModel === '' || strtoupper($xModel) === 'ALL' ? null : $xModel;
        }

        return [
            'origin' => strtoupper($request->input('origin', 'IDN')),
            'dest' => strtoupper($request->input('dest', 'IDN')),
            'level' => $request->input('level', 6),
            'x_model' => $xModel,
        ];
    }
}
