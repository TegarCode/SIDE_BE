<?php

namespace App\Http\Controllers\Api\V1\DataGenerator\Pariwisata;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\DataGenerator\PariwisataRequest;
use App\Services\DataGenerator\PariwisataService;
use App\Support\SideCacheKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class PariwisataController extends Controller
{
    protected PariwisataService $pariwisataService;

    public function __construct(PariwisataService $pariwisataService)
    {
        $this->pariwisataService = $pariwisataService;
    }

    public function kodesumber()
    {
        $data = Cache::remember(
            SideCacheKey::path(['data-generator', 'pariwisata', 'distinct-kode-sumber']),
            now()->addDay(),
            fn () => $this->pariwisataService->getKodeSumber()
        );

        return response()->json([
            'success' => true,
            'message' => 'Data kode sumber pariwisata',
            'data' => $data,
        ]);
    }

    public function tahunPariwisata()
    {
        $data = Cache::remember(
            SideCacheKey::path(['data-generator', 'pariwisata', 'distinct-tahun']),
            now()->addDay(),
            fn () => $this->pariwisataService->getTahun()
        );

        return response()->json([
            'success' => true,
            'message' => 'Data tahun pariwisata',
            'data' => $data,
        ]);
    }

    public function tahunPariwisataDefault()
    {
        $data = Cache::remember(
            SideCacheKey::path(['data-generator', 'pariwisata', 'distinct-default-tahun']),
            now()->addDay(),
            fn () => $this->pariwisataService->getTahunDefault()
        );

        return response()->json([
            'success' => true,
            'message' => 'Data tahun pariwisata',
            'data' => $data,
        ]);
    }

    /* =========================================================
     * Helper untuk build meta nama
     * ======================================================= */

    protected function buildMetaWithNames(array $filters, array $metaFromRepo = []): array
    {
        $sourceName = DB::connection('server_mysql')
            ->table('tbsumber')
            ->where('KodeSumber', $filters['sourceCode'] ?? null)
            ->value('NamaSumber');

        // --- Negara asal (dari Kode_Alpha3 origins) ---
        $originAlpha3 = $filters['origins'] ?? [];
        $originCountries = [];
        if (! empty($originAlpha3)) {
            $originCountries = DB::connection('server_mysql')
                ->table('tbnegara')
                ->whereIn('Kode_Alpha3', $originAlpha3)
                ->select(
                    'Kode_Alpha3 as code',
                    'Negara_IDN as name'
                )
                ->orderBy('Negara_IDN')
                ->get()
                ->toArray();
        }

        // --- Negara tujuan (dari Kode_Alpha3 destinations) ---
        $destinationAlpha3 = $filters['destinations'] ?? [];
        $destinationCountries = [];
        if (! empty($destinationAlpha3)) {
            $destinationCountries = DB::connection('server_mysql')
                ->table('tbnegara')
                ->whereIn('Kode_Alpha3', $destinationAlpha3)
                ->select(
                    'Kode_Alpha3 as code',
                    'Negara_IDN as name'
                )
                ->orderBy('Negara_IDN')
                ->get()
                ->toArray();
        }

        // --- Group asal (originGroups: numeric = organisasi, non-numeric = benua) ---
        $originGroupIds = $filters['originGroups'] ?? [];
        $originOrgIds = array_values(array_filter($originGroupIds, fn ($v) => is_numeric($v)));
        $originBenuaIds = array_values(array_filter($originGroupIds, fn ($v) => ! is_numeric($v)));

        $originOrgGroups = [];
        if (! empty($originOrgIds)) {
            $rows = DB::connection('server_mysql')
                ->table('tborgjenis')
                ->select('ID_Org', 'Abbreviation', 'Organization')
                ->whereIn('ID_Org', $originOrgIds)
                ->get();

            foreach ($rows as $r) {
                $abbr = trim((string) $r->Abbreviation);
                $originOrgGroups[] = [
                    'id' => $r->ID_Org,
                    'name' => $abbr !== ''
                        ? "{$abbr} ({$r->Organization})"
                        : $r->Organization,
                ];
            }
        }

        $originBenuaGroups = [];
        if (! empty($originBenuaIds)) {
            $originBenuaGroups = DB::connection('server_mysql')
                ->table('tbbenua')
                ->select('ID_benua as id', 'Benua as name')
                ->whereIn('ID_benua', $originBenuaIds)
                ->orWhereIn('Benua', $originBenuaIds)
                ->orderBy('Benua')
                ->get()
                ->toArray();
        }

        // --- Group tujuan (destinationGroups: numeric = organisasi, non-numeric = benua) ---
        $destinationGroupIds = $filters['destinationGroups'] ?? [];
        $destinationOrgIds = array_values(array_filter($destinationGroupIds, fn ($v) => is_numeric($v)));
        $destinationBenuaIds = array_values(array_filter($destinationGroupIds, fn ($v) => ! is_numeric($v)));

        $destinationOrgGroups = [];
        if (! empty($destinationOrgIds)) {
            $rows = DB::connection('server_mysql')
                ->table('tborgjenis')
                ->select('ID_Org', 'Abbreviation', 'Organization')
                ->whereIn('ID_Org', $destinationOrgIds)
                ->get();

            foreach ($rows as $r) {
                $abbr = trim((string) $r->Abbreviation);
                $destinationOrgGroups[] = [
                    'id' => $r->ID_Org,
                    'name' => $abbr !== ''
                        ? "{$abbr} ({$r->Organization})"
                        : $r->Organization,
                ];
            }
        }

        $destinationBenuaGroups = [];
        if (! empty($destinationBenuaIds)) {
            $destinationBenuaGroups = DB::connection('server_mysql')
                ->table('tbbenua')
                ->select('ID_benua as id', 'Benua as name')
                ->whereIn('ID_benua', $destinationBenuaIds)
                ->orWhereIn('Benua', $destinationBenuaIds)
                ->orderBy('Benua')
                ->get()
                ->toArray();
        }

        // Tahun: pakai dari repo kalau ada, fallback ke filter
        $years = $metaFromRepo['years'] ?? [
            $filters['yearFrom'] ?? null,
            $filters['yearTo'] ?? null,
        ];

        return array_merge($metaFromRepo, [
            'years' => $years,
            'sourceCode' => $filters['sourceCode'] ?? null,
            'sourceName' => $sourceName,
            'typeData' => $filters['typeData'] ?? null,

            // Negara (kode + nama, bukan kode saja)
            'originCountries' => $originCountries,
            'destinationCountries' => $destinationCountries,

            // Group asal/tujuan sudah berupa nama, bukan cuma ID
            'originGroups' => [
                'organisasi' => $originOrgGroups,
                'benua' => $originBenuaGroups,
            ],
            'destinationGroups' => [
                'organisasi' => $destinationOrgGroups,
                'benua' => $destinationBenuaGroups,
            ],
        ]);
    }

    /* =========================================================
     * TABLE FILTER
     * ======================================================= */

    public function tablefilter(PariwisataRequest $request): JsonResponse
    {
        try {
            $filters = $request->validated();

            // Service sekarang diharapkan balikin struktur:
            // ['success' => bool, 'message' => ?, 'data' => [...], 'meta' => [...]]
            $results = $this->pariwisataService->getFilteredTourismData($filters);

            $data = $results['data'] ?? [];
            $metaRepo = $results['meta'] ?? [];
            $message = $results['message'] ?? 'Data pariwisata berhasil diambil';

            // Build meta dengan nama (negara / organisasi / benua)
            $meta = $this->buildMetaWithNames($filters, $metaRepo);

            return ApiResponse::success($data, $message, $meta, 200);
        } catch (Throwable $e) {
            return ApiResponse::error('Gagal mengambil data pariwisata', [
                'exception' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }

    /* =========================================================
     * VISUALIZATION FILTER
     * ======================================================= */

    public function visualizationfilter(PariwisataRequest $request): JsonResponse
    {
        try {
            $filters = $request->validated();

            $results = $this->pariwisataService->getVisualizationTourismData($filters);

            $data = $results['data'] ?? [];
            $metaRepo = $results['meta'] ?? [];
            $message = $results['message'] ?? 'Data pariwisata berhasil diambil';

            $meta = $this->buildMetaWithNames($filters, $metaRepo);

            return ApiResponse::success($data, $message, $meta, 200);
        } catch (Throwable $e) {
            return ApiResponse::error('Gagal mengambil data pariwisata', [
                'exception' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }
}
