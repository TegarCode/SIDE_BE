<?php

namespace App\Repositories\Indonesia\Infrastruktur;

use Illuminate\Support\Facades\DB;

class InfrastrukturRepository implements InfrastrukturRepositoryInterface
{
    protected string $conn = 'server_mysql';

    protected string $TB_Perwakilan      = 'tbpil';
    protected string $TB_PerwakilanAsing = 'tbpai';
    protected string $TB_COUNTRY         = 'tbnegara';

    /* ============================= API ============================= */

    public function perwakilan(array $filters = [], array $sources = [], int $ttl = 1800): array
    {
        $db = DB::connection($this->conn);

        // Normalisasi kategori di level SQL: UPPER(p.Kategori)
        $catFilter = $this->normalizeCatFilters($filters['categories'] ?? null);

        $base = $db->table($this->TB_Perwakilan . ' as p')
            ->leftJoin($this->TB_COUNTRY . ' as n', 'n.Kode_Alpha3', '=', 'p.Kode_Alpha3')
            ->whereRaw('TRIM(p.Perwakilan) <> ""');

        if (!empty($filters['wilayah']) && is_array($filters['wilayah'])) {
            $base->whereIn('n.ID_WIl_Kemlu', $filters['wilayah']);
        }

        if (!empty($catFilter)) {
            // filter langsung pakai label DB dalam bentuk uppercase
            $base->whereIn(DB::raw('UPPER(p.Kategori)'), $catFilter);
        }

        // Detail rows (untuk ITEMS & STAT CARDS) — semua baris sesuai filter
        $rows = (clone $base)
            ->select([
                'p.Perwakilan',
                'p.Kategori',
                'p.Alamat',
                'p.Koordinat',
                'p.Situs_Web',
                DB::raw('p.Kode_Alpha3 as alpha3'),
                DB::raw('n.Kode_Alpha2 as alpha2'),
                DB::raw('n.Negara_IDN as negara'),
                DB::raw('n.ID_WIl_Kemlu as wilayah'),
            ])
            ->get();

        if ($rows->isEmpty()) {
            return [
                'meta' => [
                    'count_records' => 0,
                    'count_items'   => 0,
                    'filters'       => [
                        'wilayah'    => $filters['wilayah'] ?? [],
                        'categories' => $catFilter,
                    ],
                ],
                'stat_cards' => [
                    'total'       => 0,
                    'by_kategori' => [],
                ],
                'items' => [],
            ];
        }

        /* ======================= STAT CARDS (HYBRID) ======================= */
        // Hybrid:
        // - KBRI, KJRI, ITPC, IIPC  => DISTINCT per nama perwakilan
        // - BUMN, PERBANKAN        => per baris (row-based)

        // Inisialisasi 6 kategori utama untuk kartu
        $agg = [
            'KBRI'      => 0, // KBRI + PTRI
            'KJRI'      => 0, // KJRI + KRI
            'ITPC'      => 0, // dari PERWAKILAN DAGANG (ITPC/KDEI)
            'IIPC'      => 0,
            'BUMN'      => 0,
            'PERBANKAN' => 0, // dari BI/BUMN PERBANKAN
        ];

        // Untuk kategori yang harus DISTINCT, simpan kombinasi (cardKey|nama_perwakilan)
        $seenDistinct = [];

        foreach ($rows as $r) {
            $name    = $this->clean($r->Perwakilan ?? null);
            $dbCat   = $this->toUpperOrNull($r->Kategori ?? null);
            $cardKey = $this->mapDbCategoryToCardKey($dbCat);

            if ($cardKey === null || !array_key_exists($cardKey, $agg)) {
                continue;
            }

            // BUMN dan PERBANKAN = hitung per baris apa adanya
            if (in_array($cardKey, ['BUMN', 'PERBANKAN'], true)) {
                $agg[$cardKey]++;
                continue;
            }

            // Lainnya (KBRI, KJRI, ITPC, IIPC) = DISTINCT per nama perwakilan
            if ($name === null) {
                continue;
            }

            $key = $cardKey . '|' . $name;

            if (!isset($seenDistinct[$key])) {
                $seenDistinct[$key] = true;
                $agg[$cardKey]++;
            }
        }

        // Bangun array by_kategori untuk frontend
        $byKategori = [];
        foreach ($agg as $code => $total) {
            if ($total <= 0) {
                continue;
            }

            $byKategori[] = [
                'code'  => $code,
                'label' => $this->categoryLabel($code),
                'count' => $total,
            ];
        }

        usort($byKategori, static function ($A, $B) {
            $c = strcasecmp($A['label'], $B['label']);
            return $c !== 0 ? $c : strcasecmp($A['code'], $B['code']);
        });

        // Total di stat_cards = jumlah semua kartu (hybrid)
        $totalAll = array_sum($agg);

        /* ======================= ITEMS (HYBRID) ======================= */
        // - KBRI, KJRI, ITPC, IIPC  => di-group per nama perwakilan (byName)
        // - BUMN, PERBANKAN        => per baris (langsung push ke $items)

        $byName = [];
        $items  = [];

        foreach ($rows as $r) {
            $name = $this->clean($r->Perwakilan ?? null);
            if ($name === null) {
                continue;
            }

            $dbCatUpper = $this->toUpperOrNull($r->Kategori ?? null);
            $cardKey    = $this->mapDbCategoryToCardKey($dbCatUpper);

            // Safety: kalau ga ke-map, kategorinya dianggap 'LAINNYA' dan tetap di-group per nama
            if ($cardKey === null) {
                $cardKey = 'LAINNYA';
            }

            $wilayah = $this->toUpperOrNull($r->wilayah ?? null);
            $alpha3  = $this->toUpperOrNull($r->alpha3 ?? null);
            $alpha2  = $this->toUpperOrNull($r->alpha2 ?? null);
            $negara  = $this->clean($r->negara ?? null);

            // === CASE 1: BUMN & PERBANKAN → PER ROW ===
            if (in_array($cardKey, ['BUMN', 'PERBANKAN'], true)) {
                $countries = [];

                if ($alpha3 !== null) {
                    $countries[] = [
                        'kode_alpha3' => $alpha3,
                        'kode_alpha2' => $alpha2,
                        'negara'      => $negara,
                        'wilayah'     => $wilayah,
                    ];
                }

                $items[] = [
                    'perwakilan' => $name,
                    'kategori'   => $cardKey, // cardKey: BUMN / PERBANKAN
                    'alamat'     => $this->clean($r->Alamat ?? null),
                    'koordinat'  => $this->clean($r->Koordinat ?? null),
                    'situs_web'  => $this->clean($r->Situs_Web ?? null),
                    'countries'  => $countries,
                    'wilayah'    => $wilayah,
                ];

                continue; // penting: jangan ikut ke-group di byName
            }

            // === CASE 2: LAINNYA (KBRI, KJRI, ITPC, IIPC, LAINNYA) → GROUP PER NAMA ===
            if (!isset($byName[$name])) {
                $byName[$name] = [
                    'perwakilan' => $name,
                    // kategori di items = key kartu (KBRI, KJRI, ITPC, IIPC, BUMN, PERBANKAN, LAINNYA)
                    'kategori'   => $cardKey,
                    'alamat'     => $this->clean($r->Alamat ?? null),
                    'koordinat'  => $this->clean($r->Koordinat ?? null),
                    'situs_web'  => $this->clean($r->Situs_Web ?? null),
                    'countries'  => [],
                    'wilayah'    => $wilayah,
                ];
            }

            if ($alpha3 !== null) {
                $byName[$name]['countries'][$alpha3] = [
                    'kode_alpha3' => $alpha3,
                    'kode_alpha2' => $alpha2,
                    'negara'      => $negara,
                    'wilayah'     => $wilayah,
                ];
            }
        }

        // Merge hasil grouping (KBRI, KJRI, ITPC, IIPC) ke $items
        foreach ($byName as $name => $row) {
            $row['countries'] = array_values($row['countries']);
            $items[]          = $row;
        }

        // Sort final items by nama perwakilan
        usort($items, static fn($a, $b) => strcasecmp($a['perwakilan'], $b['perwakilan']));

        return [
            'meta' => [
                'count_records' => $rows->count(),   // semua baris hasil filter (raw)
                'count_items'   => count($items),    // jumlah baris di tabel (hybrid)
                'filters'       => [
                    'wilayah'    => $filters['wilayah'] ?? [],
                    'categories' => $catFilter,
                ],
            ],
            'stat_cards' => [
                'total'       => (int) $totalAll,
                'by_kategori' => $byKategori,
            ],
            'items' => $items,
        ];
    }

    public function perwakilanAsing(array $filters = [], array $sources = [], int $ttl = 1800): array
    {
        $db = DB::connection($this->conn);

        $base = $db->table($this->TB_PerwakilanAsing . ' as p')
            ->leftJoin($this->TB_COUNTRY . ' as n', 'n.Kode_Alpha3', '=', 'p.Kode_Alpha3');

        if (!empty($filters['wilayah']) && is_array($filters['wilayah'])) {
            $base->whereIn('n.ID_WIl_Kemlu', $filters['wilayah']);
        }

        $rows = (clone $base)
            ->select([
                'p.Alamat',
                'p.Email',
                'p.Koordinat',
                DB::raw('p.Kode_Alpha3 as alpha3'),
                DB::raw('n.Kode_Alpha2 as alpha2'),
                DB::raw('n.Negara_IDN as negara'),
                DB::raw('n.ID_WIl_Kemlu as wilayah'),
            ])
            ->orderBy('n.Negara_IDN')
            ->get();

        if ($rows->isEmpty()) {
            return [
                'meta' => [
                    'count_records' => 0,
                    'count_items'   => 0,
                    'filters'       => [
                        'wilayah' => $filters['wilayah'] ?? [],
                    ],
                ],
                'items' => [],
            ];
        }

        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'alamat'      => $this->clean($r->Alamat ?? null),
                'email'       => $this->clean($r->Email ?? null),
                'koordinat'   => $this->clean($r->Koordinat ?? null),
                'kode_alpha3' => $this->clean($r->alpha3 ?? null),
                'kode_alpha2' => $this->clean($r->alpha2 ?? null),
                'negara'      => $this->clean($r->negara ?? null),
                'wilayah'     => $this->toUpperOrNull($r->wilayah ?? null),
            ];
        }

        return [
            'meta' => [
                'count_records' => $rows->count(),
                'count_items'   => count($items),
                'filters'       => [
                    'wilayah' => $filters['wilayah'] ?? [],
                ],
            ],
            'items' => $items,
        ];
    }

    /* ============================= Utils ============================= */

    protected function getDb()
    {
        return DB::connection($this->conn);
    }

    private function clean($v): ?string
    {
        if (!is_string($v)) {
            return null;
        }

        $s = trim($v);

        return $s === '' ? null : $s;
    }

    private function toUpperOrNull($v): ?string
    {
        if ($v === null) {
            return null;
        }

        $s = trim((string)$v);

        return $s === '' ? null : strtoupper($s);
    }

    private function normalizeCatFilters($cats): array
    {
        $arr = is_array($cats) ? $cats : [];
        $out = [];

        foreach ($arr as $c) {
            $code = strtoupper(trim((string)$c));
            if ($code === '') {
                continue;
            }
            $out[$code] = true;
        }

        $out = array_keys($out);
        sort($out, SORT_STRING);

        return $out;
    }

    private function mapDbCategoryToCardKey(?string $dbCat): ?string
    {
        if ($dbCat === null) {
            return null;
        }

        $k = strtoupper($dbCat);

        return match (true) {
            in_array($k, ['PTRI', 'KBRI'], true)   => 'KBRI',
            in_array($k, ['KJRI', 'KRI'], true)    => 'KJRI',
            $k === 'PERWAKILAN DAGANG'             => 'ITPC',
            $k === 'BI/BUMN PERBANKAN'             => 'PERBANKAN',
            $k === 'IIPC'                          => 'IIPC',
            $k === 'BUMN'                          => 'BUMN',
            default                                => null,
        };
    }

    /**
     * Label human-readable untuk stat-cards (match dengan React CATS).
     */
    private function categoryLabel(string $code): string
    {
        switch (strtoupper($code)) {
            case 'KBRI':
                return 'Perwakilan Diplomatik (KBRI/PTRI)';
            case 'KJRI':
                return 'Perwakilan Konsuler (KJRI/KRI)';
            case 'ITPC':
                return 'Perwakilan Dagang (ITPC/KDEI)';
            case 'PERBANKAN':
                return 'Perbankan & Keuangan';
            case 'IIPC':
                return 'IIPC';
            case 'BUMN':
                return 'BUMN';
            default:
                return strtoupper($code);
        }
    }
}
