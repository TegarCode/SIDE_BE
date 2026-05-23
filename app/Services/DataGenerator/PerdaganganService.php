<?php

namespace App\Services\DataGenerator;

use App\Repositories\DataGenerator\Perdagangan\PerdaganganRepositoryInterface;

class PerdaganganService
{
    protected PerdaganganRepositoryInterface $repository;

    public function __construct(PerdaganganRepositoryInterface $repository)
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

    public function getFilteredTradeData(array $filters, int $page, int $perPage): array
    {
        return $this->repository->getTableFilterData($filters, $page, $perPage);
    }

    public function getVisualizationTradeData(array $filters): array
    {
        return $this->repository->getVisualizationFilterData($filters);
    }
}
