<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FaqItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'faq_items';

    protected $fillable = [
        'uuid',
        'faq_topic_id',
        'question',
        'answer',
        'order',
    ];

    public function topic(): BelongsTo
    {
        return $this->belongsTo(FaqTopic::class, 'faq_topic_id');
    }
}
