<?php

namespace App\Services\DataGenerator;

use App\Repositories\DataGenerator\Pariwisata\PariwisataRepositoryInterface;

class PariwisataService
{
    protected PariwisataRepositoryInterface $repository;

    public function __construct(PariwisataRepositoryInterface $repository)
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
        return $this->repository->getDistinctDefaultTahun();
    }

    public function getTahunDefault()
    {
        return $this->repository->getDistinctTahun();
    }

    public function getFilteredTourismData(array $filters): array
    {
        return $this->repository->getTableFilterData($filters);
    }

    public function getVisualizationTourismData(array $filters): array
    {
        return $this->repository->getVisualizationFilterData($filters);
    }
}
