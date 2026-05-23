<?php

namespace App\Repositories\SektorPrioritas\MineralKritis;

interface MineralKritisRepositoryInterface
{
  public function nilaiPerdaganganPerNegara(array $filters, int $kodeSumber = 5): array;
  public function nilaiPerdaganganPerProduk(array $filters, int $kodeSumber = 5, int $limit = 50): array;
}
