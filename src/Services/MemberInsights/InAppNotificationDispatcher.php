<?php

namespace App\Services\MemberInsights;

use App\Framework\Notifications\NotificationDispatcher;
use App\Framework\Support\Logger;
use App\Models\Campaign;
use App\Models\Member;
use App\Notifications\CampaignNotification;

class InAppNotificationDispatcher
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly Logger                 $logger,
    )
    {
    }

    public function dispatch(Member $member, Campaign $campaign): bool
    {
        try {
            $payload = $this->buildPayload($campaign);

            $notification = new CampaignNotification(
                userId: $member->id,
                subject: $payload['title'],
                body: $payload['body'],
            );

            $count = $this->dispatcher->dispatch($notification);

            $this->logger->info('In-app notification dispatched', [
                'member_id' => $member->id,
                'campaign_id' => $campaign->id,
                'channels_hit' => $count,
            ]);

            return $count > 0;

        } catch (\Throwable $e) {
            $this->logger->error('In-app notification dispatch failed', [
                'member_id' => $member->id,
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Normalises campaign data into a notification-friendly structure.
     */
    private function buildPayload(Campaign $campaign): array
    {
        return [
            'title' => $campaign->name,
            'body' => $campaign->push_body
                ?? $campaign->description
                    ?? '',
            'icon' => $campaign->push_icon ?? null,
            'url' => $campaign->push_url ?? null,
            'tag' => "campaign-{$campaign->id}",
        ];
    }
}