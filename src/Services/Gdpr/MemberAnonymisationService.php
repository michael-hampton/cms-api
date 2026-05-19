<?php

namespace App\Services\Gdpr;

use App\Framework\Database\Database;
use App\Models\Address;
use App\Models\Member;
use App\Models\MemberConsent;
use App\Models\MemberNote;
use App\Models\MemberSubscriptionPreference;
use App\Models\Notification;
use App\Repositories\Members\MemberRepository;
use InvalidArgumentException;
use RuntimeException;

/**
 * Implements GDPR Right to be Forgotten via irreversible anonymisation.
 *
 * Hard deletion is not permitted — financial and legal records must be
 * preserved. Instead this service:
 *
 *   1. Replaces all PII fields with anonymous placeholders.
 *   2. Deletes or nullifies non-financial personal data (addresses, notes,
 *      consents, notifications).
 *   3. Disables authentication (password scrambled, account deactivated).
 *   4. Flags the member with is_forgotten = true so downstream systems
 *      can react (email suppression lists, etc.).
 *
 * Financial records (orders, payments, refunds, subscriptions) are retained
 * with their existing references intact. Stripe IDs are kept for audit /
 * legal traceability but the PII linkage is severed at the member level.
 *
 * The operation is idempotent — running it twice on the same member is safe.
 */
final class MemberAnonymisationService
{
    public function __construct(
        private readonly Database         $database,
        private readonly GdprAuditLogger  $auditLogger,
        private readonly MemberRepository $memberRepository,
        private readonly MemberDataCleaner $dataCleaner,
    ) {}

    /**
     * Execute RTBF anonymisation for the given member.
     *
     * @throws InvalidArgumentException if member not found.
     * @throws RuntimeException         if member is already anonymised.
     */
    public function anonymise(int $memberId, int $performedByAdminId): void
    {
        $member = $this->memberRepository->find($memberId);

        if (!$member) {
            throw new InvalidArgumentException("Member [{$memberId}] not found.");
        }

        if ($this->isAlreadyAnonymised($member)) {
            throw new RuntimeException("Member [{$memberId}] has already been anonymised.");
        }

        $this->auditLogger->logAdminAction(
            action:   GdprAuditLogger::RTBF_REQUESTED,
            memberId: $memberId,
            adminId:  $performedByAdminId,
        );

        $this->database->transaction(function () use ($member, $memberId, $performedByAdminId) {
            $this->anonymiseCoreProfile($member);
            $this->dataCleaner->deleteAddresses($memberId);
            $this->dataCleaner->deleteNotes($memberId);
            $this->dataCleaner->deleteNotifications($memberId);
            $this->dataCleaner->revokeConsents($memberId);
            $this->dataCleaner->disableSubscriptions($memberId);
        });

        $this->auditLogger->logAdminAction(
            action:   GdprAuditLogger::RTBF_EXECUTED,
            memberId: $memberId,
            adminId:  $performedByAdminId,
            metadata: ['anonymised_at' => date('Y-m-d H:i:s')],
        );
    }

    // ── Private steps ──────────────────────────────────────────────────────

    private function anonymiseCoreProfile(Member $member): void
    {
        $anonEmail = 'deleted-' . $member->id . '@anonymised.invalid';

        $this->memberRepository->update($member->id, [
            // Identity — replaced with placeholders
            'first_name'              => 'Deleted',
            'last_name'               => 'User',
            'email'                   => $anonEmail,
            'phone'                   => null,
            'company_name'            => null,
            'job_title'               => null,
            'vat_number'              => null,
            'region'                  => null,
            'timezone'                => null,
            'display_name'            => null,
            'crm_notes'               => null,

            // Authentication — revoked
            'password'                => password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT),
            'is_active'               => false,
            'email_verified_at'       => null,
            'email_verification_token'=> null,
            'password_reset_token'    => null,

            // Flags
            'is_forgotten'            => true,
            'forgotten_at'            => date('Y-m-d H:i:s'),

            // Communication prefs — cleared
            'communication_preferences' => null,

            // Marketing visibility — hidden
            'show_activity'           => false,
            'show_badges'             => false,
        ]);
    }

    private function isAlreadyAnonymised(Member $member): bool
    {
        // Check the flag column if it exists, fall back to email pattern
        if (isset($member->is_forgotten)) {
            return (bool) $member->is_forgotten;
        }

        return str_ends_with($member->email ?? '', '@anonymised.invalid');
    }
}