<?php

namespace App\Http\Controllers\Api\V1\DataGenerator\Jasa;

use App\Http\Controllers\Controller;
use App\Services\DataGenerator\JasaService;
use App\Helpers\ApiResponse;
use App\Http\Requests\DataGenerator\JasaRequest;
use App\Support\SideCacheKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class JasaController extends Controller
{
    protected JasaService $jasaService;

    public function __construct(JasaService $jasaService)
    {
        $this->jasaService = $jasaService;
    }

    public function kodesumber()
    {
        $data = Cache::remember(
            SideCacheKey::path(['data-generator', 'jasa', 'distinct-kode-sumber']),
            now()->addDay(),
            fn () => $this->jasaService->getKodeSumber()
        );

        return response()->json([
            'success' => true,
            'message' => 'Data kode sumber jasa',
            'data'    => $data,
        ]);
    }

    public function tahunJasa()
    {
        $data = Cache::remember(
            SideCacheKey::path(['data-generator', 'jasa', 'distinct-tahun']),
            now()->addDay(),
            fn () => $this->jasaService->getTahun()
        );

        return response()->json([
            'success' => true,
            'message' => 'Data tahun jasa',
            'data'    => $data,
        ]);
    }

    protected function resolveGroupLabels(array $groupIds): array
    {
        if (empty($groupIds)) {
            return [];
        }

        $raw = collect($groupIds)->filter()->values();

        // Angka = organisasi, non-angka = benua
        $orgIds = $raw->filter(fn ($v) => is_numeric($v))->values()->toArray();
        $benuaIds = $raw->filter(fn ($v) => ! is_numeric($v))->values()->toArray();

        $labels = [];

        if (! empty($orgIds)) {
            $rows = DB::connection('server_mysql')
                ->table('tborgjenis')
                ->select('ID_Org', 'Abbreviation', 'Organization')
                ->whereIn('ID_Org', $orgIds)
                ->get();

            foreach ($rows as $r) {
                $abbr = trim((string) $r->Abbreviation);
                $labels[] = $abbr !== ''
                    ? "{$abbr} ({$r->Organization})"
                    : $r->Organization;
            }
        }

        if (! empty($benuaIds)) {
            $rows = DB::connection('server_mysql')
                ->table('tbbenua')
                ->select('ID_benua', 'Benua')
                ->whereIn('ID_benua', $benuaIds)
                ->orWhereIn('Benua', $benuaIds)
                ->get();

            foreach ($rows as $r) {
                $labels[] = $r->Benua;
            }
        }

        return array_values(array_unique($labels));
    }

    /* =========================================================
     * TABLE FILTER
     * ======================================================= */

    public function tablefilter(JasaRequest $request): JsonResponse
    {
        try {
            $filters = $request->validated();

            $results  = $this->jasaService->getFilteredServiceData($filters);
            $data     = $results['data'] ?? [];
            $metaRepo = $results['meta'] ?? [];

            // ===== PROFESI LABEL =====
            $profesiIds = $metaRepo['idProfesi'] ?? ($filters['idProfesi'] ?? []);
            if (in_array('all', $profesiIds, true)) {
                $profesiLabels = ['Semua Profesi'];
            } else {
                $profesiLabels = DB::connection('server_mysql')
                    ->table('tbprofesi')
                    ->whereIn('ID_Profesi', $profesiIds)
                    ->pluck('Profesi')
                    ->toArray();
            }

            // ===== SOURCE NAME (scalar) =====
            $sourceCode = $metaRepo['sourceCode'] ?? ($filters['sourceCode'] ?? null);
            $sourceName = null;
            if (!is_null($sourceCode) && $sourceCode !== '') {
                $sourceName = DB::connection('server_mysql')
                    ->table('tbsumber')
                    ->where('KodeSumber', $sourceCode)
                    ->value('NamaSumber');
            }

            $gender = $metaRepo['gender'] ?? ($filters['gender'] ?? null);

            $meta = [
                'years'            => $metaRepo['years'] ?? [
                    $filters['yearFrom'] ?? null,
                    $filters['yearTo']   ?? null,
                ],
                'sourceName'       => $sourceName,
                'origins'          => $metaRepo['origins']      ?? ($filters['origins'] ?? []),
                'originGroups'     => $this->resolveGroupLabels($filters['originGroups'] ?? []),
                'destinations'     => $metaRepo['destinations'] ?? ($filters['destinations'] ?? []),
                'destinationGroups'=> $this->resolveGroupLabels($filters['destinationGroups'] ?? []),
                'gender'           => $gender,
                'profesi'          => $profesiLabels,
            ];

            $message = $results['message'] ?? 'Data Jasa berhasil diambil';

            return ApiResponse::success($data, $message, $meta, 200);
        } catch (Throwable $e) {
            return ApiResponse::error('Gagal mengambil data Jasa', [
                'exception' => $e->getMessage(),
                'line'      => $e->getLine(),
                'file'      => $e->getFile(),
            ], 500);
        }
    }

    /* =========================================================
     * VISUALIZATION FILTER
     * ======================================================= */

    public function visualizationfilter(JasaRequest $request): JsonResponse
    {
        try {
            $filters  = $request->validated();
            $results  = $this->jasaService->getVisualizationServiceData($filters);

            $data     = $results['data'] ?? [];
            $metaRepo = $results['meta'] ?? [];

            // PROFESI LABEL
            $profesiIds = $metaRepo['idProfesi'] ?? ($filters['idProfesi'] ?? []);
            if (in_array('all', $profesiIds, true)) {
                $profesiLabels = ['Semua Profesi'];
            } else {
                $profesiLabels = DB::connection('server_mysql')
                    ->table('tbprofesi')
                    ->whereIn('ID_Profesi', $profesiIds)
                    ->pluck('Profesi')
                    ->toArray();
            }

            $gender = $metaRepo['gender'] ?? ($filters['gender'] ?? null);

            $meta = [
                'years'        => $metaRepo['years'] ?? [
                    $filters['yearFrom'] ?? null,
                    $filters['yearTo']   ?? null,
                ],
                'origins'      => $metaRepo['origins']      ?? ($filters['origins'] ?? []),
                'destinations' => $metaRepo['destinations'] ?? ($filters['destinations'] ?? []),
                'originGroups' => $this->resolveGroupLabels($filters['originGroups'] ?? []),
                'destinationGroups' => $this->resolveGroupLabels($filters['destinationGroups'] ?? []),
                'profesi'      => $profesiLabels,
                'gender'       => $gender,
            ];

            $message = $results['message'] ?? 'Data visualisasi Jasa berhasil diambil';

            return ApiResponse::success($data, $message, $meta, 200);
        } catch (Throwable $e) {
            return ApiResponse::error('Gagal mengambil data visualisasi Jasa', [
                'exception' => $e->getMessage(),
                'line'      => $e->getLine(),
                'file'      => $e->getFile(),
            ], 500);
        }
    }
}
