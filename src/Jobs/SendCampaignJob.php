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
use App\Mail\Campaigns\BaseCampaignMail;
use App\Models\Campaign;
use App\Models\Member;
use App\Repositories\Cms\CampaignRepository;
use App\Repositories\MemberInsights\CampaignDeliveryRepository;
use App\Repositories\MemberInsights\CampaignVariantAssigner;
use App\Repositories\Members\MemberRepository;
use App\Services\MemberInsights\Campaigns\CampaignConsentChecker;
use App\Services\MemberInsights\Campaigns\CampaignExecutionLogger;
use App\Services\MemberInsights\Campaigns\CampaignNotification;
use App\Services\MemberInsights\InAppNotificationDispatcher;
use App\Services\MemberInsights\Segmentation\SmartChannelResolver;

/**
 * Delivers a single campaign to a single member.
 *
 * Extended for T11–T15 + Web Push:
 *
 *   T11  — Records CampaignDelivery after a successful send.
 *          Sets deliveryToken on the mailable so BaseCampaignMail can inject
 *          the open pixel and rewrite links for CampaignTrackingController.
 *
 *   T12  — audienceKey (resolved upstream by ProcessMemberSegmentationJob)
 *          is stored on CampaignDelivery for audience-level analytics.
 *
 *   T14  — CampaignVariantAssigner assigns a deterministic A/B variant.
 *          Variant blocks override campaign blocks when present.
 *          variant_id is stored on CampaignDelivery.
 *
 *   T15  — SmartChannelResolver re-orders channels by past engagement.
 *
 *   Push — WebPushNotificationDispatcher handles CampaignChannel::PUSH.
 *
 * Tracking flow:
 *   1. CampaignDelivery row is written (token generated in repository).
 *   2. Token is set on the mailable via $mailable->deliveryToken.
 *   3. BaseCampaignMail::injectTracking() rewrites the rendered HTML to
 *      embed the pixel and wrap links — so every open/click hits
 *      CampaignTrackingController which writes to campaign_events.
 */
class SendCampaignJob extends BaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $backoff = 120;

    protected SmartChannelResolver $channelResolver;
    protected CampaignConsentChecker $consentChecker;
    protected NotificationDispatcher $notificationDispatcher;
    protected CampaignExecutionLogger $executionLogger;
    protected MemberRepository $memberRepository;
    protected CampaignRepository $campaignRepository;
    protected CampaignVariantAssigner $variantAssigner;
    protected CampaignDeliveryRepository $deliveryRepository;
    protected InAppNotificationDispatcher $webPushDispatcher;

    public function __construct(
        public readonly int    $memberId,
        public readonly int    $campaignId,
        public readonly string $segmentKey,
        public readonly string $audienceKey = 'all_users',
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
            Logger::info('SendCampaignJob: campaign not found or inactive', [
                'member_id' => $this->memberId,
                'campaign_id' => $this->campaignId,
            ]);
            return;
        }

        // ── T14: variant assignment ───────────────────────────────────────
        $variant = $this->variantAssigner->assignVariant($this->memberId, $this->campaignId);
        $variantId = $variant?->id;

        // ── Resolve mailable class ────────────────────────────────────────
        // Variant may supply its own template; fall back to campaign template.
        $mailableClass = ($variant !== null && !empty($variant->template))
            ? $variant->template
            : $campaign->template;

        if (!class_exists($mailableClass)) {
            Logger::error('SendCampaignJob: mailable class does not exist', [
                'member_id' => $this->memberId,
                'campaign_id' => $this->campaignId,
                'template' => $mailableClass,
            ]);
            $this->fail(new \RuntimeException("Mailable [{$mailableClass}] does not exist."));
            return;
        }

        // ── T15: behaviour-driven channel ordering ─────────────────────────
        $channels = $this->channelResolver->resolveChannels($this->memberId, $campaign);

        foreach ($channels as $channel) {
            if (!$this->consentChecker->canSend($member, CampaignPurpose::tryFrom($campaign->purpose), $channel)) {
                Logger::info('SendCampaignJob: consent blocked, trying fallback', [
                    'member_id' => $this->memberId,
                    'campaign_id' => $this->campaignId,
                    'channel' => $channel->value,
                ]);
                continue;
            }

            $sent = $this->sendViaChannel(
                $member, $campaign, $channel, $mailableClass, $variantId
            );

            if ($sent) {
                $this->executionLogger->log($this->memberId, $campaign, $this->segmentKey);
                return;
            }
        }

        Logger::info('SendCampaignJob: no consented channel available', [
            'member_id' => $this->memberId,
            'campaign_id' => $this->campaignId,
        ]);
    }

    // ── Private ───────────────────────────────────────────────────────────

    private function sendViaChannel(
        Member          $member,
        Campaign        $campaign,
        CampaignChannel $channel,
        string          $mailableClass,
        ?int            $variantId,
    ): bool
    {
        if ($channel === CampaignChannel::PUSH) {
            $dispatched = $this->webPushDispatcher->dispatch($member, $campaign);

            if ($dispatched) {
                // Record delivery for push — no tracking pixel, but we still
                // need the analytics row.
                $this->deliveryRepository->record(
                    memberId: $this->memberId,
                    campaignId: $this->campaignId,
                    channel: $channel->value,
                    audienceKey: $this->audienceKey,
                    variantId: $variantId,
                );
            }

            return $dispatched;
        }

        // ── Email / notification path ─────────────────────────────────────
        /** @var BaseCampaignMail $mailable */
        $mailable = new $mailableClass($member, $campaign);

        // ── T11: Write delivery row FIRST so the token exists ─────────────
        // The token is generated inside record() and returned on the model.
        $delivery = $this->deliveryRepository->record(
            memberId: $this->memberId,
            campaignId: $this->campaignId,
            channel: $channel->value,
            audienceKey: $this->audienceKey,
            variantId: $variantId,
        );

        // Attach token to the mailable so BaseCampaignMail::injectTracking()
        // can embed the pixel and rewrite links in the rendered HTML.
        $mailable->deliveryToken = $delivery->token;

        $notification = new CampaignNotification(
            mailable: $mailable,
            recipientEmailAddress: $member->email,
            recipientUserIdValue: $member->id,
        );

        $succeeded = $this->notificationDispatcher->dispatch($notification);

        if ($succeeded > 0) {
            Logger::info('SendCampaignJob: delivered', [
                'member_id' => $this->memberId,
                'campaign_id' => $this->campaignId,
                'channel' => $channel->value,
                'variant_id' => $variantId,
                'delivery_id' => $delivery->id,
            ]);
            return true;
        }

        // Dispatch failed — delete the delivery row so the analytics count
        // doesn't record a "delivered" message that was never actually sent.
        $this->deliveryRepository->delete($delivery->id);

        Logger::warning('SendCampaignJob: dispatcher reported zero successes', [
            'member_id' => $this->memberId,
            'campaign_id' => $this->campaignId,
            'channel' => $channel->value,
        ]);

        return false;
    }
}