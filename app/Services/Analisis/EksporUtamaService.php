<?php

namespace App\Services\Analisis;

use App\Repositories\Analisis\EksporUtama\EksporUtamaRepositoryInterface;

class EksporUtamaService
{
  protected EksporUtamaRepositoryInterface $repository;

  public function __construct(EksporUtamaRepositoryInterface $repository)
  {
    $this->repository = $repository;
  }

  public function getEksporUtama(array $filters): array
  {
    return $this->repository->getEksporUtama($filters);
  }
}
