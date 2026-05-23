<?php

namespace App\Repositories\SektorJasa\DemografiPMI;

interface DemografiPMIRepositoryInterface
{
    public function getNilaiJasa(array $filters): array;
    public function getStats(array $filters): array;
}