<?php

namespace App\Repositories\NegaraMitra\Perdagangan;

interface TradeRepositoryInterface
{
    public function getLatestYear(array $filters): ?int;
    public function getSummary(array $filters): array;
    public function getTimeseries(array $filters): array;
    public function getTopProducts(array $filters, string $flow): array;
    public function getComposite(array $filters, array $include): array;
}
