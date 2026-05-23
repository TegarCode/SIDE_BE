<?php

namespace App\Services\SektorPrioritas;

use App\Repositories\Indonesia\EconomyDiplomation\NilaiPerdaganganRepositoryInterface;
use App\Repositories\SektorPrioritas\Hilirisasi\NilaiPerdaganganHilirisasiRepositoryInterface;

class HilirisasiService
{
  protected NilaiPerdaganganHilirisasiRepositoryInterface $repositoryNilaiPerdagangan;

  public function __construct(
    NilaiPerdaganganHilirisasiRepositoryInterface $repositoryNilaiPerdagangan,
  ) {
    $this->repositoryNilaiPerdagangan = $repositoryNilaiPerdagangan;
  }

  public function getNilaiPerdaganganHilirisasi(array $filters): array
  {
    return $this->repositoryNilaiPerdagangan->nilaiPerdaganganPerNegara($filters);
  }

    public function getNilaiPerdaganganHilirisasiProduk(array $filters): array
  {
    return $this->repositoryNilaiPerdagangan->nilaiPerdaganganPerProduk($filters);
  }
}
