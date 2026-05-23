<?php
namespace App\Services\NegaraMitra;

use App\Repositories\NegaraMitra\Perdagangan\TradeRepositoryInterface;

class TradeOverviewService
{
    public function __construct(protected TradeRepositoryInterface $repo) {}

    public function getComposite(array $filters, array $include): array {
        return $this->repo->getComposite($filters, $include);
    }

    public function getSummary(array $filters): array {
        return $this->repo->getSummary($filters);
    }

    public function getTimeseries(array $filters): array {
        return $this->repo->getTimeseries($filters);
    }

    public function getTopProductsExport(array $filters): array {
        return $this->repo->getTopProducts($filters, 'Export');
    }

    public function getTopProductsImport(array $filters): array {
        return $this->repo->getTopProducts($filters, 'Import');
    }
}
