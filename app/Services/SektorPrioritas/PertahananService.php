<?php

namespace App\Services\SektorPrioritas;

use App\Repositories\Indonesia\EconomyDiplomation\NilaiPerdaganganRepositoryInterface;
use App\Repositories\SektorPrioritas\Pertahanan\NilaiPerdaganganPertahananRepositoryInterface;

class PertahananService
{
  protected NilaiPerdaganganPertahananRepositoryInterface $repositoryNilaiPerdagangan;

  public function __construct(
    NilaiPerdaganganPertahananRepositoryInterface $repositoryNilaiPerdagangan,
  ) {
    $this->repositoryNilaiPerdagangan = $repositoryNilaiPerdagangan;
  }

  public function getNilaiPerdaganganPertahanan(array $filters): array
  {
    return $this->repositoryNilaiPerdagangan->nilaiPerdaganganPerNegara($filters);
  }

    public function getNilaiPerdaganganPertahananProduk(array $filters): array
  {
    return $this->repositoryNilaiPerdagangan->nilaiPerdaganganPerProduk($filters);
  }
}
