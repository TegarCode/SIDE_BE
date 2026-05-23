<?php

namespace App\Http\Controllers\Api\V1\SektorPrioritas;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Support\SideCacheKey;
use App\Services\SektorPrioritas\PanganService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SektorPanganController extends Controller
{
    /** Memoization per-request agar tidak hit service berkali-kali */
    public function __construct(protected PanganService $PanganService) {}

    /** Memo per-request agar tidak hit sumber berkali-kali */
    private ?array $allHsMemo = null;

    protected string $conn = 'server_mysql';

    protected string $TB_SEKTOR = 'tbsektor_hscode';

    protected string $cacheKeyHsCode = 'side_cache:sektor-prioritas:pangan:distinct-hscode';

    protected int $cacheDuration = 259200;

    protected int $ID_Sektor = 18;

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
            ['sektor-prioritas', 'pangan', 'distinct-hscode'],
            ['hs' => $useProvided ? ($filterList ?: 'all') : 'all']
        );

        $rows = Cache::remember($cacheKey, $this->cacheDuration, function () use ($filterList, $useProvided) {
            if ($useProvided && empty($filterList)) {
                return collect();
            }

            $rows = DB::connection($this->conn)
                ->table("{$this->TB_SEKTOR} as s")
                ->leftJoin('tbsektor as ts', 'ts.ID_Sektor', '=', 's.ID_Sektor')
                ->selectRaw('
                  s.ID_Sektor AS id,
                  s.Sektor AS sektor,
                  s.Kategori AS kategori,
                  ts.Desc_Sumber AS desc_sumber,
                  GROUP_CONCAT(DISTINCT s.hscode ORDER BY s.hscode SEPARATOR ",") AS hscodes_csv
                ')
                ->where('s.ID_Sektor', $this->ID_Sektor)
                ->when(
                    $useProvided && ! empty($filterList),
                    fn ($q) => $q->whereIn('s.hscode', $filterList)
                )
                ->groupBy('s.ID_Sektor', 's.Sektor', 's.Kategori', 'ts.Desc_Sumber')
                ->orderBy('s.Sektor')
                ->orderBy('s.Kategori')
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

            $items = $rows->map(function ($row) use ($descMap) {
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
            'message' => 'Daftar HS Code sektor pangan',
            'data' => $rows,
        ]);
    }
    /* ================= NILAI PANGAN (NEGARA / AGREGAT) ================= */

    public function nilaiPangan(Request $request): JsonResponse
    {
        $idKom = (int) $request->input('id_komoditas', 0);
        $filters = $this->normalizeFilters($request);
        unset($filters['partner']);

        // === ALL HS LIST (patokan) ===
        $allHs = $this->allHsList();
        $reqHs = $filters['hs_list'] ?? [];

        // Kalau hs_list kosong => anggap ALL HS
        if (empty($reqHs)) {
            $reqHs = $allHs;
            $filters['hs_list'] = $reqHs;
        }

        // Anggap “ALL_HSCODE” bila set hs_list sama dengan set allHs
        $isAllHs = $this->sameSet($reqHs, $allHs);

        if ($isAllHs) {
            $filtersAll = $filters;
            // Pastikan hs_list yang disimpan di cache = full set (unik & terurut)
            $filtersAll['hs_list'] = $allHs;

            $cacheKey = $this->buildCacheKey('nilai-pangan:ALL_HSCODE', $filtersAll);
            $ttl = now()->addDays(3);

            $data = Cache::remember(
                $cacheKey,
                $ttl,
                fn () => $this->PanganService->getNilaiPerdaganganPangan($filtersAll)
            );

            if (empty($data)) {
                return ApiResponse::success([], 'Tidak ada data.', [
                    'filters' => $filtersAll,
                    'cached_variant' => 'ALL_HSCODE',
                ]);
            }

            if ($idKom > 0) {
                $data = $this->sliceByKomoditas($data, $idKom);
            }

            [$payload, $meta] = $this->splitPayloadAndMeta($data);
            $meta = array_merge($meta, [
                'filters' => array_merge(
                    $filtersAll,
                    $idKom > 0 ? ['id_komoditas' => $idKom] : []
                ),
                'cached_variant' => 'ALL_HSCODE',
            ]);

            return ApiResponse::success($payload, 'Nilai perdagangan pangan berhasil diambil', $meta);
        }

        $cacheKey = $this->buildCacheKey('nilai-pangan', $filters);
        $ttl = now()->addDays(3);

        $data = Cache::remember(
            $cacheKey,
            $ttl,
            fn () => $this->PanganService->getNilaiPerdaganganPangan($filters)
        );

        if (empty($data)) {
            return ApiResponse::success([], 'Tidak ada data.', [
                'filters' => $filters,
            ]);
        }

        if ($idKom > 0) {
            $data = $this->sliceByKomoditas($data, $idKom);
        }

        [$payload, $meta] = $this->splitPayloadAndMeta($data);

        return ApiResponse::success($payload, 'Nilai perdagangan pangan berhasil diambil', $meta);
    }

    /* ================= NILAI PANGAN PRODUK (HS) ================= */

    public function nilaiPanganProduk(Request $request): JsonResponse
    {
        $filters = $this->normalizeFilters($request);

        $kodeSumber = (int) $request->input('sumber', 5);
        $limit = (int) $request->input('limit', 50);

        // origin/dest dari frontend
        $reporters = $this->csvToUpperArray($request->input('origin', []));
        $partners = $this->csvToUpperArray($request->input('dest', []));
        if (! empty($reporters)) {
            $filters['reporter'] = $reporters;
        }
        if (! empty($partners)) {
            $filters['partner'] = $partners;
        }

        // === HS CODE: default ke seluruh HS Code jika belum diisi ===
        if (empty($filters['hs_list'] ?? [])) {
            $filters['hs_list'] = $this->allHsList();
        }

        $cacheKey = $this->buildCacheKeyByOriginsDests(
            'pangan-produk',
            $reporters,
            $partners,
            $filters,
            $kodeSumber,
            $limit
        );
        $ttl = now()->addDays(3);

        $data = Cache::remember(
            $cacheKey,
            $ttl,
            fn () => $this->PanganService->nilaiPerdaganganPerProduk($filters)
        );

        if (empty($data)) {
            return ApiResponse::success([], 'Tidak ada data.');
        }

        [$payload, $meta] = $this->splitPayloadAndMeta($data);

        return ApiResponse::success($payload, 'Produk perdagangan pangan berhasil diambil', $meta);
    }

    /* ================= Helpers ================= */

    private function allHsCodes(): array
    {
        if ($this->allHsMemo !== null) {
            return $this->allHsMemo;
        }

        $codes = Cache::remember(
            'side_cache:sektor-prioritas:pangan:all-hs-codes',
            now()->addDays(3),
            function () {
                $rows = DB::connection($this->conn)
                    ->table("{$this->TB_SEKTOR} as s")
                    ->where('s.ID_Sektor', $this->ID_Sektor)
                    ->distinct()
                    ->pluck('s.hscode')
                    ->toArray();

                $clean = array_values(array_unique(array_filter(array_map(
                    fn ($v) => trim((string) $v),
                    $rows
                ))));
                sort($clean, SORT_STRING);

                return $clean;
            }
        );

        $this->allHsMemo = $codes;

        return $codes;
    }

    /**
     * Ambil seluruh HS Code pangan (digits-only) sebagai patokan “all HS”.
     */
    private function allHsList(): array
    {
        return Cache::remember(
            'pangan:all_hscode_list',
            now()->addDays(3),
            function () {
                $rows = DB::connection($this->conn)
                    ->table($this->TB_SEKTOR)
                    ->where('ID_Sektor', $this->ID_Sektor)
                    ->whereNotNull('hscode')
                    ->pluck('hscode')
                    ->toArray();

                $out = [];
                foreach ($rows as $v) {
                    $digits = preg_replace('/\D+/', '', (string) $v);
                    if ($digits !== '') {
                        $out[] = $digits;
                    }
                }

                $out = array_values(array_unique($out));
                sort($out, SORT_STRING);

                return $out;
            }
        );
    }

    private function normalizeFilters(Request $request): array
    {
        $filters = [];

        // reporter/partner (boleh kirim langsung)
        $partner = $this->csvToUpperArray($request->input('partner', []));
        $reporter = $this->csvToUpperArray($request->input('reporter', []));
        if (! empty($partner)) {
            $filters['partner'] = $partner;
        }
        if (! empty($reporter)) {
            $filters['reporter'] = $reporter;
        }

        // tahun
        $ys = $request->input('year_start');
        $ye = $request->input('year_end');
        if (is_numeric($ys)) {
            $filters['year_start'] = (int) $ys;
        }
        if (is_numeric($ye)) {
            $filters['year_end'] = (int) $ye;
        }

        // status
        $status = $request->input('status');
        $canon = function ($v) {
            $s = strtolower(trim((string) $v));
            if (in_array($s, ['export', 'ekspor'], true)) {
                return 'Export';
            }
            if (in_array($s, ['import', 'impor'], true)) {
                return 'Import';
            }

            return null;
        };
        if (is_array($status)) {
            $st = array_values(array_filter(array_unique(array_map($canon, $status))));
            if ($st) {
                $filters['status'] = $st;
            }
        } elseif (is_string($status)) {
            $st = $canon($status);
            if ($st) {
                $filters['status'] = $st;
            }
        }

        // dirjen
        $dirjen = $this->csvToUpperArray($request->input('dirjen', []));
        if (! empty($dirjen)) {
            $filters['dirjen'] = $dirjen;
        }

        // hs level panjang (angka tunggal 2/4/6/8/10)
        $hsLevel = $request->input('hs');
        if (is_numeric($hsLevel)) {
            $filters['hs'] = max(2, min(10, (int) $hsLevel));
        }

        // === HS LIST (prioritas: hscode → hs_list → hs_codes → hsCodes → string hs) ===
        $rawHs = $request->input(
            'hscode',
            $request->input(
                'hs_list',
                $request->input(
                    'hs_codes',
                    $request->input(
                        'hsCodes',
                        (is_string($hsLevel) ? $hsLevel : [])
                    )
                )
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
        if (! empty($hsList)) {
            $filters['hs_list'] = $hsList;
        }

        // komoditas_ids (masih dipakai utk endpoint nilaiPangan agregat / slicing by komoditas)
        $idKom = (int) $request->input('id_komoditas', 0);
        if ($idKom > 0) {
            $filters['komoditas_ids'] = [$idKom];
        } else {
            $komoditasIds = $this->csvToIntArray(
                $request->input('komoditas_ids', $request->input('komoditas', []))
            );
            if (! empty($komoditasIds)) {
                $filters['komoditas_ids'] = $komoditasIds;
            }
        }

        // buang null/kosong
        return array_filter(
            $filters,
            fn ($v) => is_array($v) ? count($v) > 0 : ! is_null($v) && $v !== ''
        );
    }

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

    private function sliceByKomoditas(array $dataAll, int $idKom): array
    {
        $data = $dataAll;

        if (isset($data['items']) && is_array($data['items'])) {
            $data['items'] = array_values(array_filter($data['items'], function ($row) use ($idKom) {
                $rid = $row['id_komoditas'] ?? $row['komoditas_id'] ?? $row['id'] ?? null;

                return (int) $rid === $idKom;
            }));

            return $data;
        }

        if (isset($data['by_komoditas']) && is_array($data['by_komoditas'])) {
            $data['by_komoditas'] = array_key_exists($idKom, $data['by_komoditas'])
                ? [$idKom => $data['by_komoditas'][$idKom]]
                : [];

            return $data;
        }

        // Unknown structure → return as-is
        return $data;
    }

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

    private function csvToIntArray($val): array
    {
        $arr = is_string($val) ? array_map('trim', explode(',', $val)) : (is_array($val) ? $val : []);
        $out = [];
        foreach ($arr as $v) {
            if ($v === '' || $v === null) {
                continue;
            }
            $n = (int) $v;
            if ($n !== 0 || $v === '0') {
                $out[] = $n;
            }
        }
        $out = array_values(array_unique($out));
        sort($out, SORT_NUMERIC);

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
        $stable = $filters;

        // Normalisasi hs_list
        if (isset($stable['hs_list']) && is_array($stable['hs_list'])) {
            $tmp = array_values(array_unique(array_map('strval', $stable['hs_list'])));
            sort($tmp, SORT_STRING);
            $stable['hs_list'] = $tmp;
        }

        // Normalisasi komoditas_ids (kalau ada)
        if (isset($stable['komoditas_ids']) && is_array($stable['komoditas_ids'])) {
            $tmp = array_values(array_unique(array_map('intval', $stable['komoditas_ids'])));
            sort($tmp, SORT_NUMERIC);
            $stable['komoditas_ids'] = $tmp;
        }

        ksort($stable);

        return SideCacheKey::pairs(['sektor-prioritas', 'pangan', $prefix], $stable);
    }

    private function buildCacheKeyByOriginsDests(
        string $prefix,
        array $origins,
        array $dests,
        array $filters,
        int $kodeSumber,
        int $limit
    ): string {
        $o = $origins;
        sort($o, SORT_STRING);
        $d = $dests;
        sort($d, SORT_STRING);

        // Hilangkan field yang diekspresikan di segmen
        $rest = $filters;
        unset($rest['reporter'], $rest['partner']);

        // Normalisasi hs_list agar stabil
        if (isset($rest['hs_list']) && is_array($rest['hs_list'])) {
            $tmp = array_values(array_unique(array_map('strval', $rest['hs_list'])));
            sort($tmp, SORT_STRING);
            $rest['hs_list'] = $tmp;
        }

        // Normalisasi komoditas_ids kalau masih ada
        if (isset($rest['komoditas_ids']) && is_array($rest['komoditas_ids'])) {
            $tmp = array_values(array_unique(array_map('intval', $rest['komoditas_ids'])));
            sort($tmp, SORT_NUMERIC);
            $rest['komoditas_ids'] = $tmp;
        }

        // tambahkan sumber & limit ke key
        $rest['_sumber'] = $kodeSumber;
        $rest['_limit'] = $limit;

        return SideCacheKey::pairs(
            ['sektor-prioritas', 'pangan', $prefix],
            array_merge(
                [
                    'origin' => $o ?: 'all',
                    'dest' => $d ?: 'all',
                ],
                $rest
            )
        );
    }

    private function sameSet(array $a, array $b): bool
    {
        $norm = function (array $x): array {
            $x = array_values(array_unique(array_map('strval', $x)));
            sort($x, SORT_STRING);

            return $x;
        };

        return $norm($a) === $norm($b);
    }
}
