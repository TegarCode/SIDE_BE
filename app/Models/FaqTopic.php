<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FaqTopic extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'faq_topics';

    protected $fillable = [
        'uuid',
        'topic',
        'summary',
        'is_featured',
        'order',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(FaqItem::class, 'faq_topic_id');
    }
}
