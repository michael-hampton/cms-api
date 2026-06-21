<?php

declare(strict_types=1);

namespace App\Models;

class SubscriptionIssueResolution extends Model
{
    protected $table = 'subscription_issue_resolutions';

    protected $fillable = [
        'site_id',
        'subscription_id',
        'issue_delivery_id',
        'category',
        'decision',
        'reason',
        'business_decision',
        'fulfilment_replacement_id',
        'extension_fulfilment_id',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'business_decision' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function subscription($relation = false)
    {
        return $this->belongsTo(Subscription::class, 'subscription_id', 'id', $relation);
    }

    public function issueDelivery($relation = false)
    {
        return $this->belongsTo(IssueDelivery::class, 'issue_delivery_id', 'id', $relation);
    }
}
