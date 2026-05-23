<?php
// app/Services/NegaraMitra/TourismOverviewService.php

namespace App\Services\NegaraMitra;

use App\Repositories\NegaraMitra\Pariwisata\TourismRepositoryInterface;

class TourismOverviewService
{
  public function __construct(private TourismRepositoryInterface $repo) {}

  /** Cukup teruskan ke repository */
  public function getComposite(array $filters, array $include): array
  {
    return $this->repo->getComposite($filters, $include);
  }

  /** Opsi: expose method lain jika ingin dipakai terpisah */
  public function getSummary(array $filters): array
  {
    return $this->repo->getSummary($filters);
  }

  public function getInboundByPartner(array $filters): array
  {
    return $this->repo->getInboundByPartner($filters);
  }

  public function getOutboundByPartner(array $filters): array
  {
    return $this->repo->getOutboundByPartner($filters);
  }

  public function getLatestYear(array $filters): ?int
  {
    return $this->repo->getLatestYear($filters);
  }

  public function getTimeseries(array $filters): array
  {
    return $this->repo->getTimeseries($filters);
  }
}
