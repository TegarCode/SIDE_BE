<?php

namespace App\Repositories\SektorPrioritas\EkonomiDigital;

use Illuminate\Support\Facades\DB;

class NilaiEcommerceRepository implements NilaiEcommerceRepositoryInterface
{
  protected string $conn = 'server_mysql';

  protected string $TB_ECOMMERCE = 'tbecommerce'; 
  protected string $TB_PROVINSI  = 'tbprovinsi';
  protected string $TB_SUMBER    = 'tbsumber';

  public function getNilaiEcommerce(array $filters = []): array
  {
    $db          = DB::connection($this->conn);
    $kodeSumber  = $this->resolveKodeSumber($filters);
    $provIds     = $this->resolveProvinsiIds($filters);

    [$y1, $y2, $availableYears] = $this->resolveYears($filters, $kodeSumber, $provIds);

    if ($y1 === null || $y2 === null) {
      return [
        'meta' => [
          'params'           => $this->metaParams($filters, null, null, $kodeSumber, $provIds),
          'available_years'  => [],
          'effective_years'  => [],
          'sumber'           => $this->getNamaSumber($kodeSumber, $db),
          'count'            => 0,
        ],
        'data' => [],
      ];
    }

    // --- Query utama: agregasi per Provinsi per Tahun
    $rows = $db->table($this->TB_ECOMMERCE . ' as e')
      ->join($this->TB_PROVINSI . ' as p', 'p.ID', '=', 'e.ID_Provinsi')
      ->leftJoin($this->TB_SUMBER . ' as s', 's.KodeSumber', '=', 'e.Kode_Sumber')
      ->when($kodeSumber !== null, fn($q) => $q->where('e.Kode_Sumber', $kodeSumber))
      ->when(!empty($provIds), fn($q) => $q->whereIn('e.ID_Provinsi', $provIds))
      ->whereBetween('e.Tahun', [$y1, $y2])
      ->select([
        'e.ID_Provinsi',
        'p.Provinsi',
        'e.Tahun',
      ])
      // gunakan SUM jika ada kemungkinan lebih dari satu baris per provinsi-tahun
      ->selectRaw('SUM(e.Jumlah_Penduduk)    as jumlah_penduduk')
      ->selectRaw('SUM(e.Jumlah_User)        as jumlah_user')
      ->selectRaw('SUM(e.Volume_Transaksi)   as volume_transaksi')
      ->groupBy('e.ID_Provinsi', 'p.Provinsi', 'e.Tahun')
      ->orderBy('p.Provinsi')
      ->orderBy('e.Tahun')
      ->get();

    $byProv = [];
    foreach ($rows as $r) {
      $pid = (int) $r->ID_Provinsi;
      if (!isset($byProv[$pid])) {
        $byProv[$pid] = [
          'Provinsi'    => (string) $r->Provinsi,
          'years'       => [],
        ];
      }
      $byProv[$pid]['years'][(int)$r->Tahun] = [
        'Tahun'            => (int)$r->Tahun,
        'Jumlah_Penduduk'  => is_null($r->jumlah_penduduk)  ? null : (int) $r->jumlah_penduduk,
        'Jumlah_User'      => is_null($r->jumlah_user)      ? null : (int) $r->jumlah_user,
        'Volume_Transaksi' => is_null($r->volume_transaksi) ? null : (float) $r->volume_transaksi,
      ];
    }

    $effectiveYears = range((int)$y1, (int)$y2);
    $data = [];
    foreach ($byProv as $prov) {
      $series = [];
      foreach ($effectiveYears as $yy) {
        $series[] = $prov['years'][$yy] ?? [
          'Tahun'            => (int)$yy,
          'Jumlah_Penduduk'  => null,
          'Jumlah_User'      => null,
          'Volume_Transaksi' => null,
        ];
      }
      $prov['years'] = $series;
      $data[] = $prov;
    }

    if (empty($data)) {
      return [
        'meta' => [
          'params'           => $this->metaParams($filters, $y1, $y2, $kodeSumber, $provIds),
          'available_years'  => $availableYears,
          'effective_years'  => $effectiveYears,
          'sumber'           => $this->getNamaSumber($kodeSumber, $db),
          'count'            => 0,
        ],
        'data' => [],
      ];
    }

    return [
      'meta' => [
        'params'           => $this->metaParams($filters, $y1, $y2, $kodeSumber, $provIds),
        'available_years'  => $availableYears,
        'effective_years'  => $effectiveYears,
        'sumber'           => $this->getNamaSumber($kodeSumber, $db),
        'count'            => count($data),
      ],
      'data' => $data,
    ];
  }

  /* ====================== Helpers ====================== */

  private function resolveKodeSumber(array $filters): ?int
  {
    $v = $filters['kode_sumber'] ?? $filters['KodeSumber'] ?? 1;
    return is_numeric($v) ? (int) $v : null;
  }

  private function resolveProvinsiIds(array $filters): array
  {
    $v = $filters['provinsi_ids'] ?? $filters['ProvinsiIDs'] ?? $filters['provinsi'] ?? null;
    if ($v === null) return [];
    $arr = is_array($v) ? $v : [$v];
    $arr = array_values(array_filter(array_map(fn($x) => is_numeric($x) ? (int)$x : null, $arr), fn($x) => $x !== null));
    return array_unique($arr);
  }

  private function resolveYears(array $filters, ?int $kodeSumber, array $provIds): array
  {
    $db = DB::connection($this->conn);

    $base = $db->table($this->TB_ECOMMERCE . ' as e')
      ->when($kodeSumber !== null, fn($q) => $q->where('e.Kode_Sumber', $kodeSumber))
      ->when(!empty($provIds), fn($q) => $q->whereIn('e.ID_Provinsi', $provIds));

    $minYear = (clone $base)->min('e.Tahun');
    $maxYear = (clone $base)->max('e.Tahun');

    if ($minYear === null || $maxYear === null) {
      return [null, null, []];
    }

    $userFrom = isset($filters['tahun_from']) && is_numeric($filters['tahun_from']) ? (int)$filters['tahun_from'] : null;
    $userTo   = isset($filters['tahun_to'])   && is_numeric($filters['tahun_to'])   ? (int)$filters['tahun_to']   : null;

    $y1 = $userFrom !== null ? max((int)$minYear, $userFrom) : (int)$minYear;
    $y2 = $userTo   !== null ? min((int)$maxYear, $userTo)   : (int)$maxYear;

    if ($y1 > $y2) {
      // bila user mengirim range terbalik/di luar jangkauan, kosongkan
      return [null, null, []];
    }

    // daftar tahun tersedia (unik) setelah filter dasar
    $yearsAvail = $db->table($this->TB_ECOMMERCE . ' as e')
      ->when($kodeSumber !== null, fn($q) => $q->where('e.Kode_Sumber', $kodeSumber))
      ->when(!empty($provIds), fn($q) => $q->whereIn('e.ID_Provinsi', $provIds))
      ->distinct()
      ->orderBy('e.Tahun')
      ->pluck('e.Tahun')
      ->map(fn($y) => (int)$y)
      ->toArray();

    return [$y1, $y2, $yearsAvail];
  }

  private function getNamaSumber(?int $kodeSumber, $db): ?string
  {
    if ($kodeSumber === null) return null;
    $nm = $db->table($this->TB_SUMMER ?? $this->TB_SUMBER)
      ->where('KodeSumber', $kodeSumber)
      ->value('NamaSumber');
    return $nm ? (string)$nm : null;
  }

  private function metaParams(array $filters, ?int $y1, ?int $y2, ?int $kodeSumber, array $provIds): array
  {
    return [
      'tahun_from'  => $y1,
      'tahun_to'    => $y2,
      'kode_sumber' => $kodeSumber,
      'provinsi_ids'=> $provIds,
      'raw'         => $filters,
    ];
  }
}
