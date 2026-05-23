<?php

namespace App\Repositories\ReportGenerator\KerjasamaPerdagangan;

interface KerjasamaPerdaganganRepositoryInterface
{
    public function getTableFilterData(array $filters): array;
    public function getCountryName(string $alpha3): string;
    public function getSourceName(int $alpha3): string;
}
