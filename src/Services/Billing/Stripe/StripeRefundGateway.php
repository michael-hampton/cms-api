<?php

namespace App\Services\Billing\Stripe;

use App\Services\Billing\Stripe\Contracts\StripeRefundGatewayInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * Wraps Stripe refund creation.
 *
 * Single responsibility: call the Stripe refunds API and return a
 * normalised result. No business logic, no DB writes.
 */
class StripeRefundGateway implements StripeRefundGatewayInterface
{
    private StripeClient $stripe;

    public function __construct(
        ?StripeClient                      $stripeClient = null
    )
    {
        if ($stripeClient) {
            $this->stripe = $stripeClient;
        } else {
            $secretKey = $_ENV['STRIPE_SECRET_KEY'] ?? config('payment.stripe.secret_key');
            $this->stripe = new StripeClient($secretKey);
        }
    }

    /**
     * Issue a refund against a charge or payment intent.
     *
     * @param string $transactionId  Stripe charge ID (ch_…) or payment intent ID (pi_…).
     * @param float  $amount         Amount in decimal units (e.g. 9.99 for £9.99).
     * @param array  $options        Supported keys: reason (string), metadata (array).
     *
     * @return array{success: bool, refund_id?: string, amount?: float, status?: string, message?: string, error_code?: string}
     */
    public function refund(string $transactionId, float $amount, array $options = []): array
    {
        try {
            $params = ['amount' => (int) round($amount * 100)];

            if (str_starts_with($transactionId, 'pi_')) {
                $params['payment_intent'] = $transactionId;
            } else {
                $params['charge'] = $transactionId;
            }

            if (!empty($options['reason'])) {
                $params['reason'] = $options['reason'];
            }

            if (!empty($options['metadata'])) {
                $params['metadata'] = $options['metadata'];
            }

            $refund = $this->stripe->refunds->create($params);

            return [
                'success'   => true,
                'refund_id' => $refund->id,
                'amount'    => $refund->amount / 100,
                'status'    => $refund->status,
            ];

        } catch (ApiErrorException $e) {
            return [
                'success'    => false,
                'message'    => $e->getMessage(),
                'error_code' => $e->getStripeCode(),
            ];
        }
    }
}