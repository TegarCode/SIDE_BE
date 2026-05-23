<?php

namespace App\Services\ReportGenerator;

use App\Repositories\ReportGenerator\KerjasamaPerdagangan\KerjasamaPerdaganganRepositoryInterface;

class KerjasamaPerdaganganService
{
  protected KerjasamaPerdaganganRepositoryInterface $repository;

  public function __construct(KerjasamaPerdaganganRepositoryInterface $repository)
  {
    $this->repository = $repository;
  }

  /**
   * Ambil data untuk tabel berdasarkan filter.
   */
  public function getFilteredKerjasamaPerdaganganData(array $filters): array
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
}
