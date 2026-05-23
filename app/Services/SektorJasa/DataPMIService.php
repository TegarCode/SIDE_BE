<?php

namespace App\Services\SektorJasa;

use App\Repositories\SektorJasa\DataPMI\DataPMIRepositoryInterface;

class DataPMIService
{
    public function __construct(protected DataPMIRepositoryInterface $repo) {}

    public function getNilaiJasa(array $filters): array
    {
        return $this->repo->getNilaiJasa($filters);
    }

    public function getStats(array $filters): array
    {
        return $this->repo->getStats($filters);
    }
}
