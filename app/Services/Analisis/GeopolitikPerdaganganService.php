<?php

namespace App\Services\Analisis;

use App\Repositories\Analisis\GeopolitikPerdagangan\GeopolitikPerdaganganRepositoryInterface;

class GeopolitikPerdaganganService
{
  protected GeopolitikPerdaganganRepositoryInterface $repository;

  public function __construct(GeopolitikPerdaganganRepositoryInterface $repository)
  {
    $this->repository = $repository;
  }

  public function getGeopolitikPerdagangan(array $filters): array
  {
    return $this->repository->getGeopolitikPerdagangan($filters);
  }
}
