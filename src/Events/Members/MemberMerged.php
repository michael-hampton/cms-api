<?php

declare(strict_types=1);

namespace App\Events\Members;

/**
 * Fired after a member merge transaction commits successfully.
 *
 * Listeners should handle:
 *   - Recording an activity log entry on the primary member.
 *   - Sending a notification to the admin who performed the merge.
 *
 * This event must NOT be fired inside the DB transaction, because a listener
 * failure would roll back a completed merge.
 */
final class MemberMerged
{
    public function __construct(
        public readonly int $primaryMemberId,
        public readonly int $mergedMemberId,
        public readonly int $mergedBy,
    ) {}
}