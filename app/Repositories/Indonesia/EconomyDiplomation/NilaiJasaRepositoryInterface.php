<?php

namespace App\Repositories\Indonesia\EconomyDiplomation;

interface NilaiJasaRepositoryInterface
{
    public function nilaiJasa(array $filters, ?int $kodeSumber = null): array;
}
