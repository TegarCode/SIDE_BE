<?php

namespace App\Repositories\NegaraMitra\Overview;

interface OverviewRepositoryInterface
{
    public function computeStats(string $alpha3): array;
    public function computeTradeCountry(): array;
}
