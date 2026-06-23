<?php

namespace App\Services\Billing\Stripe;

use App\DTO\Stripe\CreatePaymentIntentDto;
use App\DTO\Stripe\PaymentIntentResultDto;
use App\Services\Billing\Stripe\Contracts\StripePaymentIntentGatewayInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * Wraps Stripe PaymentIntent operations.
 *
 * Handles two creation variants:
 *   - Simple: amount + currency, no customer (anonymous checkout).
 *   - With customer: attaches to a Stripe customer and saves for future use.
 *
 * No business logic, no DB writes, no customer creation.
 * Callers are responsible for resolving/creating the customer beforehand.
 */
class StripePaymentIntentGateway implements StripePaymentIntentGatewayInterface
{
    public function __construct(
        private readonly StripeClient $stripe
    ) {
    }

    /**
     * Create a simple PaymentIntent (no customer).
     */
    public function create(CreatePaymentIntentDto $dto): PaymentIntentResultDto
    {
        try {
            $intent = $this->stripe->paymentIntents->create([
                'amount'                    => $dto->amountCents,
                'currency'                  => strtolower($dto->currency),
                'automatic_payment_methods' => ['enabled' => true],
                'metadata'                  => $dto->metadata,
            ]);

            return $this->normaliseIntent($intent);
        } catch (ApiErrorException $e) {
            return $this->failed($e);
        }
    }

    /**
     * Create a PaymentIntent attached to a Stripe customer with
     * setup_future_usage so the payment method is saved for off-session use.
     */
    public function createWithCustomer(CreatePaymentIntentDto $dto): PaymentIntentResultDto
    {
        try {
            $params = [
                'amount'                    => $dto->amountCents,
                'currency'                  => strtolower($dto->currency),
                'automatic_payment_methods' => ['enabled' => true],
                'metadata'                  => $dto->metadata,
            ];

            if ($dto->stripeCustomerId !== null) {
                $params['customer'] = $dto->stripeCustomerId;
                $params['setup_future_usage'] = 'off_session';
            }

            $intent = $this->stripe->paymentIntents->create($params);

            return $this->normaliseIntent($intent, $dto->stripeCustomerId);
        } catch (ApiErrorException $e) {
            return $this->failed($e);
        }
    }

    /**
     * Retrieve an existing PaymentIntent by ID.
     */
    public function retrieve(string $paymentIntentId): PaymentIntentResultDto
    {
        try {
            $intent = $this->stripe->paymentIntents->retrieve($paymentIntentId);

            return $this->normaliseIntent($intent);
        } catch (ApiErrorException $e) {
            return $this->failed($e);
        }
    }

    /**
     * Update an existing PaymentIntent when the checkout/order state has changed
     * but the intent is still reusable.
     */
    public function update(string $paymentIntentId, CreatePaymentIntentDto $dto): PaymentIntentResultDto
    {
        try {
            $params = [
                'amount'   => $dto->amountCents,
                'metadata' => $dto->metadata,
            ];

            if ($dto->stripeCustomerId !== null) {
                $params['customer'] = $dto->stripeCustomerId;
                $params['setup_future_usage'] = 'off_session';
            }

            $intent = $this->stripe->paymentIntents->update($paymentIntentId, $params);

            return $this->normaliseIntent($intent, $dto->stripeCustomerId);
        } catch (ApiErrorException $e) {
            return $this->failed($e);
        }
    }

    public function confirmPaymentIntent(string $paymentIntentId): array
    {
        try {
            $paymentIntent = $this->stripe->paymentIntents->retrieve($paymentIntentId);

            return [
                'success' => true,
                'status' => $paymentIntent->status,
                'amount' => $paymentIntent->amount / 100,
                'currency' => $paymentIntent->currency,
            ];
        } catch (ApiErrorException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getStripeCode(),
            ];
        }
    }

    private function normaliseIntent(object $intent, ?string $fallbackCustomerId = null): PaymentIntentResultDto
    {
        $customerId = is_string($intent->customer ?? null)
            ? $intent->customer
            : (($intent->customer ?? null)?->id ?? $fallbackCustomerId);

        $paymentMethodId = is_string($intent->payment_method ?? null)
            ? $intent->payment_method
            : (($intent->payment_method ?? null)?->id ?? null);

        return new PaymentIntentResultDto(
            success:         true,
            paymentIntentId: $intent->id,
            clientSecret:    $intent->client_secret,
            status:          $intent->status,
            customerId:      $customerId,
            paymentMethodId: $paymentMethodId,
            amountCents:     $intent->amount ?? null,
            currency:        $intent->currency ?? null,
            metadata:        $this->normaliseMetadata($intent->metadata ?? []),
        );
    }

    private function failed(ApiErrorException $e): PaymentIntentResultDto
    {
        return new PaymentIntentResultDto(
            success:      false,
            errorMessage: $e->getMessage(),
            errorCode:    $e->getStripeCode(),
        );
    }

    private function normaliseMetadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if ($metadata instanceof \Traversable) {
            return iterator_to_array($metadata);
        }

        if (is_object($metadata) && method_exists($metadata, 'toArray')) {
            return $metadata->toArray();
        }

        return [];
    }
}
