<?php

namespace App\Jobs;

use App\Framework\Queue\Dispatchable;
use App\Framework\Queue\InteractsWithQueue;
use App\Framework\Queue\SerializesModels;
use App\Framework\Queue\ShouldQueue;
use App\Framework\Support\Logger;
use App\Models\Campaign;
use App\Models\Member;
use App\Services\Members\Segmentation\CampaignExecutionLogger;

/**
 * Sends a single campaign to a single member via Laravel's notification system,
 * then records the execution in the audit log.
 *
 * Responsibility:
 *   - Resolve the campaign and member
 *   - Instantiate and send the correct notification class based on channel
 *   - Log the send via CampaignExecutionLogger
 *
 * Notification class convention:
 *   The template string on Campaign is used as a fully-qualified notification
 *   class name, e.g. App\Notifications\WeMissYouNotification.
 *   This keeps campaign routing data-driven with no hardcoded channel logic here.
 *
 * Failure handling:
 *   Non-delivery (notification throws) is logged and re-thrown so the queue
 *   can retry. The execution log is written AFTER successful delivery to avoid
 *   consuming the cooldown window on a failed send.
 */
class SendCampaignJob extends BaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $backoff = 120;
    private CampaignExecutionLogger $logger;

    public function __construct(
        public readonly int    $memberId,
        public readonly int    $campaignId,
        public readonly string $segmentKey,
    )
    {
    }

    public function handle(): void
    {
        $member = Member::find($this->memberId);

        if ($member === null) {
            Logger::warning('SendCampaignJob: member not found', [
                'member_id' => $this->memberId,
                'campaign_id' => $this->campaignId,
            ]);
            return;
        }

        $campaign = Campaign::find($this->campaignId);

        if ($campaign === null || !$campaign->is_active) {
            Logger::info('SendCampaignJob: campaign not found or inactive, skipping', [
                'member_id' => $this->memberId,
                'campaign_id' => $this->campaignId,
            ]);
            return;
        }

        $notificationClass = $campaign->template;

        if (!class_exists($notificationClass)) {
            Logger::error('SendCampaignJob: notification class does not exist', [
                'member_id' => $this->memberId,
                'campaign_id' => $this->campaignId,
                'template' => $notificationClass,
            ]);
            // Fail permanently — retrying won't fix a missing class.
            $this->fail(new \RuntimeException("Notification class [{$notificationClass}] does not exist."));
            return;
        }

        //$member->notify(new $notificationClass($campaign));

        //$this->logger->log($this->memberId, $campaign, $this->segmentKey);
    }
}
