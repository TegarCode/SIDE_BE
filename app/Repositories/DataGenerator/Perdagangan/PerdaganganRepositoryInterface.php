<?php

namespace App\Repositories\DataGenerator\Perdagangan;

interface PerdaganganRepositoryInterface
{
  public function getDistinctKodeSumber();
  public function getDistinctTahun();
  public function getDistinctDefaultTahun();
  public function getTableFilterData(array $filters, int $page = 1, int $perPage = 50): array;
  public function getVisualizationFilterData(array $filters): array;
}
