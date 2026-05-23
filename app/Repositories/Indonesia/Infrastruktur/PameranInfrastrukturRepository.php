<?php

namespace App\Repositories\Indonesia\Infrastruktur;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Repositories\Indonesia\Infrastruktur\PameranInfrastrukturRepositoryInterface;

class PameranInfrastrukturRepository implements PameranInfrastrukturRepositoryInterface
{
  /* ===================== Konfigurasi Koneksi & Tabel ===================== */

  protected string $conn = 'server_mysql';
  protected string $TB_Pameran = 'tbpameran';
  protected string $TB_PameranPerwakilan = 'tbpameran_dipwk';
  protected string $TB_COUNTRY    = 'tbnegara';

  /* ============================= API ============================= */
  public function pameranIndonesia(array $filters = [], array $sources = [], int $ttl = 1800): array
  {
    $db = DB::connection($this->conn);

    $base = $db->table($this->TB_Pameran . ' as p')
      ->leftJoin($this->TB_COUNTRY . ' as n', 'n.Kode_Alpha3', '=', 'p.Kode_Alpha3_Penyelenggara');

    // Ambil baris yang dibutuhkan
    $rows = (clone $base)
      ->select([
        'p.Agenda_Kegiatan',
        'p.Kategori',
        'p.Provinsi',
        'p.Tgl_Mulai',
        'p.Tgl_Berakhir',
      ])
      ->orderBy('p.Agenda_Kegiatan')
      ->get();

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

    // Normalisasi menjadi array items
    $items = [];
    foreach ($rows as $r) {
      $items[] = [
        'agenda'      => $this->clean($r->Agenda_Kegiatan ?? null),
        'kategori'       => $this->clean($r->Kategori ?? null),
        'provinsi'   => $this->clean($r->Provinsi ?? null),
        'tgl_mulai' => $this->clean($r->Tgl_Mulai ?? null),
        'tgl_berakhir' => $this->clean($r->Tgl_Berakhir ?? null),
      ];
    }

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

  public function pameranPerwakilan(array $filters = [], array $sources = [], int $ttl = 1800): array
  {
    $db = DB::connection($this->conn);

    // Base query (gabungan + filter wilayah)
    $base = $db->table($this->TB_PameranPerwakilan . ' as p')
      ->leftJoin($this->TB_COUNTRY . ' as n', 'n.Kode_Alpha3', '=', 'p.Kode_Alpha3_Penyelenggara');

    // Filter wilayah (array of ID_WIl_Kemlu)
    if (!empty($filters['wilayah']) && is_array($filters['wilayah'])) {
      $base->whereIn('n.ID_WIl_Kemlu', $filters['wilayah']);
    }

    // Ambil baris yang dibutuhkan
    $rows = (clone $base)
      ->select([
        'p.Perwakilan',
        'p.Wilayah_Kerja',
        'p.Tempat',
        'p.Tanggal',
        'p.Exhibition_Promosi',
        DB::raw('p.Kode_Alpha3_Penyelenggara as alpha3'),
        DB::raw('n.Kode_Alpha2 as alpha2'),
        DB::raw('n.Negara_IDN as negara'),
        DB::raw('n.ID_WIl_Kemlu as wilayah'),
      ])
      ->orderBy('n.Negara_IDN')
      ->get();

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

    // Normalisasi menjadi array items (sesuai kolom hasil select)
    $items = [];
    foreach ($rows as $r) {
      $items[] = [
        'perwakilan'         => $this->clean($r->Perwakilan ?? null),
        'wilayah_kerja'      => $this->clean($r->Wilayah_Kerja ?? null),
        'tempat'             => $this->clean($r->Tempat ?? null),
        'tanggal'            => $this->clean($r->Tanggal ?? null),
        'exhibition_promosi' => $this->clean($r->Exhibition_Promosi ?? null),
        'kode_alpha3'        => $this->clean($r->alpha3 ?? null),
        'kode_alpha2'        => $this->clean($r->alpha2 ?? null),
        'negara'             => $this->clean($r->negara ?? null),
        'wilayah'            => $this->toUpperOrNull($r->wilayah ?? null),
      ];
    }

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
    if (!is_string($v)) return null;
    $s = trim($v);
    return $s === '' ? null : $s;
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
