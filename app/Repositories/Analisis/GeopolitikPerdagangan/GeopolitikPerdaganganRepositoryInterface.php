<?php

namespace App\Repositories\Analisis\GeopolitikPerdagangan;

interface GeopolitikPerdaganganRepositoryInterface
{
  public function getGeopolitikPerdagangan(array $filters): array;
}
