<?php

namespace App\Repositories\Indonesia\EconomyDiplomation;

use Illuminate\Support\Facades\DB;

class NilaiPerdaganganRepository implements NilaiPerdaganganRepositoryInterface
{
    /** ====== Koneksi & Tabel ====== */
    protected string $conn      = 'server_mysql';
    protected string $TB_TRADE  = 'tbtrade';
    protected string $TB_COUNTRY= 'tbnegara';
    protected string $TB_SOURCE = 'tbsumber';
    protected string $TB_HS     = 'tbharmonized';
    protected string $TB_ORG_NEGARA = 'tborgnegara';

    /** ====== Konstanta ====== */
    protected string $REPORTER  = 'IDN';
    protected string $COL_DIRJEN = 'ID_WIl_Kemlu';
    protected string $UNIT       = 'Ribu US$';
    protected int $TOP_N = 10;

    public function nilaiPerdagangan(array $filters, ?int $kodeSumber = null, int $limit = 50): array
    {
        $filters = $this->normalizeFilters($filters);
        $reporter = $this->resolveReporter($filters);
        $kodeSumber = $kodeSumber === null ? 5 : (int) $kodeSumber;
        $kompetitorSourceForMeta = ((int) $kodeSumber === 1) ? 5 : $kodeSumber;
        $kompetitorSourceName = $this->getSourceName($kompetitorSourceForMeta);

        // Resolve tahun tersedia untuk sumber terkait
        [$y1, $y2, $availableYears] = $this->resolveYears($filters, $kodeSumber);
        if (!$y2) {
            return [
                'meta'       => [
                    'latest_year'          => null,
                    'prev_year'            => null,
                    'years'                => [],
                    'available_years'      => [],
                    'sumber'               => null,
                    'total_world'          => 0,
                    'total_world_per_year' => [],
                    'kompetitor_sumber' => $kompetitorSourceName,
                    'applied_filters'      => $filters,
                    'hs_level'             => $filters['hs'] ?? null,
                    'unit'                 => $this->UNIT,
                    'format'               => ['unit' => $this->UNIT],
                    'effective_years'      => ['start' => null, 'end' => null],
                    'requested_years'      => [
                        'start' => $filters['year_start'] ?? null,
                        'end'   => $filters['year_end']   ?? null,
                    ],
                ],
                'items'      => [],
                'top_produk' => [],
            ];
        }

        $years = $this->filterYearsInRange($availableYears, $y1, $y2);
        if (empty($years)) {
            return [
                'meta'       => [
                    'latest_year'          => null,
                    'prev_year'            => null,
                    'years'                => [],
                    'available_years'      => $availableYears,
                    'sumber'               => null,
                    'total_world'          => 0,
                    'total_world_per_year' => [],
                    'kompetitor_sumber' => $kompetitorSourceName,
                    'applied_filters'      => $filters,
                    'hs_level'             => $filters['hs'] ?? null,
                    'unit'                 => $this->UNIT,
                    'format'               => ['unit' => $this->UNIT],
                    'effective_years'      => ['start' => $y1, 'end' => $y2],
                    'requested_years'      => [
                        'start' => $filters['year_start'] ?? null,
                        'end'   => $filters['year_end']   ?? null,
                    ],
                ],
                'items'      => [],
                'top_produk' => [],
            ];
        }
        $yLast = (int) max($years);
        $monthWindow = null;
        if ((int) $kodeSumber === 1) {
            $monthWindow = $this->resolveMonthWindowForSourceOne($filters, $yLast, $reporter);
        }

        // Metadata sumber data
        $sumber = DB::connection($this->conn)
            ->table($this->TB_SOURCE)
            ->select('KodeSumber as kode', 'NamaSumber as nama')
            ->where('KodeSumber', $kodeSumber)
            ->first();

        // ===== Base Query
        $base = $this->tradeBaseQuery('idx_trade_filter_partner', $reporter)
            ->where('t.Kode_Sumber', $kodeSumber);

        $base = $this->applyYearRange($base, $y1, $y2);
        $base = $this->applyStatusFilter($base, $filters);
        $base = $this->applyHsWhereLength($base, $filters); // level digit
        $base = $this->applyDirjenFilter($base, $filters);
        $base = $this->applyPartnersFilter($base, $filters);
        $base = $this->applyHsCodesFilter($base, $filters); // daftar HS4 spesifik

        [$worldByYear, $totalWorldYLast] = $this->buildWorldTotals($base, $years, $yLast);
        $items = $this->buildPartnerItems($base, $years, $worldByYear, $yLast);
        [$hsAgg, $hsCodes, $hsNames] = $this->buildHsAggAndNames($filters, $kodeSumber, $y1, $y2, $years, $worldByYear, $yLast, $limit, $reporter);
        [
            $tujuanEksporByHs,
            $tujuanImporByHs,
            $destMap,
            $kompetitorTopDestByHs,
            $kompetitorTopDestImpByHs,
            $kompetitorAseanTopDestByHs,
            $kompetitorAseanTopDestImpByHs
        ] = $this->buildTujuanDanKompetitor($filters, $kodeSumber, $yLast, $hsCodes, $reporter);
        $topProduk = $this->buildTopProduk(
            $hsAgg,
            $hsNames,
            $tujuanEksporByHs,
            $tujuanImporByHs,
            $kompetitorTopDestByHs,
            $kompetitorTopDestImpByHs,
            $kompetitorAseanTopDestByHs,
            $kompetitorAseanTopDestImpByHs,
            $kodeSumber
        );
        $latestYearMonthCoverage = $this->buildLatestYearMonthCoverageMeta($monthWindow, $yLast);

        return [
            'meta'  => [
                'latest_year'          => $yLast,
                'prev_year'            => (int) min($years),
                'years'                => $years,
                'available_years'      => $availableYears,
                'total_world'          => $totalWorldYLast,
                'total_world_per_year' => array_map(fn($v) => $v['world'], $worldByYear),
                'sumber'               => $sumber?->nama,
                'kompetitor_sumber' => $kompetitorSourceName,
                'applied_filters'      => $filters,
                'hs_level'             => $filters['hs'] ?? null,
                'unit'                 => $this->UNIT,
                'format'               => ['unit' => $this->UNIT],
                'effective_years'      => ['start' => $y1, 'end' => $y2],
                'latest_year_month_coverage' => $latestYearMonthCoverage,
                'reporter'            => $reporter,
            ],
            'items'      => $items,
            'top_produk' => $topProduk,
        ];
    }


    protected function buildWorldTotals($base, array $years, int $yLast): array
    {
        $worldRows = (clone $base)
            ->selectRaw("\n                t.Tahun,\n                SUM(t.Nilai) as total_world,\n                SUM(CASE WHEN t.Status = 'Export' THEN t.Nilai ELSE 0 END) as total_export,\n                SUM(CASE WHEN t.Status = 'Import' THEN t.Nilai ELSE 0 END) as total_import\n            ")
            ->groupBy('t.Tahun')
            ->get();

        $worldByYear = [];
        foreach ($years as $yr) {
            $worldByYear[$yr] = ['world' => 0, 'export' => 0, 'import' => 0];
        }
        foreach ($worldRows as $wr) {
            $yr = (int)$wr->Tahun;
            if (!array_key_exists($yr, $worldByYear)) continue;
            $worldByYear[$yr] = [
                'world'  => (int)$wr->total_world,
                'export' => (int)$wr->total_export,
                'import' => (int)$wr->total_import,
            ];
        }
        $totalWorldYLast = (int)($worldByYear[$yLast]['world'] ?? 0);

        return [$worldByYear, $totalWorldYLast];
    }

    protected function buildPartnerItems($base, array $years, array $worldByYear, int $yLast): array
    {
        $partnerYearRows = (clone $base)
            ->selectRaw("\n                t.Kode_Alpha3_Partner as partner,\n                t.Tahun,\n                SUM(CASE WHEN t.Status = 'Export' THEN t.Nilai ELSE 0 END) as eksp,\n                SUM(CASE WHEN t.Status = 'Import' THEN t.Nilai ELSE 0 END) as imp\n            ")
            ->groupBy('t.Kode_Alpha3_Partner', 't.Tahun')
            ->get();

        $partnerAgg = [];
        foreach ($partnerYearRows as $r) {
            $p  = (string)$r->partner;
            $yr = (int)$r->Tahun;
            if (!in_array($yr, $years, true)) continue;

            $ek = (int)$r->eksp;
            $im = (int)$r->imp;

            if (!isset($partnerAgg[$p])) {
                $partnerAgg[$p] = [
                    'nilai_perdagangan' => array_fill_keys($years, 0),
                    'neraca'            => array_fill_keys($years, 0),
                    'proporsi'          => array_fill_keys($years, 0.0),
                ];
            }
            $partnerAgg[$p]['nilai_perdagangan'][$yr] = $ek + $im;
            $partnerAgg[$p]['neraca'][$yr]            = $ek - $im;
        }

        foreach ($partnerAgg as $p => &$agg) {
            foreach ($years as $yr) {
                $worldDen = max(1, (int)($worldByYear[$yr]['world'] ?? 0));
                $agg['proporsi'][$yr] = round(($agg['nilai_perdagangan'][$yr] / $worldDen) * 100, 2);
            }
        }
        unset($agg);

        uasort($partnerAgg, function ($a, $b) use ($yLast) {
            return ($b['nilai_perdagangan'][$yLast] <=> $a['nilai_perdagangan'][$yLast]);
        });

        $partnerCodes = array_keys($partnerAgg);
        $countryMap = [];
        if (!empty($partnerCodes)) {
            $countryRows = DB::connection($this->conn)
                ->table($this->TB_COUNTRY . ' as n')
                ->whereIn('n.Kode_Alpha3', $partnerCodes)
                ->select('n.Kode_Alpha3', 'n.Kode_Alpha2', 'n.Negara_IDN')
                ->get();
            foreach ($countryRows as $cr) {
                $countryMap[$cr->Kode_Alpha3] = [
                    'nama' => (string)$cr->Negara_IDN,
                    'a2'   => (string)$cr->Kode_Alpha2,
                    'a3'   => (string)$cr->Kode_Alpha3,
                ];
            }
        }

        $items = [];
        foreach ($partnerAgg as $code => $series) {
            $meta = $countryMap[$code] ?? ['nama' => $code, 'a2' => null, 'a3' => $code];
            $items[] = [
                'negara'            => $meta['nama'],
                'kode_alpha2'       => $meta['a2'],
                'kode_alpha3'       => $meta['a3'],
                'nilai_perdagangan' => $series['nilai_perdagangan'],
                'neraca'            => $series['neraca'],
                'proporsi'          => $series['proporsi'],
            ];
        }

        return $items;
    }

    protected function buildHsAggAndNames(array $filters, int $kodeSumber, int $y1, int $y2, array $years, array $worldByYear, int $yLast, int $limit, string $reporter): array
    {
        $baseHs = $this->tradeBaseQuery('idx_trade_filter_hs', $reporter)
            ->where('t.Kode_Sumber', $kodeSumber);
        $baseHs = $this->applyYearRange($baseHs, $y1, $y2);
        $baseHs = $this->applyStatusFilter($baseHs, $filters);
        $baseHs = $this->applyHsWhereLength($baseHs, $filters);
        $baseHs = $this->applyDirjenFilter($baseHs, $filters);
        $baseHs = $this->applyPartnersFilter($baseHs, $filters);
        $baseHs = $this->applyHsCodesFilter($baseHs, $filters);

        $hsRows = (clone $baseHs)
            ->selectRaw("\n                t.HsCode,\n                t.Tahun,\n                SUM(CASE WHEN t.Status = 'Export' THEN t.Nilai ELSE 0 END) AS eksp,\n                SUM(CASE WHEN t.Status = 'Import' THEN t.Nilai ELSE 0 END) AS imp\n            ")
            ->groupBy('t.HsCode', 't.Tahun')
            ->get();

        $hsAgg = [];
        foreach ($hsRows as $r) {
            $code = (string)$r->HsCode;
            $yr   = (int)$r->Tahun;
            if (!in_array($yr, $years, true)) continue;

            $ek   = (int)$r->eksp;
            $im   = (int)$r->imp;

            if (!isset($hsAgg[$code])) {
                $hsAgg[$code] = [
                    'nilai'  => array_fill_keys($years, 0),
                    'neraca' => array_fill_keys($years, 0),
                    'share'  => array_fill_keys($years, 0.0),
                    'export' => array_fill_keys($years, 0),
                    'import' => array_fill_keys($years, 0),
                ];
            }
            $hsAgg[$code]['nilai'][$yr]  = $ek + $im;
            $hsAgg[$code]['neraca'][$yr] = $ek - $im;
            $hsAgg[$code]['export'][$yr] = $ek;
            $hsAgg[$code]['import'][$yr] = $im;
        }

        foreach ($hsAgg as $code => &$ag) {
            foreach ($years as $yr) {
                $den = max(1, (int) ($worldByYear[$yr]['world'] ?? 0));
                $ag['share'][$yr] = round(($ag['nilai'][$yr] / $den) * 100, 2);
            }
        }
        unset($ag);

        uasort($hsAgg, function ($a, $b) use ($yLast) {
            return ($b['nilai'][$yLast] <=> $a['nilai'][$yLast]);
        });
        $limitHs = max(1, min(200, (int) $limit));
        $hsAgg   = array_slice($hsAgg, 0, $limitHs, true);

        $hsCodes = array_keys($hsAgg);
        $hsNames = [];
        if (!empty($hsCodes)) {
            $map = DB::connection($this->conn)
                ->table($this->TB_HS)
                ->whereIn('hscode', $hsCodes)
                ->pluck('description', 'hscode');
            $hsNames = $map->toArray();
        }

        return [$hsAgg, $hsCodes, $hsNames];
    }

    protected function buildTujuanDanKompetitor(array $filters, int $kodeSumber, int $yLast, array $hsCodes, string $reporter): array
    {
        $tujuanEksporByHs = [];
        $tujuanImporByHs  = [];
        $kompetitorTopDestByHs = [];
        $kompetitorTopDestImpByHs = [];
        $kompetitorAseanTopDestByHs = [];
        $kompetitorAseanTopDestImpByHs = [];

        $allowExport = true;
        $allowImport = true;
        if (array_key_exists('status', $filters)) {
            $st = $filters['status'];
            if (is_array($st)) {
                $allowExport = in_array('Export', $st, true);
                $allowImport = in_array('Import', $st, true);
            } elseif (is_string($st)) {
                $allowExport = ($st === 'Export');
                $allowImport = ($st === 'Import');
            } else {
                $allowExport = false;
                $allowImport = false;
            }
        }

        $destMap = [];
        if (empty($hsCodes)) {
            return [
                $tujuanEksporByHs,
                $tujuanImporByHs,
                $destMap,
                $kompetitorTopDestByHs,
                $kompetitorTopDestImpByHs,
                $kompetitorAseanTopDestByHs,
                $kompetitorAseanTopDestImpByHs,
            ];
        }

        $destCodes = [];
        if ($allowExport) {
            $baseHsExport = $this->tradeBaseQuery('idx_trade_filter_hs', $reporter)
                ->where('t.Kode_Sumber', $kodeSumber)
                ->where('t.Status', 'Export');
            $baseHsExport = $this->applyYearRange($baseHsExport, $yLast, $yLast);
            $baseHsExport = $this->applyHsWhereLength($baseHsExport, $filters);
            $baseHsExport = $this->applyDirjenFilter($baseHsExport, $filters);
            $baseHsExport = $this->applyPartnersFilter($baseHsExport, $filters);
            $baseHsExport = $this->applyHsCodesFilter($baseHsExport, $filters);

            $destRows = (clone $baseHsExport)
                ->whereIn('t.HsCode', $hsCodes)
                ->selectRaw("\n                    t.HsCode,\n                    t.Kode_Alpha3_Partner as partner,\n                    SUM(t.Nilai) as eksp\n                ")
                ->groupBy('t.HsCode', 't.Kode_Alpha3_Partner')
                ->get();

            $destAgg = [];
            foreach ($destRows as $r) {
                $code = (string)$r->HsCode;
                $p    = (string)$r->partner;
                $val  = (int)$r->eksp;
                if (!isset($destAgg[$code])) $destAgg[$code] = [];
                $destAgg[$code][$p] = $val;
                $destCodes[$p] = true;
            }

            foreach ($destAgg as $code => $rows) {
                arsort($rows);
                $rankMap = [];
                $rankCounter = 0;
                foreach ($rows as $p => $val) {
                    $rankCounter++;
                    $rankMap[$p] = $rankCounter;
                }
                $top = array_slice($rows, 0, $this->TOP_N, true);
                if ($reporter !== 'IDN' && !array_key_exists('IDN', $top)) {
                    if (array_key_exists('IDN', $rows)) {
                        $top['IDN'] = $rows['IDN'];
                    } else {
                        $top['IDN'] = 0;
                        $rankMap['IDN'] = null;
                    }
                }
                $list = [];
                foreach ($top as $p => $val) {
                    $list[] = [
                        'rank'        => $rankMap[$p] ?? null,
                        'kode_alpha3' => $p,
                        'nilai'       => $val,
                    ];
                }
                $tujuanEksporByHs[$code] = $list;
            }
        }

        if ($allowImport) {
            $baseHsImport = $this->tradeBaseQuery('idx_trade_filter_hs', $reporter)
                ->where('t.Kode_Sumber', $kodeSumber)
                ->where('t.Status', 'Import');
            $baseHsImport = $this->applyYearRange($baseHsImport, $yLast, $yLast);
            $baseHsImport = $this->applyHsWhereLength($baseHsImport, $filters);
            $baseHsImport = $this->applyDirjenFilter($baseHsImport, $filters);
            $baseHsImport = $this->applyPartnersFilter($baseHsImport, $filters);
            $baseHsImport = $this->applyHsCodesFilter($baseHsImport, $filters);

            $destRowsImp = (clone $baseHsImport)
                ->whereIn('t.HsCode', $hsCodes)
                ->selectRaw("\n                    t.HsCode,\n                    t.Kode_Alpha3_Partner as partner,\n                    SUM(t.Nilai) as imp\n                ")
                ->groupBy('t.HsCode', 't.Kode_Alpha3_Partner')
                ->get();

            $destAggImp = [];
            foreach ($destRowsImp as $r) {
                $code = (string)$r->HsCode;
                $p    = (string)$r->partner;
                $val  = (int)$r->imp;
                if (!isset($destAggImp[$code])) $destAggImp[$code] = [];
                $destAggImp[$code][$p] = $val;
                $destCodes[$p] = true;
            }

            foreach ($destAggImp as $code => $rows) {
                arsort($rows);
                $rankMap = [];
                $rankCounter = 0;
                foreach ($rows as $p => $val) {
                    $rankCounter++;
                    $rankMap[$p] = $rankCounter;
                }
                $top = array_slice($rows, 0, $this->TOP_N, true);
                if ($reporter !== 'IDN' && !array_key_exists('IDN', $top)) {
                    if (array_key_exists('IDN', $rows)) {
                        $top['IDN'] = $rows['IDN'];
                    } else {
                        $top['IDN'] = 0;
                        $rankMap['IDN'] = null;
                    }
                }
                $list = [];
                foreach ($top as $p => $val) {
                    $list[] = [
                        'rank'        => $rankMap[$p] ?? null,
                        'kode_alpha3' => $p,
                        'nilai'       => $val,
                    ];
                }
                $tujuanImporByHs[$code] = $list;
            }
        }

        $kompetitorSource = ((int) $kodeSumber === 1) ? 5 : $kodeSumber;
        $kompetitorTopDestByHs = $this->buildKompetitorTopDest(
            'Export',
            $filters,
                $kompetitorSource,
                $yLast,
                $hsCodes,
                $tujuanEksporByHs
            );
        $kompetitorTopDestImpByHs = $this->buildKompetitorTopDest(
            'Import',
            $filters,
                $kompetitorSource,
                $yLast,
                $hsCodes,
                $tujuanImporByHs
            );
        $aseanAlpha3 = $this->getAseanAlpha3();
        if (!empty($aseanAlpha3)) {
            $kompetitorAseanTopDestByHs = $this->buildKompetitorTopDest(
                'Export',
                $filters,
                $kompetitorSource,
                $yLast,
                $hsCodes,
                $tujuanEksporByHs,
                $aseanAlpha3
            );
            $kompetitorAseanTopDestImpByHs = $this->buildKompetitorTopDest(
                'Import',
                $filters,
                $kompetitorSource,
                $yLast,
                $hsCodes,
                $tujuanImporByHs,
                $aseanAlpha3
            );
        }

        if (!empty($destCodes)) {
            $destRowsCountry = DB::connection($this->conn)
                ->table($this->TB_COUNTRY . ' as n')
                ->whereIn('n.Kode_Alpha3', array_keys($destCodes))
                ->select('n.Kode_Alpha3', 'n.Kode_Alpha2', 'n.Negara_IDN')
                ->get();
            foreach ($destRowsCountry as $cr) {
                $destMap[$cr->Kode_Alpha3] = [
                    'nama' => (string)$cr->Negara_IDN,
                    'a2'   => (string)$cr->Kode_Alpha2,
                    'a3'   => (string)$cr->Kode_Alpha3,
                ];
            }
        }

        foreach ($tujuanEksporByHs as $code => &$list) {
            foreach ($list as &$row) {
                $meta = $destMap[$row['kode_alpha3']] ?? ['nama' => $row['kode_alpha3'], 'a2' => null, 'a3' => $row['kode_alpha3']];
                $row['negara'] = $meta['nama'];
                $row['kode_alpha2'] = $meta['a2'];
            }
            unset($row);
        }
        unset($list);

        foreach ($tujuanImporByHs as $code => &$list) {
            foreach ($list as &$row) {
                $meta = $destMap[$row['kode_alpha3']] ?? ['nama' => $row['kode_alpha3'], 'a2' => null, 'a3' => $row['kode_alpha3']];
                $row['negara'] = $meta['nama'];
                $row['kode_alpha2'] = $meta['a2'];
            }
            unset($row);
        }
        unset($list);

        return [
            $tujuanEksporByHs,
            $tujuanImporByHs,
            $destMap,
            $kompetitorTopDestByHs,
            $kompetitorTopDestImpByHs,
            $kompetitorAseanTopDestByHs,
            $kompetitorAseanTopDestImpByHs,
        ];
    }

    protected function buildKompetitorTopDest(
        string $status,
        array $filters,
        int $kodeSumber,
        int $yLast,
        array $hsCodes,
        array $tujuanByHs,
        ?array $reporterAllow = null
    ): array
    {
        $kompetitorTopDestByHs = [];
        if (empty($tujuanByHs)) {
            return $kompetitorTopDestByHs;
        }

        $topDestByHs = [];
        foreach ($tujuanByHs as $hs => $list) {
            if (!empty($list[0]['kode_alpha3'])) {
                $topDestByHs[$hs] = $list[0]['kode_alpha3'];
            }
        }
        $destAll = array_values(array_unique(array_values($topDestByHs)));
        if (empty($destAll) || empty($topDestByHs)) {
            return $kompetitorTopDestByHs;
        }

        $compBase = DB::connection($this->conn)
            ->table(DB::raw("{$this->TB_TRADE} as t"))
            ->where('t.Kode_Sumber', $kodeSumber)
            ->where('t.Status', $status);
        $compBase = $this->applyYearRange($compBase, $yLast, $yLast);
        $compBase = $this->applyHsWhereLength($compBase, $filters);
        $compBase = $this->applyDirjenFilter($compBase, $filters);
        $compBase = $this->applyHsCodesFilter($compBase, $filters);
        if (is_array($reporterAllow) && count($reporterAllow) > 0) {
            $compBase = $compBase->whereIn('t.Kode_Alpha3_Reporter', $reporterAllow);
        }

        $compRows = (clone $compBase)
            ->whereIn('t.HsCode', $hsCodes)
            ->whereIn('t.Kode_Alpha3_Partner', $destAll)
            ->selectRaw("\n                t.HsCode,\n                t.Kode_Alpha3_Partner as partner,\n                t.Kode_Alpha3_Reporter as reporter,\n                SUM(t.Nilai) as total\n            ")
            ->groupBy('t.HsCode', 't.Kode_Alpha3_Partner', 't.Kode_Alpha3_Reporter')
            ->get();

        $compAgg = [];
        $reporterCodes = [];
        foreach ($compRows as $r) {
            $hs = (string)$r->HsCode;
            $dest = (string)$r->partner;
            $rep = (string)$r->reporter;
            $val = (int)$r->total;
            $compAgg[$hs][$dest][$rep] = $val;
            $reporterCodes[$rep] = true;
        }

        $reporterMap = [];
        if (!empty($reporterCodes)) {
            $repRows = DB::connection($this->conn)
                ->table($this->TB_COUNTRY . ' as n')
                ->whereIn('n.Kode_Alpha3', array_keys($reporterCodes))
                ->select('n.Kode_Alpha3', 'n.Kode_Alpha2', 'n.Negara_IDN')
                ->get();
            foreach ($repRows as $cr) {
                $reporterMap[$cr->Kode_Alpha3] = [
                    'nama' => (string)$cr->Negara_IDN,
                    'a2'   => (string)$cr->Kode_Alpha2,
                    'a3'   => (string)$cr->Kode_Alpha3,
                ];
            }
        }

        foreach ($topDestByHs as $hs => $dest) {
            $byRep = $compAgg[$hs][$dest] ?? [];
            if (empty($byRep)) {
                $kompetitorTopDestByHs[$hs] = [
                    'tujuan_alpha3'   => $dest,
                    'rank_indonesia'  => null,
                    'nilai_indonesia' => null,
                    'list'            => [],
                ];
                continue;
            }

            arsort($byRep);
            $sorted = $byRep;
            $rankIndo = null;
            $nilaiIndo = null;
            $rankMap = [];
            $rank = 0;
            foreach ($sorted as $rep => $val) {
                $rank++;
                $rankMap[$rep] = $rank;
                if ($rep === 'IDN') {
                    $rankIndo = $rank;
                    $nilaiIndo = $val;
                }
            }

            $topN = array_slice($sorted, 0, $this->TOP_N, true);
            if (!array_key_exists('IDN', $topN)) {
                if (array_key_exists('IDN', $sorted)) {
                    $topN['IDN'] = $sorted['IDN'];
                } else {
                    $topN['IDN'] = 0;
                }
            }

            $list = [];
            foreach ($topN as $rep => $val) {
                $meta = $reporterMap[$rep] ?? ['nama' => $rep, 'a2' => null, 'a3' => $rep];
                $list[] = [
                    'rank'       => $rankMap[$rep] ?? null,
                    'negara'     => $meta['nama'],
                    'kode_alpha2'=> $meta['a2'],
                    'kode_alpha3'=> $meta['a3'],
                    'nilai'      => $val,
                ];
            }
            $kompetitorTopDestByHs[$hs] = [
                'tujuan_alpha3' => $dest,
                'rank_indonesia' => $rankIndo,
                'nilai_indonesia' => $nilaiIndo,
                'list' => $list,
            ];
        }

        return $kompetitorTopDestByHs;
    }

    protected function buildTopProduk(
        array $hsAgg,
        array $hsNames,
        array $tujuanEksporByHs,
        array $tujuanImporByHs,
        array $kompetitorTopDestByHs,
        array $kompetitorTopDestImpByHs,
        array $kompetitorAseanTopDestByHs,
        array $kompetitorAseanTopDestImpByHs,
        int $kodeSumber
    ): array
    {
        $topProduk = [];
        foreach ($hsAgg as $code => $ag) {
            $includeReverse = ((int) $kodeSumber !== 1);
            $topProduk[] = [
                'kodeHS' => $code,
                'namaHS' => (string) ($hsNames[$code] ?? $code),
                'nilai'  => $ag['nilai'],
                'neraca' => $ag['neraca'],
                'share'  => $ag['share'],
                'export' => $ag['export'],
                'import' => $ag['import'],
                'tujuan_ekspor' => $tujuanEksporByHs[$code] ?? [],
                'tujuan_impor'  => $tujuanImporByHs[$code] ?? [],
                'kompetitor_global_top_tujuan_ekspor' => $kompetitorTopDestByHs[$code]['list'] ?? [],
                'kompetitor_global_top_tujuan_impor' => $kompetitorTopDestImpByHs[$code]['list'] ?? [],
                'kompetitor_asean_top_tujuan_ekspor' => $kompetitorAseanTopDestByHs[$code]['list'] ?? [],
                'kompetitor_asean_top_tujuan_impor' => $kompetitorAseanTopDestImpByHs[$code]['list'] ?? [],
                'export_reverse' => $includeReverse ? $ag['import'] : null,
                'import_reverse' => $includeReverse ? $ag['export'] : null,
            ];
        }

        return $topProduk;
    }

    protected function getSourceName(int $kodeSumber): ?string
    {
        $row = DB::connection($this->conn)
            ->table($this->TB_SOURCE)
            ->select('NamaSumber as nama')
            ->where('KodeSumber', $kodeSumber)
            ->first();

        return $row?->nama;
    }

    protected function getAseanAlpha3(): array
    {
        return DB::connection($this->conn)
            ->table($this->TB_ORG_NEGARA)
            ->where('ID_Org', 1)
            ->pluck('Kode_Alpha3')
            ->map(fn ($v) => (string) $v)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Top produk HS agregat (tanpa items partner).
     */
    public function topProduk(array $filters, int $kodeSumber = 5, int $limit = 50): array
    {
        $filters = $this->normalizeFilters($filters);

        $reporter = strtoupper((string)($filters['reporter'] ?? $this->REPORTER));

        // Resolve tahun
        [$y1, $y2, $availableYears] = $this->resolveYears(array_merge($filters, ['reporter' => $reporter]), $kodeSumber);
        if (!$y2) {
            return [
                'meta'       => [
                    'latest_year'          => null,
                    'prev_year'            => null,
                    'years'                => [],
                    'sumber'               => null,
                    'total_world'          => 0,
                    'total_world_per_year' => [],
                    'applied_filters'      => $filters,
                    'unit'                 => $this->UNIT,
                    'format'               => ['unit' => $this->UNIT],
                    'effective_years'      => ['start' => null, 'end' => null],
                    'reporter'             => $reporter,
                ],
                'top_produk' => [],
            ];
        }

        $years = $this->filterYearsInRange($availableYears, $y1, $y2);
        if (empty($years)) {
            return [
                'meta'       => [
                    'latest_year'          => null,
                    'prev_year'            => null,
                    'years'                => [],
                    'sumber'               => null,
                    'total_world'          => 0,
                    'total_world_per_year' => [],
                    'applied_filters'      => $filters,
                    'unit'                 => $this->UNIT,
                    'format'               => ['unit' => $this->UNIT],
                    'effective_years'      => ['start' => $y1, 'end' => $y2],
                    'reporter'             => $reporter,
                ],
                'top_produk' => [],
            ];
        }
        $yLast = (int) max($years);
        $sumber = DB::connection($this->conn)
            ->table($this->TB_SOURCE)
            ->select('KodeSumber as kode', 'NamaSumber as nama')
            ->where('KodeSumber', $kodeSumber)
            ->first();

        $base = $this->tradeBaseQuery('idx_trade_filter_partner', $reporter)
            ->where('t.Kode_Sumber', $kodeSumber);

        $base = $this->applyYearRange($base, $y1, $y2);
        $base = $this->applyStatusFilter($base, $filters);
        $base = $this->applyHsWhereLength($base, $filters);
        $base = $this->applyDirjenFilter($base, $filters);
        $base = $this->applyPartnersFilter($base, $filters);
        $base = $this->applyHsCodesFilter($base, $filters);

        // World total (untuk share)
        $worldRows = (clone $base)
            ->selectRaw("t.Tahun, SUM(t.Nilai) as total_world")
            ->groupBy('t.Tahun')
            ->get();

        $worldByYear = [];
        foreach ($years as $yr) $worldByYear[$yr] = 0;
        foreach ($worldRows as $wr) {
            $yr = (int)$wr->Tahun;
            if (isset($worldByYear[$yr])) $worldByYear[$yr] = (int)$wr->total_world;
        }
        $totalWorldYLast = (int)($worldByYear[$yLast] ?? 0);

        // Top produk
        $baseHs = $this->tradeBaseQuery('idx_trade_filter_hs', $reporter)
            ->where('t.Kode_Sumber', $kodeSumber);
        $baseHs = $this->applyYearRange($baseHs, $y1, $y2);
        $baseHs = $this->applyStatusFilter($baseHs, $filters);
        $baseHs = $this->applyHsWhereLength($baseHs, $filters);
        $baseHs = $this->applyDirjenFilter($baseHs, $filters);
        $baseHs = $this->applyPartnersFilter($baseHs, $filters);
        $baseHs = $this->applyHsCodesFilter($baseHs, $filters);

        $hsRows = (clone $baseHs)
            ->selectRaw("
                t.HsCode,
                t.Tahun,
                SUM(CASE WHEN t.Status = 'Export' THEN t.Nilai ELSE 0 END) AS eksp,
                SUM(CASE WHEN t.Status = 'Import' THEN t.Nilai ELSE 0 END) AS imp
            ")
            ->groupBy('t.HsCode', 't.Tahun')
            ->get();

        $hsAgg = [];
        foreach ($hsRows as $r) {
            $code = (string)$r->HsCode;
            $yr   = (int)$r->Tahun;
            if (!in_array($yr, $years, true)) continue;

            $ek = (int)$r->eksp;
            $im = (int)$r->imp;

            if (!isset($hsAgg[$code])) {
                $hsAgg[$code] = [
                    'nilai'  => array_fill_keys($years, 0),
                    'neraca' => array_fill_keys($years, 0),
                    'share'  => array_fill_keys($years, 0.0),
                ];
            }
            $hsAgg[$code]['nilai'][$yr]  = $ek + $im;
            $hsAgg[$code]['neraca'][$yr] = $ek - $im;
        }

        foreach ($hsAgg as $code => &$ag) {
            foreach ($years as $yr) {
                $den = max(1, (int)($worldByYear[$yr] ?? 0));
                $ag['share'][$yr] = round(($ag['nilai'][$yr] / $den) * 100, 2);
            }
        }
        unset($ag);

        uasort($hsAgg, fn($a, $b) => ($b['nilai'][$yLast] <=> $a['nilai'][$yLast]));
        $limitHs = max(1, min(200, (int)$limit));
        $hsAgg   = array_slice($hsAgg, 0, $limitHs, true);

        // Lookup nama HS
        $hsCodes = array_keys($hsAgg);
        $hsNames = [];
        if (!empty($hsCodes)) {
            $map = DB::connection($this->conn)
                ->table($this->TB_HS)
                ->whereIn('hscode', $hsCodes)
                ->pluck('description', 'hscode');
            $hsNames = $map->toArray();
        }

        $topProduk = [];
        foreach ($hsAgg as $code => $ag) {
            $topProduk[] = [
                'kodeHS' => $code,
                'namaHS' => (string)($hsNames[$code] ?? $code),
                'nilai'  => $ag['nilai'],
                'neraca' => $ag['neraca'],
                'share'  => $ag['share'],
            ];
        }
        return [
            'meta'  => [
                'latest_year'          => $yLast,
                'prev_year'            => (int)min($years),
                'years'                => $years,
                'available_years'      => $availableYears,
                'total_world'          => $totalWorldYLast,
                'total_world_per_year' => $worldByYear,
                'sumber'               => $sumber?->nama,
                'applied_filters'      => $filters,
                'unit'                 => $this->UNIT,
                'format'               => ['unit' => $this->UNIT],
                'effective_years'      => ['start' => $y1, 'end' => $y2],
                'reporter'             => $reporter,
            ],
            'top_produk' => $topProduk,
        ];
    }

    /* =========================================================================
     * Helpers: Normalisasi & Filter Query
     * ========================================================================= */

    /**
     * Normalisasi semua filter termasuk hs level dan daftar hsCode (4-digit).
     */
    protected function normalizeFilters(array $filters): array
    {
        $norm = [];

        // Tahun
        $ys = $filters['year_start'] ?? null;
        $ye = $filters['year_end']   ?? null;
        $norm['year_start'] = is_numeric($ys) ? (int)$ys : null;
        $norm['year_end']   = is_numeric($ye) ? (int)$ye : null;

        // HS level (digit) — tetap di 'hs'
        $hs = $filters['hs'] ?? null;
        if (is_string($hs)) {
            $digits = preg_replace('/\D+/', '', $hs);
            $hs = ($digits === '' ? null : (int)$digits);
        } elseif (is_numeric($hs)) {
            $hs = (int)$hs;
        } else {
            $hs = null;
        }
        $norm['hs'] = $hs;

        // Dirjen
        $dirjen = $filters['dirjen'] ?? [];
        if (is_string($dirjen)) $dirjen = array_map('trim', explode(',', $dirjen));
        if (is_array($dirjen)) {
            $dirjen = array_values(array_unique(array_filter(array_map(
                fn($v) => strtoupper((string)$v),
                $dirjen
            ))));
        } else {
            $dirjen = [];
        }
        $norm['dirjen'] = $dirjen;

        // Partners (A3)
        $partners = $filters['partners'] ?? [];
        if (is_string($partners)) $partners = array_map('trim', explode(',', $partners));
        if (is_array($partners)) {
            $partners = array_values(array_unique(array_filter(array_map(
                fn($v) => strtoupper((string)$v),
                $partners
            ))));
        } else {
            $partners = [];
        }
        $norm['partners'] = $partners;

        // Status (Export/Import)
        $canon = function ($v) {
            $s = strtolower(trim((string)$v));
            if (in_array($s, ['export', 'ekspor'], true)) return 'Export';
            if (in_array($s, ['import', 'impor'], true))  return 'Import';
            return null;
        };
        $status = $filters['status'] ?? null;
        if (is_array($status)) {
            $status = array_values(array_filter(array_unique(array_map($canon, $status))));
            if (!count($status)) $status = null;
        } elseif (is_string($status)) {
            $status = $canon($status);
        } else {
            $status = null;
        }
        $norm['status'] = $status;

        // ===== hsCode → hscodes (array 4 digit). Jika 'ALL'/kosong → tidak di-set.
        $hscodes = $filters['hscodes'] ?? ($filters['hsCode'] ?? null);
        if (is_string($hscodes)) {
            $s = trim($hscodes);
            if ($s === '' || strtoupper($s) === 'ALL') {
                $hscodes = [];
            } else {
                $hscodes = array_map('trim', explode(',', $s));
            }
        }
        if (is_array($hscodes)) {
            $hscodes = array_values(array_unique(array_filter(array_map(function ($v) {
                $d = preg_replace('/\D+/', '', (string)$v);
                return strlen($d) === 4 ? $d : null;
            }, $hscodes))));
            if (!empty($hscodes)) {
                $norm['hscodes'] = $hscodes;
            }
        }

        // Reporter (alpha3), default akan fallback ke IDN di resolveReporter()
        $reporter = $filters['reporter'] ?? null;
        if (is_array($reporter)) {
            $reporter = $reporter[0] ?? null;
        }
        if (is_string($reporter)) {
            $reporter = strtoupper(trim($reporter));
            if (strlen($reporter) === 3) {
                $norm['reporter'] = $reporter;
            }
        }

        // Bersihkan null/kosong
        return array_filter($norm, function ($v) {
            if (is_array($v)) return count($v) > 0;
            return !is_null($v) && $v !== '';
        });
    }

    /** Tahun tersedia min/max + daftar tahun distinct untuk sumber */
    protected function getAvailableYears(int $kodeSumber, ?string $reporter = null): array
    {
        $reporter = $reporter ?: $this->REPORTER;
        $conn = DB::connection($this->conn);

        $mm = $conn->table($this->TB_TRADE)
            ->where('Kode_Alpha3_Reporter', $reporter)
            ->where('Kode_Sumber', $kodeSumber)
            ->selectRaw('MIN(Tahun) AS miny, MAX(Tahun) AS maxy')
            ->first();

        if (!$mm || !$mm->miny || !$mm->maxy) {
            return [null, null, []];
        }

        $list = $conn->table($this->TB_TRADE)
            ->where('Kode_Alpha3_Reporter', $reporter)
            ->where('Kode_Sumber', $kodeSumber)
            ->distinct()
            ->orderBy('Tahun')
            ->pluck('Tahun')
            ->map(fn($y) => (int)$y)
            ->toArray();

        return [(int)$mm->miny, (int)$mm->maxy, $list];
    }

    /** Resolusi tahun efektif berdasarkan filter (fallback ke min..max). */
    protected function resolveYears(array $filters, int $kodeSumber): array
    {
        $reporter = $this->resolveReporter($filters);
        [$minY, $maxY, $list] = $this->getAvailableYears($kodeSumber, $reporter);
        if (!$maxY) return [null, null, []];

        $ys = $filters['year_start'] ?? null;
        $ye = $filters['year_end']   ?? null;

        if (is_int($ys) && is_int($ye)) {
            $a = min($ys, $ye);
            $b = max($ys, $ye);
            $a = max($a, $minY);
            $b = min($b, $maxY);
            if ($a > $b) return [$minY, $maxY, $list];
            return [$a, $b, $list];
        }

        if (is_int($ys) && !is_int($ye)) {
            $a = max(min($ys, $maxY), $minY);
            return [$a, $maxY, $list];
        }

        if (!is_int($ys) && is_int($ye)) {
            $b = min(max($ye, $minY), $maxY);
            return [$minY, $b, $list];
        }

        return [$minY, $maxY, $list];
    }

    /** Filter list tahun agar berada pada [y1..y2] */
    protected function filterYearsInRange(array $allYears, int $y1, int $y2): array
    {
        $ys = array_values(array_filter($allYears, fn($y) => is_int($y) && $y >= $y1 && $y <= $y2));
        sort($ys);
        return $ys;
    }

    /** Terapkan rentang tahun. */
    protected function applyYearRange($query, int $y1, int $y2)
    {
        return $query->whereBetween('t.Tahun', [$y1, $y2]);
    }

    protected function tradeBaseQuery(?string $indexName = null, ?string $reporter = null)
    {
        $reporter = $reporter ?: $this->REPORTER;
        $table = $this->TB_TRADE;
        $index = $indexName ? " FORCE INDEX ({$indexName})" : '';
        return DB::connection($this->conn)
            ->table(DB::raw("{$table} as t{$index}"))
            ->where('t.Kode_Alpha3_Reporter', $reporter)
            ->where('t.Kode_Alpha3_Partner', '!=', $reporter);
    }

    protected function resolveReporter(array $filters): string
    {
        $reporter = strtoupper((string) ($filters['reporter'] ?? $this->REPORTER));
        return strlen($reporter) === 3 ? $reporter : $this->REPORTER;
    }

    /** Filter wilayah/dirjen (join ke tbnegara). */
    protected function applyDirjenFilter($query, array $filters)
    {
        if (!empty($filters['dirjen'])) {
            $query->join($this->TB_COUNTRY.' as n_dirjen', 'n_dirjen.Kode_Alpha3', '=', 't.Kode_Alpha3_Partner')
                ->whereIn('n_dirjen.'.$this->COL_DIRJEN, $filters['dirjen']);
        }
        return $query;
    }

    /** Filter berdasarkan panjang HS (level digit). */
    protected function applyHsWhereLength($query, array $filters)
    {
        $hs = $filters['hs'] ?? null;
        if (is_int($hs) && $hs > 0) {
            $hs = max(2, min(10, $hs));
            $query->where('t.hs_len', $hs);
        }
        return $query;
    }

    /** Filter daftar HSCode spesifik (HS 4-digit). */
    protected function applyHsCodesFilter($query, array $filters)
    {
        if (!empty($filters['hscodes']) && is_array($filters['hscodes'])) {
            $query->whereIn('t.HsCode', $filters['hscodes']);
        }
        return $query;
    }

    /** Filter status Export/Import (mendukung array). */
    protected function applyStatusFilter($query, array $filters)
    {
        if (!array_key_exists('status', $filters)) return $query;

        $st = $filters['status'];
        if (is_array($st) && count($st) > 0) {
            return $query->whereIn('t.Status', $st);
        }
        if (is_string($st) && $st !== '') {
            return $query->where('t.Status', $st);
        }
        return $query;
    }

    /** Filter partners (A3). */
    protected function applyPartnersFilter($query, array $filters)
    {
        if (!empty($filters['partners'])) {
            $query->whereIn('t.Kode_Alpha3_Partner', $filters['partners']);
        }
        return $query;
    }

    protected function resolveMonthWindowForSourceOne(array $filters, int $year, string $reporter): ?array
    {
        $q = DB::connection($this->conn)
            ->table(DB::raw("{$this->TB_TRADE} as t FORCE INDEX (idx_trade_filter_partner)"))
            ->select('t.Bulan')
            ->where('t.Kode_Alpha3_Reporter', $reporter)
            ->where('t.Kode_Sumber', 1)
            ->where('t.Tahun', $year);

        $q = $this->applyStatusFilter($q, $filters);
        $q = $this->applyHsWhereLength($q, $filters);
        $q = $this->applyDirjenFilter($q, $filters);
        $q = $this->applyPartnersFilter($q, $filters);
        $q = $this->applyHsCodesFilter($q, $filters);

        $raw = (clone $q)->distinct()->pluck('t.Bulan')->all();
        $monthSet = [];
        foreach ($raw as $m) {
            if (!is_numeric($m)) continue;
            $mi = (int) $m;
            if ($mi >= 1 && $mi <= 12) {
                $monthSet[$mi] = true;
            }
        }

        if (empty($monthSet)) {
            return null;
        }

        ksort($monthSet);
        $months = array_keys($monthSet);
        return [
            'months' => $months,
            'start' => (int) $months[0],
            'end' => (int) $months[count($months) - 1],
        ];
    }

    protected function buildLatestYearMonthCoverageMeta(?array $monthWindow, int $year): ?array
    {
        if (!$monthWindow) {
            return null;
        }

        $start = isset($monthWindow['start']) ? (int) $monthWindow['start'] : null;
        $end = isset($monthWindow['end']) ? (int) $monthWindow['end'] : null;
        if ($start === 1 && $end === 12) {
            return null;
        }

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

        $label = null;
        if (isset($monthMap[$start], $monthMap[$end])) {
            $label = $monthMap[$start].'-'.$monthMap[$end];
        }

        return [
            'year' => $year,
            'label' => $label,
        ];
    }

    /** Util perubahan persentase (opsional). */
    protected function pctChange(int $curr, int $prev): ?float
    {
        if ($prev === 0) return $curr === 0 ? 0.0 : 100.0;
        return round((($curr - $prev) / abs($prev)) * 100.0, 2);
    }
}
