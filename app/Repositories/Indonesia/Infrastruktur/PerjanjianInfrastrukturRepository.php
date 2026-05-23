<?php

namespace App\Repositories\Indonesia\Infrastruktur;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Repositories\Indonesia\Infrastruktur\PerjanjianInfrastrukturRepositoryInterface;

class PerjanjianInfrastrukturRepository implements PerjanjianInfrastrukturRepositoryInterface
{
    /* ===================== Konfigurasi Koneksi & Tabel ===================== */

    protected string $conn = 'server_mysql';
    protected string $TB_Perjanjian = 'tbperjanjian';
    protected string $TB_WilayahKemlu = 'tbkawasan_satker';

    // nama kolom wilayah di kedua tabel
    protected string $COL_WIL = 'ID_Wil_Kemlu';

    /* ============================= API ============================= */
    public function perjanjianNegara(array $filters = [], array $sources = [], int $ttl = 1800): array
    {
        $db = DB::connection($this->conn);

        $base = $db->table($this->TB_Perjanjian . ' as p');

        // Filter wilayah (kode ID_Wil_Kemlu di tbperjanjian)
        if (!empty($filters['wilayah']) && is_array($filters['wilayah'])) {
            $base->whereIn("p.{$this->COL_WIL}", $filters['wilayah']);
        }

        // Ambil semua kolom dari tbperjanjian, kecuali id & status
        $allCols = Schema::connection($this->conn)->getColumnListing($this->TB_Perjanjian);
        $cols = array_values(array_filter($allCols, function ($c) {
            $lc = strtolower($c);
            return $lc !== 'id' && $lc !== 'status';
        }));

        $selects = [];
        foreach ($cols as $c) {
            $selects[] = DB::raw("p.`{$c}` as `{$c}`");
        }

        // Default ordering
        if (in_array('Agenda_Kegiatan', $cols, true)) {
            $base->orderBy('p.Agenda_Kegiatan');
        } elseif (!empty($cols)) {
            $first = $cols[0];
            $base->orderBy(DB::raw("p.`{$first}`"));
        }

        $rows = (clone $base)->select($selects)->get();

        if ($rows->isEmpty()) {
            return [
                'meta'  => [
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
        $wilayahCodesUsed = [];

        foreach ($rows as $r) {
            // ambil code wilayah MENTAH dulu (tanpa clean)
            $rawCode = isset($r->{$this->COL_WIL}) ? (string) $r->{$this->COL_WIL} : null;

            $arr = (array) $r;

            // Bersihkan semua nilai scalar jadi string trim/null
            foreach ($arr as $k => $v) {
                $arr[$k] = $this->clean($v ?? null);
            }

            // pastikan kolom wilayah ikut dibersihkan & tersimpan
            if ($rawCode !== null) {
                $arr[$this->COL_WIL] = $this->clean($rawCode);
            }

            // kumpulkan kode wilayah unik yang dipakai
            if (!empty($arr[$this->COL_WIL])) {
                $wilayahCodesUsed[] = $arr[$this->COL_WIL];
            }

            $items[] = $arr;
        }

        $wilayahCodesUsed = array_values(array_unique($wilayahCodesUsed));

        // Ambil nama wilayah dari tbkawasan_satker
        $wilayahMap = [];
        if (!empty($wilayahCodesUsed)) {
            $wilayahMap = $db->table($this->TB_WilayahKemlu)
                ->whereIn($this->COL_WIL, $wilayahCodesUsed)
                ->pluck('Nama_Wil_Kemlu', $this->COL_WIL)
                ->toArray();
        }

        // Inject Nama_Wil_Kemlu ke setiap item
        foreach ($items as &$item) {
            $code = $item[$this->COL_WIL] ?? null;
            if ($code && isset($wilayahMap[$code])) {
                $item['Nama_Wil_Kemlu'] = $this->clean($wilayahMap[$code]);
            } else {
                $item['Nama_Wil_Kemlu'] = null;
            }
        }
        unset($item); // putus reference

        return [
            'meta'  => [
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
        if ($v === null) return null;

        // aman untuk semua scalar (string, int, float, bool)
        if (is_scalar($v)) {
            $s = trim((string) $v);
            return $s === '' ? null : $s;
        }

        return null;
    }

    private function toUpperOrNull($v): ?string
    {
        if ($v === null) return null;
        $s = trim((string) $v);
        return $s === '' ? null : strtoupper($s);
    }

    /**
     * Normalisasi kategori untuk filter dari client.
     * - Ubah ke uppercase
     * - Hilangkan duplikat & nilai kosong
     * - Jika client mengirim 'PERWAKILAN', perluasan ke ['KBRI','KJRI','KRI']
     */
    private function normalizeCatFilters($cats): array
    {
        $arr = is_array($cats) ? $cats : [];
        $out = [];
        foreach ($arr as $c) {
            $code = strtoupper(trim((string) $c));
            if ($code === '') continue;

            if ($code === 'PERWAKILAN') {
                $out[] = 'KBRI';
                $out[] = 'KJRI';
                $out[] = 'KRI';
                continue;
            }
            $out[] = $code;
        }
        // unique & reindex
        $out = array_values(array_unique($out));
        return $out;
    }

    private function normalizeCategory($kategoriRaw, string $perwakilanName): string
    {
        $k = $this->toUpperOrNull($kategoriRaw) ?? '';
        if ($k === 'PERBANKAN' || $k === 'BANK') {
            return 'BANK';
        }

        if ($k === 'PERWAKILAN') {
            $prefix = $this->firstWordUpper($perwakilanName);
            if (in_array($prefix, ['KBRI', 'KJRI', 'KRI'], true)) {
                return $prefix;
            }
            return 'PERWAKILAN';
        }

        return $k !== '' ? $k : 'LAINNYA';
    }

    private function firstWordUpper(string $s): string
    {
        $norm = preg_replace('/\s+/u', ' ', trim($s));
        if ($norm === '' || $norm === null) return '';
        $parts = preg_split('/[\s\-—–:]+/u', $norm);
        $first = $parts[0] ?? '';
        return strtoupper($first);
    }

    private function categoryLabel(string $code): string
    {
        switch (strtoupper($code)) {
            case 'BANK':
                return 'Perbankan';
            case 'KBRI':
            case 'KJRI':
            case 'KRI':
                return 'Perwakilan';
            default:
                return $code;
        }
    }
}
