<?php

namespace App\Services\ReportGenerator;

use App\Repositories\ReportGenerator\RCACMSA\RCACMSARepositoryInterface;

class RCACMSAService
{
  protected RCACMSARepositoryInterface $repository;

  public function __construct(RCACMSARepositoryInterface $repository)
  {
    $this->repository = $repository;
  }

  /**
   * Ambil data perdagangan berdasarkan filter dari request
   *
   * @param array $filters
   * @return array
   */
  public function getFilteredRCACMSAData(array $filters): array
  {
    return $this->repository->getTableFilterData($filters);
  }

  public function getSnapshotData(string $origin, string $destination, string $strategy): array
  {
    return $this->repository->getSnapshotData($origin, $destination, $strategy);
  }

  public function getSummaryListData(string $origin, string $destination, string $strategy, int $limit = 10): array
  {
    return $this->repository->getSummaryListData($origin, $destination, $strategy, $limit);
  }

  public function getSummaryTableData(string $origin, string $destination, string $strategy): array
  {
    return $this->repository->getSummaryTableData($origin, $destination, $strategy);
  }

  public function getSummaryDataWithMetrics(string $origin, string $destination, string $strategy): array
  {
    return $this->repository->getSummaryDataWithMetrics($origin, $destination, $strategy);
  }

  public function getCountryName(string $alpha3): string
  {
    return $this->repository->getCountryName($alpha3);
  }
}
