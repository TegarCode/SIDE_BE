<?php

namespace App\Repositories\DataGenerator\KinerjaEkonomi;

interface KinerjaEkonomiRepositoryInterface
{
    public function getTableFilterData(array $filters): array;
    public function getVisualizationFilterData(array $filters): array;
}
