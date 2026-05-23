<?php

namespace App\Repositories\NegaraMitra\Overview;

interface TopJasaRepositoryInterface
{
    public function topJasa(string $alpha3): array;
}
