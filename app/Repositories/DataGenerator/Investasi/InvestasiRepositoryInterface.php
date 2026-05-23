<?php

namespace App\Repositories\DataGenerator\Investasi;

interface InvestasiRepositoryInterface
{
  public function getDistinctKodeSumber();
  public function getDistinctTahun();
  public function getDistinctDefaultTahun();
  public function getTableFilterData(array $filters): array;
  public function getVisualizationFilterData(array $filters): array;
}
