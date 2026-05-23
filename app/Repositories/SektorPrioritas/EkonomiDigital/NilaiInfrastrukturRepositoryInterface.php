<?php

namespace App\Repositories\SektorPrioritas\EkonomiDigital;

interface NilaiInfrastrukturRepositoryInterface
{
  public function getNilaiInfrastruktur(array $filters): array;
}
