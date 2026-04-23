<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;
use App\Models\CampaignVariant;
use App\Repositories\Cms\CampaignRepository;

class CampaignResource extends JsonResource
{
    public function toArray(): array
    {
        $campaignRepository = app(CampaignRepository::class);

        $variants = CampaignVariant::where('campaign_id', $this->getAttribute('id'))
            ->orderBy('key')
            ->get();

        // Populate variant stats from the repository so the frontend @Input
        // never needs a separate API call for the variants tab.
        $variantStats = $variants->isNotEmpty()
            ? $campaignRepository->aggregateStats($this->getAttribute('id'), $variants)
            : [];

        return [
            'id' => $this->getAttribute('id'),
            'site_id' => $this->getAttribute('site_id'),
            'name' => $this->getAttribute('name'),
            'slug' => $this->getAttribute('slug'),
            'description' => $this->getAttribute('description'),
            'newsletter_id' => $this->getAttribute('newsletter_id'),
            'is_active' => $this->getAttribute('is_active'),
            'gates_premium_content' => $this->getAttribute('gates_premium_content'),
            'status' => $this->getAttribute('status'),
            'campaign_type' => $this->getAttribute('campaign_type'),
            'campaign_id' => $this->getAttribute('campaign_id'),
            'start_date' => $this->getAttribute('start_date')?->format('Y-m-d H:i:s'),
            'end_date' => $this->getAttribute('end_date')?->format('Y-m-d H:i:s'),
            'tracking_params' => $this->getAttribute('tracking_params'),
            'created_by' => $this->getAttribute('created_by'),
            'updated_by' => $this->getAttribute('updated_by'),
            'created_at' => $this->getAttribute('created_at')?->format('Y-m-d H:i:s'),
            'updated_at' => $this->getAttribute('updated_at')?->format('Y-m-d H:i:s'),
            'is_currently_active' => $this->getAttribute('is_active') === true,
            'has_ended' => $this->getAttribute('end_date') > now_datetime(),
            'subscriber_count' => $campaignRepository->getSubscriberCount($this->getAttribute('id')),
            'priority' => $this->getAttribute('priority'),
            'cooldown_hours' => $this->getAttribute('cooldown_hours'),
            'fallback_channels' => $this->getAttribute('fallback_channels'),
            'force_channel' => $this->getAttribute('force_channel'),
            'purpose' => $this->getAttribute('purpose'),
            'channel' => $this->getAttribute('channel'),
            'segment_id' => $this->getAttribute('segment_id'),
            'push_body' => $this->getAttribute('push_body'),
            'push_icon' => $this->getAttribute('push_icon'),
            'template' => $this->getAttribute('template'),
            'push_url' => $this->getAttribute('push_url'),
            'variants' => $variants->map(fn($v) => [
                'id' => $v->id,
                'key' => $v->key,
                'weight' => $v->weight,
                'subject_line' => $v->subject_line ?? null,
                'template' => $v->template ?? null,
            ])->values()->all(),
            'variant_stats' => $variantStats,
        ];
    }
}