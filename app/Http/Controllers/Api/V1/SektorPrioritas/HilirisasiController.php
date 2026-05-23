<?php

namespace App\Http\Controllers\Api\V1\SektorPrioritas;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Support\SideCacheKey;
use App\Services\SektorPrioritas\HilirisasiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HilirisasiController extends Controller
{
    public function __construct(protected HilirisasiService $hilirisasiService) {}

    protected string $conn = 'server_mysql';

    protected string $TB_SEKTOR = 'tbsektor_hilirisasi';

    protected string $TB_SEKTOR_HSCODE = 'tbsektor_hscode';

    protected string $cacheKeyHsCode = 'side_cache:sektor-prioritas:hilirisasi:distinct-hscode';

    protected int $cacheDuration = 259200;

    /** ======================== PUBLIC ENDPOINTS ======================== */
    public function hscode(Request $request): JsonResponse
    {
        $rawHs = $request->input(
            'hscode',
            $request->input(
                'hs_list',
                $request->input(
                    'hs_codes',
                    $request->input('hsCodes', [])
                )
            )
        );
        $hasAll = false;
        if (is_string($rawHs)) {
            $tokens = array_map('trim', explode(',', $rawHs));
            foreach ($tokens as $t) {
                if (strtolower($t) === 'all') {
                    $hasAll = true;
                    break;
                }
            }
        } elseif (is_array($rawHs)) {
            foreach ($rawHs as $v) {
                if (is_string($v) && strtolower(trim($v)) === 'all') {
                    $hasAll = true;
                    break;
                }
            }
        }
        $filterList = $this->csvToDigitsArray($rawHs);
        $useProvided = $hasAll || ! empty($filterList);

        $cacheKey = SideCacheKey::pairs(
            ['sektor-prioritas', 'hilirisasi', 'distinct-hscode'],
            ['hs' => $useProvided ? ($filterList ?: 'all') : 'all']
        );

        $rows = Cache::remember($cacheKey, $this->cacheDuration, function () use ($filterList, $useProvided) {
            if ($useProvided && empty($filterList)) {
                return collect();
            }

            // Ambil semua sektor + HS code terkait
            $rows = DB::connection($this->conn)
                ->table("{$this->TB_SEKTOR} as s")
                ->leftJoin('tbsektor as ts', 'ts.ID_Sektor', '=', 's.ID_Sektor')
                ->leftJoin("{$this->TB_SEKTOR_HSCODE} as h", 'h.ID_Sektor', '=', 's.ID_Sektor')
                ->selectRaw('
                    s.ID_Sektor AS id,
                    s.Sektor AS sektor,
                    h.Kategori AS kategori,
                    ts.Desc_Sumber AS desc_sumber,
                    GROUP_CONCAT(DISTINCT h.hscode ORDER BY h.hscode SEPARATOR ",") AS hscodes_csv
                ')
                ->when(
                    $useProvided && ! empty($filterList),
                    fn ($q) => $q->whereIn('h.hscode', $filterList)
                )
                ->groupBy('s.ID_Sektor', 's.Sektor', 'h.Kategori', 'ts.Desc_Sumber')
                ->orderBy('s.Sektor')
                ->orderBy('h.Kategori')
                ->get();

            // Kumpulkan semua HS code unik dari semua sektor
            $allCodes = [];
            foreach ($rows as $r) {
                if ($r->hscodes_csv === null) {
                    continue;
                }
                $codes = array_map('trim', explode(',', (string) $r->hscodes_csv));
                foreach ($codes as $c) {
                    if ($c !== '') {
                        $allCodes[$c] = true;
                    }
                }
            }
            $allCodes = array_keys($allCodes);

            // Ambil deskripsi HS dari tbharmonized
            $descMap = [];
            if (! empty($allCodes)) {
                $descMap = DB::connection($this->conn)
                    ->table('tbharmonized')
                    ->whereIn('hscode', $allCodes)
                    ->pluck('description', 'hscode')
                    ->toArray();
            }

            // Bentuk struktur rapi per sektor
            $items = $rows->map(function ($row) use ($descMap) {
                // Bisa saja sektor belum punya HS - handle null
                $codes = $row->hscodes_csv
                    ? array_values(array_filter(array_unique(array_map(
                        fn ($x) => trim((string) $x),
                        explode(',', (string) $row->hscodes_csv)
                    ))))
                    : [];

                $pairs = [];
                foreach ($codes as $code) {
                    $pairs[] = [
                        'code'        => $code,
                        'description' => isset($descMap[$code])
                            ? trim((string) $descMap[$code])
                            : '',
                    ];
                }

                usort($pairs, fn ($a, $b) => strnatcmp($a['code'], $b['code']));

                return [
                    'sektor' => $row->sektor,
                    'kategori' => $row->kategori,
                    'desc_sumber' => $row->desc_sumber,
                    'hscodes' => $pairs,
                    'hscodes_count' => count($pairs),
                ];
            });

            return $items
                ->groupBy('id')
                ->map(function ($group) {
                    $first = $group->first();
                    $seen = [];
                    $sorted = $group->sortBy('kategori', SORT_NATURAL | SORT_FLAG_CASE)->values();

                    return [
                        'sektor' => $first['sektor'],
                        'desc_sumber' => $first['desc_sumber'],
                        'kategori_groups' => $sorted
                            ->map(function ($g) use (&$seen) {
                                $filtered = [];
                                foreach ($g['hscodes'] as $pair) {
                                    $code = $pair['code'];
                                    if (isset($seen[$code])) {
                                        continue;
                                    }
                                    $seen[$code] = true;
                                    $filtered[] = $pair;
                                }

                                return [
                                    'kategori' => $g['kategori'],
                                    'hscodes' => $filtered,
                                    'hscodes_count' => count($filtered),
                                ];
                            })
                            ->all(),
                    ];
                })
                ->values();
        });

        return response()->json([
            'success' => true,
            'message' => 'Daftar HS Code sektor hilirisasi',
            'data'    => $rows,
        ]);
    }

    /** Ringkasan hilirisasi per-negara (tetap) */
    public function nilaiHilirisasi(Request $request): JsonResponse
    {
        $filters = $this->normalizeFilters($request);
        unset($filters['partner']);

        $cacheKey = $this->buildCacheKey('nilai-hilirisasi', $filters);
        $ttl      = now()->addDays(3);

        $data = Cache::remember(
            $cacheKey,
            $ttl,
            fn () => $this->hilirisasiService->getNilaiPerdaganganHilirisasi($filters)
        );

        if (empty($data)) {
            return ApiResponse::success([], 'Tidak ada data.', ['filters' => $filters]);
        }

        [$payload, $meta] = $this->splitPayloadAndMeta($data);
        $meta             = array_merge($meta, ['filters' => $filters]);

        return ApiResponse::success($payload, 'Nilai hilirisasi berhasil diambil', $meta);
    }

    public function nilaiHilirisasiProduk(Request $request): JsonResponse
    {
        $filters = $this->normalizeFilters($request);

        // Ambil origin/dest sebagai array (menerima array atau CSV)
        $reporters = $this->csvToUpperArray($request->input('origin', []));
        $partners  = $this->csvToUpperArray($request->input('dest', []));

        if (! empty($reporters)) {
            $filters['reporter'] = $reporters;
        }
        if (! empty($partners)) {
            $filters['partner'] = $partners;
        }

        $cacheKey = $this->buildCacheKeyByOriginsDests('sektor-hilirisasi-produk', $reporters, $partners, $filters);
        $ttl      = now()->addDays(3);
        $data     = Cache::remember(
            $cacheKey,
            $ttl,
            fn () => $this->hilirisasiService->getNilaiPerdaganganHilirisasiProduk($filters)
        );

        if (empty($data)) {
            return ApiResponse::success([], 'Tidak ada data.');
        }

        [$payload, $meta] = $this->splitPayloadAndMeta($data);

        return ApiResponse::success($payload, 'Produk perdagangan sektor hilirisasi berhasil diambil', $meta);
    }

    /** ======================== HELPERS (DRY) ======================== */
    private function normalizeFilters(Request $request): array
    {
        // tahun
        $ys        = $request->input('year_start');
        $ye        = $request->input('year_end');
        $yearStart = is_numeric($ys) ? (int) $ys : null;
        $yearEnd   = is_numeric($ye) ? (int) $ye : null;

        // hs-length (opsional, repo tetap support)
        $hsIn = $request->input('hs');
        $hs   = is_numeric($hsIn) ? (int) $hsIn : null;

        // dirjen/ wilayah
        $dirjen = $this->csvToUpperArray($request->input('dirjen', []));
        sort($dirjen, SORT_STRING);

        // partner/reporters juga boleh dikirim di sini (opsional)
        $partner  = $this->csvToUpperArray($request->input('partner', []));
        $reporter = $this->csvToUpperArray($request->input('reporter', []));

        // HS LIST (prioritas: hscode -> hs_list -> hs_codes -> hsCodes)
        $rawHs = $request->input(
            'hscode',
            $request->input(
                'hs_list',
                $request->input('hs_codes', $request->input('hsCodes', []))
            )
        );
        if (is_string($rawHs) && strtolower(trim($rawHs)) === 'all') {
            $rawHs = [];
        } elseif (is_array($rawHs)) {
            $hasAll = false;
            foreach ($rawHs as $v) {
                if (is_string($v) && strtolower(trim($v)) === 'all') {
                    $hasAll = true;
                    break;
                }
            }
            if ($hasAll) {
                $rawHs = [];
            }
        }
        $hsList = $this->csvToDigitsArray($rawHs);

        $filters = [
            'year_start' => $yearStart,
            'year_end'   => $yearEnd,
            'hs'         => $hs,
            'dirjen'     => $dirjen,
        ];
        if (! empty($partner)) {
            $filters['partner'] = $partner;
        }
        if (! empty($reporter)) {
            $filters['reporter'] = $reporter;
        }
        if (! empty($hsList)) {
            $filters['hs_list'] = $hsList;
        }

        // buang null/kosong
        return array_filter(
            $filters,
            fn ($v) => is_array($v) ? count($v) > 0 : ! is_null($v) && $v !== ''
        );
    }

    /** Pisahkan payload & meta dari struktur repo; bersihkan 'applied_filters'. */
    protected function splitPayloadAndMeta(?array $data): array
    {
        $data    = is_array($data) ? $data : [];
        $meta    = Arr::get($data, 'meta', []);
        $payload = $data;
        unset($payload['meta']);
        if (isset($meta['applied_filters'])) {
            unset($meta['applied_filters']);
        }

        return [$payload, $meta];
    }

    /** Terima CSV ("IDN,SGP") atau array (["IDN","SGP"]) lalu uppercase & unique */
    private function csvToUpperArray($val): array
    {
        $arr = is_string($val) ? array_map('trim', explode(',', $val)) : (is_array($val) ? $val : []);
        $out = array_values(array_unique(array_filter(array_map(
            fn ($v) => strtoupper((string) $v),
            $arr
        ))));
        sort($out, SORT_STRING);

        return $out;
    }

    private function csvToDigitsArray($val): array
    {
        $arr = is_string($val) ? array_map('trim', explode(',', $val)) : (is_array($val) ? $val : []);
        $out = [];
        foreach ($arr as $v) {
            $digits = preg_replace('/\D+/', '', (string) $v);
            if ($digits !== '') {
                $out[] = $digits;
            }
        }
        $out = array_values(array_unique($out));
        sort($out, SORT_STRING);
        return $out;
    }

    private function buildCacheKey(string $prefix, array $filters): string
    {
        ksort($filters);

        return SideCacheKey::pairs(['sektor-prioritas', 'hilirisasi', $prefix], $filters);
    }

    private function buildCacheKeyByOriginsDests(string $prefix, array $origins, array $dests, array $filters): string
    {
        $o = $origins;
        sort($o, SORT_STRING);
        $d = $dests;
        sort($d, SORT_STRING);

        // Hilangkan field yang sudah diekspresikan di segmen
        $rest = $filters;
        unset($rest['reporter'], $rest['partner']);

        return SideCacheKey::pairs(
            ['sektor-prioritas', 'hilirisasi', $prefix],
            array_merge(
                [
                    'origin' => $o ?: 'all',
                    'dest' => $d ?: 'all',
                ],
                $rest
            )
        );
    }
}
