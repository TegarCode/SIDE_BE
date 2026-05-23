<?php

namespace App\Repositories\SektorJasa\Overview;

interface OverviewRepositoryInterface
{
    public function getNilaiJasa(array $filters): array;
    public function getStats(array $filters): array;
}