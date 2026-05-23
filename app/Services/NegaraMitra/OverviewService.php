<?php

namespace App\Services\NegaraMitra;

use App\Repositories\NegaraMitra\Overview\OverviewRepositoryInterface;
use App\Repositories\NegaraMitra\Overview\TopInvestasiRepositoryInterface;
use App\Repositories\NegaraMitra\Overview\TopJasaRepositoryInterface;
use App\Repositories\NegaraMitra\Overview\TopPariwisataRepositoryInterface;
use App\Repositories\NegaraMitra\Overview\TopPerdaganganRepositoryInterface;

class OverviewService
{
  protected OverviewRepositoryInterface $repository;
  protected TopPerdaganganRepositoryInterface $repositoryTopPerdagangan;
  protected TopInvestasiRepositoryInterface $repositoryTopInvestasi;
  protected TopPariwisataRepositoryInterface $repositoryTopPariwisata;
  protected TopJasaRepositoryInterface $repositoryTopJasa;

  public function __construct(
    OverviewRepositoryInterface $repository,
    TopPerdaganganRepositoryInterface $repositoryTopPerdagangan,
    TopInvestasiRepositoryInterface $repositoryTopInvestasi,
    TopPariwisataRepositoryInterface $repositoryTopPariwisata,
    TopJasaRepositoryInterface $repositoryTopJasa
  ) {
    $this->repository = $repository;
    $this->repositoryTopPerdagangan = $repositoryTopPerdagangan;
    $this->repositoryTopInvestasi = $repositoryTopInvestasi;
    $this->repositoryTopPariwisata = $repositoryTopPariwisata;
    $this->repositoryTopJasa = $repositoryTopJasa;
  }

  public function getComputeStats(string $alpha3): array
  {
    return $this->repository->computeStats(alpha3: $alpha3);
  }

  public function getComputeTradeCountry(): array
  {
    return $this->repository->computeTradeCountry();
  }

  public function getTopPerdagangan(string $alpha3): array
  {
    return $this->repositoryTopPerdagangan->topPerdagangan($alpha3);
  }

  public function getTopInvestasi(string $alpha3): array
  {
    return $this->repositoryTopInvestasi->topInvestasi($alpha3);
  }

  public function getTopPariwisata(string $alpha3): array
  {
    return $this->repositoryTopPariwisata->topPariwisata($alpha3);
  }

  public function getTopJasa(string $alpha3): array
  {
    return $this->repositoryTopJasa->topJasa($alpha3);
  }
}
