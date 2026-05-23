<?php

namespace App\Services\Analisis;

use App\Repositories\Analisis\OperationalRisk\OperationalRiskRepositoryInterface;

class OperationalRiskService
{
  protected OperationalRiskRepositoryInterface $repository;

  public function __construct(OperationalRiskRepositoryInterface $repository)
  {
    $this->repository = $repository;
  }
  
  public function getTotalScore(array $filters): array
  {
    return $this->repository->getTotalScore($filters);
  }

  public function getBreakdownScore(array $filters): array
  {
    return $this->repository->getBreakdownScore($filters);
  }
}
