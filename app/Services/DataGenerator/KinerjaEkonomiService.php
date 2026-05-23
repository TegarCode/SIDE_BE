<?php

namespace App\Services\DataGenerator;

use App\Repositories\DataGenerator\KinerjaEkonomi\KinerjaEkonomiRepositoryInterface;

class KinerjaEkonomiService
{
    protected KinerjaEkonomiRepositoryInterface $repository;

    public function __construct(KinerjaEkonomiRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getTableFilterData(array $filters): array
    {
        return $this->repository->getTableFilterData($filters);
    }

    public function getVisualizationFilterData(array $filters): array
    {
        return $this->repository->getVisualizationFilterData($filters);
    }
}
