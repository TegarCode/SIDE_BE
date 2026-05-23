<?php

namespace App\Repositories\ReportGenerator\MarketShare;

interface MarketShareRepositoryInterface
{
    public function getTableFilterData(array $filters): array;
    public function getSnapshotData(string $origin, string $destination, string $strategy): array;
    public function getCountryName(string $alpha3): string;
    public function getSourceName(int $alpha3): string;
}
