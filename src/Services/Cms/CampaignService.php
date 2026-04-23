<?php

namespace App\Services\Cms;

use App\DTO\Campaigns\CampaignResolutionResult;
use App\DTO\Campaigns\SignupContext;
use App\Enums\Campaigns\CampaignStatus;
use App\Framework\Database\Database;
use App\Mail\Campaigns\CompleteYourProfileMail;
use App\Mail\Campaigns\ConvertToActionMail;
use App\Mail\Campaigns\CreateMoreContentMail;
use App\Mail\Campaigns\EarlyAccessMail;
use App\Mail\Campaigns\NudgeToCommentMail;
use App\Mail\Campaigns\RecommendedProductsMail;
use App\Mail\Campaigns\RewardEngagementMail;
use App\Mail\Campaigns\SoftReengagementMail;
use App\Mail\Campaigns\StartEngagingMail;
use App\Mail\Campaigns\VipRewardsMail;
use App\Mail\Campaigns\WelcomeSeriesMail;
use App\Mail\Campaigns\WeMissYouMail;
use App\Models\Campaign;
use App\Models\CampaignVariant;
use App\Models\Model;
use App\Repositories\Cms\CampaignRepository;
use App\Repositories\Cms\CampaignSignupRepository;
use App\Repositories\MemberInsights\CampaignVariantRepository;
use App\Repositories\MemberInsights\SegmentRepository;
use App\Repositories\Newsletters\NewsletterRepository;

class CampaignService
{
    public function __construct(
        private readonly CampaignRepository        $campaignRepository,
        private readonly NewsletterRepository      $newsletterRepository,
        private readonly CampaignSignupRepository  $campaignSignupRepository,
        private readonly SegmentRepository         $segmentRepository,
        private readonly CampaignVariantRepository $variantRepository,
        private readonly Database                  $database
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

        return $this->campaignRepository->findValidForSignup($campaignSlug, $siteId);
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

    public function create(array $payload, int $siteId): Campaign
    {
        $variants = $payload['variants'] ?? [];
        unset($payload['variants']);

        $payload = $this->normalizePayload($payload, $siteId, isUpdate: false);

        if ($this->campaignRepository->existsBySlugForSite($payload['slug'], $siteId)) {
            throw new \InvalidArgumentException("Campaign slug \"{$payload['slug']}\" already exists.");
        }

        $this->assertSegmentExists($payload['segment_id']);

        if (isset($payload['template'])) {
            $this->assertMailableExists($payload['template']);
        }

        $this->validateVariants($variants);

        return $this->database->transaction(function () use ($payload, $variants): Campaign {
            $campaign = $this->campaignRepository->create($payload);
            $this->persistVariants($campaign->id, $variants);
            return $campaign;
        });
    }

    public function update(int $id, array $payload, int $siteId): Campaign
    {
        // variants key is optional on update — omitting it leaves variants untouched
        $variantsProvided = array_key_exists('variants', $payload);
        $variants = $payload['variants'] ?? null;
        unset($payload['variants']);

        $campaign = $this->campaignRepository->findForSite($id, $siteId);

        if (
            isset($payload['slug']) &&
            $this->campaignRepository->existsBySlugForSite($payload['slug'], $siteId, $campaign->id)
        ) {
            throw new \InvalidArgumentException("Campaign slug \"{$payload['slug']}\" already exists.");
        }

        if (isset($payload['template'])) {
            $this->assertMailableExists($payload['template']);
        }

        if (array_key_exists('segment_id', $payload)) {
            $this->assertSegmentExists($payload['segment_id']);
        }

        if ($variantsProvided) {
            $this->validateVariants($variants);
        }

        $payload = $this->normalizePayload($payload, $siteId, isUpdate: true);

        return $this->database->transaction(function () use ($campaign, $payload, $siteId, $variantsProvided, $variants): Campaign {
            $this->campaignRepository->update($campaign->id, $payload);

            if ($variantsProvided) {
                $this->persistVariants($campaign->id, $variants);
            }

            return $this->campaignRepository->findForSite($campaign->id, $siteId);
        });
    }

    /**
     * Delete and recreate variants for a campaign within an open transaction.
     */
    private function persistVariants(int $campaignId, array $variants): void
    {
        $this->variantRepository->deleteForCampaign($campaignId);

        foreach ($variants as $v) {
            $this->variantRepository->create([
                'campaign_id' => $campaignId,
                'key' => strtoupper(trim($v['key'])),
                'weight' => (int)$v['weight'],
                'subject_line' => isset($v['subject_line']) ? trim($v['subject_line']) : null,
                'template' => isset($v['template']) ? trim($v['template']) : null,
                'blocks' => $v['blocks'] ?? null,
            ]);
        }
    }


    public function delete(int $id, int $siteId): void
    {
        $campaign = $this->campaignRepository->findForSite($id, $siteId);

        $this->database->transaction(function () use ($campaign) {
            $this->campaignRepository->delete($campaign->id);
        });
    }

    /**
     * Replace the full variant set for a campaign atomically.
     *
     * $variants format:
     * [
     *   ['key' => 'A', 'weight' => 60, 'template' => null],  // uses campaign template
     *   ['key' => 'B', 'weight' => 40, 'template' => 'App\\Mail\\Campaigns\\SoftReengagementMail'],
     * ]
     *
     * Passing an empty array removes all variants (disables A/B testing).
     *
     * @throws \InvalidArgumentException on weight sum != 100 or duplicate keys
     */
    public function setVariants(int $campaignId, int $siteId, array $variants): array
    {
        $this->assertCampaignOwnership($campaignId, $siteId);
        $this->validateVariants($variants);

        return $this->database->transaction(function () use ($campaignId, $variants): array {
            $this->variantRepository->deleteForCampaign($campaignId);

            if (empty($variants)) {
                return [];
            }

            $created = [];
            foreach ($variants as $v) {
                $created[] = $this->variantRepository->create([
                    'campaign_id' => $campaignId,
                    'key' => strtoupper(trim($v['key'])),
                    'weight' => (int)$v['weight'],
                    'subject_line' => isset($v['subject_line']) ? trim($v['subject_line']) : null,
                    'template' => isset($v['template']) ? trim($v['template']) : null,
                    'blocks' => $v['blocks'] ?? null,
                ]);
            }

            return $created;
        });
    }

    /**
     * Retrieve the current variants for a campaign.
     *
     * @return CampaignVariant[]
     */
    public function getVariants(int $campaignId, int $siteId): array
    {
        $this->findForSite($campaignId, $siteId);

        return $this->variantRepository->findForCampaign($campaignId)
            ->toArray();
    }

    public function availableMailables(): array
    {
        return [
            WelcomeSeriesMail::class => 'Welcome Series',
            CompleteYourProfileMail::class => 'Complete Your Profile',
            StartEngagingMail::class => 'Start Engaging',
            NudgeToCommentMail::class => 'Nudge to Comment',
            CreateMoreContentMail::class => 'Create More Content',
            EarlyAccessMail::class => 'Early Access',
            RewardEngagementMail::class => 'Reward Engagement',
            VipRewardsMail::class => 'VIP Rewards',
            RecommendedProductsMail::class => 'Recommended Products',
            ConvertToActionMail::class => 'Convert to Action',
            SoftReengagementMail::class => 'Soft Re-engagement',
            WeMissYouMail::class => 'We Miss You',
        ];
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function findForSite(int $campaignId, int $siteId): Campaign
    {
        $campaign = $this->campaignRepository->find($campaignId);

        if ($campaign === null || $campaign->site_id !== $siteId) {
            throw new \InvalidArgumentException("Campaign [{$campaignId}] not found.");
        }

        return $campaign;
    }

    private function assertSegmentExists(?int $segmentId): void
    {
        if ($segmentId === null) {
            return;
        }

        if ($this->segmentRepository->find($segmentId) === null) {
            throw new \InvalidArgumentException("Segment #{$segmentId} not found.");
        }
    }

    private function assertMailableExists(string $class): void
    {
        if (!class_exists($class)) {
            throw new \InvalidArgumentException(
                "Mailable class [{$class}] does not exist. "
                . "Register it in CampaignAdminService::availableMailables() first."
            );
        }
    }

    private function validateVariants(array $variants): void
    {
        if (empty($variants)) {
            return;
        }

        $totalWeight = array_sum(array_column($variants, 'weight'));
        if ($totalWeight !== 100) {
            throw new \InvalidArgumentException(
                "Variant weights must sum to 100. Got {$totalWeight}."
            );
        }

        $keys = array_map(fn($v) => strtoupper(trim($v['key'] ?? '')), $variants);
        if (count($keys) !== count(array_unique($keys))) {
            throw new \InvalidArgumentException('Variant keys must be unique (A, B, C…).');
        }

        $allowedKeys = ['A', 'B', 'C', 'D', 'E'];
        foreach ($variants as $i => $v) {
            if (empty($v['key']) || !in_array(strtoupper(trim($v['key'])), $allowedKeys, true)) {
                throw new \InvalidArgumentException(
                    "Variant #{$i}: key must be one of A–E, got \"{$v['key']}\"."
                );
            }
            if (!isset($v['weight']) || (int)$v['weight'] <= 0) {
                throw new \InvalidArgumentException(
                    "Variant #{$i}: weight must be a positive integer."
                );
            }
        }
    }

    private function normalizePayload(array $payload, int $siteId, bool $isUpdate): array
    {
        $normalized = [
            'site_id' => $siteId,
            'name' => isset($payload['name']) ? trim($payload['name']) : null,
            'slug' => isset($payload['slug']) ? trim($payload['slug']) : null,
            'description' => $payload['description'] ?? null,
            'is_active' => $payload['is_active'] ?? ($isUpdate ? null : true),
            'start_date' => $payload['start_date'] ?? null,
            'end_date' => $payload['end_date'] ?? null,
            'segment_id' => $payload['segment_id'] ?? null,
            'channel' => $payload['channel'] ?? null,
            'purpose' => $payload['purpose'] ?? ($isUpdate ? null : 'marketing'),
            'fallback_channels' => $payload['fallback_channels'] ?? ($isUpdate ? null : []),
            'template' => $payload['template'] ?? null,
            'cooldown_hours' => $payload['cooldown_hours'] ?? ($isUpdate ? null : 48),
            'priority' => $payload['priority'] ?? ($isUpdate ? null : 0),
        ];

        // Payload first, normalized overrides
        $data = array_merge($payload, $normalized);

        return $isUpdate
            ? array_filter($data, fn($value) => $value !== null)
            : $data;
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

    /**
     * Return variants with their aggregated delivery/event stats.
     *
     * Stats shape per variant:
     *   key, deliveries, opens, clicks, open_rate (%), click_rate (%)
     */
    public function getVariantsWithStats(int $campaignId, int $siteId): array
    {
        $this->assertCampaignOwnership($campaignId, $siteId);

        $variants = $this->variantRepository->findForCampaign($campaignId);

        if ($variants->isEmpty()) {
            return ['variants' => [], 'stats' => []];
        }

        $stats = $this->campaignRepository->aggregateStats($campaignId, $variants);

        return [
            'variants' => $variants->toArray(),
            'stats' => $stats,
        ];
    }

    private function assertCampaignOwnership(int $campaignId, int $siteId): void
    {
        $campaign = $this->campaignRepository->find($campaignId);

        if ($campaign === null || $campaign->site_id !== $siteId) {
            throw new \InvalidArgumentException("Campaign [{$campaignId}] not found.");
        }
    }
}