<?php

namespace App\Jobs;

use App\Framework\Queue\Dispatchable;
use App\Framework\Queue\InteractsWithQueue;
use App\Framework\Queue\SerializesModels;
use App\Framework\Queue\ShouldQueue;
use App\Framework\Support\Logger;
use App\Repositories\Members\MemberRepository;
use App\Services\Billing\Stripe\StripeCustomerDetailsUpdater;
use RuntimeException;

/**
 * Syncs a member's Stripe customer record whenever their local details
 * change (email, name, billing/shipping address).
 *
 * Dispatched by SyncMemberToStripeListener in response to
 * MemberDetailsChanged — never call directly from a controller or service.
 *
 * Retry strategy:
 *   Three attempts with exponential back-off (30 s → 60 s → 120 s) to
 *   handle transient Stripe API errors without losing the update entirely.
 *   If all attempts fail the job is moved to the failed-jobs table so it
 *   can be replayed manually.
 */
final class SyncMemberToStripeJob extends BaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    private MemberRepository $memberRepository;
    private StripeCustomerDetailsUpdater $stripeCustomerDetailsUpdater;

    public function __construct(
        public readonly int  $memberId,
        public readonly ?int $addressId = null
    )
    {
    }

    public function handle(): void
    {
        $this->memberRepository = $this->resolveProperty('memberRepository', MemberRepository::class);
        $this->stripeCustomerDetailsUpdater = $this->resolveProperty('stripeCustomerDetailsUpdater', StripeCustomerDetailsUpdater::class);

        $member = $this->memberRepository->find($this->memberId);

        if ($member === null) {
            Logger::warning('SyncMemberToStripeJob: member not found — skipping', [
                'member_id' => $this->memberId
            ]);
            return;
        }

        if (empty($member->stripe_customer_id)) {
            // Member was never pushed to Stripe; nothing to update.
            Logger::info('SyncMemberToStripeJob: no Stripe customer ID — skipping', [
                'member_id' => $this->memberId,
            ]);
            return;
        }

        $payload = $this->buildPayload($member);

        $result = $this->stripeCustomerDetailsUpdater->update(
            $member->stripe_customer_id,
            $payload,
        );

        if ($result['success']) {
            Logger::info('SyncMemberToStripeJob: customer updated', [
                'member_id' => $this->memberId,
                'stripe_customer_id' => $member->stripe_customer_id,
            ]);
        } else {
            Logger::error('SyncMemberToStripeJob: update failed', [
                'member_id' => $this->memberId,
                'stripe_customer_id' => $member->stripe_customer_id,
                'message' => $result['message'] ?? 'unknown error',
            ]);

            // Re-throw so the queue runner retries the job.
            throw new RuntimeException(
                'Stripe customer sync failed: ' . ($result['message'] ?? 'unknown error')
            );
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────

    /**
     * Build the Stripe customer update payload from the member model.
     *
     * Only includes fields that are actually present so we never wipe a
     * value in Stripe that hasn't been set locally yet.
     */
    private function buildPayload($member): array
    {
        $payload = [];

        if (!empty($member->email)) {
            $payload['email'] = $member->email;
        }

        if (!empty($member->name)) {
            $payload['name'] = $member->name;
        }

        $address = $this->resolveAddress($member);

        if ($address !== null) {
            $payload['address'] = [
                'line1' => $address->address_line_1 ?? '',
                'line2' => $address->address_line_2 ?? '',
                'city' => $address->city ?? '',
                'state' => $address->state ?? '',
                'postal_code' => $address->postcode ?? '',
                'country' => $address->country ?? '',
            ];
        }

        return $payload;
    }

    /**
     * Return the member's preferred billing address, falling back through
     * the same priority chain used by StripePaymentProcessor.
     */
    private function resolveAddress($member): ?object
    {
        $addresses = $member->addresses;

        if ($addresses === null || $addresses->isEmpty()) {
            return null;
        }

        if (!empty($this->addressId)) {
            return $addresses->where('id', $this->addressId)->first();
        }

        return $addresses->first(fn($a) => $a->is_default && $a->type === 'billing')
            ?? $addresses->first(fn($a) => $a->type === 'billing')
            ?? $addresses->first(fn($a) => $a->is_default)
            ?? $addresses->first();
    }
}
