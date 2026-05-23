<?php

namespace App\Services\SektorPrioritas;

use App\Repositories\SektorPrioritas\EkonomiDigital\TIKRepositoryInterface;
use App\Repositories\SektorPrioritas\EkonomiDigital\NilaiEcommerceRepositoryInterface;
use App\Repositories\SektorPrioritas\EkonomiDigital\NilaiInfrastrukturRepositoryInterface;

class EconomyDigitalService
{
  protected TIKRepositoryInterface $repositoryNilaiPerdagangan;
  protected NilaiEcommerceRepositoryInterface $repositoryNilaiEcommerce;
  protected NilaiInfrastrukturRepositoryInterface $repositoryNilaiInfrastruktur;

  public function __construct(
    TIKRepositoryInterface $repositoryNilaiPerdagangan,
    NilaiEcommerceRepositoryInterface $repositoryNilaiEcommerce,
    NilaiInfrastrukturRepositoryInterface $repositoryNilaiInfrastruktur,

  ) {
    $this->repositoryNilaiPerdagangan = $repositoryNilaiPerdagangan;
    $this->repositoryNilaiEcommerce = $repositoryNilaiEcommerce;
    $this->repositoryNilaiInfrastruktur = $repositoryNilaiInfrastruktur;
  }

  public function getNilaiPerdaganganTIK(array $filters): array
  {
    return $this->repositoryNilaiPerdagangan->nilaiPerdaganganPerNegara($filters);
  }

    public function nilaiPerdaganganPerProduk(array $filters): array
  {
    return $this->repositoryNilaiPerdagangan->nilaiPerdaganganPerProduk($filters);
  }

  public function getNilaiEcommerce(array $filters): array
  {
    return $this->repositoryNilaiEcommerce->getNilaiEcommerce($filters);
  }

  public function getNilaiInfrastruktur(array $filters): array
  {
    return $this->repositoryNilaiInfrastruktur->getNilaiInfrastruktur($filters);
  }
}
