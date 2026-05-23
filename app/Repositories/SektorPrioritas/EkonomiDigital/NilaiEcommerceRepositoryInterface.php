<?php

namespace App\Repositories\SektorPrioritas\EkonomiDigital;

interface NilaiEcommerceRepositoryInterface
{
  public function getNilaiEcommerce(array $filters): array;
}
