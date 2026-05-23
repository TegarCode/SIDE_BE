<?php

namespace App\Repositories\Indonesia\Infrastruktur;

interface InfrastrukturRepositoryInterface
{
    public function perwakilan(array $filters): array;
    public function perwakilanAsing(array $filters): array;
}
