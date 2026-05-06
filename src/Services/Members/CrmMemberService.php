<?php

namespace App\Services\Members;

use App\Enums\Member\MemberStatus;
use App\Events\Members\MemberAssigned;
use App\Events\Members\MemberStatusChanged;
use App\Framework\Database\Database;
use App\Models\Member;
use App\Repositories\Members\CrmMemberRepository;
use App\Repositories\Members\MemberRepository;
use InvalidArgumentException;

class CrmMemberService
{
    public function __construct(
        private readonly CrmMemberRepository $crmMemberRepository,
        private readonly MemberRepository    $memberRepository,
        private readonly Database            $database
    )
    {
    }

    /**
     * Update a member's CRM-editable fields.
     * Emits MemberStatusChanged when is_active changes.
     * Emits MemberAssigned when assigned_agent_id changes.
     */
    public function updateMember(int $memberId, int $siteId, array $data): Member
    {
        $member = $this->crmMemberRepository->findForSite($memberId, $siteId);

        if (!$member) {
            throw new InvalidArgumentException("Member [{$memberId}] not found for site [{$siteId}].");
        }

        $this->guardEmailUniqueness($data['email'] ?? null, $memberId);

        return $this->database->transaction(function () use ($member, $data) {
            $previousStatus = MemberStatus::fromBool((bool)$member->is_active);
            $previousAgentId = $member->assigned_agent_id;

            $allowedFields = [
                'first_name',
                'last_name',
                'email',
                'phone',
                'company_name',
                'job_title',
                'vat_number',
                'region',
                'timezone',
                'is_active',
                'assigned_agent_id',
                'crm_notes',
                'show_activity',
                'show_badges',
                'communication_preferences',
            ];

            $payload = array_intersect_key($data, array_flip($allowedFields));

            // Reset email verification when email changes
            if (
                isset($payload['email'])
                && $payload['email'] !== $member->email
            ) {
                $payload['email_verified_at'] = null;
            }

            $updated = $this->crmMemberRepository->update($member->id, $payload);

            $newStatus = MemberStatus::fromBool((bool)$updated->is_active);

            if ($newStatus !== $previousStatus) {
                event(new MemberStatusChanged($updated, $newStatus, $previousStatus));
            }

            $newAgentId = $updated->assigned_agent_id;

            if ($newAgentId !== $previousAgentId) {
                $agent = $newAgentId
                    ? $this->crmMemberRepository->getAgents($updated->site_id)
                        ->first(fn($u) => $u->id === $newAgentId)
                    : null;

                event(new MemberAssigned($updated, $agent, $previousAgentId));
            }

            return $updated;
        });
    }

    private function guardEmailUniqueness(?string $email, ?int $excludeMemberId = null): void
    {
        if ($email === null) {
            return;
        }

        $existing = $this->memberRepository->findByEmail($email);

        if ($existing && $existing->id !== $excludeMemberId) {
            throw new InvalidArgumentException('Email address is already in use.');
        }
    }

    public function createMember(int $siteId, array $data)
    {
        $this->guardEmailUniqueness($data['email'] ?? null);

        return $this->database->transaction(function () use ($data, $siteId) {

            $allowedFields = [
                'first_name',
                'last_name',
                'email',
                'phone',
                'company_name',
                'job_title',
                'vat_number',
                'region',
                'timezone',
                'is_active',
                'assigned_agent_id',
                'crm_notes',
                'show_activity',
                'show_badges',
                'communication_preferences',
            ];

            $payload = array_intersect_key($data, array_flip($allowedFields));
            $payload['site_id'] = $siteId;

            return $this->crmMemberRepository->create($payload);
        });
    }
}
