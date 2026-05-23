<?php

namespace App\Repositories\Analisis\OperationalRisk;

use Illuminate\Support\Facades\DB;

class OperationalRiskRepository implements OperationalRiskRepositoryInterface
{
  protected string $conn = 'server_mysql';

  protected string $TB_OperationalRisk = 'tboperational_risk';
  protected string $TB_Indikator      = 'tboperational_risk_index';
  protected string $TB_Negara         = 'tbnegara';
  protected string $TB_Sumber         = 'tbsumber';

  /* ============================================================ */
  public function getTotalScore(array $filters): array
  {
    // Negara tetap dibaca (untuk meta), tapi TIDAK dipakai untuk filter query
    $negaraArr = $this->toA3Array($filters['negara'] ?? null);
    $db        = DB::connection($this->conn);

    // KodeSumber fixed = 36
    $kodeSumber = 36;
    $sumberMeta = $this->getSumberMeta($kodeSumber);

    // Tahun terbaru untuk SELURUH negara (tanpa filter negara) untuk KodeSumber 36
    $latestYear = $db->table($this->TB_OperationalRisk . ' as k')
      ->where('k.KodeSumber', $kodeSumber)
      ->max('k.Tahun');

    if (!$latestYear) {
      return [
        'meta' => $this->buildMeta($negaraArr, null, [], $sumberMeta),
        'data' => [],
      ];
    }

    $window = $this->last5YearsRange((int)$latestYear);

    // AVG skor per negara per tahun (lintas indikator) dalam window
    // TANPA filter negara → semua negara muncul di map & tabel
    $rows = $db->table($this->TB_OperationalRisk . ' as k')
      ->leftJoin($this->TB_Negara . ' as n', 'n.Kode_Alpha3', '=', 'k.Kode_Alpha3')
      ->where('k.KodeSumber', $kodeSumber)
      ->whereIn('k.Tahun', $window)
      ->select('k.Kode_Alpha3', 'n.Kode_Alpha2', 'n.Negara_IDN as Negara', 'k.Tahun')
      ->selectRaw('AVG(k.Score) as Score_Avg')
      ->groupBy('k.Kode_Alpha3', 'k.Tahun')
      ->orderBy('k.Kode_Alpha3')
      ->orderBy('k.Tahun')
      ->get();

    $grouped = [];
    foreach ($rows as $r) {
      $gid = (string)$r->Kode_Alpha3;
      if (!isset($grouped[$gid])) {
        $grouped[$gid] = [
          'Kode_Alpha3' => $r->Kode_Alpha3,
          'Kode_Alpha2' => $r->Kode_Alpha2,
          'Negara'      => $r->Negara,
          'years'       => [],
        ];
      }
      $grouped[$gid]['years'][] = [
        'Tahun' => (int)$r->Tahun,
        'Score' => is_null($r->Score_Avg) ? null : (float)$r->Score_Avg,
      ];
    }

    foreach ($grouped as &$g) {
      usort($g['years'], fn($a, $b) => $a['Tahun'] <=> $b['Tahun']);
    }
    unset($g);

    ksort($grouped, SORT_STRING);
    $data = array_values($grouped);

    return [
      'meta' => $this->buildMeta($negaraArr, (int)$latestYear, $window, $sumberMeta),
      'data' => $data,
    ];
  }

  /* ============================================================ */
  public function getBreakdownScore(array $filters): array
  {
    $negaraArr = $this->toA3Array($filters['negara'] ?? null);

    if (empty($negaraArr)) {
      return [
        'meta' => [
          'skipped' => true,
          'reason'  => 'missing_negara',
          'negara'  => [],
          'years'   => [],
          'sumber'  => [],
        ],
        'data' => [],
      ];
    }

    $db         = DB::connection($this->conn);
    $kodeSumber = 36;
    $sumberMeta = $this->getSumberMeta($kodeSumber);

    $latestYear = $db->table($this->TB_OperationalRisk . ' as k')
      ->whereIn('k.Kode_Alpha3', $negaraArr)
      ->where('k.KodeSumber', $kodeSumber)
      ->max('k.Tahun');

    if (!$latestYear) {
      return [
        'meta' => $this->buildMeta($negaraArr, null, [], $sumberMeta),
        'data' => [],
      ];
    }

    $window = $this->last5YearsRange((int)$latestYear);

    $rows = $db->table($this->TB_OperationalRisk . ' as k')
      ->join($this->TB_Indikator . ' as i', 'i.ID', '=', 'k.ID_Index')
      ->whereIn('k.Kode_Alpha3', $negaraArr)
      ->where('k.KodeSumber', $kodeSumber)
      ->whereIn('k.Tahun', $window)
      ->select('k.ID_Index', 'i.Nama', 'k.Tahun')
      ->selectRaw('AVG(k.Score) as Score_Avg')
      ->groupBy('k.ID_Index', 'i.Nama', 'k.Tahun')
      ->orderBy('k.ID_Index')
      ->orderBy('k.Tahun')
      ->get();

    $tmp = [];
    foreach ($rows as $r) {
      $gid = (string) $r->ID_Index;
      if (!isset($tmp[$gid])) {
        $tmp[$gid] = [
          'ID_Indikator' => (int) $r->ID_Index,
          'Indikator'    => $r->Nama,
          'years'        => [],
        ];
      }
      $tmp[$gid]['years'][(int) $r->Tahun] = is_null($r->Score_Avg) ? null : (float) $r->Score_Avg;
    }

    $data = [];
    foreach ($tmp as $g) {
      $yearsSeries = [];
      foreach ($window as $y) {
        $yearsSeries[] = [
          'Tahun' => (int) $y,
          'Score' => $g['years'][$y] ?? null,
        ];
      }
      $g['years'] = $yearsSeries;
      $data[] = $g;
    }

    return [
      'meta' => $this->buildMeta($negaraArr, (int)$latestYear, $window, $sumberMeta),
      'data' => $data,
    ];
  }

  /* ============================================================ */
  /* ====================== Helpers ====================== */

  /**
   * Ambil meta sumber: kode + nama dari tbsumber.
   */
  private function getSumberMeta(int $kodeSumber): array
  {
    $db = DB::connection($this->conn);

    $nama = $db->table($this->TB_Sumber)
      ->where('KodeSumber', $kodeSumber)
      ->value('NamaSumber');

    return [
      'nama_sumber' => $nama,
    ];
  }

  private function getNegaraMeta(array $negaraArr): array
  {
    if (empty($negaraArr)) {
      return [];
    }

    $rows = DB::connection($this->conn)
      ->table($this->TB_Negara)
      ->whereIn('Kode_Alpha3', $negaraArr)
      ->select('Kode_Alpha3', 'Kode_Alpha2', 'Negara_IDN')
      ->get()
      ->keyBy('Kode_Alpha3');

    $result = [];
    foreach ($negaraArr as $kodeAlpha3) {
      $negara = $rows->get($kodeAlpha3);
      $result[] = [
        'kode_alpha3' => $kodeAlpha3,
        'kode_alpha2' => $negara->Kode_Alpha2 ?? null,
        'nama'        => $negara->Negara_IDN ?? $kodeAlpha3,
      ];
    }

    return $result;
  }

  private function toA3Array($v): array
  {
    if ($v === null) return [];
    $arr = is_array($v) ? $v : [$v];
    $arr = array_map(fn($x) => strtoupper(trim((string)$x)), $arr);
    return array_values(array_filter($arr, fn($x) => preg_match('/^[A-Z]{3}$/', $x)));
  }

  /** Range 5 tahun terakhir [latest-4 .. latest] ascending */
  private function last5YearsRange(int $latest): array
  {
    $start = max(0, $latest - 4);
    return range($start, $latest);
  }

  private function buildMeta(array $negaraArr, ?int $latestYear, array $years, array $sumberMeta): array
  {
    return [
      'negara'      => $this->getNegaraMeta($negaraArr),
      'years'       => $years,
      'sumber'      => $sumberMeta,
    ];
  }
}
