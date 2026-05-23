<?php

namespace App\Repositories\Analisis\OperationalRisk;

interface OperationalRiskRepositoryInterface
{
  public function getTotalScore(array $filters): array;
  public function getBreakdownScore(array $filters): array;
}
