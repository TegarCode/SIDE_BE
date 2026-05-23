<?php
// app/Repositories/NegaraMitra/Investasi/InvestmentRepositoryInterface.php

namespace App\Repositories\NegaraMitra\Investasi;

interface InvestmentRepositoryInterface
{
  public function getLatestYear(array $filters): ?int;

  public function getSummary(array $filters): array;

  public function getInboundByPartner(array $filters): array;

  public function getOutboundByPartner(array $filters): array;

  public function getComposite(array $filters, array $include): array;

  public function getTimeseries(array $filters): array;
  
}
