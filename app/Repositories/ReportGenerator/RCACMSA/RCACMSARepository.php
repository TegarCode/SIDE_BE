<?php

namespace App\Repositories\ReportGenerator\RCACMSA;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RCACMSARepository implements RCACMSARepositoryInterface
{
    /* =========================
     * TABLE FILTER DATA
     * ======================= */
    public function getTableFilterData(array $filters): array
    {
        $query = DB::connection('server_mysql')
            ->table('tbhasilakhir')
            ->select(
                'HsCode',
                'NamaProduk',
                'Class_Asal',
                'Class_Tujuan',
                'Strategy',
                'Asal_World',
                'Tujuan_World',
                'Impor_RI_From_World',
                'Impor_RI_From_Partner',
                'Ekspor_RI_To_Partner',
                'Impor_Partner_From_World'
            )
            ->where('KodeNegara_1', $filters['origin'])
            ->where('KodeNegara_2', $filters['destination']);

        // Kalau strategy1 != ALL → filter, kalau ALL → ambil semua strategi
        if (!empty($filters['strategy1']) && $filters['strategy1'] !== 'ALL') {
            $query->where('Strategy', $filters['strategy1']);
        }

        return $query->get()->toArray();
    }

    /* =========================
     * SNAPSHOT
     * ======================= */
    public function getSnapshotData(string $origin, string $destination, string $strategy): array
    {
        $query = DB::connection('server_mysql')
            ->table('tbhasilakhir')
            ->where('KodeNegara_1', $origin)
            ->where('KodeNegara_2', $destination);

        if (!empty($strategy) && $strategy !== 'ALL') {
            $query->where('Strategy', $strategy);
        }

        return $query
            ->orderByDesc('Ekspor_RI_To_Partner')
            ->limit(20)
            ->get()
            ->toArray();
    }

    /* =========================
     * SUMMARY LIST (HS + NamaProduk)
     * ======================= */
    public function getSummaryListData(string $origin, string $destination, string $strategy, int $limit = 10): array
    {
        $query = DB::connection('server_mysql')
            ->table('tbhasilakhir')
            ->select('HsCode', 'NamaProduk')
            ->where('KodeNegara_1', $origin)
            ->where('KodeNegara_2', $destination);

        if (!empty($strategy) && $strategy !== 'ALL') {
            $query->where('Strategy', $strategy);
        }

        return $query
            ->orderByDesc('Ekspor_RI_To_Partner')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /* =========================
     * SUMMARY TABLE (20 besar)
     * ======================= */
    public function getSummaryTableData(string $origin, string $destination, string $strategy): array
    {
        $query = DB::connection('server_mysql')
            ->table('tbhasilakhir')
            ->where('KodeNegara_1', $origin)
            ->where('KodeNegara_2', $destination);

        if (!empty($strategy) && $strategy !== 'ALL') {
            $query->where('Strategy', $strategy);
        }

        return $query
            ->orderByDesc('Ekspor_RI_To_Partner')
            ->limit(20)
            ->get()
            ->toArray();
    }

    /* =========================
     * NAMA NEGARA
     * ======================= */
    public function getCountryName(string $alpha3): string
    {
        return DB::connection('server_mysql')
            ->table('tbnegara')
            ->where('Kode_Alpha3', $alpha3)
            ->value('Negara_IDN') ?? $alpha3;
    }

    /* =========================
     * SUMMARY + METRICS (RCA, CMSA)
     * ======================= */
    public function getSummaryDataWithMetrics(string $origin, string $destination, string $strategy): array
    {
        $query = DB::connection('server_mysql')
            ->table('tbhasilakhir')
            ->select(
                'HsCode',
                'NamaProduk',
                'RCA_Asal',
                'CMSA_Asal',
                'Class_Asal',
                'RCA_Tujuan',
                'CMSA_Tujuan',
                'Class_Tujuan',
                'Strategy',
                'Asal_World',
                'Ekspor_RI_To_Partner'
            )
            ->where('KodeNegara_1', $origin)
            ->where('KodeNegara_2', $destination);

        if (!empty($strategy) && $strategy !== 'ALL') {
            $query->where('Strategy', $strategy);
        }

        return $query
            ->orderByDesc('Ekspor_RI_To_Partner')
            ->limit(20)
            ->get()
            ->toArray();
    }
}
