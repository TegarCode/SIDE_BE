<?php

namespace App\Repositories\Indonesia\EconomyDiplomation;

class InsightKompetitorPerdaganganRepository implements InsightKompetitorPerdaganganRepositoryInterface
{
  public function mergeCompetitorsFromSourceFive(array $baseData, array $competitorData): array
  {
    $baseTopProduk = $baseData['top_produk'] ?? [];
    if (!is_array($baseTopProduk) || empty($baseTopProduk)) {
      return $baseData;
    }

    $competitorTopProduk = $competitorData['top_produk'] ?? [];
    if (!is_array($competitorTopProduk) || empty($competitorTopProduk)) {
      return $baseData;
    }

    $baseLatestYear = (int) ($baseData['meta']['latest_year'] ?? 0);
    $competitorLatestYear = (int) ($competitorData['meta']['latest_year'] ?? 0);
    $sameLatestYear = $baseLatestYear > 0 && $competitorLatestYear > 0 && $baseLatestYear === $competitorLatestYear;

    $mapByHs = [];
    foreach ($competitorTopProduk as $row) {
      if (!is_array($row)) {
        continue;
      }
      $hs = (string) ($row['kodeHS'] ?? '');
      if ($hs === '') {
        continue;
      }
      $mapByHs[$hs] = [
        'tujuan_ekspor_alpha3' => $this->extractTopDestinationAlpha3($row['tujuan_ekspor'] ?? []),
        'tujuan_impor_alpha3' => $this->extractTopDestinationAlpha3($row['tujuan_impor'] ?? []),
        'kompetitor_global_top_tujuan_ekspor' => $row['kompetitor_global_top_tujuan_ekspor'] ?? [],
        'kompetitor_global_top_tujuan_impor' => $row['kompetitor_global_top_tujuan_impor'] ?? [],
        'kompetitor_asean_top_tujuan_ekspor' => $row['kompetitor_asean_top_tujuan_ekspor'] ?? [],
        'kompetitor_asean_top_tujuan_impor' => $row['kompetitor_asean_top_tujuan_impor'] ?? [],
      ];
    }

    foreach ($baseTopProduk as &$row) {
      if (!is_array($row)) {
        continue;
      }
      $hs = (string) ($row['kodeHS'] ?? '');
      if ($hs === '' || !isset($mapByHs[$hs]) || !$sameLatestYear) {
        $row['kompetitor_global_top_tujuan_ekspor'] = [];
        $row['kompetitor_global_top_tujuan_impor'] = [];
        $row['kompetitor_asean_top_tujuan_ekspor'] = [];
        $row['kompetitor_asean_top_tujuan_impor'] = [];
        continue;
      }

      $baseEksporTujuan = $this->extractTopDestinationAlpha3($row['tujuan_ekspor'] ?? []);
      $baseImporTujuan = $this->extractTopDestinationAlpha3($row['tujuan_impor'] ?? []);
      $compEksporTujuan = $mapByHs[$hs]['tujuan_ekspor_alpha3'] ?? null;
      $compImporTujuan = $mapByHs[$hs]['tujuan_impor_alpha3'] ?? null;

      $row['kompetitor_global_top_tujuan_ekspor'] = ($baseEksporTujuan && $baseEksporTujuan === $compEksporTujuan)
        ? $mapByHs[$hs]['kompetitor_global_top_tujuan_ekspor']
        : [];
      $row['kompetitor_asean_top_tujuan_ekspor'] = ($baseEksporTujuan && $baseEksporTujuan === $compEksporTujuan)
        ? $mapByHs[$hs]['kompetitor_asean_top_tujuan_ekspor']
        : [];
      $row['kompetitor_global_top_tujuan_impor'] = ($baseImporTujuan && $baseImporTujuan === $compImporTujuan)
        ? $mapByHs[$hs]['kompetitor_global_top_tujuan_impor']
        : [];
      $row['kompetitor_asean_top_tujuan_impor'] = ($baseImporTujuan && $baseImporTujuan === $compImporTujuan)
        ? $mapByHs[$hs]['kompetitor_asean_top_tujuan_impor']
        : [];
    }
    unset($row);

    $baseData['top_produk'] = $baseTopProduk;
    return $baseData;
  }

  public function buildInsightResponse(array $data, string $hsCode, string $negara, array $filters = []): array
  {
    $meta = $data['meta'] ?? [];
    $topProduk = $data['top_produk'] ?? [];

    $selected = null;
    if (is_array($topProduk)) {
      foreach ($topProduk as $row) {
        if (!is_array($row)) {
          continue;
        }
        if ((string) ($row['kodeHS'] ?? '') === $hsCode) {
          $selected = $row;
          break;
        }
      }
    }

    if (!$selected) {
      return [
        'data' => [],
        'meta' => [
          'hsCode' => $hsCode,
          'negara' => $negara,
          'year' => $meta['latest_year'] ?? ($filters['year_end'] ?? null),
          'unit' => $meta['unit'] ?? 'Ribu US$',
          'sumber' => $meta['sumber'] ?? null,
          'kompetitor_sumber' => $meta['kompetitor_sumber'] ?? null,
        ],
      ];
    }

    $reporter = strtoupper((string) ($filters['reporter'] ?? $negara));
    $includeIndonesiaInTujuan = (strlen($reporter) === 3 && $reporter !== 'IDN');

    return [
      'data' => [
        'kodeHS' => $selected['kodeHS'] ?? $hsCode,
        'namaHS' => $selected['namaHS'] ?? $hsCode,
        'tujuan_ekspor' => $this->normalizeRankedList($selected['tujuan_ekspor'] ?? [], 10, $includeIndonesiaInTujuan),
        'tujuan_impor' => $this->normalizeRankedList($selected['tujuan_impor'] ?? [], 10, $includeIndonesiaInTujuan),
        'kompetitor_global_top_tujuan_ekspor' => $this->normalizeCompetitorList($selected['kompetitor_global_top_tujuan_ekspor'] ?? [], 10),
        'kompetitor_asean_top_tujuan_ekspor' => $this->normalizeCompetitorList($selected['kompetitor_asean_top_tujuan_ekspor'] ?? [], 10),
        'kompetitor_global_top_tujuan_impor' => $this->normalizeCompetitorList($selected['kompetitor_global_top_tujuan_impor'] ?? [], 10),
        'kompetitor_asean_top_tujuan_impor' => $this->normalizeCompetitorList($selected['kompetitor_asean_top_tujuan_impor'] ?? [], 10),
      ],
      'meta' => [
        'hsCode' => $hsCode,
        'negara' => $negara,
        'year' => $meta['latest_year'] ?? ($filters['year_end'] ?? null),
        'unit' => $meta['unit'] ?? 'Ribu US$',
        'sumber' => $meta['sumber'] ?? null,
        'kompetitor_sumber' => $meta['kompetitor_sumber'] ?? null,
      ],
    ];
  }

  private function extractTopDestinationAlpha3($tujuanList): ?string
  {
    if (!is_array($tujuanList) || empty($tujuanList)) {
      return null;
    }
    $first = $tujuanList[0] ?? null;
    if (!is_array($first)) {
      return null;
    }
    $a3 = strtoupper((string) ($first['kode_alpha3'] ?? ''));
    return $a3 !== '' ? $a3 : null;
  }

  private function normalizeRankedList($rows, int $limit = 10, bool $includeIndonesia = false): array
  {
    if (!is_array($rows) || empty($rows)) {
      if (!$includeIndonesia) {
        return [];
      }
      return [[
        'rank' => 1,
        'kode_alpha3' => 'IDN',
        'kode_alpha2' => 'ID',
        'negara' => 'INDONESIA',
        'nilai' => 0,
      ]];
    }

    $rows = array_values(array_filter($rows, fn($r) => is_array($r)));
    $idnRow = null;
    foreach ($rows as $r) {
      $a3 = strtoupper((string) ($r['kode_alpha3'] ?? ''));
      $name = strtoupper((string) ($r['negara'] ?? ''));
      if ($a3 === 'IDN' || $name === 'INDONESIA') {
        $idnRow = $r;
        break;
      }
    }

    $rows = array_slice($rows, 0, $limit);
    if ($includeIndonesia) {
      $hasIdnInTop = false;
      foreach ($rows as $r) {
        $a3 = strtoupper((string) ($r['kode_alpha3'] ?? ''));
        $name = strtoupper((string) ($r['negara'] ?? ''));
        if ($a3 === 'IDN' || $name === 'INDONESIA') {
          $hasIdnInTop = true;
          break;
        }
      }
      if (!$hasIdnInTop) {
        $rows[] = $idnRow ?? [
          'rank' => null,
          'kode_alpha3' => 'IDN',
          'kode_alpha2' => 'ID',
          'negara' => 'INDONESIA',
          'nilai' => 0,
        ];
      }
    }

    $rank = 0;
    foreach ($rows as &$row) {
      $rank++;
      if (array_key_exists('rank', $row)) {
        $row['rank'] = is_numeric($row['rank']) ? (int) $row['rank'] : null;
      } else {
        $row['rank'] = $rank;
      }
      $row['kode_alpha3'] = strtoupper((string) ($row['kode_alpha3'] ?? ''));
      $row['kode_alpha2'] = strtoupper((string) ($row['kode_alpha2'] ?? ''));
      $row['negara'] = (string) ($row['negara'] ?? $row['kode_alpha3']);
      $row['nilai'] = (int) ($row['nilai'] ?? 0);
    }
    unset($row);
    return $rows;
  }

  private function normalizeCompetitorList($rows, int $topLimit = 10): array
  {
    if (!is_array($rows) || empty($rows)) {
      return [];
    }

    $rows = array_values(array_filter($rows, fn($r) => is_array($r)));
    $idnRow = null;
    foreach ($rows as $r) {
      $a3 = strtoupper((string) ($r['kode_alpha3'] ?? ''));
      $name = strtoupper((string) ($r['negara'] ?? ''));
      if ($a3 === 'IDN' || $name === 'INDONESIA') {
        $idnRow = $r;
        break;
      }
    }

    $top = array_slice($rows, 0, $topLimit);
    $hasIdnInTop = false;
    foreach ($top as $r) {
      $a3 = strtoupper((string) ($r['kode_alpha3'] ?? ''));
      $name = strtoupper((string) ($r['negara'] ?? ''));
      if ($a3 === 'IDN' || $name === 'INDONESIA') {
        $hasIdnInTop = true;
        break;
      }
    }
    if (!$hasIdnInTop && $idnRow) {
      $top[] = $idnRow;
    }

    $result = [];
    foreach ($top as $idx => $row) {
      $result[] = [
        'rank' => is_numeric($row['rank'] ?? null) ? (int) $row['rank'] : ($idx + 1),
        'kode_alpha3' => strtoupper((string) ($row['kode_alpha3'] ?? '')),
        'kode_alpha2' => strtoupper((string) ($row['kode_alpha2'] ?? '')),
        'negara' => (string) ($row['negara'] ?? ($row['kode_alpha3'] ?? '-')),
        'nilai' => (int) ($row['nilai'] ?? 0),
      ];
    }
    return $result;
  }
}
