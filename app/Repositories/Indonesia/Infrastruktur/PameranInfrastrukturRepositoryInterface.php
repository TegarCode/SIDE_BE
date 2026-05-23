<?php

namespace App\Repositories\Indonesia\Infrastruktur;

interface PameranInfrastrukturRepositoryInterface
{
    public function pameranIndonesia(array $filters): array;
    public function pameranPerwakilan(array $filters): array;
}
