<?php

namespace App\Http\Controllers\Api\V1\Analisis;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Analisis\AnalisisRSCATBIService;
use Illuminate\Http\Request;

class AnalisisRSCATBIController extends Controller
{
    public function __construct(private AnalisisRSCATBIService $service) {}

    private function validateFilter(Request $request): array
    {
        return [
            'origin' => strtoupper($request->input('origin', 'IDN')),
            'dest'   => strtoupper($request->input('dest', 'IDN')),
            'level'  => $request->input('level', 6),
        ];
    }

    // ===============================
    // 1. COUNTRY TRADE ANALYSIS
    // ===============================
    public function rscaTbiData(Request $request)
    {
        try {
            $filters = $this->validateFilter($request);

            $data = $this->service->getData($filters);

            return ApiResponse::success($data, 'Data RSCA TBI');
        } catch (\Throwable $e) {
            report($e);
            return ApiResponse::error('Gagal memuat data RSCA TBI');
        }
    }

    public function rscaTbiCalculation(Request $request)
    {
        try {
            $filters = $this->validateFilter($request);

            $data = $this->service->getCalculation($filters);

            return ApiResponse::success($data, 'Data Kalkulasi RSCA TBI');
        } catch (\Throwable $e) {
            report($e);
            return ApiResponse::error('Gagal memuat kalkulasi RSCA TBI');
        }
    }

    // ===============================
    // 2. COUNTRY COMPARISON
    // ===============================
    public function comparison(Request $request)
    {
        try {
            $filters = $this->validateFilter($request);

            $data = $this->service->getComparison($filters);

            return ApiResponse::success($data, 'Comparison RSCA TBI');
        } catch (\Throwable $e) {
            report($e);
            return ApiResponse::error('Gagal memuat comparison');
        }
    }
}
