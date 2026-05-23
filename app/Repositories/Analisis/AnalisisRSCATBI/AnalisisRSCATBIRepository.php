<?php

namespace App\Repositories\Analisis\AnalisisRSCATBI;

use Illuminate\Support\Facades\DB;

class AnalisisRSCATBIRepository implements AnalisisRSCATBIRepositoryInterface
{
    protected $table = 'tbhasil_rsca_tbi';
    protected $comparisonTable = 'tbhasilakhir_rsca_tbi';

    public function getData(array $filters)
    {
        return DB::table($this->table)
            ->selectRaw("
                HsCode,
                NamaProduk,

                PM_Tahun2 as PM_2019,
                PM_Tahun4 as PM_2023,

                ROUND((tahun2 / NULLIF(tahun2_dunia,0)) * 100, 2) as share_2019,
                ROUND((tahun4 / NULLIF(tahun4_dunia,0)) * 100, 2) as share_2023,

                RSCA_Tahun2 as RSCA_2019,
                RSCA_Tahun4 as RSCA_2023,

                TBI_Tahun2 as TBI_2019,
                TBI_Tahun4 as TBI_2023
            ")
            ->where('KodeNegara', $filters['dest'])
            ->where('LevelHS', $filters['level'])
            ->orderByDesc('RSCA_Tahun4') // bisa diganti dynamic nanti
            ->limit(100)
            ->get();
    }

    public function getCalculation(array $filters)
    {
        return DB::table($this->table)
            ->where('KodeNegara', $filters['dest'])
            ->where('LevelHS', $filters['level'])
            ->get(); // full column
    }

    public function getComparison(array $filters)
    {
        return DB::table($this->comparisonTable)
            ->select($this->comparisonColumns())
            ->where('KodeNegara_1', $filters['origin'])
            ->where('KodeNegara_2', $filters['dest'])
            ->where('LevelHS', $filters['level'])
            ->get()
            ->map(fn ($row) => $this->transformComparisonRow($row))
            ->values();
    }

    private function comparisonColumns(): array
    {
        $columns = [
            'KodeNegara_1',
            'KodeNegara_2',
            'HsCode',
            'NamaProduk',
        ];

        foreach (['RCA_Asal', 'RSCA_Asal', 'RCA_Tujuan', 'RSCA_Tujuan', 'TBI_Asal', 'TBI_Tujuan'] as $base) {
            foreach (array_keys($this->comparisonYearSuffixMap()) as $suffix) {
                $columns[] = "{$base}_{$suffix}";
            }
        }

        foreach (['PM_Asal', 'PM_Tujuan', 'Strategy'] as $base) {
            foreach (array_keys($this->comparisonYearSuffixMap()) as $suffix) {
                $columns[] = "{$base}_{$suffix}";
            }
        }

        return [
            ...$columns,
            'Impor_RI_From_World',
            'Impor_RI_From_Partner',
            'Ekspor_RI_To_Partner',
            'Impor_Partner_From_World',
            'Ekspor_RI_To_World',
            'Ekspor_Partner_To_World',
        ];
    }

    private function comparisonLabelMap(): array
    {
        return [
            'KodeNegara_1' => 'Negara 1',
            'KodeNegara_2' => 'Negara 2',
            'HsCode' => 'Kode HS',
            'NamaProduk' => 'Nama Produk',
            'Impor_RI_From_World' => 'Impor RI dari Dunia',
            'Impor_RI_From_Partner' => 'Impor RI dari Mitra',
            'Ekspor_RI_To_Partner' => 'Ekspor RI ke Mitra',
            'Impor_Partner_From_World' => 'Impor Mitra dari Dunia',
            'Ekspor_RI_To_World' => 'Ekspor RI ke Dunia',
            'Ekspor_Partner_To_World' => 'Ekspor Mitra ke Dunia',
        ];
    }

    private function comparisonYearSuffixMap(): array
    {
        return [
            'Tahun2' => '2019',
            'Tahun4' => '2023',
        ];
    }

    private function readableComparisonKey(string $key): ?string
    {
        if (in_array($key, ['Year1', 'Year2', 'LevelHS'], true)) {
            return null;
        }

        $map = $this->comparisonLabelMap();
        if (isset($map[$key])) {
            return $map[$key];
        }

        $label = $key;
        foreach ($this->comparisonYearSuffixMap() as $suffix => $year) {
            $label = preg_replace(
                '/_' . preg_quote($suffix, '/') . '$/',
                ' ' . $year,
                $label
            );
        }

        return str_replace('_', ' ', $label);
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
}
