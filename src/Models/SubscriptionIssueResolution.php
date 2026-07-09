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
        'decision_source',
        'replacement_policy_id',
        'fulfilment_replacement_id',
        'extension_fulfilment_id',
        'metadata',
        'created_by',
    ];

    protected $casts = [
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

    public function replacementPolicy($relation = false)
    {
        return $this->belongsTo(ReplacementPolicy::class, 'replacement_policy_id', 'id', $relation);
    }
}