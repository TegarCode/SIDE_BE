<?php

namespace App\Repositories\Indonesia\KinerjaEkonomi;

use Illuminate\Support\Facades\DB;

class KinerjaEkonomiRepository implements KinerjaEkonomiRepositoryInterface
{
  protected string $conn = 'server_mysql';

  protected string $TB_KinerjaEkonomi = 'tbkin_ekonomi';
  protected string $TB_Indikator      = 'tbindikator_kinek';
  protected string $TB_Negara         = 'tbnegara';
  protected string $TB_Sumber         = 'tbsumber';

  /* ===== Meta ===== */
  public function getDistinctTahun()
  {
    $currentYear = (int) now()->year;

    return DB::connection($this->conn)
      ->table($this->TB_KinerjaEkonomi)
      ->whereNotNull('Tahun')
      ->whereBetween('Tahun', [$currentYear - 5, $currentYear])
      ->select('Tahun')
      ->distinct()
      ->orderByDesc('Tahun')
      ->get();
  }

  public function getIndikator()
  {
    return DB::connection($this->conn)
      ->table($this->TB_Indikator . ' as i')
      ->leftJoin($this->TB_Sumber . ' as s', 's.KodeSumber', '=', 'i.KodeSumber')
      ->where('i.Status', 'active')
      ->selectRaw("
          i.ID_Indikator as value,
          CONCAT(
            i.Indikator,
            CASE
              WHEN s.NamaSumber IS NULL OR s.NamaSumber = ''
              THEN ''
              ELSE CONCAT(' (', s.NamaSumber, ')')
            END
          ) as label
        ")
      ->orderBy('i.Indikator')
      ->get();
  }

  public function getIndikatorAll()
  {
    return DB::connection($this->conn)
      ->table($this->TB_Indikator . ' as i')
      ->leftJoin($this->TB_Sumber . ' as s', 's.KodeSumber', '=', 'i.KodeSumber')
      ->selectRaw("
          i.ID_Indikator as value,
          CONCAT(
            i.Indikator,
            CASE
              WHEN s.NamaSumber IS NULL OR s.NamaSumber = ''
              THEN ''
              ELSE CONCAT(' (', s.NamaSumber, ')')
            END
          ) as label
        ")
      ->orderBy('i.Indikator')
      ->get();
  }

  /* ===== Kinerja: AVG per tahun (5 tahun terakhir dari pivot), per negara ===== */
  public function getKinerja(array $filters): array
  {
    $pivotYear   = isset($filters['year']) ? (int)$filters['year'] : null;
    $indicatorId = isset($filters['indicator_id']) ? (int)$filters['indicator_id'] : null;

    if ($indicatorId === null) {
      return [
        'meta' => [
          'indicator_name' => null,
          'keterangan' => null,
          'years'  => [],
          'count'  => 0,
          'sumber' => null,
          'order'  => null,
          'is_yoy' => null,
        ],
        'data' => [],
      ];
    }

    $db = DB::connection($this->conn);

    $indikatorMeta = $db->table($this->TB_Indikator)
      ->where('ID_Indikator', $indicatorId)
      ->select('Indikator', 'Keterangan', 'order', 'is_yoy')
      ->first();
    $orderDir = $indikatorMeta ? strtolower((string) $indikatorMeta->order) : null;
    if (!in_array($orderDir, ['asc', 'desc'], true)) {
      $orderDir = null;
    }
    $isYoy = null;
    if ($indikatorMeta) {
      $rawIsYoy = $indikatorMeta->is_yoy;
      if (!is_null($rawIsYoy)) {
        $isYoy = filter_var($rawIsYoy, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($isYoy === null) $isYoy = (bool) $rawIsYoy;
      }
    }

    // Tahun tersedia utk indikator (ASC)
    $yearsAsc = $db->table($this->TB_KinerjaEkonomi.' as k')
      ->where('k.ID_Indikator', $indicatorId)
      ->whereNotNull('k.Tahun')
      ->distinct()
      ->orderBy('k.Tahun')
      ->pluck('k.Tahun')
      ->map(fn($y) => (int)$y)
      ->unique()
      ->values()
      ->all();

    $window = $this->window5($yearsAsc, $pivotYear); // 5 tahun ASC
    if (empty($window)) {
      return [
        'meta' => [
          'indicator_name' => $indikatorMeta?->Indikator,
          'keterangan' => $indikatorMeta?->Keterangan,
          'years'  => [],
          'count'  => 0,
          'sumber' => null,
          'order'  => $orderDir,
          'is_yoy' => $isYoy,
        ],
        'data' => [],
      ];
    }

    // AVG per negara x tahun
    $rows = $db->table($this->TB_KinerjaEkonomi.' as k')
      ->leftJoin($this->TB_Negara.' as n', 'n.Kode_Alpha3', '=', 'k.Kode_Alpha3')
      ->where('k.ID_Indikator', $indicatorId)
      ->whereIn('k.Tahun', $window)
      ->selectRaw('
        k.Kode_Alpha3,
        n.Kode_Alpha2,
        n.Negara_IDN as Negara,
        k.Tahun,
        AVG(k.Nilai) AS nilai_avg
      ')
      ->groupBy('k.Kode_Alpha3', 'n.Kode_Alpha2', 'n.Negara_IDN', 'k.Tahun')
      ->orderBy('k.Kode_Alpha3')
      ->orderBy('k.Tahun')
      ->get();

    // Grup per negara → deret tahun ASC & fill missing year = null
    $byCountry = [];
    foreach ($rows as $r) {
      $a3 = (string)$r->Kode_Alpha3;
      if (!isset($byCountry[$a3])) {
        $byCountry[$a3] = [
          'kode_alpha3' => $a3,
          'kode_alpha2' => $r->Kode_Alpha2,
          'negara'      => $r->Negara ?? $a3,
          'years'       => [],
        ];
      }
      $byCountry[$a3]['years'][] = [
        'Tahun' => (int)$r->Tahun,
        'Nilai' => is_null($r->nilai_avg) ? null : (float)$r->nilai_avg,
      ];
    }

    $items = array_values(array_map(function ($it) use ($window) {
      $map = [];
      foreach ($it['years'] as $pair) $map[$pair['Tahun']] = $pair['Nilai'];
      $filled = [];
      foreach ($window as $y) $filled[] = ['Tahun' => $y, 'Nilai' => $map[$y] ?? null];
      $it['years'] = $filled;
      return $it;
    }, $byCountry));

    // ===== Ambil NamaSumber dari tbindikator_kinek =====
    $sumber = $db->table($this->TB_Indikator.' as i')
      ->leftJoin($this->TB_Sumber.' as s', 's.KodeSumber', '=', 'i.KodeSumber')
      ->where('i.ID_Indikator', $indicatorId)
      ->value('s.NamaSumber');

    return [
      'meta' => [
        'indicator_name' => $indikatorMeta?->Indikator,
        'keterangan' => $indikatorMeta?->Keterangan,
        'years'  => $window,
        'count'  => count($items),
        'sumber' => $sumber,
        'order'  => $orderDir,
        'is_yoy' => $isYoy,
      ],
      'data' => $items,
    ];
  }

  /* ===== Helpers ===== */

  /**
   * Ambil maks 5 tahun dgn preferensi <= pivot, sisanya > pivot. (ASC)
   * Tanpa pivot → ambil 5 terakhir dari daftar ASC.
   */
  private function window5(array $yearsAsc, ?int $pivot): array
  {
    if (empty($yearsAsc)) return [];
    if ($pivot === null) {
      return array_slice($yearsAsc, max(0, count($yearsAsc) - 5));
    }
    $olderEq = array_values(array_filter($yearsAsc, fn($y) => $y <= $pivot));
    $newer   = array_values(array_filter($yearsAsc, fn($y) => $y >  $pivot));

    $takeOlder = array_slice(array_reverse($olderEq), 0, 5);
    $need      = 5 - count($takeOlder);
    $win       = array_reverse($takeOlder);
    if ($need > 0) $win = array_merge($win, array_slice($newer, 0, $need));

    $win = array_values(array_unique($win));
    sort($win);
    return $win;
  }

  private function metaParams($indicatorId, ?int $year, ?int $kodeSumber): array
  {
    return [
      'indicator_id' => $indicatorId,
      'year'         => $year,
      // tidak lagi pakai kode_sumber dari filter; sumber diambil dari data
    ];
  }
}
