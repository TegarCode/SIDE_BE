<?php

namespace App\Services\Analisis;

use App\Repositories\Analisis\AnalisisRSCATBI\AnalisisRSCATBIRepositoryInterface;

class AnalisisRSCATBIService
{
    public function __construct(
        protected AnalisisRSCATBIRepositoryInterface $repository
    ) {}

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
}