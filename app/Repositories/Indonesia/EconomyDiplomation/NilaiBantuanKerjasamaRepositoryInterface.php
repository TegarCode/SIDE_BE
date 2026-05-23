<?php

namespace App\Repositories\Indonesia\EconomyDiplomation;

interface NilaiBantuanKerjasamaRepositoryInterface
{
    public function nilaiBantuanKerjasama(array $filters, ?int $kodeSumber = null): array;
}
