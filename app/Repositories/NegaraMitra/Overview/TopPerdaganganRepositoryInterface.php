<?php

namespace App\Repositories\NegaraMitra\Overview;

interface TopPerdaganganRepositoryInterface
{
    public function topPerdagangan(string $alpha3): array;
}
