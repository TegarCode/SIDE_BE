<?php

namespace App\Services\DataGenerator;

use App\Repositories\DataGenerator\Investasi\InvestasiRepositoryInterface;

class InvestasiService
{
    protected InvestasiRepositoryInterface $repository;

    public function __construct(InvestasiRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Ambil data perdagangan berdasarkan filter dari request
     *
     * @param  array  $filters
     * @return array
     */
    public function getKodeSumber()
    {
        return $this->repository->getDistinctKodeSumber();
    }

    public function getTahun()
    {
        return $this->repository->getDistinctTahun();
    }

    public function getTahunDefault()
    {
        return $this->repository->getDistinctDefaultTahun();
    }

    public function getFilteredInvestmentData(array $filters): array
    {
        return $this->repository->getTableFilterData($filters);
    }

    public function getVisualizationInvestmentData(array $filters): array
    {
        return $this->repository->getVisualizationFilterData($filters);
    }
}
