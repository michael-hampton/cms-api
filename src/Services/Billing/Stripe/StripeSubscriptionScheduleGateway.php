<?php

namespace App\Services\Billing\Stripe;

use App\DTO\Stripe\CreateStripeSubscriptionScheduleDto;
use App\DTO\Stripe\StripeSubscriptionResultDto;
use App\Factories\Stripe\StripeSchedulePhaseFactory;
use App\Services\Billing\Stripe\Contracts\StripeSubscriptionScheduleGatewayInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * Wraps Stripe Subscription Schedule creation for intro-priced billing.
 *
 * A schedule is required (not optional) whenever:
 *   - intro_price is set on the pricing tier (INTRO strategy)
 *   - trial + intro is set (TRIAL_INTRO strategy)
 *
 * For TRIAL_INTRO: Stripe does not support a first-class trial phase inside
 * a schedule. The workaround is to set start_date to now + trial_days so
 * the schedule (and first invoice) begins after the trial window. The trial
 * itself is handled by creating a standard subscription with trial_period_days
 * and then releasing/migrating it to a schedule — but that's a future
 * complexity. For now TRIAL_INTRO creates a schedule with the trial baked in
 * via start_date offset, which is the simplest correct approach.
 */
class StripeSubscriptionScheduleGateway implements StripeSubscriptionScheduleGatewayInterface
{
    public function __construct(
        private readonly StripeClient $stripe,
        private readonly StripeSchedulePhaseFactory $phaseFactory,
        private readonly StripeCouponGateway $couponGateway,
    ) {}

    public function create(CreateStripeSubscriptionScheduleDto $dto): StripeSubscriptionResultDto
    {
        try {
            $startDate = $this->resolveStartDate($dto->trialDays);
            $coupon = $dto->voucherId !== null
                ? $this->couponGateway->getOrCreateForVoucher($dto->voucherId, $dto->currency ?? 'gbp')
                : null;
            $phases    = $this->phaseFactory->buildPhases($dto, $coupon['coupon_id'] ?? null);

            $schedule = $this->stripe->subscriptionSchedules->create([
                'customer'      => $dto->stripeCustomerId,
                'start_date'    => $startDate,
                'end_behavior'  => 'release',
                'phases'        => $phases,
                'metadata'      => $this->buildMetadata($dto, $coupon),
            ]);

            // Retrieve the attached subscription so we can return its ID and
            // period dates. The schedule's subscription field is the live sub.
            $subscriptionId = is_string($schedule->subscription)
                ? $schedule->subscription
                : $schedule->subscription->id;

            $subscription = $this->stripe->subscriptions->retrieve($subscriptionId, [
                'expand' => ['latest_invoice.payment_intent'],
            ]);

            return $this->mapToDto($subscription, stripeScheduleId: $schedule->id);

        } catch (ApiErrorException $e) {
            throw new \RuntimeException(
                "Stripe subscription schedule creation failed: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    // ── Private ──────────────────────────────────────────────────────────────

    /**
     * For TRIAL_INTRO: offset start_date by trial days so the first intro
     * invoice is raised only after the trial window.
     */
    private function resolveStartDate(?int $trialDays): string|int
    {
        if ($trialDays !== null && $trialDays > 0) {
            return (new \DateTimeImmutable())->modify("+{$trialDays} days")->getTimestamp();
        }

        return 'now';
    }

    private function buildMetadata(CreateStripeSubscriptionScheduleDto $dto, ?array $coupon = null): array
    {
        $metadata = [
            'subscription_id' => $dto->subscriptionId,
            'plan_id'         => $dto->planId,
            'member_id'       => $dto->memberId,
            'site_id'         => $dto->siteId,
        ];

        if ($coupon !== null) {
            $metadata['voucher_id'] = $coupon['voucher_id'];
            $metadata['voucher_code'] = $coupon['voucher_code'];
        }

        return $metadata;
    }

    private function mapToDto(
        \Stripe\Subscription $subscription,
        string               $stripeScheduleId,
    ): StripeSubscriptionResultDto {
        $invoice         = $subscription->latest_invoice;
        $paymentIntent   = is_object($invoice) ? ($invoice->payment_intent ?? null) : null;
        $paymentIntentId = null;
        $clientSecret    = null;
        $requiresAction  = false;

        if (is_object($paymentIntent)) {
            $paymentIntentId = $paymentIntent->id;
            $requiresAction  = $paymentIntent->status === 'requires_action';
            $clientSecret    = $paymentIntent->client_secret;
        } elseif (is_string($paymentIntent)) {
            $paymentIntentId = $paymentIntent;
        }

        return new StripeSubscriptionResultDto(
            stripeSubscriptionId:      $subscription->id,
            stripeScheduleId:          $stripeScheduleId,
            status:                    $subscription->status,
            stripeCustomerId:          is_string($subscription->customer)
                ? $subscription->customer
                : $subscription->customer->id,
            currentPeriodStart:        $subscription->current_period_start ?? null,
            currentPeriodEnd:          $subscription->current_period_end ?? null,
            latestInvoiceId:           is_object($invoice) ? $invoice->id : (is_string($invoice) ? $invoice : null),
            paymentIntentId:           $paymentIntentId,
            paymentIntentClientSecret: $clientSecret,
            requiresAction:            $requiresAction,
        );
    }
}
