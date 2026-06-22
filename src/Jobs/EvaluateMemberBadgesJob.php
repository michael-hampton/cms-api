<?php

namespace App\Jobs;

use App\Framework\Queue\Dispatchable;
use App\Framework\Queue\InteractsWithQueue;
use App\Framework\Queue\SerializesModels;
use App\Framework\Queue\ShouldQueue;
use App\Framework\Support\Logger;
use App\Repositories\Members\MemberRepository;
use App\Services\Members\BadgeAccessService;
use App\Services\Members\BadgeService;

/**
 * Async wrapper around BadgeService::checkAndAwardBadges().
 *
 * ⚠️  This job MUST NOT contain any badge logic.
 *     All criteria evaluation, awarding, and event firing lives in
 *     BadgeService exactly as before. This job is a thin transport layer.
 *
 * Dispatched by BadgeService::trackActivity() instead of calling
 * checkAndAwardBadges() directly, so badge evaluation no longer blocks
 * the activity-tracking transaction.
 *
 * afterCommit() ensures the job is only enqueued after the activity
 * write transaction has committed, preventing phantom reads.
 */
class EvaluateMemberBadgesJob extends BaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $backoff = 30;
    private BadgeService $badgeService;
    private BadgeAccessService $badgeAccess;
    private MemberRepository $memberRepository;

    public function __construct(
        public readonly int $memberId,
    )
    {
    }

    public function handle(): void
    {
        $member = $this->memberRepository->find($this->memberId);

        if ($member === null) {
            Logger::warning('EvaluateMemberBadgesJob: member not found', [
                'member_id' => $this->memberId,
            ]);
            return;
        }

        if (!$this->badgeAccess->canAccessBadges($member, (int) $member->site_id)) {
            return;
        }

        // Intentionally calling existing method unchanged.
        // Do NOT inline or refactor the logic from BadgeService here.
        $this->badgeService->checkAndAwardBadges($member);
    }
}
