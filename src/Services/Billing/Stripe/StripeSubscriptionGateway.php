<?php

namespace App\Services\Billing\Stripe;

use App\DTO\Stripe\CreateStripeSubscriptionDto;
use App\DTO\Stripe\StripeSubscriptionResultDto;
use App\Services\Billing\Stripe\Contracts\StripeSubscriptionGatewayInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * Wraps Stripe subscription creation and collection-state changes.
 *
 * Does NOT handle intro pricing — that requires a schedule
 * and lives in StripeSubscriptionScheduleGateway.
 *
 * Responsibilities:
 *   - Call the Stripe SDK
 *   - Map Stripe exceptions to domain exceptions
 *   - Return a normalised StripeSubscriptionResultDto
 *
 * Does NOT:
 *   - Build pricing rules
 *   - Resolve which price ID to use
 *   - Write to the database
 */
class StripeSubscriptionGateway implements StripeSubscriptionGatewayInterface
{
    public function __construct(
        private readonly StripeClient $stripe,
        private readonly StripeCouponGateway $couponGateway,
    )
    {
    }

    public function create(CreateStripeSubscriptionDto $dto): StripeSubscriptionResultDto
    {
        return $this->createSubscription($dto, trialDays: null);
    }

    public function createWithTrial(CreateStripeSubscriptionDto $dto): StripeSubscriptionResultDto
    {
        if ($dto->trialDays === null || $dto->trialDays < 1) {
            throw new \InvalidArgumentException(
                'createWithTrial requires a trialDays value of at least 1.'
            );
        }

        return $this->createSubscription($dto, trialDays: $dto->trialDays);
    }

    public function pauseCollection(string $stripeSubscriptionId): void
    {
        try {
            $this->stripe->subscriptions->update($stripeSubscriptionId, [
                'pause_collection' => [
                    'behavior' => 'void',
                ],
            ]);
        } catch (ApiErrorException $e) {
            throw new \RuntimeException(
                "Stripe subscription pause failed: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    public function resumeCollection(string $stripeSubscriptionId): void
    {
        try {
            $this->stripe->subscriptions->update($stripeSubscriptionId, [
                'pause_collection' => '',
            ]);
        } catch (ApiErrorException $e) {
            throw new \RuntimeException(
                "Stripe subscription resume failed: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    private function createSubscription(
        CreateStripeSubscriptionDto $dto,
        ?int                        $trialDays,
    ): StripeSubscriptionResultDto {
        try {
            $coupon = $dto->voucherId !== null
                ? $this->couponGateway->getOrCreateForVoucher($dto->voucherId, $dto->currency ?? 'gbp')
                : null;

            $params = [
                'customer'           => $dto->stripeCustomerId,
                'items'              => [['price' => $dto->stripePriceId]],
                'metadata'           => $this->buildMetadata($dto, $coupon),
                'expand'             => ['latest_invoice.payment_intent'],
                'collection_method'  => 'charge_automatically',
                'automatic_tax'      => ['enabled' => true],
            ];

            if ($coupon !== null) {
                $params['discounts'] = [['coupon' => $coupon['coupon_id']]];
            }

            if ($trialDays !== null) {
                $params['trial_period_days'] = $trialDays;
            }

            $subscription = $this->stripe->subscriptions->create($params);

            return $this->mapToDto($subscription, stripeScheduleId: null);

        } catch (ApiErrorException $e) {
            throw new \RuntimeException(
                "Stripe subscription creation failed: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    private function buildMetadata(CreateStripeSubscriptionDto $dto, ?array $coupon = null): array
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
        ?string              $stripeScheduleId,
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
            stripeCustomerId:          $subscription->customer,
            currentPeriodStart:        $subscription->current_period_start ?? null,
            currentPeriodEnd:          $subscription->current_period_end ?? null,
            latestInvoiceId:           is_object($invoice) ? $invoice->id : (is_string($invoice) ? $invoice : null),
            paymentIntentId:           $paymentIntentId,
            paymentIntentClientSecret: $clientSecret,
            requiresAction:            $requiresAction,
            stripeSubscriptionItemId:   $subscription->items->data[0]->id ?? null,
        );
    }
}
