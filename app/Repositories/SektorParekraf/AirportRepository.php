<?php

namespace App\Repositories\SektorParekraf;

use App\Models\Airport;

class AirportRepository
{
    public function getAll()
    {
        return Airport::all();
    }
}
