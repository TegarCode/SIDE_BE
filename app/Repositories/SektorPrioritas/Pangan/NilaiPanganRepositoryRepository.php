<?php

namespace App\Repositories\SektorPrioritas\Pangan;

use App\Repositories\SektorPrioritas\Pangan\NilaiPanganRepositoryRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class NilaiPanganRepositoryRepository implements NilaiPanganRepositoryRepositoryInterface
{
  protected string $conn              = 'server_mysql';
  protected string $cacheKeyKomoditas = 'distinct_komoditas';
  protected int    $cacheDuration     = 36400;
  protected string $UNIT              = 'Ribu US$';
  protected string $DEFAULT_REPORTER  = 'IDN';
  protected string $TB_TRADE          = 'tbtrade';
  protected string $TB_COUNTRY        = 'tbnegara';
  protected string $TB_SOURCE         = 'tbsumber';
  protected string $TB_HS             = 'tbharmonized';
  protected string $TB_KOMODITAS      = 'tbkomoditas';
  protected string $COL_DIRJEN        = 'ID_WIl_Kemlu';

  /** ===================== A. KOMODITAS ===================== */
  public function getKomoditas()
  {
    return Cache::remember($this->cacheKeyKomoditas, $this->cacheDuration, function () {
      return DB::connection($this->conn)
        ->table($this->TB_KOMODITAS)
        ->selectRaw('
          MIN(ID_Komoditas) AS id,
          Nama_Komoditas AS nama,
          GROUP_CONCAT(DISTINCT hscode ORDER BY hscode SEPARATOR ",") AS hscodes_csv
        ')
        ->groupBy('Nama_Komoditas')
        ->orderBy('Nama_Komoditas')
        ->get()
        ->map(function ($row) {
          $hscodes = array_values(array_filter(array_unique(array_map(
            fn($x) => trim((string)$x),
            explode(',', (string)$row->hscodes_csv)
          ))));
          unset($row->hscodes_csv);
          $row->hscodes       = $hscodes;
          $row->hscodes_count = count($hscodes);
          return $row;
        });
    });
  }

  /** =========================================================
   *  B. PER-PRODUK (HS) — LANGSUNG DARI TBTRADE SESUAI FILTER
   *  ======================================================= */
  public function nilaiPerdaganganPerProduk(array $filters, int $kodeSumber = 5, int $limit = 50): array
  {
    $filters = $this->normalizeFilters($filters);

    // HS yang dipakai: prioritas hs_list (kirim dari FE), kalau kosong baru dari komoditas_ids
    $unionHs = $this->resolveUnionHsFromFilters($filters);

    // Tentukan rentang tahun berdasar reporter terpilih
    [$y1, $y2, $availableYears] = $this->resolveYears($filters, $kodeSumber);
    if (!$y2) return ['meta' => $this->emptyMeta($filters), 'produk' => []];

    $years = $this->filterYearsInRange($availableYears, $y1, $y2);
    if (empty($years)) return ['meta' => $this->emptyMeta($filters, $availableYears, $y1, $y2), 'produk' => []];
    $yLast = (int) max($years);

    $sumber = $this->getSumber($kodeSumber);

    /** ---------- Base query: tbtrade + filter umum ---------- */
    $base = DB::connection($this->conn)
      ->table($this->TB_TRADE . ' as t')
      ->where('t.Kode_Sumber', $kodeSumber);

    // Reporter (origin)
    if (!empty($filters['reporter'])) {
      $base->whereIn('t.Kode_Alpha3_Reporter', $filters['reporter']);
    } else {
      $base->where('t.Kode_Alpha3_Reporter', $this->DEFAULT_REPORTER);
    }

    // Tahun
    $base = $this->applyYearRange($base, $y1, $y2);

    // Dirjen (opsional)
    $base = $this->applyDirjenFilter($base, $filters);

    // Panjang HS (opsional, hs level)
    $base = $this->applyHsWhereLength($base, $filters);

    // Status (opsional)
    $base = $this->applyStatusFilter($base, $filters);

    // Partner (dest)
    $base = $this->applyPartnerFilter($base, $filters);

    // Jika ada filter komoditas_ids → join ke tbkomoditas
    if (!empty($filters['komoditas_ids'])) {
      $base->join($this->TB_KOMODITAS . ' as k', 'k.hscode', '=', 't.HsCode')
        ->whereIn('k.ID_Komoditas', $filters['komoditas_ids']);
    }

    // Kalau ada HS eksplisit (hs_list atau dari komoditas) → batasi ke daftar itu
    if (!empty($unionHs)) {
      $base->whereIn('t.HsCode', $unionHs);
      // kalau nanti mau prefix: bisa ganti ke applyHsPrefixFilter($base, $unionHs);
    }

    /** ---------- Denominator: total "dunia" per tahun (berdasar filter di atas) ---------- */

    $worldByYear = $this->getWorldTotalsByYear($base, $years);

    /** ---------- Ambil agregasi per-HS per-tahun ---------- */
    $limitHs = max(1, min(500, (int)$limit));

    $rows = (clone $base)
      ->selectRaw("
        t.HsCode,
        t.Tahun,
        SUM(CASE WHEN t.Status = 'Export' THEN t.Nilai ELSE 0 END) AS eksp,
        SUM(CASE WHEN t.Status = 'Import' THEN t.Nilai ELSE 0 END) AS imp
      ")
      ->groupBy('t.HsCode', 't.Tahun')
      ->get();

    $agg = [];
    foreach ($rows as $r) {
      $code = (string)$r->HsCode;
      $yr   = (int)$r->Tahun;
      if (!in_array($yr, $years, true)) continue;

      $ek = (int)$r->eksp;
      $im = (int)$r->imp;

      if (!isset($agg[$code])) {
        $agg[$code] = [
          'ekspor' => array_fill_keys($years, 0),
          'impor'  => array_fill_keys($years, 0),
          'total'  => array_fill_keys($years, 0),
          'share'  => array_fill_keys($years, 0.0),
        ];
      }
      $agg[$code]['ekspor'][$yr] = $ek;
      $agg[$code]['impor'][$yr]  = $im;
      $agg[$code]['total'][$yr]  = $ek + $im;
    }

    // Hitung share per tahun
    foreach ($agg as $code => &$a) {
      foreach ($years as $yr) {
        $den = max(1, (int)($worldByYear[$yr]['world'] ?? 0));
        $a['share'][$yr] = round(($a['total'][$yr] / $den) * 100, 2);
      }
    }
    unset($a);

    // Ambil HS terakhir paling besar → limit
    uasort($agg, fn($A, $B) => ($B['total'][$yLast] <=> $A['total'][$yLast]));
    $agg = array_slice($agg, 0, $limitHs, true);

    // Mapping nama HS
    $codes   = array_keys($agg);
    $hsNames = $this->mapHsNames($codes);

    // Bentuk payload produk
    $produk = [];
    foreach ($agg as $code => $a) {
      $produk[] = [
        'kodeHS' => $code,
        'namaHS' => (string)($hsNames[$code] ?? $code),
        'ekspor' => $a['ekspor'],
        'impor'  => $a['impor'],
        'total'  => $a['total'],
        'share'  => $a['share'],
      ];
    }

    return [
      'meta' => [
        'latest_year'     => $yLast,
        'prev_year'       => (int)min($years),
        'years'           => $years,
        'available_years' => $availableYears,
        'sumber'          => $sumber?->nama,
        'unit'            => $this->UNIT,
        'partner'         => $this->buildCountryMetaCollection($filters['partner']  ?? []),
        'reporter'        => $this->buildCountryMetaCollection($filters['reporter'] ?? []),
        'hs_applied'      => $unionHs,                        // untuk debug di FE
        'komoditas_ids'   => $filters['komoditas_ids'] ?? [], // echo balik
      ],
      'produk' => $produk,
    ];
  }

  public function nilaiPerdaganganPerNegara(array $filters, int $kodeSumber = 5): array
  {
    $filters = $this->normalizeFilters($filters);

    [$y1, $y2, $availableYears] = $this->resolveYears($filters, $kodeSumber);

    if (!$y2) {
      return ['meta' => $this->emptyMeta($filters), 'items' => []];
    }

    if (empty($filters['year_start']) && empty($filters['year_end'])) {
      $years = array_slice($availableYears, -4); // ambil 4 tahun terakhir
      $y1    = min($years);
      $y2    = max($years);
    } else {
      $years = $this->filterYearsInRange($availableYears, $y1, $y2);
    }

    if (empty($years)) {
      return ['meta' => $this->emptyMeta($filters, $availableYears, $y1, $y2), 'items' => []];
    }
    $yLast  = (int) max($years);
    $sumber = $this->getSumber($kodeSumber);

    /** ---------- Base query: tbtrade + filter umum ---------- */
    $base = DB::connection($this->conn)
      ->table($this->TB_TRADE . ' as t')
      ->where('t.Kode_Sumber', $kodeSumber);

    // Reporter (origin)
    if (!empty($filters['reporter'])) {
      $base->whereIn('t.Kode_Alpha3_Reporter', $filters['reporter']);
    } else {
      $base->where('t.Kode_Alpha3_Reporter', $this->DEFAULT_REPORTER);
    }

    // Tahun, Dirjen, HS length, Status, Partner (opsional)
    $base = $this->applyYearRange($base, $y1, $y2);
    $base = $this->applyDirjenFilter($base, $filters);
    $base = $this->applyHsWhereLength($base, $filters);
    $base = $this->applyStatusFilter($base, $filters);
    $base = $this->applyPartnerFilter($base, $filters);

    // Batasi HS berdasar komoditas_ids bila ada
    if (!empty($filters['komoditas_ids'])) {
      $base->join($this->TB_KOMODITAS . ' as k', 'k.hscode', '=', 't.HsCode')
        ->whereIn('k.ID_Komoditas', $filters['komoditas_ids']);
    }

    // HS eksplisit (prioritas hs_list, kalau kosong pakai komoditas_ids)
    $unionHs = $this->resolveUnionHsFromFilters($filters);
    if (!empty($unionHs)) {
      $base->whereIn('t.HsCode', $unionHs);
      // atau pakai prefix: $this->applyHsPrefixFilter($base, $unionHs);
    }

    /** ---------- Denominator "world" per tahun (sesuai base query) ---------- */
    $worldByYear      = $this->getWorldTotalsByYear($base, $years);
    $totalWorldYLast  = (int)($worldByYear[$yLast]['world'] ?? 0);

    /** ---------- Agregasi per NEGARA (partner) per TAHUN ---------- */
    $partnerYearRows = (clone $base)
      ->selectRaw("
            t.Kode_Alpha3_Partner as partner,
            t.Tahun,
            SUM(CASE WHEN t.Status = 'Export' THEN t.Nilai ELSE 0 END) as eksp,
            SUM(CASE WHEN t.Status = 'Import' THEN t.Nilai ELSE 0 END) as imp
        ")
      ->groupBy('t.Kode_Alpha3_Partner', 't.Tahun')
      ->get();

    // Susun seri per-negara
    $partnerAgg = [];
    foreach ($partnerYearRows as $r) {
      $p  = (string)$r->partner;
      $yr = (int)$r->Tahun;
      if (!in_array($yr, $years, true)) continue;

      $ek = (int)$r->eksp;
      $im = (int)$r->imp;

      if (!isset($partnerAgg[$p])) {
        $partnerAgg[$p] = [
          'nilai_perdagangan' => array_fill_keys($years, 0),
          'neraca'            => array_fill_keys($years, 0),
          'proporsi'          => array_fill_keys($years, 0.0),
        ];
      }
      $partnerAgg[$p]['nilai_perdagangan'][$yr] = $ek + $im;
      $partnerAgg[$p]['neraca'][$yr]            = $ek - $im;
    }

    // Hitung proporsi per tahun
    foreach ($partnerAgg as $p => &$agg) {
      foreach ($years as $yr) {
        $den = max(1, (int)($worldByYear[$yr]['world'] ?? 0));
        $agg['proporsi'][$yr] = round(($agg['nilai_perdagangan'][$yr] / $den) * 100, 2);
      }
    }
    unset($agg);

    // Urutkan desc berdasarkan nilai perdagangan tahun terakhir
    uasort($partnerAgg, fn($A, $B) => ($B['nilai_perdagangan'][$yLast] <=> $A['nilai_perdagangan'][$yLast]));

    // Meta negara (nama, a2, a3)
    $countryMetaMap = $this->mapCountryMeta(array_keys($partnerAgg));

    // Bentuk items
    $items = [];
    foreach ($partnerAgg as $codeA3 => $series) {
      $meta = $countryMetaMap[$codeA3] ?? ['nama' => $codeA3, 'a2' => null, 'a3' => $codeA3];
      $items[] = [
        'negara'            => $meta['nama'],
        'kode_alpha2'       => $meta['a2'],
        'kode_alpha3'       => $meta['a3'],
        'nilai_perdagangan' => $series['nilai_perdagangan'],
        'neraca'            => $series['neraca'],
        'proporsi'          => $series['proporsi'],
      ];
    }

    return [
      'meta' => [
        'years'                => $years,
        'total_world'          => $totalWorldYLast,
        'total_world_per_year' => array_map(fn($v) => $v['world'], $worldByYear),
        'sumber'               => $sumber?->nama,
        'applied_filters'      => $filters,
        'hs_level'             => $filters['hs'] ?? null,
        'unit'                 => $this->UNIT,
      ],
      'items' => $items,
    ];
  }

  /** ===================== SHARED HELPERS ===================== */

  /** Map metadata negara: a3 => ['nama' => ..., 'a2' => ..., 'a3' => ...] */
  private function mapCountryMeta(array $alpha3s): array
  {
    if (empty($alpha3s)) return [];

    $rows = DB::connection($this->conn)
      ->table($this->TB_COUNTRY . ' as n')
      ->whereIn('n.Kode_Alpha3', $alpha3s)
      ->select('n.Kode_Alpha3', 'n.Kode_Alpha2', 'n.Negara_IDN')
      ->get();

    $map = [];
    foreach ($rows as $r) {
      $map[$r->Kode_Alpha3] = [
        'nama' => (string)$r->Negara_IDN,
        'a2'   => (string)$r->Kode_Alpha2,
        'a3'   => (string)$r->Kode_Alpha3,
      ];
    }

    // Pastikan semua key ada (fallback ke kode A3)
    foreach ($alpha3s as $a3) {
      if (!isset($map[$a3])) {
        $map[$a3] = ['nama' => $a3, 'a2' => null, 'a3' => $a3];
      }
    }
    return $map;
  }


  protected function getWorldTotalsByYear($base, array $years): array
  {
    $rows = (clone $base)
      ->selectRaw("t.Tahun, SUM(t.Nilai) as total_world")
      ->groupBy('t.Tahun')
      ->get();

    $byYear = [];
    foreach ($years as $yr) $byYear[$yr] = ['world' => 0];
    foreach ($rows as $r) {
      $yr = (int)$r->Tahun;
      if (!array_key_exists($yr, $byYear)) continue;
      $byYear[$yr]['world'] = (int)$r->total_world;
    }
    return $byYear;
  }

  protected function getSumber(int $kodeSumber)
  {
    return DB::connection($this->conn)
      ->table($this->TB_SOURCE)
      ->select('KodeSumber as kode', 'NamaSumber as nama')
      ->where('KodeSumber', $kodeSumber)
      ->first();
  }

  protected function mapHsNames(array $hsCodes): array
  {
    if (empty($hsCodes)) return [];
    $map = DB::connection($this->conn)
      ->table($this->TB_HS)
      ->whereIn('hscode', $hsCodes)
      ->pluck('description', 'hscode');
    return $map->toArray();
  }

  protected function buildCountryMetaCollection(array $alpha3s): array
  {
    if (empty($alpha3s)) return [];
    $rows = DB::connection($this->conn)
      ->table($this->TB_COUNTRY . ' as n')
      ->whereIn('n.Kode_Alpha3', $alpha3s)
      ->select('n.Kode_Alpha3', 'n.Kode_Alpha2', 'n.Negara_IDN')
      ->get();

    $map = [];
    foreach ($rows as $r) {
      $map[$r->Kode_Alpha3] = [
        'nama' => (string)$r->Negara_IDN,
        'a2'   => (string)$r->Kode_Alpha2,
        'a3'   => (string)$r->Kode_Alpha3,
      ];
    }

    $out = [];
    foreach ($alpha3s as $a3) {
      $row   = $map[$a3] ?? null;
      $out[] = ['a3' => $a3, 'a2' => $row['a2'] ?? null, 'nama' => $row['nama'] ?? null];
    }
    return $out;
  }

  /** ===================== NORMALIZERS ===================== */

  protected function emptyMeta(array $filters, array $availableYears = [], $y1 = null, $y2 = null): array
  {
    return [
      'latest_year'          => null,
      'prev_year'            => null,
      'years'                => [],
      'available_years'      => $availableYears,
      'sumber'               => null,
      'total_world'          => 0,
      'total_world_per_year' => [],
      'applied_filters'      => $filters,
      'hs_level'             => $filters['hs'] ?? null,
      'unit'                 => $this->UNIT,
      'format'               => ['unit' => $this->UNIT],
      'effective_years'      => ['start' => $y1, 'end' => $y2],
    ];
  }

  protected function normalizeFilters(array $filters): array
  {
    $norm = [];

    // Tahun
    $ys = $filters['year_start'] ?? null;
    $ye = $filters['year_end']   ?? null;
    $norm['year_start'] = is_numeric($ys) ? (int)$ys : null;
    $norm['year_end']   = is_numeric($ye) ? (int)$ye : null;

    // Level panjang HS (opsional)
    $hsLevel = $filters['hs'] ?? null;
    if (is_string($hsLevel)) {
      $digits  = preg_replace('/\D+/', '', $hsLevel);
      $hsLevel = ($digits === '' ? null : (int)$digits);
    } elseif (is_numeric($hsLevel)) {
      $hsLevel = (int)$hsLevel;
    } else {
      $hsLevel = null;
    }
    $norm['hs'] = $hsLevel;

    // Dirjen
    $dirjen = $filters['dirjen'] ?? [];
    if (is_string($dirjen)) $dirjen = array_map('trim', explode(',', $dirjen));
    if (is_array($dirjen)) {
      $dirjen = array_values(array_unique(array_filter(array_map(fn($v) => strtoupper((string)$v), $dirjen))));
    } else $dirjen = [];
    $norm['dirjen'] = $dirjen;

    // partner/dest
    $partner = $filters['partner'] ?? ($filters['dest'] ?? []);
    if (is_string($partner)) $partner = array_map('trim', explode(',', $partner));
    if (is_array($partner)) {
      $partner = array_values(array_unique(array_filter(array_map(fn($v) => strtoupper((string)$v), $partner))));
    } else $partner = [];
    $norm['partner'] = $partner;

    // reporter/origin
    $reporter = $filters['reporter'] ?? ($filters['origin'] ?? []);
    if (is_string($reporter)) $reporter = array_map('trim', explode(',', $reporter));
    if (is_array($reporter)) {
      $reporter = array_values(array_unique(array_filter(array_map(fn($v) => strtoupper((string)$v), $reporter))));
    } else $reporter = [];
    $norm['reporter'] = $reporter;

    // Status
    $canon = function ($v) {
      $s = strtolower(trim((string)$v));
      if (in_array($s, ['export', 'ekspor'], true)) return 'Export';
      if (in_array($s, ['import', 'impor'], true))  return 'Import';
      return null;
    };
    $status = $filters['status'] ?? null;
    if (is_array($status)) {
      $status = array_values(array_filter(array_unique(array_map($canon, $status))));
      if (!count($status)) $status = null;
    } elseif (is_string($status)) {
      $status = $canon($status);
    } else $status = null;
    if (!is_null($status)) $norm['status'] = $status;

    // komoditas_ids
    $komoditasIds = $filters['komoditas_ids'] ?? $filters['komoditas'] ?? [];
    if (is_string($komoditasIds)) $komoditasIds = array_map('trim', explode(',', $komoditasIds));
    if (!is_array($komoditasIds)) $komoditasIds = [];
    $komoditasIds = array_values(array_unique(array_filter(array_map('intval', $komoditasIds))));
    if (!empty($komoditasIds)) $norm['komoditas_ids'] = $komoditasIds;

    // hs_list (daftar HS eksplisit) — TIDAK lagi fallback ke 'hs' karena itu level panjang
    $hsListRaw = $filters['hs_list'] ?? $filters['hs_codes'] ?? $filters['hsCodes'] ?? [];
    if (is_string($hsListRaw)) $hsListRaw = array_map('trim', explode(',', $hsListRaw));
    if (!is_array($hsListRaw)) $hsListRaw = [];
    $hsList = [];
    foreach ($hsListRaw as $v) {
      $digits = preg_replace('/\D+/', '', (string)$v);
      if ($digits !== '') $hsList[] = $digits;
    }
    $hsList = array_values(array_unique($hsList));
    if (!empty($hsList)) $norm['hs_list'] = $hsList;

    return array_filter($norm, function ($v) {
      if (is_array($v)) return count($v) > 0;
      return !is_null($v) && $v !== '';
    });
  }

  protected function resolveYears(array $filters, int $kodeSumber): array
  {
    [$minY, $maxY, $list] = $this->getAvailableYears($kodeSumber, $filters['reporter'] ?? []);
    if (!$maxY) return [null, null, []];

    $ys = $filters['year_start'] ?? null;
    $ye = $filters['year_end']   ?? null;

    if (is_int($ys) && is_int($ye)) {
      $a = max(min($ys, $ye), $minY);
      $b = min(max($ys, $ye), $maxY);
      if ($a > $b) return [$minY, $maxY, $list];
      return [$a, $b, $list];
    }
    if (is_int($ys) && !is_int($ye)) return [max(min($ys, $maxY), $minY), $maxY, $list];
    if (!is_int($ys) && is_int($ye)) return [$minY, min(max($ye, $minY), $maxY), $list];

    return [$minY, $maxY, $list];
  }

  protected function getAvailableYears(int $kodeSumber, array $reporters = []): array
  {
    $q = DB::connection($this->conn)
      ->table($this->TB_TRADE)
      ->where('Kode_Sumber', $kodeSumber);

    if (!empty($reporters)) {
      $q->whereIn('Kode_Alpha3_Reporter', $reporters);
    } else {
      $q->where('Kode_Alpha3_Reporter', $this->DEFAULT_REPORTER);
    }

    $mm = (clone $q)->selectRaw('MIN(Tahun) AS miny, MAX(Tahun) AS maxy')->first();
    if (!$mm || !$mm->miny || !$mm->maxy) return [null, null, []];

    $list = (clone $q)
      ->distinct()
      ->orderBy('Tahun')
      ->pluck('Tahun')
      ->map(fn($y) => (int)$y)
      ->toArray();

    return [(int)$mm->miny, (int)$mm->maxy, $list];
  }

  protected function filterYearsInRange(array $allYears, int $y1, int $y2): array
  {
    $ys = array_values(array_filter($allYears, fn($y) => is_int($y) && $y >= $y1 && $y <= $y2));
    sort($ys);
    return $ys;
  }

  protected function applyYearRange($query, int $y1, int $y2)
  {
    return $query->whereBetween('t.Tahun', [$y1, $y2]);
  }

  protected function applyDirjenFilter($query, array $filters)
  {
    if (!empty($filters['dirjen'])) {
      $query->join($this->TB_COUNTRY . ' as n_dirjen', 'n_dirjen.Kode_Alpha3', '=', 't.Kode_Alpha3_Partner')
        ->whereIn('n_dirjen.' . $this->COL_DIRJEN, $filters['dirjen']);
    }
    return $query;
  }

  protected function applyHsWhereLength($query, array $filters)
  {
    $hs = $filters['hs'] ?? null;
    if (is_int($hs) && $hs > 0) {
      $hs = max(2, min(10, $hs));
      $query->whereRaw('CHAR_LENGTH(t.HsCode) = ?', [$hs]);
    }
    return $query;
  }

  protected function applyStatusFilter($query, array $filters)
  {
    if (!array_key_exists('status', $filters)) return $query;
    $st = $filters['status'];
    if (is_array($st) && count($st) > 0) return $query->whereIn('t.Status', $st);
    if (is_string($st) && $st !== '')   return $query->where('t.Status', $st);
    return $query;
  }

  protected function applyPartnerFilter($query, array $filters)
  {
    if (!empty($filters['partner'])) {
      $query->whereIn('t.Kode_Alpha3_Partner', $filters['partner']);
    }
    return $query;
  }

  /** Jika butuh prefix match HS2/HS4: gunakan ini (lebih berat) */
  protected function applyHsPrefixFilter($query, array $hsList): void
  {
    $query->where(function ($qq) use ($hsList) {
      foreach ($hsList as $hs) {
        $qq->orWhere('t.HsCode', 'LIKE', $hs . '%');
      }
    });
  }

  /** HS dari komoditas terpilih */
  protected function expandHsFromKomoditas(array $komoditasIds): array
  {
    if (empty($komoditasIds)) return [];
    $rows = DB::connection($this->conn)
      ->table($this->TB_KOMODITAS)
      ->whereIn('ID_Komoditas', $komoditasIds)
      ->pluck('hscode')
      ->toArray();

    $out = [];
    foreach ($rows as $v) {
      $digits = preg_replace('/\D+/', '', (string)$v);
      if ($digits !== '') $out[] = $digits;
    }
    $out = array_values(array_unique($out));
    sort($out, SORT_STRING);
    return $out;
  }

  /**
   * Gabungan HS:
   * - Kalau user kirim hs_list → PAKAI ITU SAJA (prioritas).
   * - Kalau hs_list kosong tapi ada komoditas_ids → pakai HS dari komoditas.
   * - Kalau dua-duanya kosong → tidak batasi HS (return []).
   */
  protected function resolveUnionHsFromFilters(array $filters): array
  {
    // 1. Prioritas hs_list dari filter
    if (!empty($filters['hs_list']) && is_array($filters['hs_list'])) {
      $tmp = [];
      foreach ($filters['hs_list'] as $v) {
        $digits = preg_replace('/\D+/', '', (string)$v);
        if ($digits !== '') $tmp[] = $digits;
      }
      $tmp = array_values(array_unique($tmp));
      sort($tmp, SORT_STRING);
      return $tmp;
    }

    // 2. Kalau hs_list kosong → fallback dari komoditas_ids
    $fromKomoditas = $this->expandHsFromKomoditas($filters['komoditas_ids'] ?? []);
    if (!empty($fromKomoditas)) {
      return $fromKomoditas;
    }

    // 3. Tidak ada filter HS eksplisit
    return [];
  }
}