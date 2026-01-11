<?php

namespace App\Services\Cms;

use App\Models\Campaign;
use App\Repositories\Cms\CampaignRepository;
use App\Repositories\Newsletters\NewsletterRepository;

class CampaignService
{
    public function __construct(
        private readonly CampaignRepository   $campaignRepository,
        private readonly NewsletterRepository $newsletterRepository
    )
    {
    }

    public function resolveCampaignOrNewsletter(?string $campaignSlug, ?int $newsletterId, int $siteId): array
    {
        // Priority 1: Campaign
        if ($campaignSlug) {
            $campaign = $this->getCampaignForSignup($campaignSlug, $siteId);

            if ($campaign) {
                $validation = $this->validateCampaign($campaign);

                if (!$validation['valid']) {
                    return [
                        'success' => false,
                        'error' => implode(', ', $validation['errors'])
                    ];
                }

                // If campaign has a newsletter, use it
                if ($campaign->newsletter_id) {
                    return [
                        'success' => true,
                        'newsletter_id' => $campaign->newsletter_id,
                        'campaign_id' => $campaign->id,
                        'campaign' => $campaign
                    ];
                }

                // Campaign exists but no newsletter - valid for tracking only
                return [
                    'success' => true,
                    'newsletter_id' => null,
                    'campaign_id' => $campaign->id,
                    'campaign' => $campaign
                ];
            }
        }

        // Priority 2: Explicit newsletter ID
        if ($newsletterId) {
            return [
                'success' => true,
                'newsletter_id' => $newsletterId,
                'campaign_id' => null,
                'campaign' => null
            ];
        }

        // Priority 3: Default newsletter
        $defaultNewsletter = $this->newsletterRepository->getDefaultNewsletterForSite($siteId);

        if (!$defaultNewsletter) {
            return [
                'success' => false,
                'error' => 'No newsletter available for subscription'
            ];
        }

        return [
            'success' => true,
            'newsletter_id' => $defaultNewsletter->id,
            'campaign_id' => null,
            'campaign' => null
        ];
    }

    public function getCampaignForSignup(?string $campaignSlug, int $siteId): ?Campaign
    {
        if (!$campaignSlug) {
            return null;
        }

        $campaign = $this->campaignRepository->findBySlug($campaignSlug, $siteId);

        if (!$campaign || !$campaign->isActive()) {
            return null;
        }

        return $campaign;
    }

    public function validateCampaign(Campaign $campaign): array
    {
        $errors = [];

        if ($campaign->hasEnded()) {
            $errors[] = 'Campaign has ended';
        }

        if (!$campaign->isActive()) {
            $errors[] = 'Campaign is not active';
        }

        if ($campaign->newsletter_id) {
            $newsletter = $this->newsletterRepository->find($campaign->newsletter_id);
            if (!$newsletter || !$newsletter->active) {
                $errors[] = 'Associated newsletter is not available';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    public function getActiveCampaignsForDisplay(int $siteId): array
    {
        $campaigns = $this->campaignRepository->getActiveCampaigns($siteId);

        return $campaigns->map(function ($campaign) {
            return [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'slug' => $campaign->slug,
                'description' => $campaign->description,
                'newsletter_id' => $campaign->newsletter_id,
                'gates_premium_content' => $campaign->gates_premium_content,
                'end_date' => $campaign->end_date?->format('Y-m-d H:i:s'),
            ];
        })->toArray();
    }

    public function trackCampaignSignup(int $campaignId): void
    {
        // This could be extended to track signups in a separate analytics table
        // For now, the subscriber record itself tracks the campaign relationship
    }

    public function canAccessPremiumContent(?int $campaignId, int $siteId): bool
    {
        if (!$campaignId) {
            return false;
        }

        $campaign = $this->campaignRepository->find($campaignId);

        if (!$campaign || $campaign->site_id !== $siteId) {
            return false;
        }

        return $campaign->gatesPremiumContent() && $campaign->isActive();
    }
}