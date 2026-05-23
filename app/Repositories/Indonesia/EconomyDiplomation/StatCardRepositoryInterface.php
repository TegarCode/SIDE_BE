<?php

namespace App\Repositories\Indonesia\EconomyDiplomation;

interface StatCardRepositoryInterface
{
    public function computeStats(array $filters): array;
}
