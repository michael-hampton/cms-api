<?php

namespace App\Models;

use App\Enums\MemberInsights\Newsletters\NewsletterRelationType;

class NewsletterRelation extends Model
{
    protected $table = 'newsletter_relations';

    protected $fillable = [
        'newsletter_id',
        'related_newsletter_id',
        'relation_type',
        'priority',
    ];

    protected $casts = [
        'relation_type' => NewsletterRelationType::class,
        'priority' => 'integer',
    ];

    public function sourceNewsletter(bool $relation = false)
    {
        return $this->belongsTo(Newsletter::class, 'newsletter_id', 'id', $relation);
    }

    public function relatedNewsletter(bool $relation = false)
    {
        return $this->belongsTo(Newsletter::class, 'related_newsletter_id', 'id', $relation);
    }
}
