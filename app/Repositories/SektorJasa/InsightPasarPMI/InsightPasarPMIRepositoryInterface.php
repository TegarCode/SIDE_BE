<?php

namespace App\Repositories\SektorJasa\InsightPasarPMI;

interface InsightPasarPMIRepositoryInterface
{
    public function getNilaiJasa(array $filters): array;
    public function getStats(array $filters): array;
}