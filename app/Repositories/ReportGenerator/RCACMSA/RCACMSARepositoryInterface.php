<?php

namespace App\Repositories\ReportGenerator\RCACMSA;

interface RCACMSARepositoryInterface
{
  public function getTableFilterData(array $filters): array;
  public function getSnapshotData(string $origin, string $destination, string $strategy): array;
  public function getCountryName(string $alpha3): string;
  public function getSummaryListData(string $origin, string $destination, string $strategy, int $limit = 10): array;
  public function getSummaryTableData(string $origin, string $destination, string $strategy): array;
  public function getSummaryDataWithMetrics(string $origin, string $destination, string $strategy): array;
}
