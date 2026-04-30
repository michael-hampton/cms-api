<?php

namespace App\Services\MemberInsights\Campaigns;

use App\Enums\Campaigns\CampaignScheduleStatus;
use App\Models\Campaign;
use App\Repositories\Cms\CampaignRepository;

/**
 * Manages the scheduling lifecycle of a campaign.
 *
 * This service is responsible for:
 *   - Attaching a scheduled_at time to a campaign
 *   - Pausing a pending schedule (before it has fired)
 *   - Resuming a paused schedule
 *   - Marking a schedule as sent (called by DispatchScheduledCampaignsCommand)
 *
 * It does NOT dispatch jobs. Dispatching is the responsibility of
 * DispatchScheduledCampaignsCommand so the two concerns stay decoupled
 * and independently testable.
 */
class CampaignSchedulerService
{
    public function __construct(
        private readonly CampaignRepository $campaignRepository,
    )
    {
    }

    /**
     * Attach a schedule to a campaign.
     *
     * @throws \InvalidArgumentException if scheduled_at is in the past or the
     *                                   campaign has already been sent.
     */
    public function schedule(int $campaignId, \DateTimeInterface $scheduledAt): Campaign
    {
        $campaign = $this->findOrFail($campaignId);

        if ($campaign->schedule_status === CampaignScheduleStatus::Sent->value) {
            throw new \InvalidArgumentException('Cannot reschedule a campaign that has already been sent.');
        }

        if ($scheduledAt <= new \DateTime()) {
            throw new \InvalidArgumentException('scheduled_at must be in the future.');
        }

        $this->campaignRepository->update($campaign->id, [
            'scheduled_at' => $scheduledAt,
            'schedule_status' => CampaignScheduleStatus::Scheduled->value
        ]);

        return $campaign;
    }

    private function findOrFail(int $campaignId): Campaign
    {
        $campaign = $this->campaignRepository->find($campaignId);

        if ($campaign === null) {
            throw new \InvalidArgumentException("Campaign [{$campaignId}] not found.");
        }

        return $campaign;
    }

    /**
     * Pause a pending schedule.
     *
     * Only campaigns in the `scheduled` state can be paused — there is nothing
     * to pause once the campaign has been sent or was never scheduled.
     *
     * @throws \InvalidArgumentException if the campaign is not in a pausable state.
     */
    public function pauseSchedule(int $campaignId): Campaign
    {
        $campaign = $this->findOrFail($campaignId);

        if ($campaign->schedule_status !== CampaignScheduleStatus::Scheduled->value) {
            throw new \InvalidArgumentException(
                'Only a scheduled campaign can be paused. Current status: ' . ($campaign->schedule_status ?? 'none')
            );
        }

        $this->campaignRepository->update($campaign->id, ['schedule_status' => CampaignScheduleStatus::Paused->value]);

        return $campaign;
    }

    /**
     * Resume a paused schedule.
     *
     * The original scheduled_at is preserved. If the time has now passed,
     * the caller should provide a new scheduled_at via reschedule() instead.
     *
     * @throws \InvalidArgumentException if the campaign is not paused.
     */
    public function resumeSchedule(int $campaignId): Campaign
    {
        $campaign = $this->findOrFail($campaignId);

        if ($campaign->schedule_status !== CampaignScheduleStatus::Paused->value) {
            throw new \InvalidArgumentException(
                'Only a paused campaign can be resumed. Current status: ' . ($campaign->schedule_status ?? 'none')
            );
        }

        $this->campaignRepository->update($campaign->id, ['schedule_status' => CampaignScheduleStatus::Scheduled->value]);

        return $campaign;
    }

    // ── Private ───────────────────────────────────────────────────────────────

    /**
     * Called by DispatchScheduledCampaignsCommand after jobs have been queued.
     * Marks the campaign so it is not dispatched again on the next command run.
     */
    public function markSent(int $campaignId): Campaign
    {
        $campaign = $this->findOrFail($campaignId);

        $this->campaignRepository->update($campaign->id, ['schedule_status' => CampaignScheduleStatus::Sent->value]);

        return $campaign;
    }
}