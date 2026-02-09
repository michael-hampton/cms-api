<?php

namespace App\Services\Newsletter\Services;

class TrackingUrlBuilder
{
    public function buildProductUrl(string $slug, ?int $sendId, bool $includeTracking): string
    {
        if (!$includeTracking || !$sendId) {
            return url("/{$slug}");
        }

        return url("/products/{$slug}");
    }

    public function buildOfferUrl(int $offerId, ?int $sendId, bool $includeTracking, int $newsletterId): string
    {
        if (!$includeTracking) {
            return url("/offers/{$offerId}");
        }

        $params = [
            'context' => 'newsletter',
            'surface' => $newsletterId
        ];

        return url("/offers/{$offerId}/click?" . http_build_query($params));
    }

    public function buildRewardUrl(int $rewardId, ?int $sendId, bool $includeTracking): string
    {
        return url("/rewards/{$rewardId}/view");
    }

    public function buildPageTrackingUrl(int $pageId, string $slug, ?int $sendId): string
    {
        $params = [
            'send_id' => '{{SEND_ID}}',
            'page_id' => $pageId,
            'e' => '{{TRACKING_EMAIL}}',
            'redirect' => $slug
        ];

        return url('/newsletters/track-view?' . http_build_query($params));
    }
}