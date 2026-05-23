<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AirportSeeder extends Seeder
{
    public function run()
    {
        $data = json_decode(
            file_get_contents(database_path('indo-airport_with_coord.json')),
            true
        );

        foreach ($data as $airport) {

            if (!$airport['latitude'] || !$airport['longitude']) {
                continue;
            }

            DB::table('airports')->insert([
                'name'      => $airport['Nama bandara'],
                'province'  => $airport['Provinsi'],
                'city'      => $airport['IATA'],
                'iata'      => $airport['ICAO'],
                'latitude'  => $airport['latitude'],
                'longitude' => $airport['longitude'],
            ]);
        }
    }
}
