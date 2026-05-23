<?php

namespace App\Http\Controllers\Api\V1\SektorPrioritas;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Support\SideCacheKey;
use App\Services\SektorPrioritas\EconomyDigitalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB; // ⬅️ tambahkan ini

class EkonomiDigitalController extends Controller
{
    public function __construct(protected EconomyDigitalService $economyDigitalService) {}

    protected string $conn = 'server_mysql';

    protected string $TB_SEKTOR = 'tbsektor_hscode';

    protected string $cacheKeyHsCode = 'side_cache:sektor-prioritas:tik:distinct-hscode';

    protected int $cacheDuration = 259200;

    protected int $ID_Sektor = 16;

    /** ======================== PUBLIC ENDPOINTS ======================== */
    public function hscode()
    {
        return Cache::remember($this->cacheKeyHsCode, $this->cacheDuration, function () {
            $rows = DB::connection($this->conn)
                ->table("{$this->TB_SEKTOR} as s")
                ->selectRaw('
                s.ID_Sektor AS id,
                s.Sektor AS sektor,
                GROUP_CONCAT(DISTINCT s.hscode ORDER BY s.hscode SEPARATOR ",") AS hscodes_csv
            ')
                ->where('s.ID_Sektor', $this->ID_Sektor) // contoh: 16
                ->groupBy('s.ID_Sektor', 's.Sektor')
                ->orderBy('s.Sektor')
                ->get();

            $allCodes = [];
            foreach ($rows as $r) {
                $codes = array_map('trim', explode(',', (string) $r->hscodes_csv));
                foreach ($codes as $c) {
                    if ($c !== '') {
                        $allCodes[$c] = true;
                    }
                }
            }
            $allCodes = array_keys($allCodes);

            $descMap = [];
            if (! empty($allCodes)) {
                $descMap = DB::connection($this->conn)
                    ->table('tbharmonized')
                    ->whereIn('hscode', $allCodes)
                    ->pluck('description', 'hscode')
                    ->toArray();
            }

            return $rows->map(function ($row) use ($descMap) {
                $codes = array_values(array_filter(array_unique(array_map(
                    fn ($x) => trim((string) $x),
                    explode(',', (string) $row->hscodes_csv)
                ))));

                $pairs = [];
                foreach ($codes as $code) {
                    $pairs[] = [
                        'code' => $code,
                        'description' => isset($descMap[$code]) ? trim((string) $descMap[$code]) : '',
                    ];
                }

                usort($pairs, function ($a, $b) {
                    return strnatcmp($a['code'], $b['code']);
                });

                unset($row->hscodes_csv);
                $row->hscodes = $pairs;
                $row->hscodes_count = count($pairs);

                return $row;
            });
        });
    }

    public function nilaiArusTIK(Request $request): JsonResponse
    {
        $filters = $this->normalizeFilters($request);

        if (! array_key_exists('hs', $filters) || is_null($filters['hs'])) {
            $filters['hs'] = 4;
        }

        if (! array_key_exists('hscodes', $filters) || empty($filters['hscodes'])) {
            $dbHs = $this->getHsCodesFromDb(16);
            if (! empty($dbHs)) {
                $filters['hscodes'] = $dbHs;
            }
        }

        $cacheKey = $this->buildCacheKey('nilai-arus-tik', $filters);
        $ttl = now()->addDays(3);

        $data = Cache::remember($cacheKey, $ttl, fn () => $this->economyDigitalService->getNilaiPerdagangan($filters));

        if (empty($data)) {
            return ApiResponse::success([], 'Tidak ada data.', ['filters' => $filters]);
        }

        [$payload, $meta] = $this->splitPayloadAndMeta($data);
        $meta = array_merge($meta, ['filters' => $filters]);

        return ApiResponse::success($payload, 'Nilai arus tik berhasil diambil', $meta);
    }

    public function nilaiEcommerce(Request $request): JsonResponse
    {
        $filters = $this->normalizeFilters(request: $request);

        $cacheKey = $this->buildCacheKey('nilai-ecommerce', $filters);
        $ttl = now()->addDays(3);

        $data = Cache::remember($cacheKey, $ttl, fn () => $this->economyDigitalService->getNilaiEcommerce($filters));

        if (empty($data) || empty($data['data'])) {
            return ApiResponse::success([], 'Tidak ada data.', ['filters' => $filters]);
        }

        [$payload, $meta] = $this->splitPayloadAndMeta($data);
        $meta = array_merge($meta, ['filters' => $filters]);

        return ApiResponse::success($payload, 'Nilai ecommerce berhasil diambil', $meta);
    }

    public function nilaiInfrastruktur(Request $request): JsonResponse
    {
        $filters = $this->normalizeFilters(request: $request);

        $cacheKey = $this->buildCacheKey('nilai-infrastruktur', $filters);
        $ttl = now()->addDays(3);

        $data = Cache::remember($cacheKey, $ttl, fn () => $this->economyDigitalService->getNilaiInfrastruktur($filters));

        if (empty($data) || empty($data['data'])) {
            return ApiResponse::success([], 'Tidak ada data.', ['filters' => $filters]);
        }

        [$payload, $meta] = $this->splitPayloadAndMeta($data);
        $meta = array_merge($meta, ['filters' => $filters]);

        return ApiResponse::success($payload, 'Nilai Infrastruktur berhasil diambil', $meta);
    }

    /** ======================== HELPERS (DRY) ======================== */
    private function normalizeFilters(Request $request): array
    {
        // tahun
        $ys = $request->input('year_start');
        $ye = $request->input('year_end');
        $yearStart = is_numeric($ys) ? (int) $ys : null;
        $yearEnd = is_numeric($ye) ? (int) $ye : null;

        $hsIn = $request->input('hs');
        $hs = is_numeric($hsIn) ? (int) $hsIn : null;

        $dirjen = $this->csvToUpperArray($request->input('dirjen', []));
        sort($dirjen, SORT_STRING);

        $partners = $this->csvToUpperArray($request->input('partners', []));

        $hscodes = $this->csvToHs4Array($request->input('hscodes', []));

        $filters = [
            'year_start' => $yearStart,
            'year_end' => $yearEnd,
            'hs' => $hs,
            'dirjen' => $dirjen,
            'partners' => $partners,
            'hscodes' => $hscodes,
        ];

        return array_filter($filters, fn ($v) => is_array($v) ? count($v) > 0 : ! is_null($v) && $v !== '');
    }

    /** Pisahkan payload & meta dari struktur repo; bersihkan 'applied_filters'. */
    protected function splitPayloadAndMeta(?array $data): array
    {
        $data = is_array($data) ? $data : [];
        $meta = Arr::get($data, 'meta', []);
        $payload = $data;
        unset($payload['meta']);
        if (isset($meta['applied_filters'])) {
            unset($meta['applied_filters']);
        }

        return [$payload, $meta];
    }

    private function csvToUpperArray($val): array
    {
        $arr = is_string($val) ? array_map('trim', explode(',', $val)) : (is_array($val) ? $val : []);

        return array_values(array_unique(array_filter(array_map(fn ($v) => strtoupper((string) $v), $arr))));
    }

    private function csvToHs4Array($val): array
    {
        $arr = is_string($val) ? array_map('trim', explode(',', $val)) : (is_array($val) ? $val : []);
        $clean = [];
        foreach ($arr as $v) {
            $digits = preg_replace('/\D+/', '', (string) $v);
            if ($digits !== '' && strlen($digits) === 4) {
                $clean[] = $digits;
            }
        }

        return array_values(array_unique($clean));
    }

    /** Ambil HS codes dari tbsektor_hscode untuk ID_Sektor tertentu (HS4, unique, sorted). */
    private function getHsCodesFromDb(int $sektorId): array
    {
        $rows = DB::connection('server_mysql')->table('tbsektor_hscode')->where('ID_Sektor', $sektorId)->pluck('hscode')->all();

        if (empty($rows)) {
            return [];
        }

        $clean = [];
        foreach ($rows as $v) {
            $digits = preg_replace('/\D+/', '', (string) $v);
            if ($digits !== '' && strlen($digits) >= 4) {
                $clean[] = substr($digits, 0, 4); // ambil HS4
            }
        }

        $clean = array_values(array_unique(array_filter($clean)));
        sort($clean, SORT_STRING);

        return $clean;
    }

    private function buildCacheKey(string $prefix, array $filters): string
    {
        ksort($filters);

        ksort($filters);
        return SideCacheKey::pairs(['sektor-prioritas', 'ekonomi-digital-copy', $prefix], $filters);
    }
}
