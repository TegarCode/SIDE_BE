<?php

namespace App\Services\ReportGenerator;

use App\Repositories\ReportGenerator\MarketShare\MarketShareRepositoryInterface;

class MarketShareService
{
  protected MarketShareRepositoryInterface $repository;

  public function __construct(MarketShareRepositoryInterface $repository)
  {
    $this->repository = $repository;
  }

  /**
   * Ambil data untuk tabel berdasarkan filter.
   */
  public function getFilteredMarketShareData(array $filters): array
  {
    return $this->repository->getTableFilterData($filters);
  }

  /**
   * Ambil nama negara (alpha3) via Repository.
   */
  public function getCountryName(string $alpha3): string
  {
    return $this->repository->getCountryName($alpha3);
  }

  public function getSourceName(string $alpha3): string
  {
    return $this->repository->getSourceName($alpha3);
  }

  /**
   * Untuk fitur snapshot jika perlu.
   */
  public function getSnapshotData(string $origin, string $destination, string $strategy): array
  {
    return $this->repository->getSnapshotData($origin, $destination, $strategy);
  }
}
