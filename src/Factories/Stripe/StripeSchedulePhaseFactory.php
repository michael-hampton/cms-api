<?php

namespace App\Factories\Stripe;

use App\DTO\Stripe\CreateStripeSubscriptionScheduleDto;

/**
 * Builds the Stripe Subscription Schedule phases array.
 *
 * Supported phase layouts:
 *
 *   INTRO only
 *     [intro_price × introCycles] → [recurring_price, open-ended]
 *
 *   TRIAL_INTRO  (trial_days set)
 *     Stripe does not support a dedicated trial phase inside a schedule.
 *     Instead we pass trial_end as the schedule start_date offset and
 *     begin the intro phase immediately after.
 *     The caller is responsible for passing trial_end as the start_date
 *     on the schedule; this factory always starts phases at 'now'.
 *
 * Single reason to change: the phase-building algorithm.
 * No Stripe SDK calls here — pure array construction.
 */
class StripeSchedulePhaseFactory
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildPhases(CreateStripeSubscriptionScheduleDto $dto, ?string $stripeCouponId = null): array
    {
        return [
            $this->introPhase($dto->introPriceId, $dto->introCycles, $stripeCouponId),
            $this->recurringPhase($dto->recurringPriceId),
        ];
    }

    // ── Private builders ─────────────────────────────────────────────────────

    /**
     * @param array<string, mixed>
     */
    private function introPhase(string $introPriceId, int $introCycles, ?string $stripeCouponId = null): array
    {
        $phase = [
            'items'      => [['price' => $introPriceId]],
            'iterations' => $introCycles,
        ];

        if ($stripeCouponId !== null) {
            // First-cycle vouchers belong on the first billed phase only.
            $phase['discounts'] = [['coupon' => $stripeCouponId]];
        }

        return $phase;
    }

    /**
     * Open-ended recurring phase — no iterations means "forever".
     *
     * @return array<string, mixed>
     */
    private function recurringPhase(string $recurringPriceId): array
    {
        return [
            'items' => [['price' => $recurringPriceId]],
        ];
    }
}
