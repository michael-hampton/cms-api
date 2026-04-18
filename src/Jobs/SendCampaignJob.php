<?php

namespace App\Jobs;

use App\Enums\Member\CampaignChannel;
use App\Framework\Notifications\NotificationDispatcher;
use App\Framework\Queue\Dispatchable;
use App\Framework\Queue\InteractsWithQueue;
use App\Framework\Queue\SerializesModels;
use App\Framework\Queue\ShouldQueue;
use App\Framework\Support\Logger;
use App\Models\Campaign;
use App\Models\Member;
use App\Repositories\Members\CampaignRepository;
use App\Repositories\Members\MemberRepository;
use App\Services\Members\Segmentation\CampaignConsentChecker;
use App\Services\Members\Segmentation\CampaignExecutionLogger;
use App\Services\Members\Segmentation\CampaignNotification;
use App\Services\Members\Segmentation\ChannelResolver;

/**
 * Delivers a single campaign to a single member.
 *
 * Flow:
 *   1. Load member + campaign (guard clauses on missing / inactive)
 *   2. Resolve ordered channel list (primary + fallbacks)
 *   3. For each channel: check consent via CampaignConsentChecker
 *   4. First consented channel: instantiate Mailable, wrap in CampaignNotification,
 *      dispatch through framework NotificationDispatcher
 *   5. Log execution + return
 *   6. If no channel passes consent: skip silently (log info)
 *
 * Template convention:
 *   campaign.template holds a fully-qualified Mailable class name,
 *   e.g. App\Mail\Campaigns\WeMissYouMail.
 *   The Mailable receives ($member, $campaign) in its constructor.
 *
 * Failure handling:
 *   - Missing class → permanent fail (no retry benefit)
 *   - Dispatch exception → logged + re-thrown for queue retry
 *   - No consented channel → silent skip, no retry
 */
class SendCampaignJob extends BaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $backoff = 120;

    private ChannelResolver $channelResolver;
    private CampaignConsentChecker $consentChecker;
    private NotificationDispatcher $dispatcher;
    private CampaignExecutionLogger $logger;
    private MemberRepository $memberRepository;
    private CampaignRepository $campaignRepository;

    public function __construct(
        public readonly int    $memberId,
        public readonly int    $campaignId,
        public readonly string $segmentKey,
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

        $channels = $this->channelResolver->resolveChannels($campaign);

        foreach ($channels as $channel) {
            if (!$this->consentChecker->canSend($member, $campaign->purpose, $channel)) {
                Logger::info('SendCampaignJob: consent blocked on channel, trying fallback', [
                    'member_id' => $this->memberId,
                    'campaign_id' => $this->campaignId,
                    'channel' => $channel->value,
                ]);
                continue;
            }

            $this->sendViaChannel($member, $campaign, $channel, $mailableClass, $this->dispatcher, $this->logger);
            return;
        }

        Logger::info('SendCampaignJob: no consented channel available, skipping', [
            'member_id' => $this->memberId,
            'campaign_id' => $this->campaignId,
            'purpose' => $campaign->purpose->value,
        ]);
    }

    private function sendViaChannel(
        Member                  $member,
        Campaign                $campaign,
        CampaignChannel         $channel,
        string                  $mailableClass,
        NotificationDispatcher  $dispatcher,
        CampaignExecutionLogger $logger,
    ): void
    {
        $mailable = new $mailableClass($member, $campaign);

        $notification = new CampaignNotification(
            mailable: $mailable,
            recipientEmailAddress: $member->email,
            recipientUserIdValue: $member->id,
        );

        $succeeded = $dispatcher->dispatch($notification);

        if ($succeeded > 0) {
            $logger->log($this->memberId, $campaign, $this->segmentKey);

            Logger::info('SendCampaignJob: campaign delivered', [
                'member_id' => $this->memberId,
                'campaign_id' => $this->campaignId,
                'channel' => $channel->value,
            ]);
        } else {
            Logger::warning('SendCampaignJob: dispatcher reported zero successful channels', [
                'member_id' => $this->memberId,
                'campaign_id' => $this->campaignId,
                'channel' => $channel->value,
            ]);
        }
    }
}
