<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ApiClient extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (self $apiClient) {
            if (empty($apiClient->uuid)) {
                $apiClient->uuid = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'api_key',
        'abilities',
        'allowed_domains',
        'active',
    ];

    protected $casts = [
        'abilities' => 'array',
        'allowed_domains' => 'array',
        'active' => 'boolean',
        'deleted_at' => 'datetime',
    ];
}
