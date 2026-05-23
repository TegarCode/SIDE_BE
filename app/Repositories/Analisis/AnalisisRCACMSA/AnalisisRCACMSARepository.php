<?php

namespace App\Repositories\Analisis\AnalisisRCACMSA;

use Illuminate\Support\Facades\DB;

class AnalisisRCACMSARepository implements AnalisisRCACMSARepositoryInterface
{
  protected string $conn = 'server_mysql';

  protected string $TB_TRADE   = 'tbhasilakhir';
  protected string $TB_COUNTRY = 'tbnegara';

public function getDataAnalisis(array $filters): array
{
    $db = DB::connection($this->conn);

    $pickA3 = function ($v): ?string {
        if (is_array($v)) {
            foreach ($v as $x) {
                $s = strtoupper(trim((string)$x));
                if ($s !== '' && preg_match('/^[A-Z]{3}$/', $s)) return $s;
            }
            return null;
        }
        $s = strtoupper(trim((string)$v));
        return ($s !== '' && preg_match('/^[A-Z]{3}$/', $s)) ? $s : null;
    };

    $origin = $pickA3($filters['origin'] ?? null);
    $dest   = $pickA3($filters['dest'] ?? null);

    $originRow = $origin
        ? $db->table($this->TB_COUNTRY)
            ->select('Negara_IDN as nama', 'Kode_Alpha2 as a2', 'Kode_Alpha3 as a3')
            ->where('Kode_Alpha3', $origin)
            ->first()
        : null;

    $destRow = $dest
        ? $db->table($this->TB_COUNTRY)
            ->select('Negara_IDN as nama', 'Kode_Alpha2 as a2', 'Kode_Alpha3 as a3')
            ->where('Kode_Alpha3', $dest)
            ->first()
        : null;

    // mapping strategi -> kolom SUM
    $colMap = [
        'IMPORT'       => 'Impor_RI_From_Partner',
        'EXPORT'       => 'Ekspor_RI_To_Partner',
        'FDI OUTBOUND' => 'Impor_RI_From_Partner',
        'FDI INBOUND'  => 'Ekspor_RI_To_Partner',
    ];

    $fetch = function (string $strategy) use ($db, $origin, $dest, $colMap) {
        $nilaiCol = $colMap[$strategy];

        $q = $db->table($this->TB_TRADE . ' as t')
            ->select([
                't.HsCode as kode',
                't.NamaProduk as nama_produk',
                't.Strategy as strategi',
            ])
            ->selectRaw("SUM(t.$nilaiCol) as nilai")
            ->when($origin, fn($qq) => $qq->where('t.KodeNegara_1', $origin))
            ->when($dest, fn($qq) => $qq->where('t.KodeNegara_2', $dest))
            ->where('t.Strategy', $strategy)
            ->whereNotNull($nilaiCol)
            ->groupBy('t.HsCode', 't.NamaProduk', 't.Strategy')
            ->orderByDesc('nilai');

        $rows = $q->get();

        $ranked = [];
        $rank = 1;
        foreach ($rows as $r) {
            $ranked[] = [
                'Rank'        => $rank++,
                'Kode'        => (string) $r->kode,
                'Nama Produk' => (string) $r->nama_produk,
                'Strategi'    => (string) $r->strategi,
                'Nilai'       => is_null($r->nilai) ? null : (float) $r->nilai,
            ];
        }

        return $ranked;
    };

    $data = [
        'import'       => $fetch('IMPORT'),
        'export'       => $fetch('EXPORT'),
        'fdi_outbound' => $fetch('FDI OUTBOUND'),
        'fdi_inbound'  => $fetch('FDI INBOUND'),
    ];

    $sumMap = [
    'IMPORT'       => 'Impor_RI_From_Partner',
    'EXPORT'       => 'Ekspor_RI_To_Partner',
    'FDI OUTBOUND' => 'Impor_RI_From_Partner',
    'FDI INBOUND'  => 'Ekspor_RI_To_Partner',
];


foreach ($sumMap as $strategy => $col) {
    $sumQuery = $db->table($this->TB_TRADE)
        ->when($origin, fn($q) => $q->where('KodeNegara_1', $origin))
        ->when($dest, fn($q) => $q->where('KodeNegara_2', $dest))
        ->where('Strategy', $strategy)
        ->sum($col);

    switch ($strategy) {
        case 'IMPORT':
            $data['SUMimport'] = (float) $sumQuery;
            break;
        case 'EXPORT':
            $data['SUMexport'] = (float) $sumQuery;
            break;
        case 'FDI OUTBOUND':
            $data['SUMfdi_outbound'] = (float) $sumQuery;
            break;
        case 'FDI INBOUND':
            $data['SUMfdi_inbound'] = (float) $sumQuery;
            break;
    }
}

    return [
        'meta' => [
            'sumber' => 'trademap',
            'origin' => [
                'a3'   => $origin,
                'a2'   => $originRow->a2 ?? null,
                'nama' => $originRow->nama ?? null,
            ],
            'dest' => [
                'a3'   => $dest,
                'a2'   => $destRow->a2 ?? null,
                'nama' => $destRow->nama ?? null,
            ],
        ],
        'data' => $data,
    ];
}

  public function getCalculationAnalisis(array $filters): array
  {
    $db = DB::connection($this->conn);

    /* =============== Helpers =============== */
    $pickA3 = function ($v): ?string {
      if (is_array($v)) {
        foreach ($v as $x) {
          $s = strtoupper(trim((string)$x));
          if ($s !== '' && preg_match('/^[A-Z]{3}$/', $s)) return $s;
        }
        return null;
      }
      $s = strtoupper(trim((string)$v));
      return ($s !== '' && preg_match('/^[A-Z]{3}$/', $s)) ? $s : null;
    };

    /* =============== Filters =============== */
    $origin   = $pickA3($filters['origin']  ?? null);
    $dest     = $pickA3($filters['dest']    ?? null);
    $strategy = isset($filters['strategy'])
      ? strtoupper(trim((string)$filters['strategy']))
      : null;

    /* =============== Meta negara (nama + A2/A3) =============== */
    $originRow = $origin
      ? $db->table($this->TB_COUNTRY)
      ->select('Negara_IDN as nama', 'Kode_Alpha2 as a2', 'Kode_Alpha3 as a3')
      ->where('Kode_Alpha3', $origin)
      ->first()
      : null;

    $destRow = $dest
      ? $db->table($this->TB_COUNTRY)
      ->select('Negara_IDN as nama', 'Kode_Alpha2 as a2', 'Kode_Alpha3 as a3')
      ->where('Kode_Alpha3', $dest)
      ->first()
      : null;

    /* =============== Query utama =============== */
    $q = $db->table($this->TB_TRADE . ' as t')
      ->selectRaw("LEFT(LPAD(REPLACE(COALESCE(t.HsCode,''),'.',''), 4, '0'), 4) as hs4")
      ->addSelect([
        't.KodeNegara_1 as asal',
        't.KodeNegara_2 as tujuan',
        't.HsCode       as kode',
        't.NamaProduk   as nama_produk',
        't.Strategy     as strategi',
        // kalkulasi untuk asal
        't.RCA_Asal     as rca_asal',
        't.CMSA_Asal    as cmsa_asal',
        't.Class_Asal   as class_asal',
        // kalkulasi untuk tujuan
        't.RCA_Tujuan   as rca_tujuan',
        't.CMSA_Tujuan  as cmsa_tujuan',
        't.Class_Tujuan   as class_tujuan',
        // world totals (jika ada)
        't.Asal_World   as asal_world',
        't.Tujuan_World as tujuan_world',
      ])
      ->when($origin,   fn($qq) => $qq->where('t.KodeNegara_1', $origin))
      ->when($dest,     fn($qq) => $qq->where('t.KodeNegara_2', $dest))
      ->when($strategy, fn($qq) => $qq->where('t.Strategy', $strategy))
      // urutkan biar stabil & enak dilihat
      ->orderBy('t.Strategy')
      ->orderBy('hs4')
      ->orderBy('t.NamaProduk');

    $rowsDb = $q->get();

    // Mapping ke array asosiatif yang rapi untuk FE
    $rows = [];
    foreach ($rowsDb as $r) {
      $rows[] = [
        'hs4'          => (string) ($r->hs4 ?? ''),
        'kode'         => (string) ($r->kode ?? ''),
        'nama_produk'  => (string) ($r->nama_produk ?? ''),
        'strategi'     => (string) ($r->strategi ?? ''),

        'rca_asal'     => is_null($r->rca_asal)     ? null : (float) $r->rca_asal,
        'cmsa_asal'    => is_null($r->cmsa_asal)    ? null : (float) $r->cmsa_asal,
        'class_asal'   => is_null($r->class_asal)   ? null : (string) $r->class_asal,
        
        'rca_tujuan'   => is_null($r->rca_tujuan)   ? null : (float) $r->rca_tujuan,
        'cmsa_tujuan'  => is_null($r->cmsa_tujuan)  ? null : (float) $r->cmsa_tujuan,
        'class_tujuan'   => is_null($r->class_tujuan)   ? null : (string) $r->class_tujuan,

        'asal_world'   => is_null($r->asal_world)   ? null : (float) $r->asal_world,
        'tujuan_world' => is_null($r->tujuan_world) ? null : (float) $r->tujuan_world,
      ];
    }

    return [
      'meta' => [
        'sumber' => 'trademap',
        'origin' => [
          'a3'   => $origin,
          'a2'   => $originRow->a2 ?? null,
          'nama' => $originRow->nama ?? null,
        ],
        'dest'   => [
          'a3'   => $dest,
          'a2'   => $destRow->a2 ?? null,
          'nama' => $destRow->nama ?? null,
        ],
      ],
      'data' => [
        'rows'  => $rows,
        'count' => count($rows),
      ],
    ];
  }
}
