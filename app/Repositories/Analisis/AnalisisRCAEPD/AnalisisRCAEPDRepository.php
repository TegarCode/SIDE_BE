<?php

namespace App\Repositories\Analisis\AnalisisRCAEPD;

use Illuminate\Support\Facades\DB;

class AnalisisRCAEPDRepository implements AnalisisRCAEPDRepositoryInterface
{
    protected $table = 'tbhasil_rca_epd';
    protected $comparisonTable = 'tbhasilakhir_rca_epd';

    public function getData(array $filters)
    {
        return $this->applyCountryTradeFilters(DB::table($this->table), $filters)
            ->selectRaw('
                Kategori as `Kategori EPD`,
                HsCode as `Kode HS`,
                NamaProduk as `Komoditas`,
                Avg_Growth_Share as `AVG Growth Share`,
                Avg_Growth_Demand as `AVG Growth Demand`,
                Avg_RCA as `AVG RCA`,
                xModel as `X Model`
            ')
            ->orderBy('HsCode')
            ->limit(100)
            ->get();
    }

    public function getCalculation(array $filters)
    {
        return $this->applyCountryTradeFilters(DB::table($this->table), $filters)
            ->orderBy('HsCode')
            ->get()
            ->map(fn ($row) => $this->transformCalculationRow($row))
            ->values();
    }

    public function getComparison(array $filters)
    {
        return DB::table($this->comparisonTable)
            ->select($this->comparisonColumns())
            ->where('KodeNegara_1', $filters['origin'])
            ->where('KodeNegara_2', $filters['dest'])
            ->where('LevelHS', $filters['level'])
            ->orderBy('HsCode')
            ->get()
            ->map(fn ($row) => $this->transformComparisonRow($row))
            ->values();
    }

    public function getXModelOptions(array $filters)
    {
        return DB::table($this->table)
            ->select('xModel')
            ->where('KodeNegara', $filters['dest'])
            ->where('LevelHS', $filters['level'])
            ->whereNotNull('xModel')
            ->distinct()
            ->orderBy('xModel')
            ->pluck('xModel')
            ->filter(fn ($value) => trim((string) $value) !== '')
            ->values();
    }

    private function applyCountryTradeFilters($query, array $filters)
    {
        return $query
            ->where('KodeNegara', $filters['dest'])
            ->where('LevelHS', $filters['level'])
            ->when($filters['x_model'] ?? null, fn ($query, $xModel) => $query->where('xModel', $xModel));
    }

    private function transformCalculationRow(object $row): array
    {
        $result = [];

        foreach ((array) $row as $key => $value) {
            $label = $this->readableCalculationKey($key, $row);

            if ($label === null) {
                continue;
            }

            $result[$label] = $value;
        }

        return $result;
    }

    private function readableCalculationKey(string $key, object $row): ?string
    {
        if (in_array($key, ['ID', 'Year1', 'Year2', 'LevelHS'], true)) {
            return null;
        }

        $map = [
            'KodeNegara' => 'Kode Negara',
            'HsCode' => 'Kode HS',
            'NamaProduk' => 'Nama Produk',
            'Avg_Growth_Share' => 'AVG Growth Share',
            'Avg_Growth_Demand' => 'AVG Growth Demand',
            'Avg_RCA' => 'AVG RCA',
            'Kategori' => 'Kategori EPD',
            'xModel' => 'X Model',
        ];

        if (isset($map[$key])) {
            return $map[$key];
        }

        if (preg_match('/^Tahun([1-5])$/', $key, $matches)) {
            return 'EXP ' . $this->yearLabel($row, (int) $matches[1]);
        }

        if (preg_match('/^Tahun([1-5])_Dunia$/', $key, $matches)) {
            return 'EXP W ' . $this->yearLabel($row, (int) $matches[1]);
        }

        if (preg_match('/^RCA_Tahun([1-5])$/', $key, $matches)) {
            return 'RCA ' . $this->yearLabel($row, (int) $matches[1]);
        }

        if (preg_match('/^Growth_Share([1-5])$/', $key, $matches)) {
            return 'Growth Share ' . $this->yearLabel($row, (int) $matches[1]);
        }

        if (preg_match('/^Growth_Demand([1-5])$/', $key, $matches)) {
            return 'Growth Demand ' . $this->yearLabel($row, (int) $matches[1]);
        }

        return str_replace('_', ' ', $key);
    }

    private function yearLabel(object $row, int $index): string
    {
        $baseYear = isset($row->Year1) && is_numeric($row->Year1) ? (int) $row->Year1 : null;

        if ($baseYear === null) {
            return 'Tahun ' . $index;
        }

        return (string) ($baseYear + $index - 1);
    }

    private function comparisonColumns(): array
    {
        return [
            'KodeNegara_1',
            'KodeNegara_2',
            'HsCode',
            'NamaProduk',
            'AVG_RCA_Asal',
            'AVG_Growth_Share_Asal',
            'AVG_Growth_Demand_Asal',
            'xModel_Asal',
            'Kategori_Asal',
            'AVG_RCA_Tujuan',
            'AVG_Growth_Share_Tujuan',
            'AVG_Growth_Demand_Tujuan',
            'Kategori_Tujuan',
            'xModel_Tujuan',
            'Strategy',
            'Impor_RI_From_World',
            'Impor_RI_From_Partner',
            'Ekspor_RI_To_Partner',
            'Impor_Partner_From_World',
            'Ekspor_RI_To_World',
            'Ekspor_Partner_To_World',
        ];
    }

    private function transformComparisonRow(object $row): array
    {
        $result = [];

        foreach ((array) $row as $key => $value) {
            $label = $this->readableComparisonKey($key);

            if ($label === null) {
                continue;
            }

            $result[$label] = $value;
        }

        return $result;
    }

    private function readableComparisonKey(string $key): ?string
    {
        if ($key === 'LevelHS') {
            return null;
        }

        $map = [
            'KodeNegara_1' => 'Negara 1',
            'KodeNegara_2' => 'Negara 2',
            'HsCode' => 'Kode HS',
            'NamaProduk' => 'Nama Produk',
            'AVG_RCA_Asal' => 'AVG RCA Asal',
            'AVG_Growth_Share_Asal' => 'AVG Growth Share Asal',
            'AVG_Growth_Demand_Asal' => 'AVG Growth Demand Asal',
            'xModel_Asal' => 'X Model Asal',
            'Kategori_Asal' => 'Kategori EPD Asal',
            'AVG_RCA_Tujuan' => 'AVG RCA Tujuan',
            'AVG_Growth_Share_Tujuan' => 'AVG Growth Share Tujuan',
            'AVG_Growth_Demand_Tujuan' => 'AVG Growth Demand Tujuan',
            'xModel_Tujuan' => 'X Model Tujuan',
            'Kategori_Tujuan' => 'Kategori EPD Tujuan',
            'Strategy' => 'Strategy',
            'Impor_RI_From_World' => 'Impor RI dari Dunia',
            'Impor_RI_From_Partner' => 'Impor RI dari Mitra',
            'Ekspor_RI_To_Partner' => 'Ekspor RI ke Mitra',
            'Impor_Partner_From_World' => 'Impor Mitra dari Dunia',
            'Ekspor_RI_To_World' => 'Ekspor RI ke Dunia',
            'Ekspor_Partner_To_World' => 'Ekspor Mitra ke Dunia',
        ];

        return $map[$key] ?? str_replace('_', ' ', $key);
    }
}
