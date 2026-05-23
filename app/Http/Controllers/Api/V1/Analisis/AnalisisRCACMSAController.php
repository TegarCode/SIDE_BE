<?php

namespace App\Http\Controllers\Api\V1\Analisis;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Support\SideCacheKey;
use App\Services\Analisis\AnalisisRCACMSAService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalisisRCACMSAController extends Controller
{
    public function __construct(private AnalisisRCACMSAService $service) {}

    protected $cacheKey = 'side_cache:analisis:rca-cmsa:negara-list';

    protected $cacheKeyCommonNegara = 'side_cache:analisis:rca-cmsa:common-negara';

    protected $cacheDuration = 86400;

    private function splitMeta(array $arr): array
    {
        $meta = $arr['meta'] ?? [];
        unset($arr['meta']);

        return [$meta, $arr];
    }

    private function toA3Array(mixed $v): array
    {
        $arr = is_array($v) ? $v : (isset($v) ? [$v] : []);
        $arr = array_map(fn ($x) => strtoupper(trim((string) $x)), $arr);
        $arr = array_values(array_filter($arr, fn ($x) => (bool) preg_match('/^[A-Z]{3}$/', $x)));
        $arr = array_values(array_unique($arr));
        sort($arr);

        return $arr;
    }

    private function validateAndBuild(Request $request): array
    {
        $origin = $this->toA3Array($request->input('origin', ['IDN']));
        if (empty($origin)) {
            $origin = ['IDN'];
        }

        $dest = $this->toA3Array($request->input('dest', ['CHN']));
        if (empty($dest)) {
            $dest = ['CHN'];
        }

        return [[
            'origin' => $origin,
            'dest' => $dest,
        ]];
    }

    public function commonNegaraRCACMSA()
    {
        $data = Cache::remember($this->cacheKeyCommonNegara, $this->cacheDuration, function () {
            $db = DB::connection('server_mysql');
            $destinations = $db->table('tbhasilakhir')
                ->select('KodeNegara_2')
                ->whereNotNull('KodeNegara_2')
                ->distinct();

            return $db->table('tbnegara as n')
                ->joinSub($destinations, 't', function ($join) {
                    $join->on('n.Kode_Alpha3', '=', 't.KodeNegara_2');
                })
                ->join('tbkawasan_satker as ks', 'n.ID_Wil_Kemlu', '=', 'ks.ID_Wil_Kemlu')
                ->join('tbdirjen as d', 'ks.ID_Dirjen', '=', 'd.ID_Dirjen')
                ->select('n.Kode_Alpha3 as id', 'n.Kode_Alpha2 as kode_alpha2', 'n.Negara_IDN as nama', 'ks.ID_Wil_Kemlu as id_wilayah', 'ks.Nama_Wil_Kemlu as wilayah', 'd.ID_Dirjen as id_dirjen', 'd.Nama_Dirjen as dirjen')
                ->where('n.Kode_Alpha3', '!=', '0')
                ->orderBy('n.Negara_IDN', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'kode_alpha2' => $item->kode_alpha2,
                        'nama' => $item->nama,
                        'wilayah' => [
                            'id' => $item->id_wilayah,
                            'nama' => $item->wilayah,
                            'dirjen' => [
                                'id' => $item->id_dirjen,
                                'nama' => $item->dirjen,
                            ],
                        ],
                    ];
                });
        });

        return response()->json([
            'success' => true,
            'message' => 'Data negara v1',
            'data' => $data,
        ]);
    }

    public function rcaCmsaData(Request $request)
    {
        try {
            [$filters] = $this->validateAndBuild($request);

            $isOriginIdn = (count($filters['origin']) === 1 && $filters['origin'][0] === 'IDN');
            $isDestChn = (count($filters['dest']) === 1 && $filters['dest'][0] === 'CHN');
            $shouldCache = $isOriginIdn && $isDestChn;

            $ttl = now()->addDay();

            if ($shouldCache) {
                $cacheKey = $this->makeRCACacheKey($filters);
                $data = Cache::remember($cacheKey, $ttl, function () use ($filters) {
                    return $this->service->getDataAnalisis($filters);
                });
            } else {
                $data = $this->service->getDataAnalisis($filters);
            }

            [$meta, $payload] = $this->splitMeta(is_array($data) ? $data : []);

            return ApiResponse::success($payload, 'Data Analisis RCA CMSA', $meta);
        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::error('Gagal memuat data analisis rca cmsa');
        }
    }

    private function makeRCACacheKey(array $filters): string
    {
        $keyPayload = [
            'o' => implode(',', $filters['origin'] ?? []),
            'd' => implode(',', $filters['dest'] ?? []),
        ];

        return SideCacheKey::pairs(
            ['analisis', 'rca-cmsa', 'data'],
            [
                'origin' => $filters['origin'] ?? ['IDN'],
                'dest' => $filters['dest'] ?? ['CHN'],
            ]
        );
    }

    public function rcaCmsaCalculationData(Request $request)
    {
        try {
            [$filters] = $this->validateAndBuild($request);

            $isOriginIdn = (count($filters['origin']) === 1 && $filters['origin'][0] === 'IDN');
            $isDestChn = (count($filters['dest']) === 1 && $filters['dest'][0] === 'CHN');
            $shouldCache = $isOriginIdn && $isDestChn;

            $ttl = now()->addDay();

            if ($shouldCache) {
                $cacheKey = $this->makeRCACalculationCacheKey($filters);
                $data = Cache::remember($cacheKey, $ttl, function () use ($filters) {
                    return $this->service->getCalculationAnalisis($filters);
                });
            } else {
                $data = $this->service->getCalculationAnalisis($filters);
            }

            [$meta, $payload] = $this->splitMeta(is_array($data) ? $data : []);

            return ApiResponse::success($payload, 'Data Kalkulasi Analisis RCA CMSA', $meta);
        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::error('Gagal memuat data kalkulasi analisis rca cmsa');
        }
    }

    private function makeRCACalculationCacheKey(array $filters): string
    {
        $keyPayload = [
            'o' => implode(',', $filters['origin'] ?? []),
            'd' => implode(',', $filters['dest'] ?? []),
        ];

        return SideCacheKey::pairs(
            ['analisis', 'rca-cmsa', 'kalkulasi'],
            [
                'origin' => $filters['origin'] ?? ['IDN'],
                'dest' => $filters['dest'] ?? ['CHN'],
            ]
        );
    }
}
