<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Airport extends Model
{
    protected $table = 'airports'; 
    public $timestamps = false;    

    protected $fillable = [
        'iata',
        'icao',
        'name',
        'city',
        'province',
        'country',
        'latitude',
        'longitude'
    ];
}
