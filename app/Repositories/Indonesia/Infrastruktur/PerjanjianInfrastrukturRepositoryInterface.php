<?php

namespace App\Repositories\Indonesia\Infrastruktur;

interface PerjanjianInfrastrukturRepositoryInterface
{
    public function perjanjianNegara(array $filters): array;
}
