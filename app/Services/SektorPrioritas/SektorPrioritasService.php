<?php

namespace App\Services\SektorPrioritas;

use App\Repositories\SektorPrioritas\SektorPrioritasRepositoryInterface;

class SektorPrioritasService
{
  protected SektorPrioritasRepositoryInterface $SektorPrioritasRepositoryInterface;

  public function __construct(
    SektorPrioritasRepositoryInterface $SektorPrioritasRepositoryInterface,
  ) {
    $this->SektorPrioritasRepositoryInterface = $SektorPrioritasRepositoryInterface;
  }

  public function getNilaiPerdaganganEnergi(array $filters): array
  {
    return $this->SektorPrioritasRepositoryInterface->nilaiPerdaganganPerNegara($filters);
  }

  public function nilaiPerdaganganPerProduk(array $filters): array
  {
    return $this->SektorPrioritasRepositoryInterface->nilaiPerdaganganPerProduk($filters);
  }
}
