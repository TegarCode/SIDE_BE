<?php

namespace App\Services\DataGenerator;

use App\Repositories\DataGenerator\Jasa\JasaRepositoryInterface;

class JasaService
{
  protected JasaRepositoryInterface $repository;

  public function __construct(JasaRepositoryInterface $repository)
  {
    $this->repository = $repository;
  }

  /**
   * Ambil data Jasa berdasarkan filter dari request
   *
   * @param array $filters
   * @return array
   */

  public function getKodeSumber()
  {
    return $this->repository->getDistinctKodeSumber();
  }

  public function getTahun()
  {
    return $this->repository->getDistinctTahun();
  }

  public function getFilteredServiceData(array $filters): array
  {
    return $this->repository->getTableFilterData($filters);
  }

  public function getVisualizationServiceData(array $filters): array
  {
    return $this->repository->getVisualizationFilterData($filters);
  }
}
