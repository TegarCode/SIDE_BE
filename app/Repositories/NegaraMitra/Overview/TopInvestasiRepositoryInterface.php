<?php

namespace App\Repositories\NegaraMitra\Overview;

interface TopInvestasiRepositoryInterface
{
    public function topInvestasi(string $alpha3): array;
}
