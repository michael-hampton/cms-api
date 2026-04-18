<?php

namespace App\Jobs;

use App\Models\Member;
use App\Services\Members\BadgeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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
class EvaluateMemberBadgesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly int $memberId,
    )
    {
    }

    public function handle(BadgeService $badgeService): void
    {
        $member = Member::find($this->memberId);

        if ($member === null) {
            Log::warning('EvaluateMemberBadgesJob: member not found', [
                'member_id' => $this->memberId,
            ]);
            return;
        }

        // Intentionally calling existing method unchanged.
        // Do NOT inline or refactor the logic from BadgeService here.
        $badgeService->checkAndAwardBadges($member);
    }
}