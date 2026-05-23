<?php

namespace App\Http\Controllers\Api\V1\SektorJasa;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Support\SideCacheKey;
use App\Services\SektorJasa\InsightPasarPMIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class InsightPasarPMIController extends Controller
{
    private const DEFAULT_CACHE_PARTNERS = ['USA', 'CHN', 'JPN'];

    public function __construct(protected InsightPasarPMIService $service) {}

    public function overview(Request $request): JsonResponse
    {
        $filters = $this->normalizeFilters($request);
        $useCache = $this->shouldCache($filters);

        if ($useCache) {
            $ttl = now()->endOfDay();
            $keyNilai = $this->buildCacheKey('jasa-insight-pasar-pmi:nilai', $filters);
            $keyStats = $this->buildCacheKey('jasa-insight-pasar-pmi:stats', $filters);

            $nilai = Cache::remember($keyNilai, $ttl, fn () => $this->service->getNilaiJasa($filters));
            $stats = Cache::remember($keyStats, $ttl, fn () => $this->service->getStats($filters));
        } else {
            $nilai = $this->service->getNilaiJasa($filters);
            $stats = $this->service->getStats($filters);
        }

        if (empty($nilai) && empty($stats)) {
            return ApiResponse::success([], 'Tidak ada data.', ['filters' => $filters]);
        }

        [$nilaiPayload, $nilaiMeta] = $this->splitPayloadAndMeta($nilai);
        [$statsPayload, $statsMeta] = $this->splitPayloadAndMeta($stats);

        $payload = [
            'nilai_jasa' => $nilaiPayload,
            'stats'      => $statsPayload,
        ];

        return ApiResponse::success(
            $payload,
            'Data sektor jasa berhasil diambil',
            array_merge($nilaiMeta, $statsMeta)
        );
    }

    private function shouldCache(array $filters): bool
    {
        if (!empty($filters['partners_all'])) {
            return true;
        }

        $partners = $filters['partners'] ?? [];

        $want = self::DEFAULT_CACHE_PARTNERS;
        sort($want);

        $have = array_values(array_unique($partners));
        sort($have);

        return $have === $want;
    }

    private function normalizeFilters(Request $request): array
    {
        $ys = $request->input('year_start');
        $ye = $request->input('year_end');
        $yearStart = is_numeric($ys) ? (int) $ys : null;
        $yearEnd   = is_numeric($ye) ? (int) $ye : null;

        $rawPartners = $request->input('partners', []);
        $partnersAll = false;
        $partners    = [];

        if (is_string($rawPartners)) {
            $upper = strtoupper(trim($rawPartners));
            if ($upper === 'ALL') {
                $partnersAll = true;
            } else {
                $partners = $this->csvToUpperArray($rawPartners);
            }
        } else {
            $partners = $this->csvToUpperArray($rawPartners);
            if (in_array('ALL', $partners, true)) {
                $partnersAll = true;
                $partners = [];
            }
        }

        $filters = [
            'year_start'   => $yearStart,
            'year_end'     => $yearEnd,
            'partners'     => $partners,
            'partners_all' => $partnersAll ? true : null,
        ];

        return array_filter(
            $filters,
            fn ($v) => is_array($v)
                ? count($v) > 0
                : $v !== null && $v !== ''
        );
    }

    protected function splitPayloadAndMeta(?array $data): array
    {
        $data = is_array($data) ? $data : [];
        $meta = Arr::get($data, 'meta', []);
        $payload = $data;
        unset($payload['meta'], $meta['applied_filters']);
        return [$payload, $meta];
    }

    private function csvToUpperArray($val): array
    {
        $arr = is_string($val)
            ? array_map('trim', explode(',', $val))
            : (is_array($val) ? $val : []);

        return array_values(array_unique(array_filter(array_map(
            fn ($v) => strtoupper((string) $v),
            $arr
        ))));
    }

    private function buildCacheKey(string $prefix, array $filters): string
    {
        $normalized = $filters;
        foreach ($normalized as $k => $v) {
            if (is_array($v)) {
                $normalized[$k] = array_values($v);
                sort($normalized[$k]);
            }
        }
        ksort($normalized);
        $segments = array_values(array_filter(explode(':', $prefix)));
        return SideCacheKey::filters(
            array_merge(['sektor-jasa'], $segments),
            $normalized,
            ['year_start', 'year_end', 'partners', 'partners_all']
        );
    }
}
