<?php

namespace App\Services\Billing\Stripe;

use App\DTO\Stripe\CreatePaymentIntentDto;
use App\DTO\Stripe\PaymentIntentResultDto;
use App\Services\Billing\Stripe\Contracts\StripePaymentIntentGatewayInterface;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * Wraps Stripe PaymentIntent creation.
 *
 * Handles two variants:
 *   - Simple: amount + currency, no customer (anonymous checkout).
 *   - With customer: attaches to a Stripe customer and saves for future use.
 *
 * No business logic, no DB writes, no customer creation.
 * Callers are responsible for resolving/creating the customer beforehand.
 */
class StripePaymentIntentGateway implements StripePaymentIntentGatewayInterface
{
    public function __construct(
        private readonly StripeClient                      $stripe
    )
    {
    }

    /**
     * Create a simple PaymentIntent (no customer).
     */
    public function create(CreatePaymentIntentDto $dto): PaymentIntentResultDto
    {
        try {
            $params = [
                'amount'                     => $dto->amountCents,
                'currency'                   => strtolower($dto->currency),
                'automatic_payment_methods'  => ['enabled' => true],
                'metadata'                   => $dto->metadata,
            ];

            $intent = $this->stripe->paymentIntents->create($params);

            return new PaymentIntentResultDto(
                success:          true,
                paymentIntentId:  $intent->id,
                clientSecret:     $intent->client_secret,
                status:           $intent->status,
                customerId:       null,
            );

        } catch (ApiErrorException $e) {
            return new PaymentIntentResultDto(
                success:      false,
                errorMessage: $e->getMessage(),
                errorCode:    $e->getStripeCode(),
            );
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
                $params['customer']            = $dto->stripeCustomerId;
                $params['setup_future_usage']  = 'off_session';
            }

            $intent = $this->stripe->paymentIntents->create($params);

            return new PaymentIntentResultDto(
                success:         true,
                paymentIntentId: $intent->id,
                clientSecret:    $intent->client_secret,
                status:          $intent->status,
                customerId:      $dto->stripeCustomerId,
            );

        } catch (ApiErrorException $e) {
            return new PaymentIntentResultDto(
                success:      false,
                errorMessage: $e->getMessage(),
                errorCode:    $e->getStripeCode(),
            );
        }
    }

    /**
     * Retrieve an existing PaymentIntent by ID.
     */
    public function retrieve(string $paymentIntentId): PaymentIntentResultDto
    {
        try {
            $intent = $this->stripe->paymentIntents->retrieve($paymentIntentId);

            return new PaymentIntentResultDto(
                success:         true,
                paymentIntentId: $intent->id,
                clientSecret:    $intent->client_secret,
                status:          $intent->status,
                customerId:      is_string($intent->customer) ? $intent->customer : $intent->customer?->id,
            );

        } catch (ApiErrorException $e) {
            return new PaymentIntentResultDto(
                success:      false,
                errorMessage: $e->getMessage(),
                errorCode:    $e->getStripeCode(),
            );
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
                'error_code' => $e->getStripeCode()
            ];
        }
    }
}