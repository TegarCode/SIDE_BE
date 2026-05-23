<?php

namespace App\Repositories\NegaraMitra\Pariwisata;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TourismRepository implements TourismRepositoryInterface
{
  private const CONN = 'server_mysql';
  private const DEFAULT_SOURCE = 1;
  private const DEFAULT_TABLE = 'tbtourism';

  /* =========================
   * Helpers
   * ========================= */
  private function tourismTable(array $filters = []): string
  {
    return self::DEFAULT_TABLE;
  }

  /** Normalisasi array alpha-3 (uppercase), dukung "all" / "" -> [] */
  private function toAlpha3List(mixed $v): array
  {
    if ($v === null) return [];
    if (is_string($v)) {
      $s = strtoupper(trim($v));
      if ($s === '' || $s === 'ALL') return [];
      return [$s];
    }
    if (is_array($v)) {
      $out = [];
      foreach ($v as $item) {
        $s = strtoupper(trim((string) $item));
        if ($s !== '' && $s !== 'ALL') $out[] = $s;
      }
      return array_values(array_unique($out));
    }
    return [];
  }

  /** Normalisasi source -> array<int> */
  private function normalizedSourceCodes(array $filters): array
  {
    $src = $filters['source'] ?? self::DEFAULT_SOURCE;
    if (is_array($src)) {
      $arr = array_values(array_filter(array_map('intval', $src)));
      return $arr ?: [self::DEFAULT_SOURCE];
    }
    $v = (int) $src;
    return [$v ?: self::DEFAULT_SOURCE];
  }

  /** Ambil peta kode->nama sumber (fallback null jika tabel referensi tak ada) */
  private function getSourceNames(array $codes): array
  {
    $conn = self::CONN;
    $candidates = ['tbsumber', 'tbref_sumber', 'ref_sumber'];
    $table = null;

    foreach ($candidates as $t) {
      if (Schema::connection($conn)->hasTable($t)) {
        $table = $t;
        break;
      }
    }
    if (!$table) return array_fill_keys($codes, null);

    $schema = Schema::connection($conn);
    $codeCol = $schema->hasColumn($table, 'Kode_Sumber') ? 'Kode_Sumber' : 'KodeSumber';
    $nameCol = $schema->hasColumn($table, 'Nama_Sumber') ? 'Nama_Sumber' : 'NamaSumber';

    $rows = DB::connection($conn)
      ->table($table)
      ->whereIn($codeCol, $codes)
      ->select([$codeCol . ' as code', $nameCol . ' as name'])
      ->get();

    $map = [];
    foreach ($rows as $r) {
      $map[(int) $r->code] = $r->name ? (string) $r->name : null;
    }
    foreach ($codes as $c) {
      if (!array_key_exists($c, $map)) $map[$c] = null;
    }
    return $map;
  }

  private function resolveCountryNames(string|array|null $alpha3): array|string|null
  {
    if ($alpha3 === null) return null;

    $list = is_array($alpha3) ? $alpha3 : [$alpha3];
    $list = array_values(array_unique(array_filter(array_map(
      fn($x) => strtoupper(trim((string)$x)),
      $list
    ), fn($x) => $x !== '')));

    if (empty($list)) return is_array($alpha3) ? [] : null;

    $rows = DB::connection(self::CONN)
      ->table('tbnegara')
      ->whereIn('Kode_Alpha3', $list)
      ->pluck('Negara_IDN', 'Kode_Alpha3')
      ->toArray();

    $map = [];
    foreach ($list as $code) {
      $map[$code] = $rows[$code] ?? $code;
    }

    if (is_array($alpha3)) return $map;
    return $map[$list[0]] ?? $list[0];
  }

  /** Terapkan filter source */
  private function applySource(Builder $q, array $filters): Builder
  {
    $codes = $this->normalizedSourceCodes($filters);
    if (count($codes) === 1) {
      $q->where('t.Kode_Sumber', $codes[0]);
    } else {
      $q->whereIn('t.Kode_Sumber', $codes);
    }
    return $q;
  }

  /** Latest year: country bisa ada di sisi asal ATAU tujuan */
  private function applyCountryEitherSide(Builder $q, array $filters): Builder
  {
    $country = strtoupper(trim((string) ($filters['country'] ?? 'IDN')));
    if ($country !== '' && $country !== 'ALL') {
      $q->where(function ($w) use ($country) {
        $w->where('t.Kode_Alpha3_Asal', $country)
          ->orWhere('t.Kode_Alpha3_Tujuan', $country);
      });
    }
    return $q;
  }

  /** Country = tujuan (wisatawan masuk) */
  private function applyCountryInbound(Builder $q, array $filters): Builder
  {
    $country = strtoupper(trim((string) ($filters['country'] ?? 'IDN')));
    if ($country !== '' && $country !== 'ALL') {
      $q->where('t.Kode_Alpha3_Tujuan', $country);
    }
    return $q;
  }

  /** Country = asal (wisatawan keluar) */
  private function applyCountryOutbound(Builder $q, array $filters): Builder
  {
    $country = strtoupper(trim((string) ($filters['country'] ?? 'IDN')));
    if ($country !== '' && $country !== 'ALL') {
      $q->where('t.Kode_Alpha3_Asal', $country);
    }
    return $q;
  }

  /** Terapkan filter OD (origin/dest) bila ada */
  private function applyOriginDest(Builder $q, array $filters): Builder
  {
    $origins = $this->toAlpha3List($filters['origin'] ?? ($filters['origins'] ?? null));
    $dests   = $this->toAlpha3List($filters['dest']   ?? ($filters['dests']   ?? null));

    if (!empty($origins)) {
      $q->whereIn('t.Kode_Alpha3_Asal', $origins);
    }
    if (!empty($dests)) {
      $q->whereIn('t.Kode_Alpha3_Tujuan', $dests);
    }

    return $q;
  }

  private function applyExcludeWor(Builder $q): Builder
  {
    return $q->where('t.Kode_Alpha3_Asal', '!=', 'WOR')
      ->where('t.Kode_Alpha3_Tujuan', '!=', 'WOR');
  }

  /** Cek mode bilateral (origin & dest sama-sama diisi) */
  private function isBilateral(array $filters): bool
  {
    $origins = $this->toAlpha3List($filters['origin'] ?? ($filters['origins'] ?? null));
    $dests   = $this->toAlpha3List($filters['dest']   ?? ($filters['dests']   ?? null));
    return !empty($origins) && !empty($dests);
  }

  /* =========================
   * Queries
   * ========================= */

  public function getLatestYear(array $filters): ?int
  {
    $table = $this->tourismTable($filters);
    $bilat = $this->isBilateral($filters);

    $origins = $this->toAlpha3List($filters['origin'] ?? ($filters['origins'] ?? null));
    $dests   = $this->toAlpha3List($filters['dest']   ?? ($filters['dests']   ?? null));

    $q = DB::connection(self::CONN)
      ->table($table . ' as t')
      ->selectRaw('MAX(t.Tahun) as y');

    $q = $this->applyExcludeWor($q);

    $q = $this->applySource($q, $filters);

    if ($bilat) {
      // Untuk cari tahun, cukup lihat arah origin -> dest
      $q->whereIn('t.Kode_Alpha3_Asal', $origins)
        ->whereIn('t.Kode_Alpha3_Tujuan', $dests);
    } elseif (!empty($origins) || !empty($dests)) {
      // Kalau user cuma isi salah satu, pakai filter OD biasa
      $q = $this->applyOriginDest($q, $filters);
    } else {
      // Fallback country
      $q = $this->applyCountryEitherSide($q, $filters);
    }

    $row = $q->first();
    $year = $row?->y ? (int) $row->y : null;
    return $year;
  }

  /** Ringkasan: wisatawan masuk (count + spending) & keluar (count), tahun & tahun-1 */
  public function getSummary(array $filters): array
  {
    $year = (int) ($filters['year'] ?? 0);
    if ($year <= 0) {
      return [
        'inbound_count_now'     => 0,
        'inbound_spending_now'  => 0.0,
        'outbound_count_now'    => 0,
        'inbound_count_prev'    => 0,
        'inbound_spending_prev' => 0.0,
        'outbound_count_prev'   => 0,
      ];
    }

    $prevYear = $year - 1;
    $bilat    = $this->isBilateral($filters);

    $origins  = $this->toAlpha3List($filters['origin'] ?? ($filters['origins'] ?? null));
    $dests    = $this->toAlpha3List($filters['dest']   ?? ($filters['dests']   ?? null));

    // INBOUND (wisatawan masuk)
    $qIn = DB::connection(self::CONN)
      ->table($this->tourismTable($filters) . ' as t')
      ->selectRaw("
        SUM(CASE WHEN t.Tahun = ? THEN COALESCE(t.Jumlah_Wisatawan,0) ELSE 0 END) AS cnt_now,
        SUM(CASE WHEN t.Tahun = ? THEN COALESCE(t.Nilai_Spending,0)   ELSE 0 END) AS spend_now,
        SUM(CASE WHEN t.Tahun = ? THEN COALESCE(t.Jumlah_Wisatawan,0) ELSE 0 END) AS cnt_prev,
        SUM(CASE WHEN t.Tahun = ? THEN COALESCE(t.Nilai_Spending,0)   ELSE 0 END) AS spend_prev
      ", [$year, $year, $prevYear, $prevYear])
      ->whereIn('t.Tahun', [$year, $prevYear]);

    $qIn = $this->applyExcludeWor($qIn);

    $qIn = $this->applySource($qIn, $filters);

    if ($bilat) {
      // Inbound bilateral: Asal = origin, Tujuan = dest
      $qIn->whereIn('t.Kode_Alpha3_Asal', $origins)
          ->whereIn('t.Kode_Alpha3_Tujuan', $dests);
    } else {
      // Mode country (seperti versi awal): inbound = tujuan = country
      $qIn = $this->applyCountryInbound($qIn, $filters);
    }

    $inRow = $qIn->first();

    // OUTBOUND (wisatawan keluar)
    $qOut = DB::connection(self::CONN)
      ->table($this->tourismTable($filters) . ' as t')
      ->selectRaw("
        SUM(CASE WHEN t.Tahun = ? THEN COALESCE(t.Jumlah_Wisatawan,0) ELSE 0 END) AS cnt_now,
        SUM(CASE WHEN t.Tahun = ? THEN COALESCE(t.Jumlah_Wisatawan,0) ELSE 0 END) AS cnt_prev
      ", [$year, $prevYear])
      ->whereIn('t.Tahun', [$year, $prevYear]);

    $qOut = $this->applyExcludeWor($qOut);

    $qOut = $this->applySource($qOut, $filters);

    if ($bilat) {
      // OUTBOUND bilateral = kebalikan:
      // wisatawan keluar dari dest ke origin → Asal = dest, Tujuan = origin
      $qOut->whereIn('t.Kode_Alpha3_Asal', $dests)
           ->whereIn('t.Kode_Alpha3_Tujuan', $origins);
    } else {
      // Mode country: outbound = asal = country
      $qOut = $this->applyCountryOutbound($qOut, $filters);
    }

    $outRow = $qOut->first();

    return [
      'inbound_count_now'     => (int) ($inRow->cnt_now      ?? 0),
      'inbound_spending_now'  => (float) ($inRow->spend_now  ?? 0),
      'inbound_count_prev'    => (int) ($inRow->cnt_prev     ?? 0),
      'inbound_spending_prev' => (float) ($inRow->spend_prev ?? 0),

      'outbound_count_now'    => (int) ($outRow->cnt_now     ?? 0),
      'outbound_count_prev'   => (int) ($outRow->cnt_prev    ?? 0),
    ];
  }

  /** Tabel inbound: asal turis ke negara (wisatawan masuk) */
  public function getInboundByPartner(array $filters): array
  {
    $year = (int) ($filters['year'] ?? 0);
    if ($year <= 0) return [];

    $prevYear = $year - 1;
    $limit    = (int) ($filters['limit'] ?? 20);

    $bilat   = $this->isBilateral($filters);
    $origins = $this->toAlpha3List($filters['origin'] ?? ($filters['origins'] ?? null));
    $dests   = $this->toAlpha3List($filters['dest']   ?? ($filters['dests']   ?? null));

    $q = DB::connection(self::CONN)
      ->table($this->tourismTable($filters) . ' as t')
      ->leftJoin('tbnegara as c', 'c.Kode_Alpha3', '=', 't.Kode_Alpha3_Asal')
      ->selectRaw("
        t.Kode_Alpha3_Asal as partner_code,
        c.Kode_Alpha2      as a2,
        COALESCE(c.Negara_IDN, t.Kode_Alpha3_Asal) as partner_name,

        SUM(CASE WHEN t.Tahun = ? THEN COALESCE(t.Jumlah_Wisatawan,0) ELSE 0 END) AS total_value_now,
        SUM(CASE WHEN t.Tahun = ? THEN COALESCE(t.Nilai_Spending,0)   ELSE 0 END) AS total_spending_now,

        SUM(CASE WHEN t.Tahun = ? THEN COALESCE(t.Jumlah_Wisatawan,0) ELSE 0 END) AS total_value_prev,
        SUM(CASE WHEN t.Tahun = ? THEN COALESCE(t.Nilai_Spending,0)   ELSE 0 END) AS total_spending_prev
      ", [$year, $year, $prevYear, $prevYear])
      ->whereIn('t.Tahun', [$year, $prevYear]);

    $q = $this->applyExcludeWor($q);

    $q = $this->applySource($q, $filters);

    if ($bilat) {
      // Inbound bilateral: Asal = origin, Tujuan = dest
      $q->whereIn('t.Kode_Alpha3_Asal', $origins)
        ->whereIn('t.Kode_Alpha3_Tujuan', $dests);
    } else {
      // Mode country: inbound = tujuan = country
      $q = $this->applyCountryInbound($q, $filters);
    }

    $rows = $q->groupBy('t.Kode_Alpha3_Asal', 'c.Negara_IDN', 'c.Kode_Alpha2')
      ->orderByDesc('total_value_now')
      ->when($limit > 0, fn ($qq) => $qq->limit($limit))
      ->get();

    return collect($rows)->map(fn ($r) => [
      'code'          => (string) $r->partner_code,
      'a2'            => (string) ($r->a2 ?? ''),
      'label'         => (string) $r->partner_name,
      'value_now'     => (int) ($r->total_value_now     ?? 0),
      'value_prev'    => (int) ($r->total_value_prev    ?? 0),
      'spending_now'  => (float) ($r->total_spending_now  ?? 0),
      'spending_prev' => (float) ($r->total_spending_prev ?? 0),
    ])->all();
  }

  /** Tabel outbound: tujuan turis dari negara (wisatawan keluar) */
  public function getOutboundByPartner(array $filters): array
  {
    $year = (int) ($filters['year'] ?? 0);
    if ($year <= 0) return [];

    $prevYear = $year - 1;
    $limit    = (int) ($filters['limit'] ?? 20);

    $bilat   = $this->isBilateral($filters);
    $origins = $this->toAlpha3List($filters['origin'] ?? ($filters['origins'] ?? null));
    $dests   = $this->toAlpha3List($filters['dest']   ?? ($filters['dests']   ?? null));

    $q = DB::connection(self::CONN)
      ->table($this->tourismTable($filters) . ' as t')
      // partner = negara tujuan (kemana warga kita pergi)
      ->leftJoin('tbnegara as c', 'c.Kode_Alpha3', '=', 't.Kode_Alpha3_Tujuan')
      ->selectRaw("
        t.Kode_Alpha3_Tujuan as partner_code,
        c.Kode_Alpha2        as a2,
        COALESCE(c.Negara_IDN, t.Kode_Alpha3_Tujuan) as partner_name,

        SUM(CASE WHEN t.Tahun = ? THEN COALESCE(t.Jumlah_Wisatawan,0) ELSE 0 END) AS total_value_now,
        SUM(CASE WHEN t.Tahun = ? THEN COALESCE(t.Jumlah_Wisatawan,0) ELSE 0 END) AS total_value_prev
      ", [$year, $prevYear])
      ->whereIn('t.Tahun', [$year, $prevYear]);

    $q = $this->applyExcludeWor($q);

    $q = $this->applySource($q, $filters);

    if ($bilat) {
      // OUTBOUND bilateral: Asal = dest, Tujuan = origin (kebalikan inbound)
      $q->whereIn('t.Kode_Alpha3_Asal', $dests)
        ->whereIn('t.Kode_Alpha3_Tujuan', $origins);
    } else {
      // Mode country: outbound = asal = country
      $q = $this->applyCountryOutbound($q, $filters);
    }

    $rows = $q->groupBy('t.Kode_Alpha3_Tujuan', 'c.Negara_IDN', 'c.Kode_Alpha2')
      ->orderByDesc('total_value_now')
      ->when($limit > 0, fn ($qq) => $qq->limit($limit))
      ->get();

    return collect($rows)->map(fn ($r) => [
      'code'       => (string) $r->partner_code,
      'a2'         => (string) ($r->a2 ?? ''),
      'label'      => (string) $r->partner_name,
      'value_now'  => (int) ($r->total_value_now  ?? 0),
      'value_prev' => (int) ($r->total_value_prev ?? 0),
    ])->all();
  }

  /** Timeseries multi (tanpa kolom Status) */
  public function getTimeseries(array $filters): array
  {
    $year     = isset($filters['year']) ? (int) $filters['year'] : null;
    $yearFrom = isset($filters['year_from']) ? (int) $filters['year_from'] : null;
    $yearTo   = isset($filters['year_to'])   ? (int) $filters['year_to']   : null;
    if ($year && !$yearFrom && !$yearTo) {
      $yearFrom = $yearTo = $year;
    }
    if (!$year && !$yearFrom && !$yearTo) {
      $latestYear = $this->getLatestYear($filters);
      if ($latestYear) {
        $yearTo = $latestYear;
        $yearFrom = $latestYear - 4;
      }
    }

    $bilat   = $this->isBilateral($filters);
    $origins = $this->toAlpha3List($filters['origin'] ?? ($filters['origins'] ?? null));
    $dests   = $this->toAlpha3List($filters['dest']   ?? ($filters['dests']   ?? null));

    // Kalau hanya "country" diisi → treat sebagai bilateral country-country
    $country = strtoupper(trim((string) ($filters['country'] ?? '')));
    if ($country && $country !== 'ALL' && empty($origins) && empty($dests)) {
      $origins = [$country];
      $dests   = [$country];
    }

    $table    = $this->tourismTable($filters);
    $bindings = [];
    $selects  = ["t.Tahun"];

    if ($bilat && !empty($origins) && !empty($dests)) {
      // Bilateral: inbound = origin→dest, outbound = dest→origin
      $phO1 = implode(',', array_fill(0, count($origins), '?'));
      $phD1 = implode(',', array_fill(0, count($dests), '?'));
      $phD2 = implode(',', array_fill(0, count($dests), '?'));
      $phO2 = implode(',', array_fill(0, count($origins), '?'));

      $selects[] = "
        SUM(
          CASE
            WHEN t.Kode_Alpha3_Asal IN ($phO1)
             AND t.Kode_Alpha3_Tujuan IN ($phD1)
            THEN COALESCE(t.Jumlah_Wisatawan,0)
            ELSE 0
          END
        ) AS inbound_count
      ";
      $selects[] = "
        SUM(
          CASE
            WHEN t.Kode_Alpha3_Asal IN ($phO1)
             AND t.Kode_Alpha3_Tujuan IN ($phD1)
            THEN COALESCE(t.Nilai_Spending,0)
            ELSE 0
          END
        ) AS inbound_spending
      ";
      $selects[] = "
        SUM(
          CASE
            WHEN t.Kode_Alpha3_Asal IN ($phD2)
             AND t.Kode_Alpha3_Tujuan IN ($phO2)
            THEN COALESCE(t.Jumlah_Wisatawan,0)
            ELSE 0
          END
        ) AS outbound_count
      ";

      // urutan binding: O1, D1, O1, D1, D2, O2
      array_push($bindings, ...$origins, ...$dests, ...$origins, ...$dests, ...$dests, ...$origins);
    } else {
      // Fallback: logika global / per sisi seperti versi awal
      if (!empty($dests)) {
        $ph = implode(',', array_fill(0, count($dests), '?'));
        $selects[] = "SUM(CASE WHEN t.Kode_Alpha3_Tujuan IN ($ph) THEN COALESCE(t.Jumlah_Wisatawan,0) ELSE 0 END) AS inbound_count";
        $selects[] = "SUM(CASE WHEN t.Kode_Alpha3_Tujuan IN ($ph) THEN COALESCE(t.Nilai_Spending,0)   ELSE 0 END) AS inbound_spending";
        array_push($bindings, ...$dests, ...$dests);
      } else {
        $selects[] = "SUM(COALESCE(t.Jumlah_Wisatawan,0)) AS inbound_count";
        $selects[] = "SUM(COALESCE(t.Nilai_Spending,0))   AS inbound_spending";
      }

      if (!empty($origins)) {
        $ph = implode(',', array_fill(0, count($origins), '?'));
        $selects[] = "SUM(CASE WHEN t.Kode_Alpha3_Asal IN ($ph) THEN COALESCE(t.Jumlah_Wisatawan,0) ELSE 0 END) AS outbound_count";
        array_push($bindings, ...$origins);
      } else {
        $selects[] = "SUM(COALESCE(t.Jumlah_Wisatawan,0)) AS outbound_count";
      }
    }

    $q = DB::connection(self::CONN)
      ->table($table . ' as t')
      ->selectRaw(implode(",\n", $selects), $bindings);

    $q = $this->applyExcludeWor($q);

    // Untuk bilateral, filter OD sudah di CASE, jadi tidak pakai whereIn tambahan
    if (!$bilat && (!empty($origins) || !empty($dests))) {
      $q = $this->applyOriginDest($q, $filters);
    }

    $q = $this->applySource($q, $filters);

    if ($yearFrom && $yearTo) {
      $q->whereBetween('t.Tahun', [$yearFrom, $yearTo]);
    } elseif ($yearFrom) {
      $q->where('t.Tahun', '>=', $yearFrom);
    } elseif ($yearTo) {
      $q->where('t.Tahun', '<=', $yearTo);
    }

    $rows = $q->groupBy('t.Tahun')
      ->orderBy('t.Tahun')
      ->get();

    $data = collect($rows)->map(function ($r) {
      return [
        'year'             => (int) $r->Tahun,
        'inbound_count'    => (int) ($r->inbound_count    ?? 0),
        'inbound_spending' => (float) ($r->inbound_spending ?? 0),
        'outbound_count'   => (int) ($r->outbound_count   ?? 0),
      ];
    })->values()->all();

    if (!$yearFrom || !$yearTo) {
      $years = array_column($data, 'year');
      $minY  = $years ? min($years) : null;
      $maxY  = $years ? max($years) : null;
    } else {
      $minY = $yearFrom;
      $maxY = $yearTo;
    }

    $codes = $this->normalizedSourceCodes($filters);
    $names = $this->getSourceNames($codes);

    return [
      'meta' => [
        'origins'     => $origins ?: null,
        'dests'       => $dests   ?: null,
        'origin_names' => $this->resolveCountryNames($filters['origin'] ?? ($filters['origins'] ?? null)),
        'dest_names'   => $this->resolveCountryNames($filters['dest'] ?? ($filters['dests'] ?? null)),
        'source_name' => count($codes) === 1 ? $names[$codes[0]] : array_values($names),
        'year_from'   => $minY,
        'year_to'     => $maxY,
      ],
      'timeseries' => [
        'data' => $data,
      ],
    ];
  }

  /** Composite: meta (+nama sumber), summary, tables */
  public function getComposite(array $filters, array $include): array
  {
    // Default
    $filters['country'] = strtoupper(trim((string) ($filters['country'] ?? 'IDN')));
    $filters['source']  = $filters['source'] ?? self::DEFAULT_SOURCE;
    $filters['limit']   = (int) ($filters['limit']  ?? 20);

    $year = (int) ($filters['year'] ?? 0);
    if (!$year) $year = $this->getLatestYear($filters);

    $codes = $this->normalizedSourceCodes($filters);
    $names = $this->getSourceNames($codes);

    if (!$year) {
      return [
        'meta' => [
          'year'        => null,
          'prevYear'    => null,
          'country'     => $filters['country'],
          'country_name' => $this->resolveCountryNames($filters['country']),
          'source_name' => count($codes) === 1 ? $names[$codes[0]] : array_values($names),
        ],
        'summary'        => null,
        'table_inbound'  => [],
        'table_outbound' => [],
      ];
    }

    $filters['year'] = $year;
    $prevYear = $year - 1;

    $out = [
      'meta' => [
        'year'        => $year,
        'prevYear'    => $prevYear,
        'country'     => $filters['country'],
        'country_name' => $this->resolveCountryNames($filters['country']),
        'source_name' => count($codes) === 1 ? $names[$codes[0]] : array_values($names),
      ],
    ];

    $include = $include ?: ['summary', 'table_inbound', 'table_outbound'];

    if (in_array('summary', $include, true)) {
      $s = $this->getSummary($filters);
      $out['summary'] = [
        'inbound' => [
          'count_now'     => $s['inbound_count_now']     ?? 0,
          'count_prev'    => $s['inbound_count_prev']    ?? 0,
          'spending_now'  => $s['inbound_spending_now']  ?? 0.0,
          'spending_prev' => $s['inbound_spending_prev'] ?? 0.0,
        ],
        'outbound' => [
          'count_now'     => $s['outbound_count_now']    ?? 0,
          'count_prev'    => $s['outbound_count_prev']   ?? 0,
        ],
      ];
    }

    if (in_array('table_inbound', $include, true)) {
      $out['table_inbound'] = $this->getInboundByPartner($filters);
    }
    if (in_array('table_outbound', $include, true)) {
      $out['table_outbound'] = $this->getOutboundByPartner($filters);
    }

    return $out;
  }
}
