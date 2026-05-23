<?php

namespace App\Repositories\Analisis\AnalisisRCACMSA;

interface AnalisisRCACMSARepositoryInterface
{
  public function getDataAnalisis(array $filters): array;
  public function getCalculationAnalisis(array $filters): array;
}
