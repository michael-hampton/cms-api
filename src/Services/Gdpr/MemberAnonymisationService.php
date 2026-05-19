<?php

namespace App\Services\Gdpr;

use App\Framework\Database\Database;
use App\Models\Address;
use App\Models\Member;
use App\Models\MemberConsent;
use App\Models\MemberNote;
use App\Models\MemberSubscriptionPreference;
use App\Models\Notification;
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
    ) {}

    /**
     * Execute RTBF anonymisation for the given member.
     *
     * @throws InvalidArgumentException if member not found.
     * @throws RuntimeException         if member is already anonymised.
     */
    public function anonymise(int $memberId, int $performedByAdminId): void
    {
        $member = Member::find($memberId);

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
            $this->deleteAddresses($memberId);
            $this->anonymiseConsents($memberId);
            $this->deleteNotes($memberId);
            $this->deleteNotifications($memberId);
            $this->unsubscribeFromNewsletters($member);
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

        $member->update([
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

    private function deleteAddresses(int $memberId): void
    {
        Address::where('member_id', $memberId)->delete();
    }

    private function anonymiseConsents(int $memberId): void
    {
        // Revoke all active consents — preserve the rows for legal audit trail
        MemberConsent::where('member_id', $memberId)
            ->where('is_granted', true)
            ->update([
                'is_granted' => false,
                'revoked_at' => date('Y-m-d H:i:s'),
            ]);
    }

    private function deleteNotes(int $memberId): void
    {
        // CRM notes contain PII written by agents — hard delete
        MemberNote::where('member_id', $memberId)->delete();
    }

    private function deleteNotifications(int $memberId): void
    {
        Notification::where('member_id', $memberId)->delete();
    }

    private function unsubscribeFromNewsletters(Member $member): void
    {
        MemberSubscriptionPreference::where('member_id', $member->id)
            ->update([
                'is_active'            => false,
                'email_notifications'  => false,
                'newsletter_opt_out'   => true,
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