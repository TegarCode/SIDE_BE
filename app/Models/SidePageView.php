<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SidePageView extends Model
{
    protected $table = 'side_page_views';

    protected $fillable = [
        'user_id',
        'session_id',
        'path',
        'module',
        'user_agent',
        'ip_hash',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
