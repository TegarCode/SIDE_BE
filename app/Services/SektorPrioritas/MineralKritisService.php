<?php

namespace App\Services\SektorPrioritas;

use App\Repositories\SektorPrioritas\MineralKritis\MineralKritisRepositoryInterface;

class MineralKritisService
{
  protected MineralKritisRepositoryInterface $MineralKritisRepositoryInterface;

  public function __construct(
    MineralKritisRepositoryInterface $MineralKritisRepositoryInterface,
  ) {
    $this->MineralKritisRepositoryInterface = $MineralKritisRepositoryInterface;
  }

  public function getNilaiPerdaganganMineralKritis(array $filters): array
  {
    return $this->MineralKritisRepositoryInterface->nilaiPerdaganganPerNegara($filters);
  }

  public function nilaiPerdaganganPerProduk(array $filters): array
  {
    return $this->MineralKritisRepositoryInterface->nilaiPerdaganganPerProduk($filters);
  }
}
