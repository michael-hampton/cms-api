<?php

declare(strict_types=1);

namespace App\DTO\Members;

use App\Enums\Member\DuplicateMemberMatchType;
use App\Models\Member;

/**
 * Represents one possible duplicate found during detection.
 *
 * Immutable value object — constructed by MemberDuplicateDetectionService
 * and consumed by CrmMemberProfileService to build the API payload.
 */
final class DuplicateMatch
{
    public function __construct(
        public readonly Member                 $duplicateMember,
        public readonly DuplicateMemberMatchType $matchType,
        public readonly int                    $confidenceScore,
    ) {}

    public function toArray(): array
    {
        return [
            'duplicate_member_id'   => $this->duplicateMember->id,
            'duplicate_member_name' => trim(
                $this->duplicateMember->first_name . ' ' . $this->duplicateMember->last_name
            ),
            'match_type'            => $this->matchType->value,
            'match_label'           => $this->matchType->label(),
            'confidence_score'      => $this->confidenceScore,
        ];
    }
}