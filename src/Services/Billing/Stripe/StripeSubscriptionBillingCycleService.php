<?php

namespace App\Services\Billing\Stripe;

use DateTime;
use Exception;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeSubscriptionBillingCycleService
{
    private StripeClient $stripe;

    public function __construct(?StripeClient $stripeClient = null)
    {
        if ($stripeClient) {
            $this->stripe = $stripeClient;
            return;
        }

        $secretKey = $_ENV['STRIPE_SECRET_KEY'] ?? config('payment.stripe.secret_key');
        $this->stripe = new StripeClient($secretKey);
    }

    public function updateBillingCycleAnchor(
        string $stripeSubscriptionId,
        int $dayOfMonth,
        bool $prorate = true
    ): array {
        try {
            $subscription = $this->stripe->subscriptions->retrieve($stripeSubscriptionId, [
                'expand' => ['schedule'],
            ]);

            if ($subscription->status === 'canceled') {
                return [
                    'success' => false,
                    'message' => 'Cannot update billing date for cancelled subscription',
                ];
            }

            $now = new DateTime();
            $targetDate = new DateTime();
            $targetDate->setDate(
                (int) $targetDate->format('Y'),
                (int) $targetDate->format('m'),
                min($dayOfMonth, (int) $targetDate->format('t'))
            );

            if ($targetDate <= $now) {
                $targetDate->modify('+1 month');
                $targetDate->setDate(
                    (int) $targetDate->format('Y'),
                    (int) $targetDate->format('m'),
                    min($dayOfMonth, (int) $targetDate->format('t'))
                );
            }

            $schedule = null;
            if (!empty($subscription->schedule)) {
                $scheduleId = is_string($subscription->schedule)
                    ? $subscription->schedule
                    : $subscription->schedule->id;

                $schedule = $this->stripe->subscriptionSchedules->retrieve($scheduleId);
            }

            if (!$schedule) {
                $schedule = $this->stripe->subscriptionSchedules->create([
                    'from_subscription' => $stripeSubscriptionId,
                ]);
            }

            $items = [];
            foreach ($subscription->items->data as $item) {
                $items[] = [
                    'price' => $item->price->id,
                    'quantity' => $item->quantity ?? 1,
                ];
            }

            $updatedSchedule = $this->stripe->subscriptionSchedules->update($schedule->id, [
                'end_behavior' => 'release',
                'phases' => [
                    [
                        'items' => $items,
                        'start_date' => (int) $subscription->current_period_start,
                        'end_date' => $targetDate->getTimestamp(),
                        'proration_behavior' => $prorate ? 'create_prorations' : 'none',
                    ],
                    [
                        'items' => $items,
                        'proration_behavior' => $prorate ? 'create_prorations' : 'none',
                    ],
                ],
            ]);

            $releasedSchedule = $this->stripe->subscriptionSchedules->release($updatedSchedule->id);

            return [
                'success' => true,
                'subscription' => $releasedSchedule->subscription,
                'schedule_id' => $releasedSchedule->id,
                'new_billing_date' => $targetDate->format('Y-m-d'),
                'message' => 'Billing date updated successfully',
            ];
        } catch (ApiErrorException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getStripeCode(),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'An error occurred while updating billing date',
            ];
        }
    }

    public function calculateBillingDateProration(string $stripeSubscriptionId, int $newDayOfMonth): array
    {
        try {
            $subscription = $this->stripe->subscriptions->retrieve($stripeSubscriptionId);

            $periodEnd = $subscription->items->data[0]->current_period_end;
            $now = new DateTime();
            $currentPeriodEnd = new DateTime();
            $currentPeriodEnd->setTimestamp($periodEnd);

            $targetDate = new DateTime();
            $targetDate->setDate(
                (int) $targetDate->format('Y'),
                (int) $targetDate->format('m'),
                min($newDayOfMonth, (int) $targetDate->format('t'))
            );

            if ($targetDate <= $now) {
                $targetDate->modify('+1 month');
                $targetDate->setDate(
                    (int) $targetDate->format('Y'),
                    (int) $targetDate->format('m'),
                    min($newDayOfMonth, (int) $targetDate->format('t'))
                );
            }

            $daysToNewDate = $now->diff($targetDate)->days;
            $daysInCurrentPeriod = $now->diff($currentPeriodEnd)->days;
            $amount = $subscription->items->data[0]->price->unit_amount / 100;
            $dailyRate = $amount / 30;

            if ($daysToNewDate < $daysInCurrentPeriod) {
                $prorationAmount = -($dailyRate * ($daysInCurrentPeriod - $daysToNewDate));
            } else {
                $prorationAmount = $dailyRate * ($daysToNewDate - $daysInCurrentPeriod);
            }

            return [
                'success' => true,
                'current_period_end' => $currentPeriodEnd->format('Y-m-d'),
                'new_billing_date' => $targetDate->format('Y-m-d'),
                'proration_amount' => round($prorationAmount, 2),
                'is_credit' => $prorationAmount < 0,
                'days_difference' => $daysToNewDate - $daysInCurrentPeriod,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
