<?php

namespace App\Repositories\Analisis\AnalisisRCAEPD;

interface AnalisisRCAEPDRepositoryInterface
{
    public function getData(array $filters);

    public function getCalculation(array $filters);

    public function getComparison(array $filters);

    public function getXModelOptions(array $filters);
}
