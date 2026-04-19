<?php

namespace App\Jobs;

use App\Enums\Member\CampaignChannel;
use App\Enums\Member\CampaignPurpose;
use App\Framework\Notifications\NotificationDispatcher;
use App\Framework\Queue\Dispatchable;
use App\Framework\Queue\InteractsWithQueue;
use App\Framework\Queue\SerializesModels;
use App\Framework\Queue\ShouldQueue;
use App\Framework\Support\Logger;
use App\Models\Campaign;
use App\Models\Member;
use App\Repositories\Cms\CampaignRepository;
use App\Repositories\MemberInsights\CampaignDeliveryRepository;
use App\Repositories\Members\MemberRepository;
use App\Services\MemberInsights\Campaigns\CampaignConsentChecker;
use App\Services\MemberInsights\Campaigns\CampaignExecutionLogger;
use App\Services\MemberInsights\Campaigns\CampaignNotification;
use App\Services\MemberInsights\Campaigns\CampaignVariantAssigner;
use App\Services\MemberInsights\InAppNotificationDispatcher;
use App\Services\MemberInsights\Segmentation\SmartChannelResolver;

/**
 * Delivers a single campaign to a single member.
 *
 * Extended for Tickets 11–15 + Web Push:
 *   - T11: records a CampaignDelivery row with tracking token after send
 *   - T14: resolves A/B variant; uses variant's blocks if available
 *   - T15: delegates to SmartChannelResolver (behaviour-driven ordering)
 *   - WebPush: dispatches via WebPushNotificationDispatcher when channel=push
 *
 * Flow:
 *   1. Load member + campaign (guard clauses on missing / inactive)
 *   2. Assign A/B variant (T14)
 *   3. Resolve ordered channel list via SmartChannelResolver (T15)
 *   4. For each channel: check consent
 *   5. First consented channel → send via channel dispatcher
 *   6. Record CampaignDelivery for analytics (T11)
 *   7. Log execution + return
 */
class SendCampaignJob extends BaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $backoff = 120;

    private SmartChannelResolver $channelResolver;
    private CampaignConsentChecker $consentChecker;
    private NotificationDispatcher $dispatcher;
    private CampaignExecutionLogger $logger;
    private MemberRepository $memberRepository;
    private CampaignRepository $campaignRepository;
    private CampaignVariantAssigner $variantAssigner;
    private CampaignDeliveryRepository $deliveryRepository;
    private InAppNotificationDispatcher $webPushDispatcher;

    public function __construct(
        public readonly int    $memberId,
        public readonly int    $campaignId,
        public readonly string $segmentKey,
        public readonly string $audienceKey = 'all_users', // T12 snapshot
    )
    {
    }

    public function handle(): void
    {
        $member = $this->memberRepository->find($this->memberId);

        if ($member === null) {
            Logger::warning('SendCampaignJob: member not found', [
                'member_id' => $this->memberId,
                'campaign_id' => $this->campaignId,
            ]);
            return;
        }

        $campaign = $this->campaignRepository->find($this->campaignId, ['segment']);

        if ($campaign === null || !$campaign->is_active) {
            Logger::info('SendCampaignJob: campaign not found or inactive, skipping', [
                'member_id' => $this->memberId,
                'campaign_id' => $this->campaignId,
            ]);
            return;
        }

        // ── T14: A/B variant assignment ────────────────────────────────────
        $variant = $this->variantAssigner->assignVariant($this->memberId, $this->campaignId);
        $variantId = $variant?->id;

        // Use variant blocks if available, otherwise use campaign blocks.
        $mailableBlocks = ($variant !== null && !empty($variant->blocks))
            ? $variant->blocks
            : null; // null = use campaign's own template

        // ── Resolve mailable class ─────────────────────────────────────────
        $mailableClass = $campaign->template;

        if (!class_exists($mailableClass)) {
            Logger::error('SendCampaignJob: mailable class does not exist', [
                'member_id' => $this->memberId,
                'campaign_id' => $this->campaignId,
                'template' => $mailableClass,
            ]);
            $this->fail(new \RuntimeException("Mailable class [{$mailableClass}] does not exist."));
            return;
        }

        // ── T15: Behaviour-driven channel ordering ─────────────────────────
        $channels = $this->channelResolver->resolveChannels($this->memberId, $campaign);

        foreach ($channels as $channel) {
            if (!$this->consentChecker->canSend($member, CampaignPurpose::tryFrom($campaign->purpose), $channel)) {
                Logger::info('SendCampaignJob: consent blocked on channel, trying fallback', [
                    'member_id' => $this->memberId,
                    'campaign_id' => $this->campaignId,
                    'channel' => $channel->value,
                ]);
                continue;
            }

            $sent = $this->sendViaChannel($member, $campaign, $channel, $mailableClass, $variantId);

            if ($sent) {
                // ── T11/T12: Record delivery for analytics ─────────────────
                $this->deliveryRepository->record(
                    memberId: $this->memberId,
                    campaignId: $this->campaignId,
                    channel: $channel->value,
                    audienceKey: $this->audienceKey,
                    variantId: $variantId,
                );

                $this->logger->log($this->memberId, $campaign, $this->segmentKey);
            }
        }

        Logger::info('SendCampaignJob: no consented channel available, skipping', [
            'member_id' => $this->memberId,
            'campaign_id' => $this->campaignId,
            'purpose' => $campaign->purpose->value,
        ]);
    }

    // -------------------------------------------------------------------------

    private function sendViaChannel(
        Member          $member,
        Campaign        $campaign,
        CampaignChannel $channel,
        string          $mailableClass,
        ?int            $variantId,
    ): bool
    {
        // Web push channel — delegate to dedicated dispatcher.
        if ($channel === CampaignChannel::PUSH) {
            $dispatched = $this->webPushDispatcher->dispatch($member, $campaign);

            if (!$dispatched) {
                Logger::info('SendCampaignJob: web push had no subscription, trying fallback', [
                    'member_id' => $this->memberId,
                    'campaign_id' => $this->campaignId,
                ]);
            }

            return $dispatched;
        }

        // Email / notification channels — existing mailable path.
        $mailable = new $mailableClass($member, $campaign);

        $notification = new CampaignNotification(
            mailable: $mailable,
            recipientEmailAddress: $member->email,
            recipientUserIdValue: $member->id,
        );

        $succeeded = $this->dispatcher->dispatch($notification);

        if ($succeeded > 0) {
            Logger::info('SendCampaignJob: campaign delivered', [
                'member_id' => $this->memberId,
                'campaign_id' => $this->campaignId,
                'channel' => $channel->value,
                'variant_id' => $variantId,
            ]);
            return true;
        }

        Logger::warning('SendCampaignJob: dispatcher reported zero successful channels', [
            'member_id' => $this->memberId,
            'campaign_id' => $this->campaignId,
            'channel' => $channel->value,
        ]);
        return false;
    }
}