<?php

namespace App\Http\Controllers\Api\V1\DataGenerator\Investasi;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\DataGenerator\InvestasiRequest;
use App\Services\DataGenerator\InvestasiService;
use App\Support\SideCacheKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class InvestasiController extends Controller
{
    protected InvestasiService $investasiService;

    public function __construct(InvestasiService $investasiService)
    {
        $this->investasiService = $investasiService;
    }

    public function kodesumber()
    {
        $data = Cache::remember(
            SideCacheKey::path(['data-generator', 'investasi', 'distinct-kode-sumber']),
            now()->addDay(),
            fn () => $this->investasiService->getKodeSumber()
        );

        return response()->json([
            'success' => true,
            'message' => 'Data kode sumber investasi',
            'data' => $data,
        ]);
    }

    public function tahunInvestasi()
    {
        $data = Cache::remember(
            SideCacheKey::path(['data-generator', 'investasi', 'distinct-tahun']),
            now()->addDay(),
            fn () => $this->investasiService->getTahun()
        );

        return response()->json([
            'success' => true,
            'message' => 'Data tahun investasi',
            'data' => $data,
        ]);
    }

    public function tahunInvestasiDefault()
    {
        $data = Cache::remember(
            SideCacheKey::path(['data-generator', 'investasi', 'distinct-default-tahun']),
            now()->addDay(),
            fn () => $this->investasiService->getTahunDefault()
        );

        return response()->json([
            'success' => true,
            'message' => 'Data tahun investasi',
            'data' => $data,
        ]);
    }

    /* =========================================================
     * Helper meta: ubah kode → nama
     * ======================================================= */

    protected function buildMetaWithNames(array $filters, array $metaFromRepo = []): array
    {
        $sourceCode = $metaFromRepo['sourceCode'] ?? ($filters['sourceCode'] ?? null);

        $sourceName = DB::connection('server_mysql')
            ->table('tbsumber')
            ->where('KodeSumber', $sourceCode)
            ->value('NamaSumber');

        $investmentType = $metaFromRepo['investmentType'] ?? ($filters['investmentType'] ?? null);
        $unit = $metaFromRepo['unit'] ?? null;

        // --- Negara (dari kode Alpha-3) ---
        $originCodes = $metaFromRepo['originCodes'] ?? ($filters['origins'] ?? []);
        $destinationCodes = $metaFromRepo['destinationCodes'] ?? ($filters['destinations'] ?? []);

        $originNames = [];
        if (! empty($originCodes)) {
            $originNames = DB::connection('server_mysql')
                ->table('tbnegara')
                ->whereIn('Kode_Alpha3', $originCodes)
                ->orderBy('Negara_IDN')
                ->pluck('Negara_IDN')
                ->toArray();
        }

        $destinationNames = [];
        if (! empty($destinationCodes)) {
            $destinationNames = DB::connection('server_mysql')
                ->table('tbnegara')
                ->whereIn('Kode_Alpha3', $destinationCodes)
                ->orderBy('Negara_IDN')
                ->pluck('Negara_IDN')
                ->toArray();
        }

        // --- Group (organisasi / benua) ---
        $originGroupIds = $metaFromRepo['originGroupIds'] ?? ($filters['originGroups'] ?? []);
        $destinationGroupIds = $metaFromRepo['destinationGroupIds'] ?? ($filters['destinationGroups'] ?? []);

        // pisahkan numeric & non-numeric
        $originOrgIds = array_values(array_filter($originGroupIds, fn ($v) => is_numeric($v)));
        $originBenuaIds = array_values(array_filter($originGroupIds, fn ($v) => ! is_numeric($v)));

        $destOrgIds = array_values(array_filter($destinationGroupIds, fn ($v) => is_numeric($v)));
        $destBenuaIds = array_values(array_filter($destinationGroupIds, fn ($v) => ! is_numeric($v)));

        $originGroups = [];
        if (! empty($originOrgIds)) {
            $rows = DB::connection('server_mysql')
                ->table('tborgjenis')
                ->select('ID_Org', 'Abbreviation', 'Organization')
                ->whereIn('ID_Org', $originOrgIds)
                ->get();

            foreach ($rows as $r) {
                $abbr = trim((string) $r->Abbreviation);
                $originGroups[] = $abbr !== ''
                    ? "{$abbr} ({$r->Organization})"
                    : $r->Organization;
            }
        }
        if (! empty($originBenuaIds)) {
            $originGroups = array_merge(
                $originGroups,
                DB::connection('server_mysql')
                    ->table('tbbenua')
                    ->select('ID_benua', 'Benua')
                    ->whereIn('ID_benua', $originBenuaIds)
                    ->orWhereIn('Benua', $originBenuaIds)
                    ->pluck('Benua')
                    ->toArray()
            );
        }

        $destinationGroups = [];
        if (! empty($destOrgIds)) {
            $rows = DB::connection('server_mysql')
                ->table('tborgjenis')
                ->select('ID_Org', 'Abbreviation', 'Organization')
                ->whereIn('ID_Org', $destOrgIds)
                ->get();

            foreach ($rows as $r) {
                $abbr = trim((string) $r->Abbreviation);
                $destinationGroups[] = $abbr !== ''
                    ? "{$abbr} ({$r->Organization})"
                    : $r->Organization;
            }
        }
        if (! empty($destBenuaIds)) {
            $destinationGroups = array_merge(
                $destinationGroups,
                DB::connection('server_mysql')
                    ->table('tbbenua')
                    ->select('ID_benua', 'Benua')
                    ->whereIn('ID_benua', $destBenuaIds)
                    ->orWhereIn('Benua', $destBenuaIds)
                    ->pluck('Benua')
                    ->toArray()
            );
        }

        // Tahun dari repo kalau ada, fallback ke filter
        $years = $metaFromRepo['years'] ?? [
            $filters['yearFrom'] ?? null,
            $filters['yearTo'] ?? null,
        ];

        return [
            'years' => $years,
            'unit' => $unit,
            'sourceName' => $sourceName,
            'investmentType' => $investmentType,

            // PENTING: origins / destinations di meta jadi NAMA
            'origins' => $originNames,
            'destinations' => $destinationNames,

            // group juga langsung nama, cocok dengan React yang join string
            'originGroups' => $originGroups,
            'destinationGroups' => $destinationGroups,
        ];
    }

    /* =========================================================
     * TABLE FILTER
     * ======================================================= */

    public function tablefilter(InvestasiRequest $request): JsonResponse
    {
        try {
            $filters = $request->validated();

            // Sesuaikan nama method dengan service-mu
            $results = $this->investasiService->getFilteredInvestmentData($filters);

            $data = $results['data'] ?? [];
            $metaRepo = $results['meta'] ?? [];
            $message = $results['message'] ?? 'Data investasi berhasil diambil';

            $meta = $this->buildMetaWithNames($filters, $metaRepo);

            return ApiResponse::success($data, $message, $meta, 200);
        } catch (Throwable $e) {
            return ApiResponse::error('Gagal mengambil data investasi', [
                'exception' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }

    /* =========================================================
     * VISUALIZATION FILTER
     * ======================================================= */

    public function visualizationfilter(InvestasiRequest $request): JsonResponse
    {
        try {
            $filters = $request->validated();

            $results = $this->investasiService->getVisualizationInvestmentData($filters);

            $data = $results['data'] ?? [];
            $metaRepo = $results['meta'] ?? [];
            $message = $results['message'] ?? 'Data investasi berhasil diambil';

            $meta = $this->buildMetaWithNames($filters, $metaRepo);

            return ApiResponse::success($data, $message, $meta, 200);
        } catch (Throwable $e) {
            return ApiResponse::error('Gagal mengambil data investasi', [
                'exception' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }
}
