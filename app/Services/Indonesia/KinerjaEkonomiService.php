<?php

namespace App\Services\Indonesia;

use App\Repositories\Indonesia\KinerjaEkonomi\KinerjaEkonomiRepositoryInterface;

class KinerjaEkonomiService
{
  protected KinerjaEkonomiRepositoryInterface $repository;

  public function __construct(KinerjaEkonomiRepositoryInterface $repository)
  {
    $this->repository = $repository;
  }
  public function getTahun()
  {
    return $this->repository->getDistinctTahun();
  }

  public function getIndikator()
  {
    return $this->repository->getIndikator();
  }

  public function getIndikatorAll()
  {
    return $this->repository->getIndikatorAll();
  }

  public function getKinerja(array $filters): array
  {
    return $this->repository->getKinerja($filters);
  }
}
