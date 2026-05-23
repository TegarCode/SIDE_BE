<?php

namespace App\Repositories\DataGenerator\Jasa;

interface JasaRepositoryInterface
{
  public function getDistinctKodeSumber();
  public function getDistinctTahun();
  public function getTableFilterData(array $filters): array;
  public function getVisualizationFilterData(array $filters): array;
}
