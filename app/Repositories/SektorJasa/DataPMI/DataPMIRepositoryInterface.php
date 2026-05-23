<?php

namespace App\Repositories\SektorJasa\DataPMI;

interface DataPMIRepositoryInterface
{
    public function getNilaiJasa(array $filters): array;
    public function getStats(array $filters): array;
}