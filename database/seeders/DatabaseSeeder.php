<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
  /**
   * Seed the application's database.
   */
  public function run(): void
  {
    $this->call(PermissionSeeder::class);
    $this->call(RoleTableSeeder::class);
    $this->call(UserTableSeeder::class);
    $this->call(ApiClientSeeder::class);
    $this->call(TutorialPlaylistSeeder::class);
    $this->call(FaqSeeder::class);
    // $this->call(AirportSeeder::class);
  }
  
}
