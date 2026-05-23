<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UserTableSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    $hasUuidColumn = Schema::hasColumn('users', 'uuid');
    $hasStatusColumn = Schema::hasColumn('users', 'status');

    $users = [
      [
        'email' => 'visitor@gmail.com',
        'name' => 'Visitor',
        'password' => '$2y$12$LIAcD6OYgcpfiWtH01cQEeP.h4Q3/XtnzMeaBwcM4wbg615s7Hccy',
        'roles' => ['visitor'],
      ],
      [
        'email' => 'admin@gmail.com',
        'name' => 'Admin',
        'password' => '$2y$12$irYSVPA0IyyVSX2cJreYFuyBd5.Hhz/yG3qXuedRavayD84qzZDaS',
        'roles' => ['admin'],
      ],
      [
        'email' => 'user@gmail.com',
        'name' => 'User',
        'password' => '$2y$12$LIAcD6OYgcpfiWtH01cQEeP.h4Q3/XtnzMeaBwcM4wbg615s7Hccy',
        'roles' => ['user'],
      ],
      [
        'email' => 'binusrecruitment@user.com',
        'name' => 'Binus Recruitment',
        'password' => '$2y$12$ag5RrtMA38MDH6hMVaqW6OKN3dkzqBgsQkkLAIq.6m2Tg21yzD3qO',
        'roles' => ['visitor'],
      ],
      [
        'email' => 'visitorpertamina@user.com',
        'name' => 'Visitor Pertamina',
        'password' => '$2y$12$WW6amGifm7uSOKuaNnwdseidroNv.9B92I86XqtUtWNScVwoNDuHm',
        'roles' => ['visitor'],
      ],
      [
        'email' => 'superadmin@side.com',
        'name' => 'Super Admin',
        'password' => '$2y$12$l7Xoh3xiZ8JnJLqRphPouu36sht9spVvewelPRPhv3yTIuiFp/.mu',
        'roles' => ['super_admin'],
      ],
    ];

    foreach ($users as $data) {
      $user = User::updateOrCreate(
        ['email' => $data['email']],
        [
          ...($hasUuidColumn ? ['uuid' => User::query()->where('email', $data['email'])->value('uuid') ?: (string) Str::uuid()] : []),
          'name' => $data['name'],
          'password' => $data['password'],
          ...($hasStatusColumn ? ['status' => 'active'] : []),
        ]
      );

      $user->syncRoles($data['roles']);
    }
  }
}
