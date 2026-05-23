<?php

namespace App\Repositories\SektorPrioritas\Hilirisasi;

interface NilaiPerdaganganHilirisasiRepositoryInterface
{
  public function nilaiPerdaganganPerNegara(array $filters, int $kodeSumber = 5): array;
  public function nilaiPerdaganganPerProduk(array $filters, int $kodeSumber = 5, int $limit = 50): array;
}
