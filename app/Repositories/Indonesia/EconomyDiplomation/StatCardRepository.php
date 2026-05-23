<?php

namespace App\Repositories\Indonesia\EconomyDiplomation;

use App\Support\SideCacheKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StatCardRepository implements StatCardRepositoryInterface
{
    /* ===================== Konfigurasi Koneksi & Tabel ===================== */
    protected string $conn = 'server_mysql';

    protected string $IDN = 'IDN';

    // Nama tabel dipisah agar mudah diganti
    protected string $TB_TRADE = 'tbtrade';

    protected string $TB_INVESTMENT = 'tbinvestment';

    protected string $TB_TOURISM = 'tbtourism';

    protected string $TB_AID = 'tbhibah';

    protected string $TB_COUNTRY = 'tbnegara';

    protected string $TB_SOURCE = 'tbsumber';

    protected string $TB_HS = 'tbharmonized';

    /* ======================== Kode Sumber Default =========================== */
    protected ?int $SRC_TRADE_TOTAL = 5;

    protected ?int $SRC_TRADE_BALANCE = 5;

    protected ?int $SRC_EXPORT = 5;

    protected ?int $SRC_IMPORT = 5;

    protected ?int $SRC_TOP_PARTNER = 5;

    protected ?int $SRC_TOURISM = 1;

    protected ?int $SRC_FDI_IN = 6;

    protected ?int $SRC_FDI_OUT = 16;

    protected ?int $SRC_AID = 21;

    /* ========================= Format / Unit per Tabel ====================== */
    protected array $FORMAT_BY_TABLE = [
        'tbtrade' => ['unit' => 'Ribu US$'],
        'tbinvestment' => ['unit' => 'Ribu US$'],
        'tbtourism' => ['unit' => 'Orang'],
        'tbhibah' => ['unit' => 'IDR Miliar'],
    ];

    /* ======================== Normalisasi & Cache Key ======================= */

    protected function normalizeFilters(array $filters): array
    {
        $norm = [];

        // Tahun
        $ys = $filters['year_start'] ?? null;
        $ye = $filters['year_end'] ?? null;
        $norm['year_start'] = is_numeric($ys) ? (int) $ys : null;
        $norm['year_end'] = is_numeric($ye) ? (int) $ye : null;

        // HS length (bisa 'HS4' / 'HS-4' / '4')
        $hs = $filters['hs'] ?? null;
        if (is_string($hs)) {
            $digits = preg_replace('/\D+/', '', $hs);
            $hs = ($digits === '' ? null : (int) $digits);
        } elseif (is_numeric($hs)) {
            $hs = (int) $hs;
        } else {
            $hs = null;
        }
        $norm['hs'] = $hs;

        // Dirjen array / csv → array unik uppercase
        $dirjen = $filters['dirjen'] ?? [];
        if (is_string($dirjen)) {
            $dirjen = array_map('trim', explode(',', $dirjen));
        }
        if (is_array($dirjen)) {
            $dirjen = array_values(array_unique(array_filter(array_map(fn ($v) => strtoupper((string) $v), $dirjen))));
        } else {
            $dirjen = [];
        }
        $norm['dirjen'] = $dirjen;

        // Hapus null/empty
        return array_filter($norm, function ($v) {
            if (is_array($v)) {
                return count($v) > 0;
            }

            return ! is_null($v) && $v !== '';
        });
    }

    protected function normalizeSources(array $sources): array
    {
        $out = [];
        foreach (
            [
                'trade_total',
                'trade_balance',
                'export',
                'import',
                'top_partner',
                'tourism',
                'fdi_in',
                'fdi_out',
                'aid',
            ] as $k
        ) {
            if (array_key_exists($k, $sources) && $sources[$k] !== null && $sources[$k] !== '') {
                $out[$k] = (int) $sources[$k];
            }
        }

        return $out;
    }

    protected function applySourceOverrides(array $sources): void
    {
        if (isset($sources['trade_total'])) {
            $this->SRC_TRADE_TOTAL = (int) $sources['trade_total'];
        }
        if (isset($sources['trade_balance'])) {
            $this->SRC_TRADE_BALANCE = (int) $sources['trade_balance'];
        }
        if (isset($sources['export'])) {
            $this->SRC_EXPORT = (int) $sources['export'];
        }
        if (isset($sources['import'])) {
            $this->SRC_IMPORT = (int) $sources['import'];
        }
        if (isset($sources['top_partner'])) {
            $this->SRC_TOP_PARTNER = (int) $sources['top_partner'];
        }
        if (isset($sources['tourism'])) {
            $this->SRC_TOURISM = (int) $sources['tourism'];
        }
        if (isset($sources['fdi_in'])) {
            $this->SRC_FDI_IN = (int) $sources['fdi_in'];
        }
        if (isset($sources['fdi_out'])) {
            $this->SRC_FDI_OUT = (int) $sources['fdi_out'];
        }
        if (isset($sources['aid'])) {
            $this->SRC_AID = (int) $sources['aid'];
        }
    }

    /* ================================= Entry ================================ */
    public function computeStats(array $filters = [], array $sources = [], int $ttl = 1800): array
    {
        // 1) Normalisasi
        $filters = $this->normalizeFilters($filters);
        $sources = $this->normalizeSources($sources);
        $this->applySourceOverrides($sources);

        $db = DB::connection($this->conn);

        /* ============================= TRADE ============================= */
        [$tradeYear, $tradePrev] = $this->latestTwoYearsTrade($db, $filters);
        $tradeMonthWindow = null;
        $tradeCodes = $this->collectCodesUsed([
            $this->SRC_TRADE_TOTAL,
            $this->SRC_TRADE_BALANCE,
            $this->SRC_EXPORT,
            $this->SRC_IMPORT,
            $this->SRC_TOP_PARTNER,
        ]);
        if (in_array(1, $tradeCodes, true) && $tradeYear) {
            $tradeMonthWindow = $this->resolveTradeMonthWindowForSourceOne($db, (int) $tradeYear, $filters);
        }

        $tradeAgg = $this->tradeAggregateIndonesia(
            $db,
            [$tradeYear, $tradePrev],
            $this->SRC_TRADE_TOTAL,
            $this->SRC_EXPORT,
            $this->SRC_IMPORT,
            $filters
        );

        $totalNow = $tradeAgg['total'][$tradeYear] ?? null;
        $totalPrev = $tradeAgg['total'][$tradePrev] ?? null;
        $expNow = $tradeAgg['export'][$tradeYear] ?? null;
        $expPrev = $tradeAgg['export'][$tradePrev] ?? null;
        $impNow = $tradeAgg['import'][$tradeYear] ?? null;
        $impPrev = $tradeAgg['import'][$tradePrev] ?? null;

        if ($this->SRC_TRADE_BALANCE !== null && $this->SRC_TRADE_BALANCE !== $this->SRC_EXPORT) {
            $balAgg = $this->tradeAggregateIndonesia(
                $db,
                [$tradeYear, $tradePrev],
                null,
                $this->SRC_TRADE_BALANCE,
                $this->SRC_TRADE_BALANCE,
                $filters
            );
            $balNow = ($expNow === null && $impNow === null) ? null : (($expNow ?? 0) - ($impNow ?? 0));
            $balPrev = ($expPrev === null && $impPrev === null) ? null : (($expPrev ?? 0) - ($impPrev ?? 0));
        } else {
            // kalau export & import keduanya null → neraca juga null (bukan 0)
            if ($expNow === null && $impNow === null) {
                $balNow = null;
            } else {
                $balNow = ($expNow ?? 0) - ($impNow ?? 0);
            }

            if ($expPrev === null && $impPrev === null) {
                $balPrev = null;
            } else {
                $balPrev = ($expPrev ?? 0) - ($impPrev ?? 0);
            }
        }

        $partnerAggNow = $this->tradePartnerAggForYear($db, $tradeYear, $this->SRC_EXPORT, $this->SRC_IMPORT, $filters);
        $partnerAggPrev = $this->tradePartnerAggForYear($db, $tradePrev, $this->SRC_EXPORT, $this->SRC_IMPORT, $filters);

        $topPartnerNow = $this->pickTopPartner($db, $partnerAggNow, 'total');
        $topPartnerPrev = $this->pickTopPartner($db, $partnerAggPrev, 'total');
        $topExportDestNow = $this->pickTopPartner($db, $partnerAggNow, 'export');
        $topExportDestPrev = $this->pickTopPartner($db, $partnerAggPrev, 'export');
        $topImportOrigNow = $this->pickTopPartner($db, $partnerAggNow, 'import');
        $topImportOrigPrev = $this->pickTopPartner($db, $partnerAggPrev, 'import');
        [$topExpProdHSNow,  $topImpProdHSNow] = $this->topProducts($db, $tradeYear, $this->SRC_EXPORT, $this->SRC_IMPORT, $filters);
        [$topExpProdHSPrev, $topImpProdHSPrev] = $this->topProducts($db, $tradePrev, $this->SRC_EXPORT, $this->SRC_IMPORT, $filters);
        $topSurplusNow = $this->pickTopPartner($db, $partnerAggNow, 'surplus');
        $topSurplusPrev = $this->pickTopPartner($db, $partnerAggPrev, 'surplus');

        /* ============================ TOURISM =========================== */
        [$tourYear, $tourPrev] = $this->latestTwoYearsTourismInbound($db, $filters);
        $tourNow = $this->tourismInboundTotal($db, $tourYear, $this->SRC_TOURISM, $filters);
        $tourPrevValue = $this->tourismInboundTotal($db, $tourPrev, $this->SRC_TOURISM, $filters);
        $tourTopOriginNow = $this->tourismInboundTopOrigin($db, $tourYear, $this->SRC_TOURISM, $filters);
        $tourTopOriginPrev = $this->tourismInboundTopOrigin($db, $tourPrev, $this->SRC_TOURISM, $filters);

        /* ============================== FDI ============================= */
        [$invYear, $invPrev] = $this->latestTwoYearsInvestment($db, $filters);
        $fdiInNow = $this->fdiInboundTotal($db, $invYear, $this->SRC_FDI_IN, $filters);
        $fdiInPrev = $this->fdiInboundTotal($db, $invPrev, $this->SRC_FDI_IN, $filters);
        $fdiInTopNow = $this->fdiInboundTopOrigin($db, $invYear, $this->SRC_FDI_IN, $filters);
        $fdiInTopPrev = $this->fdiInboundTopOrigin($db, $invPrev, $this->SRC_FDI_IN, $filters);
        $fdiOutNow = $this->fdiOutboundTotal($db, $invYear, $this->SRC_FDI_OUT, $filters);
        $fdiOutPrev = $this->fdiOutboundTotal($db, $invPrev, $this->SRC_FDI_OUT, $filters);
        $fdiOutTopNow = $this->fdiOutboundTopDest($db, $invYear, $this->SRC_FDI_OUT, $filters);
        $fdiOutTopPrev = $this->fdiOutboundTopDest($db, $invPrev, $this->SRC_FDI_OUT, $filters);

        /* ============================== AID ============================= */
        [$aidYear, $aidPrev] = $this->latestTwoYearsAid($db, $filters);
        $aidNow = $this->aidTotalIdr($db, $aidYear, $this->SRC_AID, $filters);
        $aidPrevV = $this->aidTotalIdr($db, $aidPrev, $this->SRC_AID, $filters);
        $aidTopNow = $this->aidTopDest($db, $aidYear, $this->SRC_AID, $filters);
        $aidTopPrev = $this->aidTopDest($db, $aidPrev, $this->SRC_AID, $filters);

        /* ===================== Nama Sumber Sekali ===================== */
        $codesUsed = $this->collectCodesUsed([
            $this->SRC_TRADE_TOTAL,
            $this->SRC_TRADE_BALANCE,
            $this->SRC_EXPORT,
            $this->SRC_IMPORT,
            $this->SRC_TOP_PARTNER,
            $this->SRC_TOURISM,
            $this->SRC_FDI_IN,
            $this->SRC_FDI_OUT,
            $this->SRC_AID,
        ]);
        $sourceNameMap = $this->fetchSourceNameMap($this->getDb(), $codesUsed);

        /* =========================== Build Cards ======================== */
        $cards = [
            'trade_total' => [
                'value' => $totalNow,
                'prevValue' => $totalPrev,
                'note' => "Nilai Perdagangan Internasional Indonesia ($tradeYear)",
                'year' => $tradeYear,
                'prevYear' => $tradePrev,
                'source' => $this->buildSourceMeta($this->TB_TRADE, $this->SRC_TRADE_TOTAL, $sourceNameMap),
                'format' => $this->cardFormat($this->TB_TRADE),
            ],
            'top_partner' => [
                'value' => $topPartnerNow['totalUSD'] ?? null,
                'prevValue' => $topPartnerPrev['totalUSD'] ?? null,
                'country' => $topPartnerNow['name'] ?? '-',
                'prevCountry' => $topPartnerPrev['name'] ?? '-',
                'note' => "Mitra Dagang Utama Indonesia ($tradeYear)",
                'year' => $tradeYear,
                'prevYear' => $tradePrev,
                'source' => $this->buildSourceMeta($this->TB_TRADE, $this->SRC_TOP_PARTNER ?? $this->SRC_TRADE_TOTAL, $sourceNameMap),
                'format' => $this->cardFormat($this->TB_TRADE),
            ],
            'trade_balance' => [
                'value' => $balNow,
                'prevValue' => $balPrev,
                'note' => "Neraca Perdagangan Internasional Indonesia ($tradeYear)",
                'year' => $tradeYear,
                'prevYear' => $tradePrev,
                'source' => $this->buildSourceMeta($this->TB_TRADE, $this->SRC_TRADE_BALANCE ?? $this->SRC_TRADE_TOTAL, $sourceNameMap),
                'format' => $this->cardFormat($this->TB_TRADE),
            ],
            'top_surplus_country' => [
                'value' => $topSurplusNow['surplus'] ?? null,
                'prevValue' => $topSurplusPrev['surplus'] ?? null,
                'country' => $topSurplusNow['name'] ?? '-',
                'prevCountry' => $topSurplusPrev['name'] ?? '-',
                'note' => "Surplus Perdagangan Terbesar Indonesia ($tradeYear)",
                'year' => $tradeYear,
                'prevYear' => $tradePrev,
                'source' => $this->buildSourceMeta($this->TB_TRADE, [$this->SRC_EXPORT, $this->SRC_IMPORT], $sourceNameMap),
                'format' => $this->cardFormat($this->TB_TRADE),
            ],

            // Green (Export)
            'export_total' => [
                'value' => $expNow,
                'prevValue' => $expPrev,
                'note' => "Jumlah Ekspor Indonesia ($tradeYear)",
                'year' => $tradeYear,
                'prevYear' => $tradePrev,
                'source' => $this->buildSourceMeta($this->TB_TRADE, $this->SRC_EXPORT, $sourceNameMap),
                'format' => $this->cardFormat($this->TB_TRADE),
            ],
            'top_export_dest' => [
                'value' => $topExportDestNow['totalUSD'] ?? null,
                'prevValue' => $topExportDestPrev['totalUSD'] ?? null,
                'country' => $topExportDestNow['name'] ?? '-',
                'prevCountry' => $topExportDestPrev['name'] ?? '-',
                'note' => "Negara/Entitas Tujuan Ekspor Terbesar Indonesia ($tradeYear)",
                'year' => $tradeYear,
                'prevYear' => $tradePrev,
                'source' => $this->buildSourceMeta($this->TB_TRADE, $this->SRC_EXPORT, $sourceNameMap),
                'format' => $this->cardFormat($this->TB_TRADE),
            ],

            // Orange (Import)
            'import_total' => [
                'value' => $impNow,
                'prevValue' => $impPrev,
                'note' => "Jumlah Impor Indonesia ($tradeYear)",
                'year' => $tradeYear,
                'prevYear' => $tradePrev,
                'source' => $this->buildSourceMeta($this->TB_TRADE, $this->SRC_IMPORT, $sourceNameMap),
                'format' => $this->cardFormat($this->TB_TRADE),
            ],
            'top_import_origin' => [
                'value' => $topImportOrigNow['totalUSD'] ?? null,
                'prevValue' => $topImportOrigPrev['totalUSD'] ?? null,
                'country' => $topImportOrigNow['name'] ?? '-',
                'prevCountry' => $topImportOrigPrev['name'] ?? '-',
                'note' => "Negara/Entitas Asal Impor Terbesar Indonesia ($tradeYear)",
                'year' => $tradeYear,
                'prevYear' => $tradePrev,
                'source' => $this->buildSourceMeta($this->TB_TRADE, $this->SRC_IMPORT, $sourceNameMap),
                'format' => $this->cardFormat($this->TB_TRADE),
            ],
            'top_export_product' => [
                'value' => $topExpProdHSNow['usd'] ?? null,
                'prevValue' => $topExpProdHSPrev['usd'] ?? null,
                'product' => $topExpProdHSNow['label'] ?? '-',
                'prevProduct' => $topExpProdHSPrev['label'] ?? '-',
                'productHs' => $topExpProdHSNow['hs'] ?? null,
                'productDesc' => $topExpProdHSNow['description'] ?? null,
                'prevProductHs' => $topExpProdHSPrev['hs'] ?? null,
                'prevProductDesc' => $topExpProdHSPrev['description'] ?? null,
                'note' => "Produk Ekspor Utama Indonesia ($tradeYear)",
                'year' => $tradeYear,
                'prevYear' => $tradePrev,
                'source' => $this->buildSourceMeta($this->TB_TRADE, $this->SRC_EXPORT, $sourceNameMap),
                'format' => $this->cardFormat($this->TB_TRADE),
            ],
            'top_import_product' => [
                'value' => $topImpProdHSNow['usd'] ?? null,
                'prevValue' => $topImpProdHSPrev['usd'] ?? null,
                'product' => $topImpProdHSNow['label'] ?? '-',
                'prevProduct' => $topImpProdHSPrev['label'] ?? '-',
                'productHs' => $topImpProdHSNow['hs'] ?? null,
                'productDesc' => $topImpProdHSNow['description'] ?? null,
                'prevProductHs' => $topImpProdHSPrev['hs'] ?? null,
                'prevProductDesc' => $topImpProdHSPrev['description'] ?? null,
                'note' => "Produk Impor Utama Indonesia ($tradeYear)",
                'year' => $tradeYear,
                'prevYear' => $tradePrev,
                'source' => $this->buildSourceMeta($this->TB_TRADE, $this->SRC_IMPORT, $sourceNameMap),
                'format' => $this->cardFormat($this->TB_TRADE),
            ],

            // Tourism
            'tourist_inbound' => [
                // kalau tidak ada data sama sekali → null (frontend jadi "–")
                'value' => $tourNow === null ? null : (int) $tourNow,
                'prevValue' => $tourPrevValue === null ? null : (int) $tourPrevValue,
                'note' => "Jumlah Kunjungan Wisman ke Indonesia ($tourYear)",
                'year' => $tourYear,
                'prevYear' => $tourPrev,
                'source' => $this->buildSourceMeta($this->TB_TOURISM, $this->SRC_TOURISM, $sourceNameMap),
                'format' => $this->cardFormat($this->TB_TOURISM),
            ],
            'tourist_inbound_top_origin' => [
                'value' => isset($tourTopOriginNow['visits']) ? (int) $tourTopOriginNow['visits'] : null,
                'prevValue' => isset($tourTopOriginPrev['visits']) ? (int) $tourTopOriginPrev['visits'] : null,
                'country' => $tourTopOriginNow['name'] ?? '-',
                'prevCountry' => $tourTopOriginPrev['name'] ?? '-',
                'note' => "Asal Kunjungan Wisman Utama Indonesia ($tourYear)",
                'year' => $tourYear,
                'prevYear' => $tourPrev,
                'source' => $this->buildSourceMeta($this->TB_TOURISM, $this->SRC_TOURISM, $sourceNameMap),
                'format' => $this->cardFormat($this->TB_TOURISM),
            ],

            // FDI
            'fdi_in_total' => [
                'value' => $fdiInNow,
                'prevValue' => $fdiInPrev,
                'note' => "Total Inbound Investment Indonesia ($invYear)",
                'year' => $invYear,
                'prevYear' => $invPrev,
                'source' => $this->buildSourceMeta($this->TB_INVESTMENT, $this->SRC_FDI_IN, $sourceNameMap),
                'format' => $this->cardFormat($this->TB_INVESTMENT),
            ],
            'fdi_in_top_origin' => [
                'value' => $fdiInTopNow['usd'] ?? null,
                'prevValue' => $fdiInTopPrev['usd'] ?? null,
                'country' => $fdiInTopNow['name'] ?? '-',
                'prevCountry' => $fdiInTopPrev['name'] ?? '-',
                'note' => "Asal Inbound Investment Utama Indonesia ($invYear)",
                'year' => $invYear,
                'prevYear' => $invPrev,
                'source' => $this->buildSourceMeta($this->TB_INVESTMENT, $this->SRC_FDI_IN, $sourceNameMap),
                'format' => $this->cardFormat($this->TB_INVESTMENT),
            ],
            // 'fdi_out_total' => [
            //     'value' => $fdiOutNow,
            //     'prevValue' => $fdiOutPrev,
            //     'note' => "Total Outbound Investment Indonesia ($invYear)",
            //     'year' => $invYear,
            //     'prevYear' => $invPrev,
            //     'source' => $this->buildSourceMeta($this->TB_INVESTMENT, $this->SRC_FDI_OUT, $sourceNameMap),
            //     'format' => $this->cardFormat($this->TB_INVESTMENT),
            // ],
            // 'fdi_out_top_dest' => [
            //     'value' => $fdiOutTopNow['usd'] ?? null,
            //     'prevValue' => $fdiOutTopPrev['usd'] ?? null,
            //     'country' => $fdiOutTopNow['name'] ?? '-',
            //     'prevCountry' => $fdiOutTopPrev['name'] ?? '-',
            //     'note' => "Tujuan Outbound Investment Indonesia ($invYear)",
            //     'year' => $invYear,
            //     'prevYear' => $invPrev,
            //     'source' => $this->buildSourceMeta($this->TB_INVESTMENT, $this->SRC_FDI_OUT, $sourceNameMap),
            //     'format' => $this->cardFormat($this->TB_INVESTMENT),
            // ],

            // Aid
            'aid_total' => [
                'value' => $aidNow,
                'prevValue' => $aidPrevV,
                'note' => "Nilai Bantuan Kerjasama Pembangunan Internasional Indonesia ($aidYear)",
                'year' => $aidYear,
                'prevYear' => $aidPrev,
                'source' => $this->buildSourceMeta($this->TB_AID, $this->SRC_AID, $sourceNameMap),
                'format' => $this->cardFormat($this->TB_AID),
            ],
            'aid_top_dest' => [
                'value' => $aidTopNow['idr'] ?? null,
                'prevValue' => $aidTopPrev['idr'] ?? null,
                'country' => $aidTopNow['name'] ?? '-',
                'prevCountry' => $aidTopPrev['name'] ?? '-',
                'note' => "Tujuan Bantuan Kerjasama Pembangunan Internasional Utama Indonesia ($aidYear)",
                'year' => $aidYear,
                'prevYear' => $aidPrev,
                'source' => $this->buildSourceMeta($this->TB_AID, $this->SRC_AID, $sourceNameMap),
                'format' => $this->cardFormat($this->TB_AID),
            ],
        ];

        $tradeLatestYearMonthCoverage = null;
        if ($tradeMonthWindow) {
            $monthMap = [
                1 => 'Jan',
                2 => 'Feb',
                3 => 'Mar',
                4 => 'Apr',
                5 => 'May',
                6 => 'Jun',
                7 => 'Jul',
                8 => 'Aug',
                9 => 'Sep',
                10 => 'Oct',
                11 => 'Nov',
                12 => 'Dec',
            ];
            $mStart = $tradeMonthWindow['start'] ?? null;
            $mEnd = $tradeMonthWindow['end'] ?? null;
            if ((int) $mStart !== 1 || (int) $mEnd !== 12) {
                $label = null;
                if (isset($monthMap[$mStart], $monthMap[$mEnd])) {
                    $label = $monthMap[$mStart].'-'.$monthMap[$mEnd];
                }
                $tradeLatestYearMonthCoverage = [
                    'year' => $tradeYear,
                    'label' => $label,
                ];
            }
        }

        return [
            'meta' => [
                'tradeYears' => [$tradePrev, $tradeYear],
                'tradeLatestYearMonthCoverage' => $tradeLatestYearMonthCoverage,
                'tourismYears' => [$tourPrev, $tourYear],
                'fdiYears' => [$invPrev, $invYear],
                'aidYears' => [$aidPrev, $aidYear],
            ],
            'cards' => $cards,
        ];
    }

    protected function getDb()
    {
        return DB::connection($this->conn);
    }

    /* ========================= Helper: Source & Format ====================== */
    protected function collectCodesUsed(array $maybeCodes): array
    {
        $out = [];
        foreach ($maybeCodes as $c) {
            if (is_int($c)) {
                $out[] = $c;
            } elseif (is_array($c)) {
                foreach ($c as $x) {
                    if (is_int($x)) {
                        $out[] = $x;
                    }
                }
            }
        }
        $out = array_values(array_unique($out));
        sort($out);

        return $out;
    }

    protected function fetchSourceNameMap($db, array $codes): array
    {
        if (empty($codes)) {
            return [];
        }
        try {
            return $db->table($this->TB_SOURCE)
                ->whereIn('KodeSumber', $codes)
                ->pluck('NamaSumber', 'KodeSumber')
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function buildSourceMeta(string $table, $codes, array $sourceNameMap = []): array
    {
        if (is_null($codes)) {
            return ['table' => $table, 'name' => null];
        }
        if (is_int($codes)) {
            $name = $sourceNameMap[$codes] ?? (string) $codes;

            return ['table' => $table, 'name' => $name];
        }
        if (is_array($codes)) {
            $norm = array_values(array_unique(array_map('intval', array_filter($codes, fn ($v) => ! is_null($v)))));
            if (empty($norm)) {
                return ['table' => $table, 'name' => null];
            }
            $names = array_map(function ($c) use ($sourceNameMap) {
                return $sourceNameMap[$c] ?? (string) $c;
            }, $norm);

            return ['table' => $table, 'name' => implode(' / ', $names)];
        }

        return ['table' => $table, 'name' => null];
    }

    protected function cardFormat(string $table): array
    {
        return $this->FORMAT_BY_TABLE[$table] ?? ['unit' => ''];
    }

    /* ============================== Filters =============================== */
    protected function applyYearSpan($q, array $filters, string $col = 'Tahun'): void
    {
        $start = $filters['year_start'] ?? null;
        $end = $filters['year_end'] ?? null;

        if ($start !== null && $end !== null) {
            if ($start > $end) {
                [$start, $end] = [(int) $end, (int) $start];
            }
            $q->whereBetween($col, [(int) $start, (int) $end]);
        } elseif ($start !== null) {
            $q->where($col, '>=', (int) $start);
        } elseif ($end !== null) {
            $q->where($col, '<=', (int) $end);
        }
    }

    protected function applyHsFilter($q, array $filters, string $col = 'HsCode', string $aliasPrefix = ''): void
    {
        $qualified = "{$aliasPrefix}{$col}";

        // Parse hs (bisa "HS4", "hs-6", "4", atau angka)
        $hsLen = null;
        if (isset($filters['hs'])) {
            if (is_string($filters['hs'])) {
                $d = preg_replace('/\D+/', '', $filters['hs']);
                $hsLen = ($d === '') ? null : (int) $d;
            } elseif (is_numeric($filters['hs'])) {
                $hsLen = (int) $filters['hs'];
            }
        }

        // Parse prefix (hanya digit)
        $prefix = null;
        if (! empty($filters['hscode'])) {
            $digits = preg_replace('/\D+/', '', (string) $filters['hscode']);
            $prefix = ($digits === '') ? null : $digits;
        }

        // Jika ada prefix tapi hsLen kosong → pakai panjang prefix
        if ($prefix !== null && $hsLen === null) {
            $hsLen = strlen($prefix);
        }

        // Jika keduanya ada dan panjang prefix != hsLen → potong prefix ke hsLen
        if ($prefix !== null && $hsLen !== null && strlen($prefix) !== $hsLen) {
            $prefix = substr($prefix, 0, $hsLen);
        }

        // Terapkan filter
        if ($hsLen !== null && $hsLen > 0) {
            $lenCol = "{$aliasPrefix}hs_len";
            $leftExpr = "LEFT($qualified, ?)";

            if ($prefix !== null && $prefix !== '') {
                // Panjang persis & prefix match
                $q->where($lenCol, $hsLen)
                    ->whereRaw("$leftExpr = ?", [$hsLen, $prefix]);
            } else {
                // Hanya panjang persis
                $q->where($lenCol, $hsLen);
            }
        } elseif ($prefix !== null && $prefix !== '') {
            // Tidak ada hsLen, tapi ada prefix → pakai panjang prefix untuk LEFT()
            $n = strlen($prefix);
            $q->whereRaw("LEFT($qualified, ?) = ?", [$n, $prefix]);
        }
    }

    protected function hsAggExpr(array $filters, string $aliasPrefix = 't.'): string
    {
        $base = "{$aliasPrefix}HsCode";
        if (! empty($filters['hscode'])) {
            $n = strlen((string) $filters['hscode']);

            return "LEFT($base, {$n})";
        }

        // Jika hanya level HS (hs_len) tanpa prefix, gunakan kolom langsung
        if (isset($filters['hs']) && is_numeric($filters['hs'])) {
            return $base;
        }

        return "COALESCE($base)";
    }

    protected function applyDirjenFilter($q, array $filters, string $table, string $aliasPrefix = '', ?string $mode = null): void
    {
        if (empty($filters['dirjen']) || ! is_array($filters['dirjen'])) {
            return;
        }
        $vals = array_values(array_unique(array_map('strtoupper', $filters['dirjen'])));

        $nAlias = 'n';
        $country = $this->TB_COUNTRY.' as '.$nAlias;

        switch ($table) {
            case $this->TB_TRADE:
                $codeCol = "{$aliasPrefix}Kode_Alpha3_Partner";
                $q->join($this->TB_COUNTRY.' as '.$nAlias, "{$nAlias}.Kode_Alpha3", '=', DB::raw($codeCol))
                    ->whereIn("{$nAlias}.ID_Wil_Kemlu", $vals);
                break;

            case $this->TB_TOURISM:
                $codeCol = "{$aliasPrefix}Kode_Alpha3_Asal";
                $q->join($country, $codeCol, '=', "{$nAlias}.Kode_Alpha3")
                    ->whereIn("{$nAlias}.ID_Wil_Kemlu", $vals);
                break;

            case $this->TB_AID:
                $codeCol = "{$aliasPrefix}Kode_Alpha3";
                $q->join($country, $codeCol, '=', "{$nAlias}.Kode_Alpha3")
                    ->whereIn("{$nAlias}.ID_Wil_Kemlu", $vals);
                break;

            case $this->TB_INVESTMENT:
                if ($mode === 'inbound') {
                    $codeCol = "{$aliasPrefix}Kode_Alpha3_Asal";
                    $q->join($country, $codeCol, '=', "{$nAlias}.Kode_Alpha3")
                        ->whereIn("{$nAlias}.ID_Wil_Kemlu", $vals);
                } elseif ($mode === 'outbound') {
                    $codeCol = "{$aliasPrefix}Kode_Alpha3_Tujuan";
                    $q->join($country, $codeCol, '=', "{$nAlias}.Kode_Alpha3")
                        ->whereIn("{$nAlias}.ID_Wil_Kemlu", $vals);
                }
                break;
        }
    }

    protected function tradeTableWithIndex($db, string $index, string $alias = '')
    {
        $table = $this->TB_TRADE;
        $suffix = $alias !== '' ? " as {$alias}" : '';
        return $db->table(DB::raw("{$table}{$suffix} FORCE INDEX ({$index})"));
    }

    /* =========================== TRADE =========================== */
    protected function latestTwoYearsTrade($db, array $filters = []): array
    {
        return $this->resolveTwoYearsExact($filters, function () use ($db, $filters) {
            $codes = $this->collectCodesUsed([
                $this->SRC_TRADE_TOTAL,
                $this->SRC_TRADE_BALANCE,
                $this->SRC_EXPORT,
                $this->SRC_IMPORT,
                $this->SRC_TOP_PARTNER,
            ]);

            $y = $this->pickLatestYearWithData(
                $db,
                $this->TB_TRADE,
                function ($q) use ($codes, $filters) {
                    $q->where('Kode_Alpha3_Reporter', $this->IDN)
                        ->whereIn('Status', ['Export', 'Import']);
                    if (! empty($codes)) {
                        $q->whereIn('Kode_Sumber', $codes);
                    }
                    $this->applyHsFilter($q, $filters, 'HsCode');
                    $this->applyDirjenFilter($q, $filters, $this->TB_TRADE);
                },
                $filters,
                'Nilai'
            );

            if (! $y) {
                $y = (int) date('Y');
            }

            return [(int) $y, (int) $y - 1];
        });
    }

    protected function tradeAggregateIndonesia($db, array $years, ?int $srcTotal, ?int $srcExport, ?int $srcImport, array $filters = [], ?array $months = null): array
    {
        // siapkan hasil dengan null (bukan 0)
        $res = [
            'total' => [(int) $years[0] => null, (int) $years[1] => null],
            'export' => [(int) $years[0] => null, (int) $years[1] => null],
            'import' => [(int) $years[0] => null, (int) $years[1] => null],
        ];

        try {
            // agregat per sumber (bila diminta)
            $needSrc = array_values(array_unique(array_filter([$srcTotal, $srcExport, $srcImport], fn ($v) => ! is_null($v))));
            $bySrc = [];
            if (! empty($needSrc)) {
                $rowsBySrcQ = $this->tradeTableWithIndex($db, 'idx_trade_filter_partner')
                    ->selectRaw("
          Tahun, Kode_Sumber,
          SUM(CASE WHEN Status='Export' THEN Nilai ELSE 0 END) AS exp_sum,
          SUM(CASE WHEN Status='Import' THEN Nilai ELSE 0 END) AS imp_sum,
          COUNT(*) AS cnt
        ")
                    ->where('Kode_Alpha3_Reporter', $this->IDN)
                    ->whereIn('Kode_Sumber', $needSrc)
                    ->whereIn('Tahun', $years)
                    ->whereIn('Status', ['Export', 'Import']);

                $this->applyHsFilter($rowsBySrcQ, $filters, 'HsCode');
                $this->applyTradeMonthsFilter($rowsBySrcQ, $months);
                $this->applyDirjenFilter($rowsBySrcQ, $filters, $this->TB_TRADE);

                $rowsBySrc = $rowsBySrcQ->groupBy('Tahun', 'Kode_Sumber')->get();
                foreach ($rowsBySrc as $r) {
                    if ((int) $r->cnt > 0) {
                        $code = (int) $r->Kode_Sumber;
                        $y = (int) $r->Tahun;
                        $bySrc[$code][$y] = ['exp' => (float) $r->exp_sum, 'imp' => (float) $r->imp_sum];
                    }
                }
            }

            // agregat semua sumber (hanya jika tidak ada sumber spesifik)
            $mapAll = [];
            if (empty($needSrc)) {
                $rowsAllQ = $this->tradeTableWithIndex($db, 'idx_trade_filter_partner')
                    ->selectRaw("
        Tahun,
        SUM(CASE WHEN Status='Export' THEN Nilai ELSE 0 END) AS exp_sum,
        SUM(CASE WHEN Status='Import' THEN Nilai ELSE 0 END) AS imp_sum,
        COUNT(*) AS cnt
      ")
                    ->where('Kode_Alpha3_Reporter', $this->IDN)
                    ->whereIn('Tahun', $years)
                    ->whereIn('Status', ['Export', 'Import']);

                $this->applyHsFilter($rowsAllQ, $filters, 'HsCode');
                $this->applyTradeMonthsFilter($rowsAllQ, $months);
                $this->applyDirjenFilter($rowsAllQ, $filters, $this->TB_TRADE);

                $rowsAll = $rowsAllQ->groupBy('Tahun')->get();
                foreach ($rowsAll as $r) {
                    $y = (int) $r->Tahun;
                    $has = ((int) $r->cnt) > 0;
                    if ($has) {
                        $mapAll[$y] = ['exp' => (float) $r->exp_sum, 'imp' => (float) $r->imp_sum];
                    }
                }
            }

            $pick = function (?int $code, int $y, string $kind) use ($mapAll, $bySrc, $needSrc) {
                if (! is_null($code) && isset($bySrc[$code][$y])) {
                    return $bySrc[$code][$y][$kind] ?? null;
                }

                if (empty($needSrc)) {
                    return $mapAll[$y][$kind] ?? null;
                }

                return null;
            };

            foreach ($years as $yy) {
                $y = (int) $yy;

                $exp = $pick($srcExport, $y, 'exp');
                $imp = $pick($srcImport, $y, 'imp');

                $res['export'][$y] = $exp;
                $res['import'][$y] = $imp;

                if ($srcTotal !== null) {
                    $tExp = $pick($srcTotal, $y, 'exp');
                    $tImp = $pick($srcTotal, $y, 'imp');
                    if ($tExp === null && $tImp === null) {
                        $res['total'][$y] = null;
                    } else {
                        $res['total'][$y] = (float) (($tExp ?? 0.0) + ($tImp ?? 0.0));
                    }
                } else {
                    if ($exp === null && $imp === null) {
                        $res['total'][$y] = null;
                    } else {
                        $res['total'][$y] = (float) (($exp ?? 0.0) + ($imp ?? 0.0));
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        return $res;
    }

    protected function topTradePartnerForYear($db, int $year, ?int $sourceCode = null, array $filters = []): array
    {
        try {
            $q = $this->tradeTableWithIndex($db, 'idx_trade_filter_partner')
                ->selectRaw('Kode_Alpha3_Partner AS a3, SUM(Nilai) AS totalUSD')
                ->where('Kode_Alpha3_Reporter', $this->IDN);

            if (! is_null($sourceCode)) {
                $q->where('Kode_Sumber', $sourceCode);
            }
            $q->where('Tahun', $year)
                ->whereIn('Status', ['Export', 'Import']);
            $this->applyHsFilter($q, $filters, 'HsCode');
            $this->applyDirjenFilter($q, $filters, $this->TB_TRADE);

            $row = $q->groupBy('Kode_Alpha3_Partner')
                ->orderByDesc(DB::raw('totalUSD'))
                ->limit(1)
                ->first();

            if (! $row) {
                return [];
            }
            $m = $this->countryMeta($db, (string) $row->a3);

            return [
                'alpha3' => (string) $row->a3,
                'alpha2' => $m['alpha2'],
                'name' => $m['name'],
                'totalUSD' => (float) $row->totalUSD,
            ];
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function topTradeByStatusForYear($db, int $year, string $status, ?int $sourceCode = null, array $filters = []): array
    {
        try {
            $q = $this->tradeTableWithIndex($db, 'idx_trade_filter_partner')
                ->selectRaw('Kode_Alpha3_Partner AS a3, SUM(Nilai) AS totalUSD')
                ->where('Kode_Alpha3_Reporter', $this->IDN);

            if (! is_null($sourceCode)) {
                $q->where('Kode_Sumber', $sourceCode);
            }
            $q->where('Tahun', $year)
                ->where('Status', $status);
            $this->applyHsFilter($q, $filters, 'HsCode');
            $this->applyDirjenFilter($q, $filters, $this->TB_TRADE);

            $row = $q->groupBy('Kode_Alpha3_Partner')
                ->orderByDesc(DB::raw('totalUSD'))
                ->limit(1)
                ->first();

            if (! $row) {
                return [];
            }
            $m = $this->countryMeta($db, (string) $row->a3);

            return [
                'alpha3' => (string) $row->a3,
                'alpha2' => $m['alpha2'],
                'name' => $m['name'],
                'totalUSD' => (float) $row->totalUSD,
            ];
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function tradePartnerAggForYear($db, int $year, ?int $srcExport = null, ?int $srcImport = null, array $filters = [], ?array $months = null): array
    {
        try {
            $q = $this->tradeTableWithIndex($db, 'idx_trade_filter_partner')
                ->selectRaw("
        Kode_Alpha3_Partner AS a3,
        SUM(CASE WHEN Status='Export' THEN Nilai ELSE 0 END) AS exp_sum,
        SUM(CASE WHEN Status='Import' THEN Nilai ELSE 0 END) AS imp_sum
      ")
                ->where('Kode_Alpha3_Reporter', $this->IDN)
                ->where('Tahun', $year);

            if ($srcExport === null && $srcImport === null) {
                $q->whereIn('Status', ['Export', 'Import']);
            } elseif ($srcExport !== null && $srcImport !== null && (int) $srcExport === (int) $srcImport) {
                $q->where('Kode_Sumber', $srcExport)
                    ->whereIn('Status', ['Export', 'Import']);
            } else {
                $q->where(function ($sub) use ($srcExport, $srcImport) {
                    if ($srcExport !== null) {
                        $sub->orWhere(function ($qq) use ($srcExport) {
                            $qq->where('Status', 'Export')
                                ->where('Kode_Sumber', $srcExport);
                        });
                    }
                    if ($srcImport !== null) {
                        $sub->orWhere(function ($qq) use ($srcImport) {
                            $qq->where('Status', 'Import')
                                ->where('Kode_Sumber', $srcImport);
                        });
                    }
                });
            }

            $this->applyHsFilter($q, $filters, 'HsCode');
            $this->applyTradeMonthsFilter($q, $months);
            $this->applyDirjenFilter($q, $filters, $this->TB_TRADE);

            $rows = $q->groupBy('Kode_Alpha3_Partner')->get();
            $out = [];
            foreach ($rows as $r) {
                $exp = (float) ($r->exp_sum ?? 0);
                $imp = (float) ($r->imp_sum ?? 0);
                $out[] = [
                    'a3' => (string) $r->a3,
                    'export' => $exp,
                    'import' => $imp,
                    'total' => $exp + $imp,
                    'surplus' => $exp - $imp,
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function pickTopPartner($db, array $rows, string $metric): array
    {
        if (empty($rows)) {
            return [];
        }

        $best = null;
        foreach ($rows as $r) {
            if (!isset($r[$metric])) {
                continue;
            }
            if ($best === null || $r[$metric] > $best[$metric]) {
                $best = $r;
            }
        }

        if ($best === null) {
            return [];
        }

        $m = $this->countryMeta($db, (string) $best['a3']);
        if ($metric === 'surplus') {
            return [
                'alpha3' => (string) $best['a3'],
                'alpha2' => $m['alpha2'],
                'name' => $m['name'],
                'surplus' => (float) $best['surplus'],
            ];
        }

        return [
            'alpha3' => (string) $best['a3'],
            'alpha2' => $m['alpha2'],
            'name' => $m['name'],
            'totalUSD' => (float) $best[$metric],
        ];
    }

    protected function topSurplusCountry($db, int $year, ?int $srcExport = null, ?int $srcImport = null, array $filters = []): array
    {
        try {
            $expQ = $this->tradeTableWithIndex($db, 'idx_trade_filter_partner')
                ->selectRaw('Kode_Alpha3_Partner AS a3, SUM(Nilai) AS val')
                ->where('Kode_Alpha3_Reporter', $this->IDN);
            if (! is_null($srcExport)) {
                $expQ->where('Kode_Sumber', $srcExport);
            }
            $expQ->where('Tahun', $year)
                ->where('Status', 'Export');
            $this->applyHsFilter($expQ, $filters, 'HsCode');
            $this->applyDirjenFilter($expQ, $filters, $this->TB_TRADE);
            $expRows = $expQ->groupBy('Kode_Alpha3_Partner')->get();

            $impQ = $this->tradeTableWithIndex($db, 'idx_trade_filter_partner')
                ->selectRaw('Kode_Alpha3_Partner AS a3, SUM(Nilai) AS val')
                ->where('Kode_Alpha3_Reporter', $this->IDN);
            if (! is_null($srcImport)) {
                $impQ->where('Kode_Sumber', $srcImport);
            }
            $impQ->where('Tahun', $year)
                ->where('Status', 'Import');
            $this->applyHsFilter($impQ, $filters, 'HsCode');
            $this->applyDirjenFilter($impQ, $filters, $this->TB_TRADE);
            $impRows = $impQ->groupBy('Kode_Alpha3_Partner')->get();

            $expMap = [];
            foreach ($expRows as $r) {
                $expMap[(string) $r->a3] = (float) $r->val;
            }
            $impMap = [];
            foreach ($impRows as $r) {
                $impMap[(string) $r->a3] = (float) $r->val;
            }

            $bestA3 = null;
            $bestSurp = null;
            foreach ($expMap as $a3 => $v) {
                $surp = $v - ($impMap[$a3] ?? 0.0);
                if ($bestSurp === null || $surp > $bestSurp) {
                    $bestSurp = $surp;
                    $bestA3 = $a3;
                }
            }

            if (! $bestA3) {
                return [];
            }
            $m = $this->countryMeta($db, $bestA3);

            return ['alpha3' => $bestA3, 'alpha2' => $m['alpha2'], 'name' => $m['name'], 'surplus' => (float) $bestSurp];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /* ===================== TOURISM (inbound IDN) ===================== */
    protected function latestTwoYearsTourismInbound($db, array $filters = []): array
    {
        return $this->resolveTwoYearsExact($filters, function () use ($db, $filters) {
            $codes = $this->collectCodesUsed([$this->SRC_TOURISM]);

            $y = $this->pickLatestYearWithData(
                $db,
                $this->TB_TOURISM,
                function ($q) use ($codes, $filters) {
                    $q->where('Kode_Alpha3_Tujuan', $this->IDN);
                    if (! empty($codes)) {
                        $q->whereIn('Kode_Sumber', $codes);
                    }
                    $this->applyDirjenFilter($q, $filters, $this->TB_TOURISM);
                },
                $filters,
                'Jumlah_Wisatawan'
            );

            if (! $y) {
                $y = (int) date('Y') - 1;
            }

            return [(int) $y, (int) $y - 1];
        });
    }

    protected function tourismInboundTotal($db, ?int $year, ?int $sourceCode = null, array $filters = []): ?int
    {
        if (! $year) {
            return null;
        }
        try {
            $q = $db->table($this->TB_TOURISM)
                ->where('Kode_Alpha3_Tujuan', $this->IDN)
                ->where('Tahun', $year);
            if (! is_null($sourceCode)) {
                $q->where('Kode_Sumber', $sourceCode);
            }
            $this->applyDirjenFilter($q, $filters, $this->TB_TOURISM);
            if (! (clone $q)->exists()) {
                return null;
            }

            return (int) $q->sum('Jumlah_Wisatawan');
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function tourismInboundTopOrigin($db, int $year, ?int $sourceCode = null, array $filters = []): array
    {
        try {
            $q = $db->table($this->TB_TOURISM)
                ->selectRaw('Kode_Alpha3_Asal AS a3, SUM(Jumlah_Wisatawan) AS visits')
                ->where('Kode_Alpha3_Tujuan', $this->IDN)
                ->where('Tahun', $year);
            if (! is_null($sourceCode)) {
                $q->where('Kode_Sumber', $sourceCode);
            }
            $this->applyDirjenFilter($q, $filters, $this->TB_TOURISM);

            $row = $q->groupBy('Kode_Alpha3_Asal')
                ->orderByDesc(DB::raw('visits'))
                ->limit(1)
                ->first();

            if (! $row) {
                return [];
            }
            $m = $this->countryMeta($db, (string) $row->a3);

            return [
                'alpha3' => (string) $row->a3,
                'alpha2' => $m['alpha2'],
                'name' => $m['name'],
                'visits' => (int) $row->visits,
            ];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /* ============================== FDI ============================== */
    protected function latestTwoYearsInvestment($db, array $filters = []): array
    {
        return $this->resolveTwoYearsExact($filters, function () use ($db, $filters) {
            $codes = $this->collectCodesUsed([$this->SRC_FDI_IN, $this->SRC_FDI_OUT]);

            $y = $this->pickLatestYearWithData(
                $db,
                $this->TB_INVESTMENT,
                function ($q) use ($codes, $filters) {
                    $q->where('Kode_Alpha3_Tujuan', $this->IDN)
                        ->where('Kode_Alpha3_Asal', '<>', $this->IDN);

                    if (! empty($codes)) {
                        $q->whereIn('Kode_Sumber', $codes);
                    }

                    $this->applyDirjenFilter($q, $filters, $this->TB_INVESTMENT, '', 'inbound');
                },
                $filters,
                'Nilai_Investasi'
            );

            if (! $y) {
                $y = (int) date('Y') - 1;
            }

            return [(int) $y, (int) $y - 1];
        });
    }

    protected function fdiInboundTotal($db, ?int $year, ?int $sourceCode = null, array $filters = []): ?float
    {
        if (! $year) {
            return null;
        }

        try {
            $q = $db->table($this->TB_INVESTMENT)
                ->where('Tahun', $year)
                ->where('Kode_Alpha3_Tujuan', $this->IDN)
                // 🔽 exclude IDN → IDN
                ->where('Kode_Alpha3_Asal', '<>', $this->IDN);

            if (! is_null($sourceCode)) {
                $q->where('Kode_Sumber', $sourceCode);
            }
            $this->applyDirjenFilter($q, $filters, $this->TB_INVESTMENT, '', 'inbound');

            if (! (clone $q)->exists()) {
                return null;
            }

            return (float) $q->sum('Nilai_Investasi');
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function fdiOutboundTotal($db, ?int $year, ?int $sourceCode = null, array $filters = []): ?float
    {
        if (! $year) {
            return null;
        }

        try {
            $q = $db->table($this->TB_INVESTMENT)
                ->where('Tahun', $year)
                ->where('Kode_Alpha3_Asal', $this->IDN);

            if (! is_null($sourceCode)) {
                $q->where('Kode_Sumber', $sourceCode);
            }

            $this->applyDirjenFilter($q, $filters, $this->TB_INVESTMENT, '', 'outbound');

            if (! (clone $q)->exists()) {
                return null;
            }

            return (float) $q->sum('Nilai_Investasi');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /* ==================== TOP PRODUCTS (HS) ==================== */
    protected function topProducts($db, int $year, ?int $srcExport = null, ?int $srcImport = null, array $filters = [], ?array $months = null): array
    {
        $exp = null;
        $imp = null;

        try {
            $HS_EXPR = $this->hsAggExpr($filters, 't.');
            $re = $this->tradeTableWithIndex($db, 'idx_trade_filter_hs', 't')
                ->selectRaw("$HS_EXPR AS hs, SUM(t.Nilai) AS usd")
                ->where('t.Kode_Alpha3_Reporter', $this->IDN);

            if (! is_null($srcExport)) {
                $re->where('t.Kode_Sumber', $srcExport);
            }
            $re->where('t.Tahun', $year)
                ->where('t.Status', 'Export');
            $this->applyHsFilter($re, $filters, 'HsCode', 't.');
            $this->applyTradeMonthsFilter($re, $months, 't.Bulan');
            $this->applyDirjenFilter($re, $filters, $this->TB_TRADE);

            $re = $re->groupBy(DB::raw($HS_EXPR))
                ->orderByDesc(DB::raw('usd'))
                ->limit(1)
                ->first();

            if ($re) {
                $hs = (string) $re->hs;
                $desc = $this->hsDescription($db, $hs);
                $exp = [
                    'hs' => $hs,
                    'usd' => (float) $re->usd,
                    'label' => $desc ? ("HS {$hs} – {$desc}") : ("HS {$hs}"),
                    'description' => $desc,
                ];
            }
        } catch (\Throwable $e) {
        }

        try {
            $HS_EXPR = $this->hsAggExpr($filters, 't.');
            $ri = $this->tradeTableWithIndex($db, 'idx_trade_filter_hs', 't')
                ->selectRaw("$HS_EXPR AS hs, SUM(t.Nilai) AS usd")
                ->where('t.Kode_Alpha3_Reporter', $this->IDN);

            if (! is_null($srcImport)) {
                $ri->where('t.Kode_Sumber', $srcImport);
            }
            $ri->where('t.Tahun', $year)
                ->where('t.Status', 'Import');
            $this->applyHsFilter($ri, $filters, 'HsCode', 't.');
            $this->applyTradeMonthsFilter($ri, $months, 't.Bulan');
            $this->applyDirjenFilter($ri, $filters, $this->TB_TRADE);

            $ri = $ri->groupBy(DB::raw($HS_EXPR))
                ->orderByDesc(DB::raw('usd'))
                ->limit(1)
                ->first();

            if ($ri) {
                $hs = (string) $ri->hs;
                $desc = $this->hsDescription($db, $hs);
                $imp = [
                    'hs' => $hs,
                    'usd' => (float) $ri->usd,
                    'label' => $desc ? ("HS {$hs} – {$desc}") : ("HS {$hs}"),
                    'description' => $desc,
                ];
            }
        } catch (\Throwable $e) {
        }

        // kalau benar-benar tidak ada produk, usd = null dan label "-"
        return [
            $exp ?? ['usd' => null, 'label' => '-', 'description' => null],
            $imp ?? ['usd' => null, 'label' => '-', 'description' => null],
        ];
    }

    protected function fdiInboundTopOrigin($db, int $year, ?int $sourceCode = null, array $filters = []): array
    {
        try {
            $q = $db->table($this->TB_INVESTMENT)
                ->selectRaw('Kode_Alpha3_Asal AS a3, SUM(Nilai_Investasi) AS usd')
                ->where('Tahun', $year)
                ->where('Kode_Alpha3_Tujuan', $this->IDN)
                ->where('Kode_Alpha3_Asal', '<>', $this->IDN);

            if (! is_null($sourceCode)) {
                $q->where('Kode_Sumber', $sourceCode);
            }

            $this->applyDirjenFilter($q, $filters, $this->TB_INVESTMENT, '', 'inbound');

            $row = $q->groupBy('Kode_Alpha3_Asal')
                ->havingRaw('SUM(Nilai_Investasi) <> 0')
                ->orderByDesc(DB::raw('usd'))
                ->limit(1)
                ->first();

            if (! $row) {
                return [];
            }

            $m = $this->countryMeta($db, (string) $row->a3);

            return [
                'alpha3' => (string) $row->a3,
                'alpha2' => $m['alpha2'],
                'name' => $m['name'],
                'usd' => (float) $row->usd,
            ];
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function fdiOutboundTopDest($db, int $year, ?int $sourceCode = null, array $filters = []): array
    {
        try {
            $q = $db->table($this->TB_INVESTMENT)
                ->selectRaw('Kode_Alpha3_Tujuan AS a3, SUM(Nilai_Investasi) AS usd')
                ->where('Tahun', $year)
                ->where('Kode_Alpha3_Asal', $this->IDN);

            if (! is_null($sourceCode)) {
                $q->where('Kode_Sumber', $sourceCode);
            }

            $this->applyDirjenFilter($q, $filters, $this->TB_INVESTMENT, '', 'outbound');

            $row = $q->groupBy('Kode_Alpha3_Tujuan')
                ->havingRaw('SUM(Nilai_Investasi) <> 0')
                ->orderByDesc(DB::raw('usd'))
                ->limit(1)
                ->first();

            if (! $row) {
                return [];
            }

            $m = $this->countryMeta($db, (string) $row->a3);

            return [
                'alpha3' => (string) $row->a3,
                'alpha2' => $m['alpha2'],
                'name' => $m['name'],
                'usd' => (float) $row->usd,
            ];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /* ============================== AID ============================== */
    protected function latestTwoYearsAid($db, array $filters = []): array
    {
        return $this->resolveTwoYearsExact($filters, function () use ($db, $filters) {
            $codes = $this->collectCodesUsed([$this->SRC_AID]);

            $y = $this->pickLatestYearWithData(
                $db,
                $this->TB_AID,
                function ($q) use ($codes, $filters) {
                    if (! empty($codes)) {
                        $q->whereIn('Kode_Sumber', $codes);
                    }
                    $this->applyDirjenFilter($q, $filters, $this->TB_AID);
                },
                $filters,
                'Realisasi'
            );

            if (! $y) {
                $y = (int) date('Y') - 1;
            }

            return [(int) $y, (int) $y - 1];
        });
    }

    protected function aidTotalIdr($db, ?int $year, ?int $sourceCode = null, array $filters = []): ?float
    {
        if (! $year) {
            return null;
        }
        try {
            $q = $db->table($this->TB_AID.' as h')->where('h.Tahun', $year);
            if (! is_null($sourceCode)) {
                $q->where('h.Kode_Sumber', $sourceCode);
            }
            $this->applyDirjenFilter($q, $filters, $this->TB_AID, 'h.');
            if (! (clone $q)->exists()) {
                return null;
            }

            $sum = (float) $q->sum('h.Realisasi');

            return $sum;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function aidTopDest($db, int $year, ?int $sourceCode = null, array $filters = []): array
    {
        try {
            $q = $db->table($this->TB_AID.' as h')
                ->selectRaw('h.Kode_Alpha3 AS a3, SUM(h.Realisasi) AS idr')
                ->where('h.Tahun', $year)
                ->whereNotNull('h.Kode_Alpha3');

            if (! is_null($sourceCode)) {
                $q->where('h.Kode_Sumber', $sourceCode);
            }
            $this->applyDirjenFilter($q, $filters, $this->TB_AID, 'h.');

            $row = $q->groupBy('h.Kode_Alpha3')
                ->orderByDesc(DB::raw('idr'))
                ->limit(1)
                ->first();

            if (! $row) {
                return [];
            }

            $m = $this->countryMeta($db, (string) $row->a3);

            return [
                'alpha3' => (string) $row->a3,
                'alpha2' => $m['alpha2'],
                'name' => $m['name'],
                'idr' => (float) $row->idr,
            ];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /* ========================= Meta & Util ========================= */

    protected function countryMeta($db, string $alpha3): array
    {
        try {
            $r = $db->table($this->TB_COUNTRY)
                ->select('Negara_IDN', 'Kode_Alpha2')
                ->where('Kode_Alpha3', $alpha3)
                ->first();
        } catch (\Throwable $e) {
            $r = null;
        }

        return [
            'name' => $r->Negara_IDN ?? $alpha3,
            'alpha2' => $r->Kode_Alpha2 ?? null,
        ];
    }

    protected function hsDescription($db, string $hs): ?string
    {
        try {
            $desc = $db->table($this->TB_HS)->where('hscode', $hs)->value('description');

            return $desc ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function fallbackYearFromFilter(array $filters, int $default): int
    {
        if (isset($filters['year_end']) && is_numeric($filters['year_end'])) {
            return (int) $filters['year_end'];
        }
        if (isset($filters['year_start']) && is_numeric($filters['year_start'])) {
            return (int) $filters['year_start'];
        }

        return $default;
    }

    protected function resolveTradeMonthWindowForSourceOne($db, int $year, array $filters = []): ?array
    {
        $normalizedFilters = $this->normalizeForCacheKey($filters);
        $cacheKey = SideCacheKey::pairs(
            ['indonesia', 'stats', 'trade', 'source-1', 'month-window'],
            array_merge(['year' => $year], $normalizedFilters)
        );

        $cached = Cache::remember($cacheKey, now()->addDays(15), function () use ($db, $year, $filters) {
            try {
                $q = $this->tradeTableWithIndex($db, 'idx_trade_filter_partner')
                    ->select('Bulan')
                    ->where('Kode_Alpha3_Reporter', $this->IDN)
                    ->where('Tahun', $year)
                    ->where('Kode_Sumber', 1)
                    ->whereIn('Status', ['Export', 'Import']);

                $this->applyHsFilter($q, $filters, 'HsCode');
                $this->applyDirjenFilter($q, $filters, $this->TB_TRADE);

                $rawMonths = (clone $q)->distinct()->pluck('Bulan')->all();
                $monthSet = [];
                foreach ($rawMonths as $m) {
                    if (! is_numeric($m)) {
                        continue;
                    }
                    $mi = (int) $m;
                    if ($mi >= 1 && $mi <= 12) {
                        $monthSet[$mi] = true;
                    }
                }

                if (empty($monthSet)) {
                    return ['months' => [], 'start' => null, 'end' => null];
                }

                ksort($monthSet);
                $months = array_keys($monthSet);

                return [
                    'months' => $months,
                    'start' => (int) $months[0],
                    'end' => (int) $months[count($months) - 1],
                ];
            } catch (\Throwable $e) {
                return ['months' => [], 'start' => null, 'end' => null];
            }
        });

        if (empty($cached['months']) || ! is_array($cached['months'])) {
            return null;
        }

        return $cached;
    }

    protected function normalizeForCacheKey($value)
    {
        if (! is_array($value)) {
            return $value;
        }

        $isAssoc = array_keys($value) !== range(0, count($value) - 1);
        if ($isAssoc) {
            ksort($value);
        } else {
            sort($value);
        }

        foreach ($value as $k => $v) {
            $value[$k] = $this->normalizeForCacheKey($v);
        }

        return $value;
    }

    protected function applyTradeMonthsFilter($q, ?array $months, string $col = 'Bulan'): void
    {
        if (empty($months)) {
            return;
        }

        $norm = [];
        foreach ($months as $m) {
            if (! is_numeric($m)) {
                continue;
            }
            $mi = (int) $m;
            if ($mi >= 1 && $mi <= 12) {
                $norm[$mi] = true;
            }
        }

        $norm = array_keys($norm);
        sort($norm);
        if (empty($norm)) {
            return;
        }

        $q->whereIn($col, $norm);
    }

    protected function pickLatestYearWithData($db, string $table, callable $config, array $filters, string $sumCol = 'Nilai'): ?int
    {
        try {
            $q = $db->table($table);
            $config($q);

            $this->applyYearSpan($q, $filters, 'Tahun');

            $row = (clone $q)
                ->selectRaw('Tahun, SUM('.$sumCol.') AS s')
                ->groupBy('Tahun')
                ->havingRaw('SUM('.$sumCol.') <> 0')
                ->orderByDesc('Tahun')
                ->first();

            if ($row && isset($row->Tahun)) {
                return (int) $row->Tahun;
            }

            $y = (int) (clone $q)->max('Tahun');
            if ($y) {
                return $y;
            }

            if (isset($filters['year_end']) || isset($filters['year_start'])) {
                return $this->fallbackYearFromFilter($filters, (int) date('Y'));
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function resolveTwoYearsExact(array $filters, callable $fallback): array
    {
        $ys = isset($filters['year_start']) && is_numeric($filters['year_start']) ? (int) $filters['year_start'] : null;
        $ye = isset($filters['year_end']) && is_numeric($filters['year_end']) ? (int) $filters['year_end'] : null;

        if ($ys !== null && $ye !== null) {
            if ($ys > $ye) {
                [$ys, $ye] = [$ye, $ys];
            }
            // jika sama, pakai y dan y-1 agar benar2 "now vs prev"
            if ($ys === $ye) {
                return [$ye, $ye - 1];
            }

            return [$ye, $ys];
        }

        if ($ys !== null) {
            return [$ys, $ys - 1];
        }
        if ($ye !== null) {
            return [$ye, $ye - 1];
        }

        return $fallback();
    }
}
