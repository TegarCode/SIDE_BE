<?php

namespace App\Repositories\Indonesia\EconomyDiplomation;

use App\Repositories\Indonesia\EconomyDiplomation\NilaiBantuanKerjasamaRepositoryInterface;
use Illuminate\Support\Facades\DB;

class NilaiBantuanKerjasamaRepository implements NilaiBantuanKerjasamaRepositoryInterface
{
  protected string $conn = 'server_mysql';

  // ================== KONFIG ==================
  protected string $REPORTER = 'IDN';
  protected string $TB_INVEST = 'tbhibah';
  protected string $TB_COUNTRY = 'tbnegara';
  protected string $TB_SOURCE  = 'tbsumber';
  protected string $TB_REGION  = 'tbkawasan_satker';
  protected string $COL_DIRJEN = 'ID_Wil_Kemlu';
  protected string $UNIT = 'IDR Miliar';

  // ================== API ==================
  public function nilaiBantuanKerjasama(array $filters, ?int $kodeSumber = null, int $limit = 50): array
  {
    $filters = $this->normalizeFilters($filters);
    $strictYears = !empty($filters['strict_source_years']);
    $kodeSumber = $kodeSumber === null ? 21 : (int) $kodeSumber;

    // ====== BASE TANPA TAHUN (untuk deteksi "tahun tersedia") ======
    $partnerCol = 't.Kode_Alpha3';
    $baseNoYear = DB::connection($this->conn)
      ->table($this->TB_INVEST . ' as t')
      ->where('t.Kode_Sumber', $kodeSumber);

    $baseNoYear = $this->applyDirjenFilter($baseNoYear, $filters, $partnerCol);
    $baseNoYear = $this->applyPartnersFilter($baseNoYear, $filters, $partnerCol);

    // ====== RESOLVE YEARS ======
    [$y1, $y2] = $this->resolveYears($filters, $kodeSumber);
    $years = [];

    $hasExplicitYears = isset($filters['year_start']) && isset($filters['year_end']);

    if ($hasExplicitYears && $y1 !== null && $y2 !== null) {
      if ($strictYears) {
        $availableYears = $this->fetchAvailableYears($baseNoYear);
        $years = $this->filterYearsInRange($availableYears, $y1, $y2);
        if (empty($years)) {
          return [
            'meta'  => [
              'latest_year'          => null,
              'prev_year'            => null,
              'years'                => [],
              'sumber'               => null,
              'total_world'          => 0.0,
              'total_world_per_year' => [],
              'applied_filters'      => $filters,
              'unit'                 => $this->UNIT,
              'format'               => ['unit' => $this->UNIT],
            ],
            'items'       => [],
            'per_kawasan' => ['years' => [], 'items' => []],
            'tren_hibah'  => ['tahun' => null, 'tahun_sebelumnya' => null, 'items' => []],
          ];
        }
        $y1 = (int) min($years);
        $y2 = (int) max($years);
      } else {
        // pakai range eksplisit (ASC)
        $years = range($y1, $y2);
      }
    } else {
      // ambil 5 tahun terakhir yang T-E-R-S-E-D-I-A (terfilter dirjen/partners)
      $years = $this->fetchLastYears($baseNoYear, 5); // sorted ASC
      if (empty($years)) {
        return [
          'meta'  => [
            'latest_year'          => null,
            'prev_year'            => null,
            'years'                => [],
            'sumber'               => null,
            'total_world'          => 0.0,
            'total_world_per_year' => [],
            'applied_filters'      => $filters,
            'unit'                 => $this->UNIT,
            'format'               => ['unit' => $this->UNIT],
          ],
          'items'       => [],
          'per_kawasan' => ['years' => [], 'items' => []],
          'tren_hibah'  => ['tahun' => null, 'tahun_sebelumnya' => null, 'items' => []],
        ];
      }
      // set y1/y2 dari list real
      $y1 = (int)min($years);
      $y2 = (int)max($years);
    }

    // ====== sumber data (tbsumber) ======
    $sumber = DB::connection($this->conn)
      ->table($this->TB_SOURCE)
      ->select('KodeSumber as kode', 'NamaSumber as nama')
      ->where('KodeSumber', $kodeSumber)
      ->first();

    // ================== BASE DENGAN FILTER TAHUN AKTUAL ==================
    $base = (clone $baseNoYear);
    $base = $this->applyYearFilter($base, $years); // pakai whereIn ke tahun actually available

    // ================== TOTAL PER TAHUN ==================
    $worldRows = (clone $base)
      ->selectRaw("t.Tahun, SUM(t.Realisasi) AS total_world")
      ->groupBy('t.Tahun')
      ->get();

    $worldByYear = [];
    foreach ($years as $yr) $worldByYear[$yr] = 0.0;
    foreach ($worldRows as $wr) {
      $worldByYear[(int)$wr->Tahun] = (float)$wr->total_world;
    }
    $totalWorldY2 = (float)($worldByYear[$y2] ?? 0.0);

    // ================== PER NEGARA ==================
    $partnerYearRows = (clone $base)
      ->selectRaw("
        {$partnerCol} as partner,
        t.Tahun,
        SUM(t.Realisasi) as nilai,
        COUNT(*) as total_kegiatan
      ")
      ->groupBy($partnerCol, 't.Tahun')
      ->get();

    $partnerAgg = [];
    foreach ($partnerYearRows as $r) {
      $p  = (string)$r->partner;
      $yr = (int)$r->Tahun;
      $vl = (float)$r->nilai;
      $kg = (int)$r->total_kegiatan;

      if (!isset($partnerAgg[$p])) {
        $partnerAgg[$p] = [
          'nilai_bantuan'  => array_fill_keys($years, 0.0),
          'total_kegiatan' => array_fill_keys($years, 0),
          'share'          => array_fill_keys($years, 0.0),
        ];
      }
      $partnerAgg[$p]['nilai_bantuan'][$yr]  = $vl;
      $partnerAgg[$p]['total_kegiatan'][$yr] = $kg;
    }

    foreach ($partnerAgg as $p => &$agg) {
      foreach ($years as $yr) {
        $den = (float)($worldByYear[$yr] ?? 0.0);
        $agg['share'][$yr] = $den == 0.0 ? 0.0 : round(($agg['nilai_bantuan'][$yr] / $den) * 100, 2);
      }
    }
    unset($agg);

    // Tahun aktif (cari tahun terakhir yang ada nilai world atau ada nilai negara)
    $yearsDesc  = array_values(array_reverse($years));
    $activeYear = null;
    foreach ($yearsDesc as $yr) {
      if ((float)($worldByYear[$yr] ?? 0.0) !== 0.0) { $activeYear = $yr; break; }
    }
    if ($activeYear === null) {
      foreach ($yearsDesc as $yr) {
        $found = false;
        foreach ($partnerAgg as $ag) {
          if ((float)($ag['nilai_bantuan'][$yr] ?? 0.0) !== 0.0) { $found = true; break; }
        }
        if ($found) { $activeYear = $yr; break; }
      }
    }
    if ($activeYear === null) $activeYear = $y2;

    $idxActive      = array_search($activeYear, $years, true);
    $prevActiveYear = ($idxActive !== false && $idxActive > 0) ? $years[$idxActive - 1] : null;

    uasort(
      $partnerAgg,
      fn($a, $b) => (($b['nilai_bantuan'][$activeYear] ?? 0.0) <=> ($a['nilai_bantuan'][$activeYear] ?? 0.0))
    );

    // Info negara
    $partnerCodes = array_keys($partnerAgg);
    $countryMap = [];
    if (!empty($partnerCodes)) {
      $countryRows = DB::connection($this->conn)
        ->table($this->TB_COUNTRY . ' as n')
        ->whereIn('n.Kode_Alpha3', $partnerCodes)
        ->select('n.Kode_Alpha3', 'n.Kode_Alpha2', 'n.Negara_IDN', 'n.' . $this->COL_DIRJEN . ' as kawasan')
        ->get();
      foreach ($countryRows as $cr) {
        $countryMap[$cr->Kode_Alpha3] = [
          'nama'    => (string)$cr->Negara_IDN,
          'a2'      => (string)$cr->Kode_Alpha2,
          'a3'      => (string)$cr->Kode_Alpha3,
          'kawasan' => (string)$cr->kawasan,
        ];
      }
    }

    // Items per negara
    $items = [];
    $i = 0;
    foreach ($partnerAgg as $code => $series) {
      $meta = $countryMap[$code] ?? ['nama' => $code, 'a2' => null, 'a3' => $code, 'kawasan' => null];
      $items[] = [
        'negara'          => $meta['nama'],
        'kode_alpha2'     => $meta['a2'],
        'kode_alpha3'     => $meta['a3'],
        'kawasan'         => $meta['kawasan'],
        'nilai_bantuan'   => $series['nilai_bantuan'],
        'share'           => $series['share'],
        'total_kegiatan'  => $series['total_kegiatan'],
      ];
      $i++;
      if ($limit && $i >= $limit) break;
    }

    // Tren hibah (YoY)
    $trenItems = [];
    foreach ($partnerAgg as $code => $series) {
      $curr = (float)($series['nilai_bantuan'][$activeYear] ?? 0.0);
      $prev = $prevActiveYear !== null ? (float)($series['nilai_bantuan'][$prevActiveYear] ?? 0.0) : null;
      $delta = ($prevActiveYear === null) ? null : ($curr - (float)$prev);
      $pct   = ($prevActiveYear === null)
        ? null
        : ((float)$prev === 0.0
            ? ($curr === 0.0 ? 0.0 : 100.0)
            : round((($curr - (float)$prev) / abs((float)$prev)) * 100.0, 2));

      $meta = $countryMap[$code] ?? ['nama' => $code, 'a2' => null, 'a3' => $code, 'kawasan' => null];

      $trenItems[] = [
        'negara'       => $meta['nama'],
        'kode_alpha2'  => $meta['a2'],
        'kode_alpha3'  => $meta['a3'],
        'kawasan'      => $meta['kawasan'],
        'nilai_prev'   => $prevActiveYear === null ? null : (float)$prev,
        'nilai_curr'   => $curr,
        'delta'        => $delta,
        'delta_pct'    => $pct,
      ];
    }
    usort($trenItems, function ($a, $b) {
      $av = $a['delta'];
      $bv = $b['delta'];
      if ($av === null && $bv === null) return 0;
      if ($av === null) return 1;
      if ($bv === null) return -1;
      return $bv <=> $av;
    });

    // ================== PER KAWASAN (tetap seperti semula) ==================
    $kawasanRows = (clone $base)
      ->join($this->TB_COUNTRY . ' as nk', 'nk.Kode_Alpha3', '=', DB::raw($partnerCol))
      ->leftJoin($this->TB_REGION . ' as kw', 'kw.' . $this->COL_DIRJEN, '=', 'nk.' . $this->COL_DIRJEN)
      ->selectRaw("
        nk.{$this->COL_DIRJEN} as kawasan_kode,
        COALESCE(kw.Nama_Wil_Kemlu, nk.{$this->COL_DIRJEN}) as kawasan_nama,
        t.Tahun,

        COUNT(*) as total_program,
        SUM(t.Realisasi) as total_realisasi,

        SUM(CASE WHEN t.`DPRH/Non_DRPH` = 'DRPH' THEN 1 ELSE 0 END) as program_drph,
        SUM(CASE WHEN t.`DPRH/Non_DRPH` = 'DRPH' THEN t.Realisasi ELSE 0 END) as nilai_drph,

        SUM(CASE WHEN (t.`DPRH/Non_DRPH` IS NULL OR t.`DPRH/Non_DRPH`='' OR t.`DPRH/Non_DRPH` <> 'DRPH')
                 THEN 1 ELSE 0 END) as program_non_drph,
        SUM(CASE WHEN (t.`DPRH/Non_DRPH` IS NULL OR t.`DPRH/Non_DRPH`='' OR t.`DPRH/Non_DRPH` <> 'DRPH')
                 THEN t.Realisasi ELSE 0 END) as nilai_non_drph
      ")
      ->groupBy('kawasan_kode', 'kawasan_nama', 't.Tahun')
      ->get();

    $aggKawasan = [];
    foreach ($kawasanRows as $r) {
      $kode = (string)($r->kawasan_kode ?? 'LAINNYA');
      $nama = (string)($r->kawasan_nama ?? $kode);
      $yr   = (int)$r->Tahun;

      if (!isset($aggKawasan[$kode])) {
        $aggKawasan[$kode] = [
          'nama'                => $nama,
          'total_kegiatan'      => array_fill_keys($years, 0),
          'nilai_bantuan'       => array_fill_keys($years, 0.0),
          'share'               => array_fill_keys($years, 0.0),

          'kegiatan_drph'       => array_fill_keys($years, 0),
          'nilai_drph'          => array_fill_keys($years, 0.0),
          'kegiatan_non_drph'   => array_fill_keys($years, 0),
          'nilai_non_drph'      => array_fill_keys($years, 0.0),
        ];
      }

      $aggKawasan[$kode]['nama']                 = $nama;
      $aggKawasan[$kode]['total_kegiatan'][$yr]  = (int)$r->total_program;
      $aggKawasan[$kode]['nilai_bantuan'][$yr]   = (float)$r->total_realisasi;

      $aggKawasan[$kode]['kegiatan_drph'][$yr]   = (int)$r->program_drph;
      $aggKawasan[$kode]['nilai_drph'][$yr]      = (float)$r->nilai_drph;

      $aggKawasan[$kode]['kegiatan_non_drph'][$yr] = (int)$r->program_non_drph;
      $aggKawasan[$kode]['nilai_non_drph'][$yr]    = (float)$r->nilai_non_drph;
    }

    foreach ($aggKawasan as $kode => &$ag) {
      foreach ($years as $yr) {
        $den = (float)($worldByYear[$yr] ?? 0.0);
        $ag['share'][$yr] = $den == 0.0 ? 0.0 : round(($ag['nilai_bantuan'][$yr] / $den) * 100, 2);
      }
    }
    unset($ag);

    uasort($aggKawasan, fn($a, $b) => (($b['nilai_bantuan'][$activeYear] ?? 0.0) <=> ($a['nilai_bantuan'][$activeYear] ?? 0.0)));

    $perKawasanItems = [];
    foreach ($aggKawasan as $kode => $ag) {
      $perKawasanItems[] = [
        'kawasan_kode'      => $kode,
        'kawasan_nama'      => $ag['nama'],

        'total_kegiatan'    => $ag['total_kegiatan'],
        'nilai_bantuan'     => $ag['nilai_bantuan'],
        'share'             => $ag['share'],

        'kegiatan_drph'     => $ag['kegiatan_drph'],
        'nilai_drph'        => $ag['nilai_drph'],
        'kegiatan_non_drph' => $ag['kegiatan_non_drph'],
        'nilai_non_drph'    => $ag['nilai_non_drph'],
      ];
    }

    // ====== prev_year = tahun sebelum latest jika ada ======
    $latestYear = (int)$y2;
    $prevYear = null;
    if (count($years) >= 2) {
      $prevYear = $years[count($years) - 2];
    }

    // ================== RETURN ==================
    return [
      'meta'  => [
        'latest_year'          => $latestYear,
        'prev_year'            => $prevYear,
        'years'                => $years,
        'total_world'          => $totalWorldY2,
        'total_world_per_year' => $worldByYear,
        'active_year'          => $activeYear,
        'active_prev_year'     => $prevActiveYear,
        'sumber'               => $sumber?->nama,
        'applied_filters'      => $filters,
        'unit'                 => $this->UNIT,
        'format'               => ['unit' => $this->UNIT],
      ],

      'items' => $items,

      'per_kawasan' => [
        'years' => $years,
        'items' => $perKawasanItems,
      ],

      'tren_hibah' => [
        'tahun'            => $activeYear,
        'tahun_sebelumnya' => $prevActiveYear,
        'items'            => $trenItems,
      ],
    ];
  }

  // ================== HELPERS ==================
  protected function normalizeFilters(array $filters): array
  {
    $norm = [];
    if (!empty($filters['strict_source_years'])) {
      $norm['strict_source_years'] = true;
    }
    $ys = $filters['year_start'] ?? null;
    $ye = $filters['year_end']   ?? null;
    $norm['year_start'] = is_numeric($ys) ? (int)$ys : null;
    $norm['year_end']   = is_numeric($ye) ? (int)$ye : null;

    // dirjen
    $dirjen = $filters['dirjen'] ?? [];
    if (is_string($dirjen)) $dirjen = array_map('trim', explode(',', $dirjen));
    if (is_array($dirjen)) {
      $dirjen = array_values(array_unique(array_filter(array_map(
        fn($v) => strtoupper((string)$v),
        $dirjen
      ))));
    } else $dirjen = [];
    $norm['dirjen'] = $dirjen;

    // partners (A3)
    $partners = $filters['partners'] ?? [];
    if (is_string($partners)) $partners = array_map('trim', explode(',', $partners));
    if (is_array($partners)) {
      $partners = array_values(array_unique(array_filter(array_map(
        fn($v) => strtoupper((string)$v),
        $partners
      ))));
    } else $partners = [];
    $norm['partners'] = $partners;

    return array_filter($norm, function ($v) {
      if (is_array($v)) return count($v) > 0;
      return !is_null($v) && $v !== '';
    });
  }

  /**
   * Jika user memberi year_start & year_end -> return keduanya (min..max).
   * Jika tidak, fallback lama: ambil maxYear dan (maxYear-1) supaya tidak null,
   * namun nanti akan ditimpa oleh fetchLastYears(...) agar memakai 5 tahun terakhir yang tersedia.
   */
  protected function resolveYears(array $filters, int $kodeSumber): array
  {
    $ys = $filters['year_start'] ?? null;
    $ye = $filters['year_end']   ?? null;

    if (is_int($ys) && is_int($ye)) {
      $a = min($ys, $ye);
      $b = max($ys, $ye);
      return [$a, $b];
    }

    $maxYear = DB::connection($this->conn)
      ->table($this->TB_INVEST)
      ->where('Kode_Sumber', $kodeSumber)
      ->max('Tahun');

    if (!$maxYear) return [null, null];

    $y2 = (int)$maxYear;
    $y1 = $y2 - 1; // placeholder (akan ditimpa oleh fetchLastYears)
    return [$y1, $y2];
  }

  /** Terapkan filter tahun berbasis daftar tahun sebenarnya (whereIn). */
  protected function applyYearFilter($query, array $years)
  {
    $years = array_values(array_unique(array_map('intval', $years)));
    sort($years);
    return $query->whereIn('t.Tahun', $years);
  }

  protected function fetchAvailableYears($baseNoYear): array
  {
    $rows = (clone $baseNoYear)
      ->select('t.Tahun')
      ->distinct()
      ->orderBy('t.Tahun')
      ->pluck('t.Tahun')
      ->toArray();
    return array_values(array_map('intval', $rows));
  }

  protected function filterYearsInRange(array $availableYears, int $y1, int $y2): array
  {
    $a = min($y1, $y2);
    $b = max($y1, $y2);
    return array_values(array_filter($availableYears, fn($y) => $y >= $a && $y <= $b));
  }

  /** Ambil N tahun terakhir yang tersedia dari base query (sudah terfilter sumber/dirjen/partners), urut ASC. */
  protected function fetchLastYears($baseNoYear, int $limit = 5): array
  {
    $rows = (clone $baseNoYear)
      ->select('t.Tahun')
      ->distinct()
      ->orderBy('t.Tahun', 'desc')
      ->limit($limit)
      ->get();

    $desc = array_map(fn($r) => (int)$r->Tahun, $rows->all());
    $asc  = array_values(array_reverse($desc));
    return $asc;
  }

  protected function applyDirjenFilter($query, array $filters, string $partnerCol)
  {
    if (!empty($filters['dirjen'])) {
      $query->join($this->TB_COUNTRY . ' as n_dirjen', 'n_dirjen.Kode_Alpha3', '=', DB::raw($partnerCol))
        ->whereIn('n_dirjen.' . $this->COL_DIRJEN, $filters['dirjen']);
    }
    return $query;
  }

  protected function applyPartnersFilter($query, array $filters, string $partnerCol)
  {
    if (!empty($filters['partners'])) {
      $query->whereIn(DB::raw($partnerCol), $filters['partners']);
    }
    return $query;
  }
}
