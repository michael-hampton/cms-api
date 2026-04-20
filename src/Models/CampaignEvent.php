<?php

namespace App\Models;

/**
 * campaign_events
 *
 * One row per open or click engagement event.
 *
 * metadata JSON shape:
 *   open  → {}
 *   click → { "url": "https://...", "block_key": "churn_offer" }
 *
 * block_key is extracted by CampaignEventRepository::clicksByBlockKey()
 * using JSON_EXTRACT for T13 block-level performance reports.
 *
 * @property int $id
 * @property int $member_id
 * @property int $campaign_id
 * @property string $event_type   open|click
 * @property array|null $metadata
 * @property int|null $variant_id   FK campaign_variants.id (T14)
 * @property \DateTime $created_at
 */
class CampaignEvent extends Model
{
    protected $table = 'campaign_events';

    protected $fillable = [
        'member_id',
        'campaign_id',
        'event_type',
        'metadata',
        'variant_id',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected $dates = ['created_at'];

    // ── Relationships ──────────────────────────────────────────────────────

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function variant()
    {
        return $this->belongsTo(CampaignVariant::class, 'variant_id');
    }
}