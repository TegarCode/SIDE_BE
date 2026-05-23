<?php

namespace App\Services\Analisis;

use App\Repositories\Analisis\AnalisisRCAEPD\AnalisisRCAEPDRepositoryInterface;

class AnalisisRCAEPDService
{
    public function __construct(
        private readonly AnalisisRCAEPDRepositoryInterface $repository
    ) {
    }

    public function getData(array $filters)
    {
        return $this->repository->getData($filters);
    }

    public function getCalculation(array $filters)
    {
        return $this->repository->getCalculation($filters);
    }

    public function getComparison(array $filters)
    {
        return $this->repository->getComparison($filters);
    }

    public function getXModelOptions(array $filters)
    {
        return $this->repository->getXModelOptions($filters);
    }
}
