<?php

namespace App\Http\Controllers\Api\V1\SektorParekraf;


use App\Http\Controllers\Controller;
use App\Services\SektorParekraf\ParekrafService;

class OverviewController extends Controller
{
    protected $parekrafService;

    public function __construct(ParekrafService $parekrafService)
    {
        $this->parekrafService = $parekrafService;
    }

    public function airports()
    {
        $airports = $this->parekrafService->getAirports();
        return response()->json($airports);
    }
}
