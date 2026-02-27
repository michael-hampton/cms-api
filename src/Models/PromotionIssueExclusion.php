<?php

namespace App\Models;

class PromotionIssueExclusion extends Model
{
    protected $table = 'promotion_issue_exclusions';

    public $timestamps = false;

    protected $fillable = [
        'promotion_id',
        'issue_delivery_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function promotion($relation = false)
    {
        return $this->belongsTo(GiftPromotion::class, 'promotion_id', 'id', $relation);
    }

    public function issueDelivery($relation = false)
    {
        return $this->belongsTo(IssueDelivery::class, 'issue_delivery_id', 'id', $relation);
    }
}