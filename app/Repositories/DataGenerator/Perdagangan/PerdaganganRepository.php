<?php

namespace App\Repositories\DataGenerator\Perdagangan;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class PerdaganganRepository implements PerdaganganRepositoryInterface
{
    public function getDistinctKodeSumber()
    {
        $kodeSumberList = DB::connection('server_mysql')
            ->table('tbtrade')
            ->select('Kode_Sumber')
            ->whereNotNull('Kode_Sumber')
            ->where('Kode_Sumber', '!=', '')
            ->where('Kode_Sumber', '!=', '13')
            ->distinct()
            ->pluck('Kode_Sumber')
            ->toArray();

        return DB::connection('server_mysql')
            ->table('tbsumber')
            ->select('KodeSumber as id', 'NamaSumber as name')
            ->whereIn('KodeSumber', $kodeSumberList)
            ->orderBy('NamaSumber')
            ->get();
    }

    public function getDistinctTahun()
    {
        return DB::connection('server_mysql')
            ->table('tbtrade')
            ->select('Tahun')
            ->distinct()
            ->orderByDesc('Tahun')
            ->get();
    }

    public function getDistinctDefaultTahun()
    {
        return DB::connection('server_mysql')
            ->table('tbtrade')
            ->select('Tahun')
            ->where('Kode_Sumber', 5)
            ->distinct()
            ->orderByDesc('Tahun')
            ->get();
    }

    public function getTableFilterData(array $filters, int $page = 1, int $perPage = 50): array
    {
        // 1) Validasi & normalisasi dasar
        $norm = $this->validateAndNormalize($filters);
        if (! $norm['ok']) {
            return $norm['error'];
        }

        // 2) Resolve asal/tujuan + map nama negara
        $originDest = $this->resolveOriginDestination($filters);
        if (! $originDest['ok']) {
            return $originDest['error'];
        }
        [$originList, $destinationList, $namesMap] = $originDest['payload'];

        // 3) Siapkan base query & pagination HS
        $baseQ = $this->buildBaseQuery(
            $norm['years'],
            $norm['tradeType'],
            $norm['sourceCode'],
            $norm['hsLength'],
            $norm['product'],
            $norm['hasProductFilter']
        );

        try {
            [$pageHs, $total, $page, $perPage, $lastPage] = $this->paginateByHsAsc(
                $baseQ,
                $originList,
                $destinationList,
                $norm['latestYear'],
                $norm['tradeType'],
                $page,
                $perPage
            );
        } catch (\Throwable $e) {
            return [
                'data' => [],
                'pagination' => null,
                'meta' => null,
                'success' => false,
                'message' => 'Gagal melakukan pagination HS',
                'errors' => [$e->getMessage()],
            ];
        }

        $pagination = [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => $lastPage,
        ];

        if (empty($pageHs)) {
            return [
                'data' => [],
                'pagination' => $pagination,
                'meta' => [
                    'years' => [
                        (string) ($norm['years'][0] ?? $filters['yearFrom'] ?? ''),
                        (string) (end($norm['years']) ?: ($filters['yearTo'] ?? '')),
                    ],
                    'hsLevel' => $norm['hsLength'],
                    'tradeType' => $norm['tradeType'],
                    // >> NAMA NEGARA, BUKAN KODE
                    'origins' => $this->codesToNames($originList, $namesMap),
                    'originGroups' => $filters['originGroups'] ?? [],
                    'destinations' => $this->codesToNames($destinationList, $namesMap),
                    'destinationGroups' => $filters['destinationGroups'] ?? [],
                    'products' => is_array($norm['product'])
                        ? $norm['product']
                        : (($norm['product'] !== null && $norm['product'] !== '') ? [$norm['product']] : []),
                    'source' => $this->resolveSourceLabel($norm['sourceCode']),
                    'pagination' => $pagination,
                ],
                'success' => true,
                'message' => 'Tidak ada HS pada halaman ini.',
            ];
        }

        // 4) Ambil 3 segmen untuk HS di halaman ini
        $segA = $this->fetchSegmentAsalTujuan(clone $baseQ, $originList, $destinationList, $pageHs, $norm['tradeType']);
        $segB = $this->fetchSegmentAsalDunia(clone $baseQ, $originList, $pageHs, $norm['tradeType']);
        $segC = $this->fetchSegmentDuniaTujuan(clone $baseQ, $destinationList, $pageHs, $norm['tradeType']);

        // 5) Format hasil (angka & struktur)
        $data = $this->formatSegments(
            $segA,
            $segB,
            $segC,
            $norm['years'],
            $namesMap,
            $norm['tradeType']
        );

        // 6) Bangun meta: pakai NAMA negara
        $meta = [
            'years' => [
                (string) ($norm['years'][0] ?? $filters['yearFrom'] ?? ''),
                (string) (end($norm['years']) ?: ($filters['yearTo'] ?? '')),
            ],
            'hsLevel' => $norm['hsLength'],
            'tradeType' => $norm['tradeType'],
            // ==== DI SINI KUNCI NYA ====
            'origins' => $this->codesToNames($originList, $namesMap),
            'originGroups' => $this->resolveGroupLabels($filters['originGroups'] ?? []),
            'destinations' => $this->codesToNames($destinationList, $namesMap),
            'destinationGroups' => $this->resolveGroupLabels($filters['destinationGroups'] ?? []),
            'products' => is_array($norm['product'])
                ? $norm['product']
                : (($norm['product'] !== null && $norm['product'] !== '') ? [$norm['product']] : []),
            // label sumber, misalnya "Trademap"
            'source' => $this->resolveSourceLabel($norm['sourceCode']),
            'pagination' => $pagination,
        ];

        return [
            'data' => $data,
            'pagination' => $pagination,
            'meta' => $meta,
            'success' => true,
            'message' => 'OK',
        ];
    }

    public function getVisualizationFilterData(array $filters, int $page = 1, int $perPage = 5): array
    {
        // 1) Validasi & normalisasi dasar
        $norm = $this->validateAndNormalize($filters);
        if (! $norm['ok']) {
            return $norm['error'];
        }

        // 2) Resolve asal/tujuan + map nama negara
        $originDest = $this->resolveOriginDestination($filters);
        if (! $originDest['ok']) {
            return $originDest['error'];
        }
        [$originList, $destinationList, $namesMap] = $originDest['payload'];

        // Siapkan nama asal & tujuan (semua yang dipilih user)
        $originNamesAll = $this->codesToNames($originList, $namesMap);
        $destinationNamesAll = $this->codesToNames($destinationList, $namesMap);
        $originGroupLabels = $this->resolveGroupLabels($filters['originGroups'] ?? []);
        $destinationGroupLabels = $this->resolveGroupLabels($filters['destinationGroups'] ?? []);
        $hasOriginGroup = !empty($originGroupLabels);
        $hasDestinationGroup = !empty($destinationGroupLabels);

        // 3) Base query TANPA JOIN (filter tahun, status, sumber, hsLevel/produk dipasang di buildBaseQuery)
        $baseQ = $this->buildBaseQuery(
            $norm['years'],
            $norm['tradeType'],
            $norm['sourceCode'],
            $norm['hsLength'],
            $norm['product'],
            $norm['hasProductFilter']
        );

        // 4) Tentukan HS target (TOP 10 atau sesuai pilihan user; tetap dibatasi 10)
        try {
            [$targetHs, $selectionMetaHs] = $this->pickHsForVisualization(
                $baseQ,
                $originList,
                $destinationList,
                $norm['hasProductFilter'],
                $norm['product'],
                $norm['tradeType'],
                10
            );
        } catch (\Throwable $e) {
            return [
                'data' => [],
                'pagination' => null,
                'success' => false,
                'message' => 'Gagal menentukan daftar HS untuk visualisasi',
                'errors' => [$e->getMessage()],
            ];
        }

        if (empty($targetHs)) {
            return [
                'data' => [],
                'pagination' => null,
                'success' => true,
                'message' => 'Tidak ada HS yang memenuhi kriteria untuk visualisasi.',
                'meta' => [
                    'years' => $norm['years'],
                    'origins' => $originNamesAll,
                    'destinations' => $destinationNamesAll,
                    'tradeType' => $norm['tradeType'],
                    'source' => $this->resolveSourceLabel($norm['sourceCode']),
                ],
            ];
        }

        // 5) Ambil TOP negara berdasarkan filter aktif (dipakai sebagai breakdown bila ada group)
        try {
            [$topOrigins, $topDestinations, $selectionMetaCountry] = $this->pickTopCountriesForVisualization(
                $baseQ,
                $originList,
                $destinationList,
                $norm['tradeType'],
                10 // << TOP 10 country
            );
        } catch (\Throwable $e) {
            return [
                'data' => [],
                'pagination' => null,
                'success' => false,
                'message' => 'Gagal menentukan negara teratas untuk visualisasi',
                'errors' => [$e->getMessage()],
            ];
        }

        if ((!$hasOriginGroup && empty($topOrigins)) || (!$hasDestinationGroup && empty($topDestinations))) {
            return [
                'data' => [],
                'pagination' => null,
                'success' => true,
                'message' => 'Negara teratas tidak ditemukan untuk visualisasi.',
                'meta' => [
                    'years' => $norm['years'],
                    'origins' => $originNamesAll,
                    'destinations' => $destinationNamesAll,
                    'tradeType' => $norm['tradeType'],
                    'source' => $this->resolveSourceLabel($norm['sourceCode']),
                ],
            ];
        }

        $isGroupMode = $hasOriginGroup || $hasDestinationGroup;
        $mainOrigins = $isGroupMode ? $originList : $topOrigins;
        $mainDestinations = $isGroupMode ? $destinationList : $topDestinations;

        // 6) Data utama tetap mengikuti filter penuh user bila ada group, selain itu pakai perilaku lama (top negara)
        $segA = $this->fetchSegmentAsalTujuan(clone $baseQ, $mainOrigins, $mainDestinations, $targetHs, $norm['tradeType']);
        $segB = $isGroupMode ? collect() : $this->fetchSegmentAsalDunia(clone $baseQ, $mainOrigins, $targetHs, $norm['tradeType']);
        $segC = $isGroupMode ? collect() : $this->fetchSegmentDuniaTujuan(clone $baseQ, $mainDestinations, $targetHs, $norm['tradeType']);

        $data = $this->formatSegments(
            $segA,
            $segB,
            $segC,
            $norm['years'],
            $namesMap,
            $norm['tradeType']
        );

        // 7) Tambahkan total seluruh HS untuk filter penuh
        $allSegA = $this->fetchSegmentAsalTujuanAllHs(clone $baseQ, $mainOrigins, $mainDestinations, $norm['tradeType']);
        $allSegB = $isGroupMode ? collect() : $this->fetchSegmentAsalDuniaAllHs(clone $baseQ, $mainOrigins, $norm['tradeType']);
        $allSegC = $isGroupMode ? collect() : $this->fetchSegmentDuniaTujuanAllHs(clone $baseQ, $mainDestinations, $norm['tradeType']);
        $data['total_all_hs'] = $this->formatSegments(
            $allSegA,
            $allSegB,
            $allSegC,
            $norm['years'],
            $namesMap,
            $norm['tradeType']
        );

        $data = $this->formatVisualizationPayload($data, $norm['tradeType'], $isGroupMode);

        // 8) Kembalikan tanpa pagination + meta context
        return [
            'data' => $data,
            'pagination' => null,
            'success' => true,
            'message' => 'OK',
            'meta' => [
                'years' => $norm['years'],
                'origins' => $hasOriginGroup ? $originGroupLabels : $this->codesToNames($mainOrigins, $namesMap),
                'destinations' => $hasDestinationGroup ? $destinationGroupLabels : $this->codesToNames($mainDestinations, $namesMap),
                'tradeType' => $norm['tradeType'],
                'source' => $this->resolveSourceLabel($norm['sourceCode']),
            ],
        ];
    }

    private function formatVisualizationPayload(array $data, string $tradeType, bool $isGroupMode): array
    {
        $asalTujuanKey = "{$tradeType}_asal_ke_tujuan";
        $asalDuniaKey = "{$tradeType}_asal_ke_dunia";
        $duniaTujuanKey = "{$tradeType}_dunia_ke_tujuan";

        $perProduct = $data[$asalTujuanKey]['per_product'] ?? [];
        $totalAllHs = $data['total_all_hs'][$asalTujuanKey]['per_product'] ?? [];

        $formatted = [
            $asalTujuanKey => [
                'products' => $perProduct,
                'total_all_hs' => $totalAllHs,
            ],
        ];

        if (! $isGroupMode) {
            $formatted[$asalDuniaKey] = $data[$asalDuniaKey]['per_product'] ?? [];
            $formatted[$duniaTujuanKey] = $data[$duniaTujuanKey]['per_product'] ?? [];
        }

        return $formatted;
    }

    private function pickHsForVisualization(
        Builder $baseQ,
        array $originList,
        array $destinationList,
        bool $hasProductFilter,
        $product,
        string $tradeType,
        int $topN = 5
    ): array {
        $maxCap = 5;
        $topN = max(1, min($topN, $maxCap));

        // Jika user sudah pilih HS → gunakan itu (dibatasi)
        if ($hasProductFilter) {
            $hsList = is_array($product) ? $product : [$product];
            $hsList = array_values(array_filter($hsList, fn ($v) => $v !== 'all' && $v !== null && $v !== ''));
            if (count($hsList) > $maxCap) {
                $hsList = array_slice($hsList, 0, $maxCap);
                $meta = [
                    'selection_hs' => 'product_filter',
                    'note_hs' => "HS dibatasi {$maxCap} pertama untuk performa.",
                    'hs_count' => count($hsList),
                ];
            } else {
                $meta = [
                    'selection_hs' => 'product_filter',
                    'hs_count' => count($hsList),
                ];
            }

            return [$hsList, $meta];
        }

        // Tidak ada product filter → TOP-N HS berdasarkan total nilai (asal→tujuan)
        $cacheKey = 'viz:topHs:'.md5(json_encode([$originList, $destinationList, 'topN' => $topN, 'tradeType' => $tradeType]));
        $hsList = Cache::remember($cacheKey, 300, function () use ($baseQ, $originList, $destinationList, $topN, $tradeType) {
            $sumExpr = $this->sumExpressionForTradeType($tradeType);

            return (clone $baseQ)
                ->whereIn('t.Kode_Alpha3_Reporter', $originList)
                ->whereIn('t.Kode_Alpha3_Partner', $destinationList)
                ->select('t.HsCode', DB::raw("{$sumExpr} as s"))
                ->groupBy('t.HsCode')
                ->orderByDesc('s')
                ->limit($topN)
                ->pluck('t.HsCode')
                ->toArray();
        });

        return [$hsList, [
            'selection_hs' => 'top_n',
            'top_n_hs' => $topN,
            'hs_count' => count($hsList),
        ]];
    }

    private function pickTopCountriesForVisualization(
        Builder $baseQ,
        array $originList,
        array $destinationList,
        string $tradeType,
        int $topN = 5
    ): array {
        $topN = max(1, min($topN, 5)); // << batas 10

        $sumExpr = $this->sumExpressionForTradeType($tradeType);

        // TOP reporter (asal)
        $cacheKeyRep = 'viz:topRep:'.md5(json_encode([$originList, $destinationList, 'topN' => $topN, 'tradeType' => $tradeType]));
        $topOrigins = Cache::remember($cacheKeyRep, 300, function () use ($baseQ, $originList, $destinationList, $topN, $sumExpr) {
            return (clone $baseQ)
                ->whereIn('t.Kode_Alpha3_Reporter', $originList)
                ->whereIn('t.Kode_Alpha3_Partner', $destinationList)
                ->select('t.Kode_Alpha3_Reporter as rep', DB::raw("{$sumExpr} as s"))
                ->groupBy('rep')
                ->orderByDesc('s')
                ->limit($topN)
                ->pluck('rep')
                ->toArray();
        });

        // TOP partner (tujuan)
        $cacheKeyPar = 'viz:topPar:'.md5(json_encode([$originList, $destinationList, 'topN' => $topN, 'tradeType' => $tradeType]));
        $topDestinations = Cache::remember($cacheKeyPar, 300, function () use ($baseQ, $originList, $destinationList, $topN, $sumExpr) {
            return (clone $baseQ)
                ->whereIn('t.Kode_Alpha3_Reporter', $originList)
                ->whereIn('t.Kode_Alpha3_Partner', $destinationList)
                ->select('t.Kode_Alpha3_Partner as par', DB::raw("{$sumExpr} as s"))
                ->groupBy('par')
                ->orderByDesc('s')
                ->limit($topN)
                ->pluck('par')
                ->toArray();
        });

        return [$topOrigins, $topDestinations, [
            'selection_country' => 'top_n',
            'top_n_country' => $topN,
            'origin_count' => count($topOrigins),
            'destination_count' => count($topDestinations),
        ]];
    }

    private function validateAndNormalize(array $filters): array
    {
        $yearFrom = isset($filters['yearFrom']) ? (int) $filters['yearFrom'] : null;
        $yearTo = isset($filters['yearTo']) ? (int) $filters['yearTo'] : null;
        if ($yearFrom === null || $yearTo === null || $yearFrom > $yearTo) {
            return [
                'ok' => false,
                'error' => [
                    'data' => [],
                    'pagination' => null,
                    'success' => false,
                    'message' => 'Rentang tahun tidak valid.',
                    'errors' => ['yearFrom/yearTo invalid'],
                ],
            ];
        }

        $validYears = DB::connection('server_mysql')
            ->table('tbtrade')->distinct()->pluck('Tahun')
            ->filter(fn ($y) => is_numeric($y))->map(fn ($y) => (int) $y)
            ->values()->toArray();

        $years = array_values(array_intersect(range($yearFrom, $yearTo), $validYears));
        if (empty($years)) {
            return [
                'ok' => false,
                'error' => [
                    'data' => [],
                    'pagination' => null,
                    'success' => false,
                    'message' => 'Data tidak ditemukan untuk rentang tahun yang dipilih.',
                    'errors' => ['Tahun tidak tersedia dalam database'],
                ],
            ];
        }

        $tradeType = $this->normalizeTradeType($filters['tradeType'] ?? '');
        $sourceCode = $filters['source'] ?? '';
        $existsExact = DB::connection('server_mysql')
            ->table('tbtrade')
            ->where('Kode_Sumber', (string) $sourceCode)
            ->exists();

        if (! $existsExact) {
            $mapped = DB::connection('server_mysql')
                ->table('tbsumber')
                ->where('KodeSumber', $sourceCode)
                ->value('KodeSumber');
            if ($mapped) {
                $sourceCode = (string) $mapped;
            }
        }
        if (! $tradeType || $sourceCode === '') {
            return [
                'ok' => false,
                'error' => [
                    'data' => [],
                    'pagination' => null,
                    'success' => false,
                    'message' => 'tradeType dan source wajib diisi.',
                    'errors' => ['tradeType/source kosong atau tidak valid'],
                ],
            ];
        }

        $hsLength = isset($filters['hsLevel']) ? (int) $filters['hsLevel'] : 0;

        /** @var null|string|string[] $product */
        $product = $filters['product'] ?? null;
        $hasProductFilter = is_array($product)
          ? ! in_array('all', $product, true)
          : ($product !== null && $product !== '' && $product !== 'all');

        return [
            'ok' => true,
            'years' => $years,
            'latestYear' => max($years),
            'hsLength' => $hsLength,
            'tradeType' => $tradeType,
            'sourceCode' => $sourceCode,
            'product' => $product,
            'hasProductFilter' => $hasProductFilter,
        ];
    }

    private function resolveOriginDestination(array $filters): array
    {
        $originsExplicit = collect($filters['origins'] ?? [])->filter()->values()->toArray();
        $destsExplicit = collect($filters['destinations'] ?? [])->filter()->values()->toArray();
        $originGroupsRaw = collect($filters['originGroups'] ?? [])->filter()->values();
        $destGroupsRaw = collect($filters['destinationGroups'] ?? [])->filter()->values();

        // =========================
        // 1. Pisah group: angka vs huruf
        // =========================
        $originGroupOrgIds = $originGroupsRaw
            ->filter(fn ($v) => is_numeric($v))   // angka → tborgjenis
            ->values()
            ->toArray();

        $originGroupBenuaIds = $originGroupsRaw
            ->filter(fn ($v) => ! is_numeric($v))  // huruf → tbbenua (ID_benua)
            ->values()
            ->toArray();

        $destGroupOrgIds = $destGroupsRaw
            ->filter(fn ($v) => is_numeric($v))   // angka → tborgjenis
            ->values()
            ->toArray();

        $destGroupBenuaIds = $destGroupsRaw
            ->filter(fn ($v) => ! is_numeric($v))  // huruf → tbbenua (ID_benua)
            ->values()
            ->toArray();

        // =========================
        // 2. Ambil negara dari group Benua (tbbenua via tbkawasan)
        // =========================
        $originFromBenua = ! empty($originGroupBenuaIds)
            ? DB::connection('server_mysql')
                ->table('tbkawasan as k')
                ->join('tbnegara as n', 'k.ID_Wil', '=', 'n.ID_Wil')
                ->whereIn('k.ID_benua', $originGroupBenuaIds)
                ->pluck('n.Kode_Alpha3')
                ->toArray()
            : [];

        $destFromBenua = ! empty($destGroupBenuaIds)
            ? DB::connection('server_mysql')
                ->table('tbkawasan as k')
                ->join('tbnegara as n', 'k.ID_Wil', '=', 'n.ID_Wil')
                ->whereIn('k.ID_benua', $destGroupBenuaIds)
                ->pluck('n.Kode_Alpha3')
                ->toArray()
            : [];

        // =========================
        // 3. Ambil negara dari group Organisasi (tborgjenis)
        //    ASUMSI: tbnegara.ID_Org = tborgjenis.ID_Org
        // =========================
        $originFromOrg = ! empty($originGroupOrgIds)
            ? DB::connection('server_mysql')
                ->table('tborgjenis as o')
                ->join('tborgnegara as tbn', 'o.ID_Org', '=', 'tbn.ID_Org')
                ->join('tbnegara as n', 'tbn.Kode_Alpha3', '=', 'n.Kode_Alpha3')
                ->whereIn('o.ID_Org', $originGroupOrgIds)
                ->pluck('n.Kode_Alpha3')
                ->toArray()
            : [];

        $destFromOrg = ! empty($destGroupOrgIds)
            ? DB::connection('server_mysql')
                ->table('tborgjenis as o')
                ->join('tborgnegara as tbn', 'o.ID_Org', '=', 'tbn.ID_Org')
                ->join('tbnegara as n', 'tbn.Kode_Alpha3', '=', 'n.Kode_Alpha3')
                ->whereIn('o.ID_Org', $destGroupOrgIds)
                ->pluck('n.Kode_Alpha3')
                ->toArray()
            : [];

        // =========================
        // 4. Merge explicit + hasil group
        // =========================
        $originFromGroup = array_merge($originFromBenua, $originFromOrg);
        $destFromGroup = array_merge($destFromBenua, $destFromOrg);

        $originList = collect($originsExplicit)
            ->merge($originFromGroup)
            ->unique()
            ->values()
            ->toArray();

        $destinationList = collect($destsExplicit)
            ->merge($destFromGroup)
            ->unique()
            ->values()
            ->toArray();

        if (empty($originList) || empty($destinationList)) {
            return [
                'ok' => false,
                'error' => [
                    'data' => [],
                    'pagination' => null,
                    'success' => false,
                    'message' => 'Origin atau destination list kosong setelah resolusi grup/selection.',
                    'errors' => ['origin/destination list empty'],
                ],
            ];
        }

        // =========================
        // 5. Map nama negara
        // =========================
        $allCodes = array_values(array_unique(array_merge($originList, $destinationList)));
        $namesMap = [];

        if (! empty($allCodes)) {
            $namesMap = DB::connection('server_mysql')
                ->table('tbnegara')
                ->whereIn('Kode_Alpha3', $allCodes)
                ->pluck('Negara_IDN', 'Kode_Alpha3')
                ->toArray();
        }

        return [
            'ok' => true,
            'payload' => [$originList, $destinationList, $namesMap],
        ];
    }

    private function codesToNames(array $codes, array $namesMap): array
    {
        $names = [];
        foreach ($codes as $code) {
            $names[] = $namesMap[$code] ?? $code;
        }

        return array_values(array_unique($names));
    }

    private function resolveGroupLabels(array $groupIds): array
    {
        if (empty($groupIds)) {
            return [];
        }

        $raw = collect($groupIds)->filter()->values();

        // Angka → tborgjenis, huruf → tbbenua (sesuai rule kamu)
        $orgIds = $raw->filter(fn ($v) => is_numeric($v))->values()->toArray();
        $benuaIds = $raw->filter(fn ($v) => ! is_numeric($v))->values()->toArray();

        $labels = [];

        // Group organisasi (tborgjenis)
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

        // Group benua (tbbenua) – kalau kamu kirim ID_benua atau nama benua
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

    /**
     * Ambil label sumber dari tbsumber berdasarkan KodeSumber.
     */
    private function resolveSourceLabel(string $sourceCode): string
    {
        $label = DB::connection('server_mysql')
            ->table('tbsumber')
            ->where('KodeSumber', $sourceCode)
            ->value('NamaSumber');

        return $label ?: $sourceCode;
    }

    private function buildBaseQuery(
        array $years,
        string $tradeType,
        $sourceCode,
        int $hsLength,
        $product,
        bool $hasProductFilter
    ): Builder {
        $q = DB::connection('server_mysql')
            ->table('tbtrade as t')
            ->join('tbharmonized as h', 't.HsCode', '=', 'h.hscode')
            ->whereIn('t.Tahun', $years)
            ->where('t.Kode_Sumber', $sourceCode);

        if ($this->isCombinedTradeType($tradeType)) {
            $q->whereIn('t.Status', ['Export', 'Import']);
        } else {
            $q->where('t.Status', $tradeType);
        }

        if ($hsLength > 0) {
            $q->where('t.hs_len', $hsLength);
        }

        if ($hasProductFilter) {
            if (is_array($product)) {
                $q->whereIn('t.HsCode', $product);
            } else {
                $q->where('t.HsCode', $product);
            }
        }

        return $q;
    }

    private function paginateByHsAsc(
        Builder $baseQ,
        array $originList,
        array $destinationList,
        int $latestYear,
        string $tradeType,
        int $page,
        int $perPage
    ): array {
        $total = (clone $baseQ)
            ->whereIn('t.Kode_Alpha3_Reporter', $originList)
            ->whereIn('t.Kode_Alpha3_Partner', $destinationList)
            ->distinct()
            ->count('t.HsCode');

        $perPage = max(1, min(500, $perPage));
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));

        // 2) daftar HS untuk halaman ini, urut berdasar total tahun terakhir asal -> tujuan
        $pageHs = (clone $baseQ)
            ->whereIn('t.Kode_Alpha3_Reporter', $originList)
            ->whereIn('t.Kode_Alpha3_Partner', $destinationList)
            ->select('t.HsCode')
            ->selectRaw(
                $this->latestYearSortExpression($tradeType).' as latest_year_total',
                $this->latestYearSortBindings($tradeType, $latestYear)
            )
            ->groupBy('t.HsCode')
            ->orderByDesc('latest_year_total')
            ->orderBy('t.HsCode', 'asc')
            ->forPage($page, $perPage)
            ->pluck('t.HsCode')
            ->toArray();

        return [$pageHs, $total, $page, $perPage, $lastPage];
    }

    private function fetchSegmentAsalTujuan(
        Builder $baseQ,
        array $originList,
        array $destinationList,
        array $pageHs,
        string $tradeType
    )
    {
        $sumExpr = $this->sumExpressionForTradeType($tradeType);

        return (clone $baseQ)
            ->select(
                'h.hscode',
                'h.description',
                't.Tahun',
                't.Kode_Alpha3_Reporter as rep',
                't.Kode_Alpha3_Partner  as par',
                DB::raw("{$sumExpr} as total")
            )
            ->whereIn('t.Kode_Alpha3_Reporter', $originList)
            ->whereIn('t.Kode_Alpha3_Partner', $destinationList)
            ->whereIn('h.hscode', $pageHs)
            ->groupBy('h.hscode', 'h.description', 't.Tahun', 'rep', 'par')
            ->get();
    }

    private function fetchSegmentAsalTujuanAllHs(
        Builder $baseQ,
        array $originList,
        array $destinationList,
        string $tradeType
    ) {
        $sumExpr = $this->sumExpressionForTradeType($tradeType);

        return (clone $baseQ)
            ->select(
                DB::raw("'ALL' as hscode"),
                DB::raw("'Total Semua HS' as description"),
                't.Tahun',
                't.Kode_Alpha3_Reporter as rep',
                't.Kode_Alpha3_Partner  as par',
                DB::raw("{$sumExpr} as total")
            )
            ->whereIn('t.Kode_Alpha3_Reporter', $originList)
            ->whereIn('t.Kode_Alpha3_Partner', $destinationList)
            ->groupBy('t.Tahun', 'rep', 'par')
            ->get();
    }

    private function fetchSegmentAsalDunia(
        Builder $baseQ,
        array $originList,
        array $pageHs,
        string $tradeType
    )
    {
        $sumExpr = $this->sumExpressionForTradeType($tradeType);

        return (clone $baseQ)
            ->select(
                'h.hscode',
                'h.description',
                't.Tahun',
                't.Kode_Alpha3_Reporter as rep',
                DB::raw("{$sumExpr} as total")
            )
            ->whereIn('t.Kode_Alpha3_Reporter', $originList)
            ->whereIn('h.hscode', $pageHs)
            ->groupBy('h.hscode', 'h.description', 't.Tahun', 'rep')
            ->get();
    }

    private function fetchSegmentAsalDuniaAllHs(
        Builder $baseQ,
        array $originList,
        string $tradeType
    ) {
        $sumExpr = $this->sumExpressionForTradeType($tradeType);

        return (clone $baseQ)
            ->select(
                DB::raw("'ALL' as hscode"),
                DB::raw("'Total Semua HS' as description"),
                't.Tahun',
                't.Kode_Alpha3_Reporter as rep',
                DB::raw("{$sumExpr} as total")
            )
            ->whereIn('t.Kode_Alpha3_Reporter', $originList)
            ->groupBy('t.Tahun', 'rep')
            ->get();
    }

    private function fetchSegmentDuniaTujuan(
        Builder $baseQ,
        array $destinationList,
        array $pageHs,
        string $tradeType
    )
    {
        $sumExpr = $this->sumExpressionForTradeType($tradeType);

        return (clone $baseQ)
            ->select(
                'h.hscode',
                'h.description',
                't.Tahun',
                't.Kode_Alpha3_Partner as par',
                DB::raw("{$sumExpr} as total")
            )
            ->whereIn('t.Kode_Alpha3_Partner', $destinationList)
            ->whereIn('h.hscode', $pageHs)
            ->groupBy('h.hscode', 'h.description', 't.Tahun', 'par')
            ->get();
    }

    private function fetchSegmentDuniaTujuanAllHs(
        Builder $baseQ,
        array $destinationList,
        string $tradeType
    ) {
        $sumExpr = $this->sumExpressionForTradeType($tradeType);

        return (clone $baseQ)
            ->select(
                DB::raw("'ALL' as hscode"),
                DB::raw("'Total Semua HS' as description"),
                't.Tahun',
                't.Kode_Alpha3_Partner as par',
                DB::raw("{$sumExpr} as total")
            )
            ->whereIn('t.Kode_Alpha3_Partner', $destinationList)
            ->groupBy('t.Tahun', 'par')
            ->get();
    }

    private function formatSegments($rowsAT, $rowsAD, $rowsDT, array $years, array $namesMap, string $tradeType): array
    {
        $fmt = fn ($v) => number_format((float) $v, 0, ',', '.');

        // A: ASAL → TUJUAN
        $exportTujuan = [];
        foreach ($rowsAT as $r) {
            $code = $r->hscode;
            $yr = (int) $r->Tahun;
            $val = (float) $r->total;
            $asal = $namesMap[$r->rep] ?? $r->rep;
            $tuju = $namesMap[$r->par] ?? $r->par;

            $exportTujuan[$code] ??= ['hscode' => $code, 'product' => $r->description, 'total' => 0.0];
            $exportTujuan[$code][$yr] ??= ['per_negara' => [], 'total' => 0.0];

            $exportTujuan[$code][$yr]['per_negara'][] = ['asal' => $asal, 'tujuan' => $tuju, 'total' => $val];
            $exportTujuan[$code][$yr]['total'] += $val;
            $exportTujuan[$code]['total'] += $val;
        }
        foreach ($exportTujuan as &$p) {
            foreach ($years as $y) {
                if (isset($p[$y])) {
                    $p[$y]['total'] = $fmt($p[$y]['total']);
                    $p[$y]['per_negara'] = array_map(
                        fn ($row) => ['asal' => $row['asal'], 'tujuan' => $row['tujuan'], 'total' => $fmt($row['total'])],
                        $p[$y]['per_negara']
                    );
                } else {
                    $p[$y] = ['per_negara' => [], 'total' => $fmt(0)];
                }
            }
            $p['total'] = $fmt($p['total'] ?? 0);
        }
        unset($p);

        // B: ASAL → DUNIA
        $asalDunia = [];
        foreach ($rowsAD as $r) {
            $code = $r->hscode;
            $yr = (int) $r->Tahun;
            $val = (float) $r->total;
            $asal = $namesMap[$r->rep] ?? $r->rep;

            $asalDunia[$code] ??= ['hscode' => $code, 'product' => $r->description, 'total' => 0.0];
            $asalDunia[$code][$yr] ??= ['per_negara' => [], 'total' => 0.0];

            $asalDunia[$code][$yr]['per_negara'][] = ['asal' => $asal, 'total' => $val];
            $asalDunia[$code][$yr]['total'] += $val;
            $asalDunia[$code]['total'] += $val;
        }
        foreach ($asalDunia as &$p) {
            foreach ($years as $y) {
                if (isset($p[$y])) {
                    $p[$y]['total'] = $fmt($p[$y]['total']);
                    $p[$y]['per_negara'] = array_map(
                        fn ($row) => ['asal' => $row['asal'], 'total' => $fmt($row['total'])],
                        $p[$y]['per_negara']
                    );
                }
            }
            $p['total'] = $fmt($p['total'] ?? 0);
        }
        unset($p);

        // C: DUNIA → TUJUAN
        $duniaTujuan = [];
        foreach ($rowsDT as $r) {
            $code = $r->hscode;
            $yr = (int) $r->Tahun;
            $val = (float) $r->total;
            $tuju = $namesMap[$r->par] ?? $r->par;

            $duniaTujuan[$code] ??= ['hscode' => $code, 'product' => $r->description, 'total' => 0.0];
            $duniaTujuan[$code][$yr] ??= ['per_negara' => [], 'total' => 0.0];

            $duniaTujuan[$code][$yr]['per_negara'][] = ['tujuan' => $tuju, 'total' => $val];
            $duniaTujuan[$code][$yr]['total'] += $val;
            $duniaTujuan[$code]['total'] += $val;
        }
        foreach ($duniaTujuan as &$p) {
            foreach ($years as $y) {
                if (isset($p[$y])) {
                    $p[$y]['total'] = $fmt($p[$y]['total']);
                    $p[$y]['per_negara'] = array_map(
                        fn ($row) => ['tujuan' => $row['tujuan'], 'total' => $fmt($row['total'])],
                        $p[$y]['per_negara']
                    );
                }
            }
            $p['total'] = $fmt($p['total'] ?? 0);
        }
        unset($p);

        return [
            "{$tradeType}_asal_ke_tujuan" => ['per_product' => array_values($exportTujuan)],
            "{$tradeType}_asal_ke_dunia" => ['per_product' => array_values($asalDunia)],
            "{$tradeType}_dunia_ke_tujuan" => ['per_product' => array_values($duniaTujuan)],
        ];
    }

    private function normalizeTradeType(?string $tradeType): ?string
    {
        $raw = trim((string) $tradeType);
        if ($raw === '') {
            return null;
        }

        $map = [
            'export' => 'Export',
            'import' => 'Import',
            'neraca' => 'Neraca',
            'total perdagangan' => 'Total',
            'total_perdagangan' => 'Total',
            'totalperdagangan' => 'Total',
            'total' => 'Total',
        ];

        return $map[strtolower($raw)] ?? null;
    }

    private function isCombinedTradeType(string $tradeType): bool
    {
        return in_array($tradeType, ['Neraca', 'Total'], true);
    }

    private function sumExpressionForTradeType(string $tradeType): string
    {
        if ($tradeType === 'Neraca') {
            return "SUM(CASE WHEN t.Status='Export' THEN t.Nilai ELSE 0 END) - SUM(CASE WHEN t.Status='Import' THEN t.Nilai ELSE 0 END)";
        }

        return 'SUM(t.Nilai)';
    }

    private function latestYearSortExpression(string $tradeType): string
    {
        if ($tradeType === 'Neraca') {
            return "SUM(CASE WHEN t.Tahun = ? AND t.Status='Export' THEN t.Nilai ELSE 0 END) - SUM(CASE WHEN t.Tahun = ? AND t.Status='Import' THEN t.Nilai ELSE 0 END)";
        }

        return 'SUM(CASE WHEN t.Tahun = ? THEN t.Nilai ELSE 0 END)';
    }

    private function latestYearSortBindings(string $tradeType, int $latestYear): array
    {
        if ($tradeType === 'Neraca') {
            return [$latestYear, $latestYear];
        }

        return [$latestYear];
    }
}
