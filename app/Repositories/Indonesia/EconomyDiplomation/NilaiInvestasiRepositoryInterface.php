<?php

namespace App\Repositories\Indonesia\EconomyDiplomation;

interface NilaiInvestasiRepositoryInterface
{
    public function nilaiInvestasi(array $filters, ?int $kodeSumber = null): array;
}
