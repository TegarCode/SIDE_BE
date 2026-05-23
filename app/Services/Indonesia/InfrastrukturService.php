<?php

namespace App\Services\Indonesia;

use App\Repositories\Indonesia\Infrastruktur\InfrastrukturRepositoryInterface;
use App\Repositories\Indonesia\Infrastruktur\PameranInfrastrukturRepositoryInterface;
use App\Repositories\Indonesia\Infrastruktur\PerjanjianInfrastrukturRepositoryInterface;
use Illuminate\Support\Facades\Log;

class InfrastrukturService
{
  protected InfrastrukturRepositoryInterface $perwakilanRepository;
  protected PameranInfrastrukturRepositoryInterface $pameranRepository;
  protected PerjanjianInfrastrukturRepositoryInterface $perjanjianRepository;

  public function __construct(
    InfrastrukturRepositoryInterface $perwakilanRepository,
    PameranInfrastrukturRepositoryInterface $pameranRepository,
    PerjanjianInfrastrukturRepositoryInterface $perjanjianRepository,

  ) {
    $this->perwakilanRepository = $perwakilanRepository;
    $this->pameranRepository = $pameranRepository;
    $this->perjanjianRepository = $perjanjianRepository;
  }

  public function getPerwakilan(array $filters): array
  {
    return $this->perwakilanRepository->perwakilan($filters);
  }

  public function getPerwakilanAsing(array $filters): array
  {
    return $this->perwakilanRepository->perwakilanAsing($filters);
  }

  public function getPameranIndonesia(array $filters): array
  {
    return $this->pameranRepository->pameranIndonesia($filters);
  }

  public function getPameranPerwakilan(array $filters): array
  {
    return $this->pameranRepository->pameranPerwakilan($filters);
  }

  public function getPerjanjian(array $filters): array
  {
    return $this->perjanjianRepository->perjanjianNegara($filters);
  }
}
