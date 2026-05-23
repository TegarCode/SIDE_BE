<?php

namespace App\Repositories\Analisis\AnalisisRSCATBI;

interface AnalisisRSCATBIRepositoryInterface
{
    public function getData(array $filters);
    public function getCalculation(array $filters);
    public function getComparison(array $filters);
}