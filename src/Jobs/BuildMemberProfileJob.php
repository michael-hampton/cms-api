<?php

namespace App\Jobs;

use App\Framework\Queue\Dispatchable;
use App\Framework\Queue\InteractsWithQueue;
use App\Framework\Queue\SerializesModels;
use App\Framework\Queue\ShouldQueue;
use App\Framework\Support\Logger;
use App\Repositories\Members\MemberRepository;
use App\Services\MemberInsights\MemberStatEngine;

/**
 * Ticket 8 — Profile Enrichment Engine.
 *
 * Runs the full MemberStatEngine pipeline for a single member and writes
 * the structured profile to member_stats.data.
 *
 * The profile schema produced matches AudienceRegistry resolver expectations:
 *
 * [
 *   'scores'   => ['activity_score' => int, 'engagement_score' => int],
 *   'behaviour'=> [
 *       'last_active_days'   => int,
 *       'activity_frequency' => 'low|medium|high',
 *       'churn_risk'         => 'low|medium|high',
 *       'is_new_user'        => bool,
 *   ],
 *   'stats'    => ['total_comments' => int, 'total_likes' => int, 'total_pages_read' => int],
 *   'trends'   => ['7d_change' => int],
 *   'flags'    => string[],
 * ]
 *
 * Scheduling:
 *   This job is dispatched by BuildMemberProfiles console command, which
 *   mirrors the pattern of BuildMemberStats (chunked, per-site dispatch).
 *
 * Idempotency:
 *   MemberStatEngine::rebuild() uses updateOrCreate — safe to re-run.
 */
final class BuildMemberProfileJob extends BaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $backoff = 30;
    private MemberRepository $memberRepository;
    private MemberStatEngine $engine;

    public function __construct(
        public readonly int $memberId,
        public readonly int $siteId,
    )
    {
    }

    public function handle(): void
    {
        $member = $this->memberRepository->find($this->memberId);

        if ($member === null) {
            Logger::warning('BuildMemberProfileJob: member not found', [
                'member_id' => $this->memberId,
                'site_id' => $this->siteId,
            ]);
            return;
        }

        $this->engine->rebuild($this->memberId, $this->siteId);

        Logger::info('BuildMemberProfileJob: profile rebuilt', [
            'member_id' => $this->memberId,
            'site_id' => $this->siteId,
        ]);
    }
}