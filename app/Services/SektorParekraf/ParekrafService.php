<?php

namespace App\Services\SektorParekraf;

use App\Repositories\SektorParekraf\AirportRepository;

class ParekrafService
{
    protected $airportRepo;

    public function __construct(AirportRepository $airportRepo)
    {
        $this->airportRepo = $airportRepo;
    }

    public function getAirports()
    {
        return $this->airportRepo->getAll();
    }
}
