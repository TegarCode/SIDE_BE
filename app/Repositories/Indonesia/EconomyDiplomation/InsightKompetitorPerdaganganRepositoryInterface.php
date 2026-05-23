<?php

namespace App\Repositories\Indonesia\EconomyDiplomation;

interface InsightKompetitorPerdaganganRepositoryInterface
{
  public function mergeCompetitorsFromSourceFive(array $baseData, array $competitorData): array;

  public function buildInsightResponse(array $data, string $hsCode, string $negara, array $filters = []): array;
}

