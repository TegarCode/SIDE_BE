<?php

namespace App\Repositories\Analisis\EksporUtama;

interface EksporUtamaRepositoryInterface
{
    public function getEksporUtama(array $filters): array;
}
