<?php

declare(strict_types=1);

namespace App\Services\Members;

use App\Events\Members\MemberMerged;
use App\Exceptions\Members\MergeConflictException;
use App\Framework\Database\Database;
use App\Models\Member;
use App\Repositories\Members\MemberMergeRepository;
use App\Repositories\Members\MemberRepository;

/**
 * MemberMergeService
 *
 * Orchestrates the controlled merge of two member accounts.
 *
 * Responsibilities:
 *   1. Validate there are no blocking conflicts.
 *   2. Inside a single DB transaction:
 *      a. Reassign orders, subscriptions, payments, notes to the primary member.
 *      b. Copy non-duplicate addresses to the primary member.
 *      c. Mark the secondary member as merged/inactive.
 *      d. Write the merge audit record.
 *   3. Emit MemberMerged event for downstream side effects (email, activity log).
 *
 * What this service does NOT do:
 *   - Merge Stripe customers. Stripe has no merge API. Billing continues against
 *     the primary's Stripe customer. Historical payments retain their original
 *     transaction IDs.
 *   - Format data for presentation (that is CrmMemberProfileService's job).
 *   - Call other services (events handle cross-cutting concerns).
 */
final class MemberMergeService
{
    public function __construct(
        private readonly MemberRepository      $memberRepository,
        private readonly MemberMergeRepository $memberMergeRepository,
        private readonly Database            $database
    ) {}

    /**
     * Merge $secondary into $primary.
     *
     * @param int   $primaryMemberId   The member account that survives.
     * @param int   $secondaryMemberId The member account to be absorbed and deactivated.
     * @param int   $adminId           ID of the admin performing the merge.
     * @param array $options           Optional: ['reason' => string]
     *
     * @throws MergeConflictException   When blocking conflicts are detected.
     * @throws \InvalidArgumentException When either member does not exist, or IDs are the same.
     * @throws \RuntimeException        On unexpected persistence failures.
     */
    public function merge(
        int   $primaryMemberId,
        int   $secondaryMemberId,
        int   $adminId,
        array $options = [],
    ): void {
        if ($primaryMemberId === $secondaryMemberId) {
            throw new \InvalidArgumentException('Primary and secondary member cannot be the same account.');
        }

        $primary   = $this->memberRepository->find($primaryMemberId);
        $secondary = $this->memberRepository->find($secondaryMemberId);

        if ($primary === null) {
            throw new \InvalidArgumentException("Primary member #{$primaryMemberId} not found.");
        }

        if ($secondary === null) {
            throw new \InvalidArgumentException("Secondary member #{$secondaryMemberId} not found.");
        }

        $this->assertNoConflicts($primary, $secondary);

        $mergedAt = now_datetime()->format('Y-m-d H:i:s');
        $reason   = $options['reason'] ?? null;

        $this->database->transaction(function () use (
            $primary,
            $secondary,
            $adminId,
            $mergedAt,
            $reason,
        ): void {
            // 1. Reassign all related data to the primary member.
            $this->memberRepository->reassignOrders($secondary->id, $primary->id);
            $this->memberRepository->reassignSubscriptions($secondary->id, $primary->id);
            $this->memberRepository->reassignPayments($secondary->id, $primary->id);
            $this->memberRepository->reassignNotes($secondary->id, $primary->id);
            $this->memberRepository->mergeAddresses($secondary->id, $primary->id);

            // 2. Mark the secondary member as merged and deactivate it.
            $this->memberRepository->markAsMerged(
                memberId:              $secondary->id,
                mergedIntoMemberId:    $primary->id,
                mergedBy:              $adminId,
                mergedAt:              $mergedAt,
            );

            // 3. Write the audit record.
            $this->memberMergeRepository->recordMerge(
                primaryMemberId: $primary->id,
                mergedMemberId:  $secondary->id,
                mergedBy:        $adminId,
                mergedAt:        $mergedAt,
                reason:          $reason,
                metadata:        [
                    'primary_email'   => $primary->email,
                    'secondary_email' => $secondary->email,
                ],
            );
        });

        // 4. Emit domain event outside the transaction so listeners that write
        //    to external systems (email, activity log) do not cause a rollback
        //    if they fail.
        event(new MemberMerged($primary->id, $secondary->id, $adminId));
    }

    // ─── Conflict detection ───────────────────────────────────────────────────

    /**
     * Checks all blocking conditions and throws MergeConflictException if any
     * are present. Returns void when the merge is safe to proceed.
     *
     * @throws MergeConflictException
     */
    public function assertNoConflicts(Member $primary, Member $secondary): void
    {
        $conflicts = $this->detectConflicts($primary, $secondary);

        if (!empty($conflicts)) {
            throw new MergeConflictException($conflicts);
        }
    }

    /**
     * Returns a structured list of conflicts between two members.
     * Empty array means no conflicts — merge can proceed.
     *
     * Exposed publicly so the preview endpoint can surface conflicts before
     * the admin confirms.
     *
     * @return array<array{code: string, message: string}>
     */
    public function detectConflicts(Member $primary, Member $secondary): array
    {
        $conflicts = [];

        // Both members have active subscriptions.
        $primaryActiveSubs = $this->memberRepository
            ->countActiveSubscriptions($primary->id);

        $secondaryActiveSubs = $this->memberRepository
            ->countActiveSubscriptions($secondary->id);

        if ($primaryActiveSubs > 0 && $secondaryActiveSubs > 0) {
            $conflicts[] = [
                'code'    => 'active_subscriptions',
                'message' => 'Both members have active subscriptions. Resolve which to keep before merging.',
            ];
        }

        // Both members have different active Stripe customer IDs.
        if (
            !empty($primary->stripe_customer_id)
            && !empty($secondary->stripe_customer_id)
            && $primary->stripe_customer_id !== $secondary->stripe_customer_id
        ) {
            $conflicts[] = [
                'code'    => 'conflicting_stripe_customers',
                'message' => 'Both members have different Stripe customer IDs. Future billing will use the primary member\'s Stripe customer.',
            ];
        }

        // Both members have different verified email addresses.
        if (
            $primary->isEmailVerified()
            && $secondary->isEmailVerified()
            && strtolower(trim($primary->email)) !== strtolower(trim($secondary->email))
        ) {
            $conflicts[] = [
                'code'    => 'conflicting_verified_emails',
                'message' => 'Both members have different verified email addresses.',
            ];
        }

        // Secondary has pending payments.
        if ($this->memberRepository->hasPendingPayments($secondary->id)) {
            $conflicts[] = [
                'code'    => 'pending_payments',
                'message' => 'The secondary member has pending payments that must be resolved first.',
            ];
        }

        return $conflicts;
    }}