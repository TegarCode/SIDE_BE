<?php

namespace App\Repositories\Indonesia\KinerjaEkonomi;

interface KinerjaEkonomiRepositoryInterface
{
  public function getDistinctTahun();
  public function getIndikator();
  public function getIndikatorAll();
  public function getKinerja(array $filters): array;
}
