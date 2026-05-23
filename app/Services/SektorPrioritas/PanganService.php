<?php

namespace App\Services\SektorPrioritas;

use App\Repositories\SektorPrioritas\Pangan\NilaiPanganRepositoryRepositoryInterface;

class PanganService
{
  protected NilaiPanganRepositoryRepositoryInterface $repositoryNilaiPangan;

  public function __construct(
    NilaiPanganRepositoryRepositoryInterface $repositoryNilaiPangan,
  ) {
    $this->repositoryNilaiPangan = $repositoryNilaiPangan;
  }

  public function getKomoditas()
  {
    return $this->repositoryNilaiPangan->getKomoditas();
  }

  public function getNilaiPerdaganganPangan(array $filters): array
  {
    return $this->repositoryNilaiPangan->nilaiPerdaganganPerNegara($filters);
  }

  public function nilaiPerdaganganPerProduk(array $filters): array
  {
    return $this->repositoryNilaiPangan->nilaiPerdaganganPerProduk($filters);
  }
}
