<?php

namespace App\Repositories\Indonesia\EconomyDiplomation;

interface NilaiPerdaganganRepositoryInterface
{
  public function nilaiPerdagangan(array $filters, ?int $kodeSumber = null): array;

  public function topProduk(array $filters, int $kodeSumber = 5, int $limit = 50): array;
}
