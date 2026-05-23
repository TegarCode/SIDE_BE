<?php

namespace Database\Seeders;

use App\Models\ApiClient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApiClientSeeder extends Seeder
{
    public function run(): void
    {
        $allPermissions = DB::table('permissions')
            ->where('guard_name', 'web')
            ->pluck('name')
            ->values()
            ->all();

        $plainTextApiKey = '4FBOMbaBufAFEOhjE98fa7p9jn1ViIhcON1qJJL8';

        $existingUuid = ApiClient::query()
            ->where('name', 'frontend side')
            ->value('uuid');

        ApiClient::query()->updateOrCreate(
            ['name' => 'frontend side'],
            [
                'uuid' => $existingUuid ?: (string) Str::uuid(),
                'description' => 'API client untuk frontend SIDE.',
                'api_key' => hash('sha256', $plainTextApiKey),
                'abilities' => $allPermissions,
                'allowed_domains' => [],
                'active' => true,
            ]
        );
    }
}
