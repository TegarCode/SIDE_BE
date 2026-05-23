<?php

namespace App\Http\Controllers\Api\V1\Analisis;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Support\SideCacheKey;
use App\Services\Analisis\OperationalRiskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class OperationalRiskController extends Controller
{

  protected OperationalRiskService $operationalRiskService;

  public function __construct(OperationalRiskService $operationalRiskService)
  {
    $this->operationalRiskService = $operationalRiskService;
  }

  public function operationalRisk(Request $request): JsonResponse
  {
    try {
      $v = $request->validate([
        'negara'       => ['nullable', 'string', 'size:3'],
        'kode_sumber'  => ['nullable', 'integer'],
      ]);

      $filters = [
        'negara'       => isset($v['negara']) ? strtoupper($v['negara']) : null,
        'kode_sumber'  => $v['kode_sumber'] ?? null,
      ];

      $cacheKey  = SideCacheKey::pairs(
        ['analisis', 'operational-risk'],
        [
          'negara' => $filters['negara'] ?? 'all',
          'sumber' => $filters['kode_sumber'] ?? 'default',
        ]
      );

      $isDefault = $filters['negara'] === 'IDN';

      $ttl = now()->addDay();
      $fetchData = function () use ($filters) {
        $total = $this->operationalRiskService->getTotalScore($filters) ?? ['data' => [], 'meta' => []];
        $breakdown = $this->operationalRiskService->getBreakdownScore($filters) ?? ['data' => [], 'meta' => []];

        $meta = $total['meta'] ?? [];
        if (($breakdown['meta']['skipped'] ?? false) === true) {
          $meta['breakdown_skipped'] = true;
          $meta['breakdown_reason'] = $breakdown['meta']['reason'] ?? null;
        }

        return [
          'data' => [
            'total' => $total['data'] ?? [],
            'breakdown' => $breakdown['data'] ?? [],
          ],
          'meta' => $meta,
        ];
      };

      $payload = $isDefault
        ? Cache::remember($cacheKey, $ttl, $fetchData)
        : $fetchData();

      if (empty($payload['data']['total']) && empty($payload['data']['breakdown'])) {
        return ApiResponse::success([
          'total' => [],
          'breakdown' => [],
        ], 'Tidak ada data.', $payload['meta'] ?? []);
      }

      return ApiResponse::success($payload['data'] ?? [], 'Data operational risk berhasil diambil.', $payload['meta'] ?? []);
    } catch (\Throwable $e) {
      $errors = app()->environment('local') ? ['exception' => $e->getMessage()] : null;
      return ApiResponse::error('Terjadi kesalahan saat mengambil data operational risk.', $errors, 500);
    }
  }
}
