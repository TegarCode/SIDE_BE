<?php

namespace App\Repositories\Indonesia\EconomyDiplomation;

use App\Repositories\Indonesia\EconomyDiplomation\NilaiWisatawanRepositoryInterface;
use Illuminate\Support\Facades\DB;

class NilaiWisatawanRepository implements NilaiWisatawanRepositoryInterface
{
  protected string $conn = 'server_mysql';

  // ================== KONFIGURASI KUNCI (mudah diubah) ==================
  protected string $REPORTER = 'IDN';
  protected string $TB_INVEST = 'tbtourism';
  protected string $TB_COUNTRY = 'tbnegara';
  protected string $TB_SOURCE  = 'tbsumber';
  protected string $COL_DIRJEN = 'ID_WIl_Kemlu';
  protected string $UNIT = 'Orang';

  // ================== API ==================
  public function nilaiWisatawan(array $filters, ?int $kodeSumber = null, int $limit = 50): array
  {
    $filters = $this->normalizeFilters($filters);
    $strictYears = !empty($filters['strict_source_years']);
    $kodeSumber = $kodeSumber === null ? 1 : (int) $kodeSumber;
    $status  = $this->resolveStatus($filters);
    $availableYears = [];
    if ($strictYears) {
      $baseYears = DB::connection($this->conn)
        ->table($this->TB_INVEST . ' as t')
        ->where('t.Kode_Sumber', $kodeSumber);

      [$homeCol, $partnerCol] = $this->applyStatusConstraints($baseYears, $status);
      $this->applyDirjenFilter($baseYears, $filters, $partnerCol);
      $this->applyPartnerFilter($baseYears, $filters, $partnerCol);

      $availableYears = $this->fetchAvailableYears($baseYears);
      $years = $this->filterYearsByRequest($availableYears, $filters);
      if (empty($years)) {
        return [
          'meta'  => [
            'latest_year'          => null,
            'prev_year'            => null,
            'years'                => [],
            'sumber'               => null,
            'total_world'          => 0,
            'total_world_per_year' => [],
            'applied_filters'      => $filters,
            'status'               => $status,
            'unit'                 => $this->UNIT,
            'format'               => ['unit' => $this->UNIT],
          ],
          'items' => [],
        ];
      }
      $y1 = (int) min($years);
      $y2 = (int) max($years);
    } else {
      [$y1, $y2] = $this->resolveYears($filters, $kodeSumber);
      $years = $y2 ? range($y1, $y2) : [];
    }

    if (!$y2) {
      return [
        'meta'  => [
          'latest_year'          => null,
          'prev_year'            => null,
          'years'                => [],
          'sumber'               => null,
          'total_world'          => 0,
          'total_world_per_year' => [],
          'applied_filters'      => $filters,
          'status'               => $status,
          'unit'                 => $this->UNIT,
          'format'               => ['unit' => $this->UNIT],
        ],
        'items' => [],
      ];
    }

    // sumber data
    $sumber = DB::connection($this->conn)
      ->table($this->TB_SOURCE)
      ->select('KodeSumber as kode', 'NamaSumber as nama')
      ->where('KodeSumber', $kodeSumber)
      ->first();

    // ================== BASE QUERY (rentang tahun + filter umum) ==================
    $base = DB::connection($this->conn)
      ->table($this->TB_INVEST . ' as t')
      ->where('t.Kode_Sumber', $kodeSumber);

    // Terapkan status → kolom mana yang harus = IDN serta partnerCol mana
    [$homeCol, $partnerCol] = $this->applyStatusConstraints($base, $status);

    // Rentang tahun
    $base = $strictYears
      ? $this->applyYearFilter($base, $years)
      : $this->applyYearRange($base, $y1, $y2);

    // Filter Dirjen (berbasis negara partner)
    $base = $this->applyDirjenFilter($base, $filters, $partnerCol);

    // ——— Penting: Pisahkan base untuk WORLD vs PARTNER agar share tetap terhadap dunia ———
    $baseWorld   = clone $base;                         // TANPA filter partners
    $basePartner = $this->applyPartnerFilter(clone $base, $filters, $partnerCol); // DENGAN filter partners (bila ada)

    // ================== TOTAL (INDONESIA) PER TAHUN — tidak terfilter partners ==================
    $worldRows = $baseWorld
      ->selectRaw("t.Tahun, SUM(t.Jumlah_Wisatawan) AS total_world")
      ->groupBy('t.Tahun')
      ->get();

    $worldByYear = [];
    foreach ($years as $yr) $worldByYear[$yr] = 0;
    foreach ($worldRows as $wr) {
      $worldByYear[(int)$wr->Tahun] = (int)$wr->total_world;
    }
    $totalWorldY2 = (int)($worldByYear[$y2] ?? 0);

    // ================== PARTNER PER TAHUN ==================
    $partnerYearRows = $basePartner
      ->selectRaw("
        {$partnerCol} as partner,
        t.Tahun,
        SUM(t.Jumlah_Wisatawan) as nilai
      ")
      ->groupBy($partnerCol, 't.Tahun')
      ->get();

    // Aggregate partner → year series
    $partnerAgg = [];
    foreach ($partnerYearRows as $r) {
      $p  = (string)$r->partner;
      $yr = (int)$r->Tahun;
      $vl = (int)$r->nilai;

      if (!isset($partnerAgg[$p])) {
        $partnerAgg[$p] = [
          'Jumlah_Wisatawan' => array_fill_keys($years, 0),
          'share'            => array_fill_keys($years, 0.0),
        ];
      }
      $partnerAgg[$p]['Jumlah_Wisatawan'][$yr] = $vl;
    }

    // Hitung share per tahun terhadap total dunia (bukan terhadap subset partners)
    foreach ($partnerAgg as $p => &$agg) {
      foreach ($years as $yr) {
        $den = max(1, (int)($worldByYear[$yr] ?? 0));
        $agg['share'][$yr] = round(($agg['Jumlah_Wisatawan'][$yr] / $den) * 100, 2);
      }
    }
    unset($agg);

    // ================== TENTUKAN TAHUN AKTIF UNTUK SORT ==================
    $yearsDesc = array_reverse($years);
    $activeYear = null;

    // 1) Coba tahun total dunia ≠ 0
    foreach ($yearsDesc as $yr) {
      if (isset($worldByYear[$yr]) && (int)$worldByYear[$yr] !== 0) {
        $activeYear = $yr;
        break;
      }
    }
    // 2) Fallback: ada minimal satu negara ≠ 0
    if ($activeYear === null) {
      foreach ($yearsDesc as $yr) {
        $found = false;
        foreach ($partnerAgg as $agg) {
          if ((int)($agg['Jumlah_Wisatawan'][$yr] ?? 0) !== 0) {
            $found = true;
            break;
          }
        }
        if ($found) {
          $activeYear = $yr;
          break;
        }
      }
    }
    // 3) Fallback terakhir: pakai tahun akhir rentang
    if ($activeYear === null) $activeYear = $y2;

    // Tahun sebelumnya (untuk tren YoY)
    $idxActive = array_search($activeYear, $years, true);
    $prevActiveYear = ($idxActive !== false && $idxActive > 0) ? $years[$idxActive - 1] : null;

    // ================== URUTKAN PARTNER BERDASAR NILAI TAHUN AKTIF ==================
    uasort($partnerAgg, function ($a, $b) use ($activeYear) {
      return ($b['Jumlah_Wisatawan'][$activeYear] <=> $a['Jumlah_Wisatawan'][$activeYear]);
    });

    // Jika tidak ada filter partners, terapkan limit top-N
    if (empty($filters['partners']) && is_int($limit) && $limit > 0) {
      $partnerAgg = array_slice($partnerAgg, 0, $limit, true);
    }

    // Info negara partner
    $partnerCodes = array_keys($partnerAgg);
    $countryMap = [];
    if (!empty($partnerCodes)) {
      $countryRows = DB::connection($this->conn)
        ->table($this->TB_COUNTRY . ' as n')
        ->whereIn('n.Kode_Alpha3', $partnerCodes)
        ->select('n.Kode_Alpha3', 'n.Kode_Alpha2', 'n.Negara_IDN')
        ->get();
      foreach ($countryRows as $cr) {
        $countryMap[$cr->Kode_Alpha3] = [
          'nama' => (string)$cr->Negara_IDN,
          'a2'   => (string)$cr->Kode_Alpha2,
          'a3'   => (string)$cr->Kode_Alpha3,
        ];
      }
    }

    // Bentuk items final (SUDAH terurut berdasarkan tahun aktif)
    $items = [];
    foreach ($partnerAgg as $code => $series) {
      $meta = $countryMap[$code] ?? ['nama' => $code, 'a2' => null, 'a3' => $code];
      $items[] = [
        'negara'            => $meta['nama'],
        'kode_alpha2'       => $meta['a2'],
        'kode_alpha3'       => $meta['a3'],
        'Jumlah_Wisatawan'  => $series['Jumlah_Wisatawan'],
        'share'             => $series['share'],
      ];
    }

    // ================== TREN (YoY) ==================
    $trenItems = [];
    foreach ($partnerAgg as $code => $series) {
      $curr = (int)($series['Jumlah_Wisatawan'][$activeYear] ?? 0);
      $prev = $prevActiveYear !== null ? (int)($series['Jumlah_Wisatawan'][$prevActiveYear] ?? 0) : null;

      $delta = ($prevActiveYear === null) ? null : ($curr - $prev);
      if ($prevActiveYear === null) {
        $pct = null;
      } else {
        if ($prev === 0) {
          $pct = ($curr === 0) ? 0.0 : 100.0;
        } else {
          $pct = (($curr - $prev) / abs($prev)) * 100.0;
        }
        $pct = round($pct, 2);
      }

      $meta = $countryMap[$code] ?? ['nama' => $code, 'a2' => null, 'a3' => $code];

      $trenItems[] = [
        'negara'       => $meta['nama'],
        'kode_alpha2'  => $meta['a2'],
        'kode_alpha3'  => $meta['a3'],
        'nilai_prev'   => $prevActiveYear === null ? null : $prev,
        'nilai_curr'   => $curr,
        'delta'        => $delta,
        'delta_pct'    => $pct,
      ];
    }
    // Urutkan tren berdasarkan delta desc (null di bawah)
    usort($trenItems, function ($a, $b) {
      $av = $a['delta'];
      $bv = $b['delta'];
      if ($av === null && $bv === null) return 0;
      if ($av === null) return 1;
      if ($bv === null) return -1;
      return $bv <=> $av;
    });

    return [
      'meta'  => [
        'latest_year'          => (int) $y2,
        'prev_year'            => (int) $y1,
        'years'                => $years,
        'total_world'          => $totalWorldY2,
        'total_world_per_year' => $worldByYear,
        'active_year'          => $activeYear,
        'active_prev_year'     => $prevActiveYear,
        'sumber'               => $sumber?->nama,
        'applied_filters'      => $filters,
        'status'               => $status,
        'unit'                 => $this->UNIT,
        'format'               => ['unit' => $this->UNIT],
      ],
      'items' => $items,

      'tren_wisatawan_masuk' => [
        'tahun'             => $activeYear,
        'tahun_sebelumnya'  => $prevActiveYear,
        'items'             => $trenItems,
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

    // Tahun
    $ys = $filters['year_start'] ?? null;
    $ye = $filters['year_end']   ?? null;
    $norm['year_start'] = is_numeric($ys) ? (int)$ys : null;
    $norm['year_end']   = is_numeric($ye) ? (int)$ye : null;

    // Dirjen -> array unik uppercase
    $dirjen = $filters['dirjen'] ?? [];
    if (is_string($dirjen)) {
      $dirjen = array_map('trim', explode(',', $dirjen));
    }
    if (is_array($dirjen)) {
      $dirjen = array_values(array_unique(array_filter(array_map(
        fn($v) => strtoupper((string)$v),
        $dirjen
      ))));
    } else {
      $dirjen = [];
    }
    $norm['dirjen'] = $dirjen;

    // Partners (kode alpha-3) -> array unik uppercase
    $partners = $filters['partners'] ?? [];
    if (is_string($partners)) {
      $partners = array_map('trim', explode(',', $partners));
    }
    if (is_array($partners)) {
      $partners = array_values(array_unique(array_filter(array_map(
        fn($v) => strtoupper((string)$v),
        $partners
      ))));
    } else {
      $partners = [];
    }
    $norm['partners'] = $partners;

    $status = $filters['status'] ?? null;
    if (is_array($status)) {
      $status = $status[0] ?? null;
    }
    $norm['status'] = $this->canonStatus($status);

    // Hapus null/empty
    return array_filter($norm, function ($v) {
      if (is_array($v)) return count($v) > 0;
      return !is_null($v) && $v !== '';
    });
  }

  protected function canonStatus($v): ?string
  {
    $s = strtolower(trim((string)$v));
    if (in_array($s, ['inbound', 'masuk'], true))  return 'inbound';
    if (in_array($s, ['outbound', 'keluar'], true)) return 'outbound';
    return null; // biar defaulting di resolveStatus()
  }

  protected function resolveStatus(array $filters): string
  {
    // default ke inbound bila tidak diset
    return $filters['status'] ?? 'inbound';
  }

  protected function resolveYears(array $filters, int $kodeSumber): array
  {
    $ys = $filters['year_start'] ?? null;
    $ye = $filters['year_end']   ?? null;

    // Ambil rentang tahun tersedia untuk sumber tsb
    $minYear = DB::connection($this->conn)
      ->table($this->TB_INVEST . ' as t')
      ->where('t.Kode_Sumber', $kodeSumber)
      ->min('t.Tahun');

    $maxYear = DB::connection($this->conn)
      ->table($this->TB_INVEST . ' as t')
      ->where('t.Kode_Sumber', $kodeSumber)
      ->max('t.Tahun');

    if (!$minYear || !$maxYear) {
      return [null, null];
    }

    $minYear = (int) $minYear;
    $maxYear = (int) $maxYear;

    $ys = is_numeric($ys) ? (int)$ys : null;
    $ye = is_numeric($ye) ? (int)$ye : null;

    // Kedua sisi diisi → kembalikan (min..max) yang sudah di-clamp ke rentang tersedia
    if ($ys !== null && $ye !== null) {
      $a = min($ys, $ye);
      $b = max($ys, $ye);
      $a = max($minYear, min($a, $maxYear));
      $b = max($minYear, min($b, $maxYear));
      return [$a, $b];
    }

    // Hanya start → [start..maxAvailable]
    if ($ys !== null) {
      $a = max($minYear, min($ys, $maxYear));
      return [$a, $maxYear];
    }

    // Hanya end → [minAvailable..end]
    if ($ye !== null) {
      $b = max($minYear, min($ye, $maxYear));
      return [$minYear, $b];
    }

    // Tidak ada input tahun → pakai penuh: [minAvailable..maxAvailable]
    return [$minYear, $maxYear];
  }

  protected function applyStatusConstraints($query, string $status): array
  {
    if ($status === 'outbound') {
      $query->where('t.Kode_Alpha3_Asal', $this->REPORTER);
      return ['t.Kode_Alpha3_Asal', 't.Kode_Alpha3_Tujuan'];
    }

    $query->where('t.Kode_Alpha3_Tujuan', $this->REPORTER);
    return ['t.Kode_Alpha3_Tujuan', 't.Kode_Alpha3_Asal'];
  }

  protected function applyYearRange($query, int $y1, int $y2)
  {
    return $query->whereBetween('t.Tahun', [$y1, $y2]);
  }

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

  protected function filterYearsByRequest(array $availableYears, array $filters): array
  {
    $ys = $filters['year_start'] ?? null;
    $ye = $filters['year_end'] ?? null;
    $ys = is_numeric($ys) ? (int) $ys : null;
    $ye = is_numeric($ye) ? (int) $ye : null;

    if ($ys !== null && $ye !== null) {
      $a = min($ys, $ye);
      $b = max($ys, $ye);
      return array_values(array_filter($availableYears, fn($y) => $y >= $a && $y <= $b));
    }
    if ($ys !== null) {
      return array_values(array_filter($availableYears, fn($y) => $y >= $ys));
    }
    if ($ye !== null) {
      return array_values(array_filter($availableYears, fn($y) => $y <= $ye));
    }

    return $availableYears;
  }

  protected function applyDirjenFilter($query, array $filters, string $partnerCol)
  {
    if (!empty($filters['dirjen'])) {
      $query->join($this->TB_COUNTRY . ' as n_dirjen', 'n_dirjen.Kode_Alpha3', '=', DB::raw($partnerCol))
        ->whereIn('n_dirjen.' . $this->COL_DIRJEN, $filters['dirjen']);
    }
    return $query;
  }

  protected function applyPartnerFilter($query, array $filters, string $partnerCol)
  {
    if (!empty($filters['partners'])) {
      // catatan: string 't.Kolom' valid dipakai langsung di whereIn
      $query->whereIn($partnerCol, $filters['partners']);
    }
    return $query;
  }
}
