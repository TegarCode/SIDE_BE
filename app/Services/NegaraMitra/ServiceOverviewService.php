<?php

namespace App\Services\NegaraMitra;

use App\Repositories\NegaraMitra\Jasa\ServiceRepositoryInterface;

class ServiceOverviewService
{
    public function __construct(protected ServiceRepositoryInterface $repo) {}

    public function getComposite(array $filters, array $include): array
    {
        return $this->repo->getComposite($filters, $include);
    }

    public function getCountryComposite(array $filters, array $include): array
    {
        return $this->repo->getCountryComposite($filters, $include);
    }

    public function getSummary(array $filters): array
    {
        return $this->repo->getSummary($filters);
    }

    public function getTimeseries(array $filters): array
    {
        return $this->repo->getTimeseries($filters);
    }

    /** ====== Baru (nama sesuai flow tanpa Status) ====== */
    public function getTopServicesInbound(array $filters): array
    {
        return $this->repo->getTopServices($filters, 'inbound');
    }

    public function getTopServicesOutbound(array $filters): array
    {
        return $this->repo->getTopServices($filters, 'outbound');
    }

    /** ====== Back-compat (tetap tersedia) ======
     * Map Export -> inbound (Tujuan = country),
     *     Import -> outbound (Asal = country)
     */
    public function getTopServicesExport(array $filters): array
    {
        return $this->repo->getTopServices($filters, 'inbound');
    }

    public function getTopServicesImport(array $filters): array
    {
        return $this->repo->getTopServices($filters, 'outbound');
    }
}
