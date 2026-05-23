<?php

namespace App\Repositories\NegaraMitra\Overview;

interface TopPariwisataRepositoryInterface
{
    public function topPariwisata(string $alpha3): array;
}
