<?php

namespace App\Repositories\NegaraMitra\Jasa;

interface ServiceRepositoryInterface
{
  public function getLatestYear(array $filters): ?int;
  public function getSummary(array $filters): array;
  public function getTimeseries(array $filters): array;
  public function getTopServices(array $filters, string $flow): array;
  public function getComposite(array $filters, array $include): array;
  public function getCountryComposite(array $filters, array $include): array;
}
