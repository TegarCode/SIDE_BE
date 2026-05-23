<?php

namespace App\Services\SektorJasa;

use App\Repositories\SektorJasa\InsightPasarPMI\InsightPasarPMIRepositoryInterface;

class InsightPasarPMIService
{
    public function __construct(protected InsightPasarPMIRepositoryInterface $repo) {}

    public function getNilaiJasa(array $filters): array
    {
        return $this->repo->getNilaiJasa($filters);
    }

    public function getStats(array $filters): array
    {
        return $this->repo->getStats($filters);
    }
}
