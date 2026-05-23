<?php

namespace App\Repositories\Indonesia\EconomyDiplomation;

interface NilaiWisatawanRepositoryInterface
{
    public function nilaiWisatawan(array $filters, ?int $kodeSumber = null): array;
}
