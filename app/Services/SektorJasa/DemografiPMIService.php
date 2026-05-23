<?php

namespace App\Services\SektorJasa;

use App\Repositories\SektorJasa\DemografiPMI\DemografiPMIRepositoryInterface;

class DemografiPMIService
{
    public function __construct(protected DemografiPMIRepositoryInterface $repo) {}

    public function getNilaiJasa(array $filters): array
    {
        return $this->repo->getNilaiJasa($filters);
    }

    public function getStats(array $filters): array
    {
        return $this->repo->getStats($filters);
    }
}
