<?php
namespace App\Services\SektorJasa;

use App\Repositories\SektorJasa\Overview\OverviewRepositoryInterface;

class OverviewService
{
    public function __construct(protected OverviewRepositoryInterface $repo) {}

    public function getNilaiJasa(array $filters): array
    {
        return $this->repo->getNilaiJasa($filters);
    }

    public function getStats(array $filters): array
    {
        return $this->repo->getStats($filters);
    }
}
