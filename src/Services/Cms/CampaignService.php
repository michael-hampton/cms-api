<?php

namespace App\Services\Cms;

use App\DTO\Campaigns\CampaignResolutionResult;
use App\DTO\Campaigns\SignupContext;
use App\Enums\Campaigns\CampaignStatus;
use App\Framework\Database\Database;
use App\Models\Campaign;
use App\Models\Model;
use App\Repositories\Cms\CampaignRepository;
use App\Repositories\Cms\CampaignSignupRepository;
use App\Repositories\Newsletters\NewsletterRepository;

class CampaignService
{
    public function __construct(
        private readonly CampaignRepository   $campaignRepository,
        private readonly NewsletterRepository $newsletterRepository,
        private CampaignSignupRepository      $campaignSignupRepository,
        private Database                      $database
    )
    {
    }

    public function resolveCampaignOrNewsletter(?string $campaignSlug, ?int $newsletterId, int $siteId): CampaignResolutionResult
    {
        // Priority 1: Campaign
        if ($campaignSlug) {
            $result = $this->resolveCampaign($campaignSlug, $siteId);
            if ($result) return $result;
        }

        // Priority 2: Explicit newsletter
        if ($newsletterId) {
            return new CampaignResolutionResult(
                success: true,
                newsletterId: $newsletterId
            );
        }

        // Priority 3: Default newsletter
        return $this->resolveDefaultNewsletter($siteId);
    }

    private function resolveCampaign(string $campaignSlug, int $siteId): ?CampaignResolutionResult
    {
        $campaign = $this->getCampaignForSignup($campaignSlug, $siteId);
        if (!$campaign) return null;

        $validation = $this->validateCampaign($campaign);
        if (!$validation['valid']) {
            return new CampaignResolutionResult(
                success: false,
                error: implode('. ', $validation['errors'])
            );
        }

        return new CampaignResolutionResult(
            success: true,
            newsletterId: $campaign->newsletter_id,
            campaignId: $campaign->id,
            campaign: $campaign->toArray()
        );
    }

    private function resolveDefaultNewsletter(int $siteId): CampaignResolutionResult
    {
        $defaultNewsletter = $this->newsletterRepository->getDefaultNewsletterForSite($siteId);
        if (!$defaultNewsletter) {
            return new CampaignResolutionResult(
                success: false,
                error: 'No newsletter available for subscription'
            );
        }

        return new CampaignResolutionResult(
            success: true,
            newsletterId: $defaultNewsletter->id
        );
    }

    public function getCampaignForSignup(?string $campaignSlug, int $siteId): ?Model
    {
        if (!$campaignSlug) {
            return null;
        }

        $campaign = $this->campaignRepository->findBySlug($campaignSlug, $siteId);

        return ($campaign && $campaign->isValidForSignup()) ? $campaign : null;

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

        return $campaigns->map(fn($campaign) => [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'slug' => $campaign->slug,
            'description' => $campaign->description,
            'newsletter_id' => $campaign->newsletter_id,
            'gates_premium_content' => $campaign->gates_premium_content,
            'end_date' => $campaign->end_date?->format('Y-m-d H:i:s'),
        ])->toArray();
    }

    public function trackCampaignSignup(
        int            $campaignId,
        ?int           $userId = null,
        ?string        $email = null,
        ?SignupContext $context = null): array
    {
        $campaign = $this->campaignRepository->find($campaignId);

        if (!$campaign) {
            return [
                'success' => false
            ];
        }

        $this->database->transaction(function () use (
            $campaign,
            $userId,
            $email,
            $context
        ) {
            // 1️⃣ Source of truth
            $this->campaignSignupRepository->create([
                'campaign_id' => $campaign->id,
                'site_id' => $campaign->site_id,
                'user_id' => $userId,
                'email' => $email,
                'ip_address' => $context->ip ?? null,
                'user_agent' => $context->userAgent ?? null,
                'referrer' => $context->referrer ?? null,
            ]);

            // 2️⃣ Fast read model
            $this->campaignRepository->incrementSignupCount($campaign->id);
        });

        return [
            'success' => true,
            'campaign_id' => $campaign->id,
            'user_id' => $userId,
            'email' => $email,
        ];
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

    public function canDeleteCampaign(int $campaignId): array
    {
        $subscriberCount = $this->campaignRepository->getSubscriberCount($campaignId);

        if ($subscriberCount > 0) {
            return [
                'can_delete' => false,
                'reason' => "Cannot delete campaign with {$subscriberCount} subscribers. Deactivate instead."
            ];
        }

        return [
            'can_delete' => true
        ];
    }

    public function getCampaignWithStats(int $campaignId, int $siteId): ?array
    {
        $campaign = $this->campaignRepository->find($campaignId);

        if (!$campaign || $campaign->site_id !== $siteId) {
            return null;
        }

        return [
            'campaign' => $campaign->toArray(),
            'subscriber_count' => $this->campaignRepository->getSubscriberCount($campaignId),
            'is_currently_active' => $campaign->isActive(),
            'has_ended' => $campaign->hasEnded(),
        ];
    }

    public function pauseCampaign(int $campaignId, int $siteId): array
    {
        return $this->updateCampaignStatus(
            $campaignId,
            $siteId,
            CampaignStatus::PAUSED,
            false
        );
    }

    public function resumeCampaign(int $campaignId, int $siteId): array
    {
        return $this->updateCampaignStatus(
            $campaignId,
            $siteId,
            CampaignStatus::ACTIVE,
            true
        );
    }

    private function updateCampaignStatus(
        int            $campaignId,
        int            $siteId,
        CampaignStatus $status,
        bool           $isActive
    ): array
    {
        $campaign = $this->campaignRepository->find($campaignId);
        if (!$campaign || $campaign->site_id !== $siteId) {
            return ['success' => false, 'error' => 'Campaign not found', 'code' => 404];
        }

        // Update status in repository
        $updated = $this->campaignRepository->update($campaignId, [
            'status' => $status->value,
            'is_active' => $isActive
        ]);

        return [
            'success' => true,
            'campaign' => $updated,
            'message' => ucfirst($status->value) . ' campaign successfully'
        ];
    }
}