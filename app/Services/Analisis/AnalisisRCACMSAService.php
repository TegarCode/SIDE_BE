<?php

namespace App\Services\Analisis;

use App\Repositories\Analisis\AnalisisRCACMSA\AnalisisRCACMSARepositoryInterface;

class AnalisisRCACMSAService
{
  protected AnalisisRCACMSARepositoryInterface $repository;

  public function __construct(AnalisisRCACMSARepositoryInterface $repository)
  {
    $this->repository = $repository;
  }

  public function getDataAnalisis(array $filters): array
  {
    return $this->repository->getDataAnalisis($filters);
  }

  public function getCalculationAnalisis(array $filters): array
  {
    return $this->repository->getCalculationAnalisis($filters);
  }
}
