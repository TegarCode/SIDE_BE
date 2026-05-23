<?php

namespace App\Repositories\SektorPrioritas\EkonomiDigital;

use Illuminate\Support\Facades\DB;

class NilaiInfrastrukturRepository implements NilaiInfrastrukturRepositoryInterface
{
  protected string $conn = 'server_mysql';

  protected string $TB_INFRA    = 'tbinfrastructure_digital';
  protected string $TB_PROVINSI = 'tbprovinsi';
  protected string $TB_SUMBER   = 'tbsumber';

  public function getNilaiInfrastruktur(array $filters = []): array
  {
    $db          = DB::connection($this->conn);
    $kodeSumber  = $this->resolveKodeSumber($filters);
    $provIds     = $this->resolveProvinsiIds($filters);
    $a3          = $this->resolveKodeAlpha3($filters);

    // Cari rentang tahun efektif setelah filter dasar
    [$y1, $y2, $availableYears] = $this->resolveYears($filters, $kodeSumber, $provIds, $a3);

    if ($y1 === null || $y2 === null) {
      return [
        'meta' => [
          'params'           => $this->metaParams($filters, null, null, $kodeSumber, $provIds, $a3),
          'available_years'  => [],
          'effective_years'  => [],
          'sumber'           => $this->getNamaSumber($kodeSumber, $db),
          'count'            => 0,
        ],
        'data' => [],
      ];
    }

    // Query utama: agregasi per Provinsi per Tahun
    $rows = $db->table($this->TB_INFRA . ' as t')
      ->join($this->TB_PROVINSI . ' as p', 'p.ID', '=', 't.ID_Provinsi')
      ->leftJoin($this->TB_SUMBER   . ' as s', 's.KodeSumber', '=', 't.Kode_Sumber')
      ->when($kodeSumber !== null, fn($q) => $q->where('t.Kode_Sumber', $kodeSumber))
      ->when(!empty($provIds),      fn($q) => $q->whereIn('t.ID_Provinsi', $provIds))
      ->when($a3 !== null,          fn($q) => $q->where('t.Kode_Alpha3', $a3))
      ->whereBetween('t.Tahun', [$y1, $y2])
      ->select([
        't.ID_Provinsi',
        'p.Provinsi',
        't.Tahun',
      ])
      // SUM untuk hitungan, AVG untuk kecepatan
      ->selectRaw('SUM(t.Jumlah_Penduduk)                  as jumlah_penduduk')
      ->selectRaw('SUM(t.Jumlah_User)                      as jumlah_user')
      ->selectRaw('SUM(t.Jumlah_Kecamatan)                 as jumlah_kecamatan')
      ->selectRaw('SUM(t.Jumlah_Kecamatan_Fiber_Optic)     as jumlah_kecamatan_fiber_optic')
      ->selectRaw('AVG(t.Avg_Download_Speed)               as avg_download_speed')
      ->selectRaw('AVG(t.Avg_Upload_Speed)                 as avg_upload_speed')
      ->groupBy('t.ID_Provinsi', 'p.Provinsi', 't.Tahun')
      ->orderBy('p.Provinsi')
      ->orderBy('t.Tahun')
      ->get();

    // Bentuk map per provinsi
    $byProv = [];
    foreach ($rows as $r) {
      $pid = (int) $r->ID_Provinsi;
      if (!isset($byProv[$pid])) {
        $byProv[$pid] = [
          'ID_Provinsi' => $pid,
          'Provinsi'    => (string) $r->Provinsi,
          'years'       => [],
        ];
      }
      $byProv[$pid]['years'][(int)$r->Tahun] = [
        'Tahun'                          => (int)$r->Tahun,
        'Jumlah_Penduduk'                => is_null($r->jumlah_penduduk)              ? null : (int)   $r->jumlah_penduduk,
        'Jumlah_User'                    => is_null($r->jumlah_user)                  ? null : (int)   $r->jumlah_user,
        'Jumlah_Kecamatan'               => is_null($r->jumlah_kecamatan)             ? null : (int)   $r->jumlah_kecamatan,
        'Jumlah_Kecamatan_Fiber_Optic'   => is_null($r->jumlah_kecamatan_fiber_optic) ? null : (int)   $r->jumlah_kecamatan_fiber_optic,
        'Avg_Download_Speed'             => is_null($r->avg_download_speed)           ? null : (float) $r->avg_download_speed,
        'Avg_Upload_Speed'               => is_null($r->avg_upload_speed)             ? null : (float) $r->avg_upload_speed,
      ];
    }

    // Lengkapkan deret tahun
    $effectiveYears = range((int)$y1, (int)$y2);
    $data = [];
    foreach ($byProv as $prov) {
      $series = [];
      foreach ($effectiveYears as $yy) {
        $series[] = $prov['years'][$yy] ?? [
          'Tahun'                        => (int)$yy,
          'Jumlah_Penduduk'              => null,
          'Jumlah_User'                  => null,
          'Jumlah_Kecamatan'             => null,
          'Jumlah_Kecamatan_Fiber_Optic' => null,
          'Avg_Download_Speed'           => null,
          'Avg_Upload_Speed'             => null,
        ];
      }
      $prov['years'] = $series;
      $data[] = $prov;
    }

    if (empty($data)) {
      return [
        'meta' => [
          'params'           => $this->metaParams($filters, $y1, $y2, $kodeSumber, $provIds, $a3),
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
        'params'           => $this->metaParams($filters, $y1, $y2, $kodeSumber, $provIds, $a3),
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
    $v = $filters['kode_sumber'] ?? $filters['KodeSumber'] ?? 12;
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

  private function resolveKodeAlpha3(array $filters): ?string
  {
    $v = $filters['kode_alpha3'] ?? $filters['Kode_Alpha3'] ?? $filters['a3'] ?? null;
    if (!is_string($v) || $v === '') return null;
    $v = strtoupper(trim($v));
    return preg_match('/^[A-Z]{3}$/', $v) ? $v : null;
  }

  private function resolveYears(array $filters, ?int $kodeSumber, array $provIds, ?string $a3): array
  {
    $db = DB::connection($this->conn);

    $base = $db->table($this->TB_INFRA . ' as t')
      ->when($kodeSumber !== null, fn($q) => $q->where('t.Kode_Sumber', $kodeSumber))
      ->when(!empty($provIds),      fn($q) => $q->whereIn('t.ID_Provinsi', $provIds))
      ->when($a3 !== null,          fn($q) => $q->where('t.Kode_Alpha3', $a3));

    $minYear = (clone $base)->min('t.Tahun');
    $maxYear = (clone $base)->max('t.Tahun');

    if ($minYear === null || $maxYear === null) {
      return [null, null, []];
    }

    $userFrom = isset($filters['tahun_from']) && is_numeric($filters['tahun_from']) ? (int)$filters['tahun_from'] : null;
    $userTo   = isset($filters['tahun_to'])   && is_numeric($filters['tahun_to'])   ? (int)$filters['tahun_to']   : null;

    $y1 = $userFrom !== null ? max((int)$minYear, $userFrom) : (int)$minYear;
    $y2 = $userTo   !== null ? min((int)$maxYear, $userTo)   : (int)$maxYear;

    if ($y1 > $y2) {
      return [null, null, []];
    }

    $yearsAvail = $db->table($this->TB_INFRA . ' as t')
      ->when($kodeSumber !== null, fn($q) => $q->where('t.Kode_Sumber', $kodeSumber))
      ->when(!empty($provIds),      fn($q) => $q->whereIn('t.ID_Provinsi', $provIds))
      ->when($a3 !== null,          fn($q) => $q->where('t.Kode_Alpha3', $a3))
      ->distinct()
      ->orderBy('t.Tahun')
      ->pluck('t.Tahun')
      ->map(fn($y) => (int)$y)
      ->toArray();

    return [$y1, $y2, $yearsAvail];
  }

  private function getNamaSumber(?int $kodeSumber, $db): ?string
  {
    if ($kodeSumber === null) return null;
    $nm = $db->table($this->TB_SUMBER)
      ->where('KodeSumber', $kodeSumber)
      ->value('NamaSumber');
    return $nm ? (string)$nm : null;
  }

  private function metaParams(array $filters, ?int $y1, ?int $y2, ?int $kodeSumber, array $provIds, ?string $a3): array
  {
    return [
      'tahun_from'   => $y1,
      'tahun_to'     => $y2,
      'kode_sumber'  => $kodeSumber,
      'provinsi_ids' => $provIds,
      'kode_alpha3'  => $a3,
      'raw'          => $filters,
    ];
  }
}
