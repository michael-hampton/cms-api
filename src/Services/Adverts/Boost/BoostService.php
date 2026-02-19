<?php

namespace App\Services\Adverts\Boost;

use App\Contracts\Boost\BoostableInterface;
use App\Contracts\ClockInterface;
use App\Enums\Boost\BoostStatus;
use App\Events\Boost\BoostActivatedEvent;
use App\Events\Boost\BoostCancelledEvent;
use App\Events\Boost\BoostCreatedEvent;
use App\Events\Boost\BoostExpiredEvent;
use App\Events\Boost\BoostResumedEvent;
use App\Exceptions\Boost\BoostNotFoundException;
use App\Exceptions\Boost\BoostTransitionException;
use App\Framework\Database\Database;
use App\Models\Boost;
use App\Repositories\Adverts\Boost\BoostRepository;

class BoostService
{
    public function __construct(
        private readonly BoostRepository         $boostRepository,
        private readonly BoostEligibilityService $eligibilityService,
        private readonly BoostPricingService     $pricingService,
        private readonly Database                $database,
        private readonly ClockInterface          $clock,
    )
    {
    }

    public function createBoost(
        BoostableInterface $target,
        string             $boostableType,
        int                $merchantId,
        string             $context,
        \DateTimeImmutable $startsAt,
        \DateTimeImmutable $endsAt,
        float              $multiplier,
        string             $currency = 'GBP',
        ?string            $paymentReference = null,
        ?array             $campaignOverride = null,
        ?array             $limits = null,           // ← new
    ): Boost
    {
        $this->eligibilityService->assertEligible($target, $boostableType, $merchantId);

        $pricePaid = $this->pricingService->calculate(
            $boostableType, $context, $startsAt, $endsAt, $campaignOverride
        );

        return $this->database->transaction(function () use (
            $target, $boostableType, $merchantId, $context,
            $startsAt, $endsAt, $multiplier, $pricePaid,
            $currency, $paymentReference, $limits
        ) {
            $boost = $this->boostRepository->create([
                'boostable_type' => $boostableType,
                'boostable_id' => $target->getBoostableId(),
                'merchant_id' => $merchantId,
                'context' => $context,
                'starts_at' => $startsAt->format('Y-m-d H:i:s'),
                'ends_at' => $endsAt->format('Y-m-d H:i:s'),
                'multiplier' => $multiplier,
                'status' => BoostStatus::Pending->value,
                'price_paid' => $pricePaid,
                'currency' => $currency,
                'payment_reference' => $paymentReference,
            ]);

            if ($limits !== null) {
                $this->boostRepository->createLimit($boost->id, $limits);
            }

            event(new BoostCreatedEvent($boost));

            return $boost;
        });
    }

    public function pauseBoost(int $boostId): Boost
    {
        $boost = $this->findOrFail($boostId);

        if (!$boost->isActive()) {
            throw new BoostTransitionException('Only active boosts can be paused.');
        }

        return $this->database->transaction(function () use ($boost) {
            $updated = $this->boostRepository->update($boost->id, [
                'status' => BoostStatus::Paused->value,
            ]);

            event(new BoostResumedEvent($updated));

            return $updated;
        });
    }

    private function findOrFail(int $boostId): Boost
    {
        $boost = $this->boostRepository->find($boostId);

        if (!$boost) {
            throw BoostNotFoundException::forId($boostId);
        }

        return $boost;
    }

    public function resumeBoost(int $boostId): Boost
    {
        $boost = $this->findOrFail($boostId);

        if (!$boost->isPaused()) {
            throw new BoostTransitionException('Only paused boosts can be resumed.');
        }

        // Check if the boost period has already ended while paused
        $now = $this->clock->now();
        if ($now >= $boost->ends_at) {
            throw new BoostTransitionException('Boost period has ended and cannot be resumed.');
        }

        return $this->database->transaction(function () use ($boost) {
            $updated = $this->boostRepository->update($boost->id, [
                'status' => BoostStatus::Active->value,
            ]);

            event(new BoostResumedEvent($updated));

            return $updated;
        });
    }

    public function activateBoost(int $boostId): Boost
    {
        $boost = $this->findOrFail($boostId);
        $now = $this->clock->now();

        if ($boost->isCancelled()) {
            throw new BoostTransitionException('Cannot activate a cancelled boost.');
        }

        if ($boost->isActive()) {
            return $boost;
        }

        if ($now < $boost->starts_at) {
            throw new BoostTransitionException('Boost cannot be activated before its start time.');
        }

        return $this->database->transaction(function () use ($boost) {
            $updated = $this->boostRepository->update($boost->id, [
                'status' => BoostStatus::Active->value,
            ]);

            event(new BoostActivatedEvent($updated));

            return $updated;
        });
    }

    public function expireBoost(int $boostId): Boost
    {
        $boost = $this->findOrFail($boostId);

        if ($boost->isExpired() || $boost->isCancelled()) {
            return $boost;
        }

        return $this->database->transaction(function () use ($boost) {
            $updated = $this->boostRepository->update($boost->id, [
                'status' => BoostStatus::Expired->value,
            ]);

            event(new BoostExpiredEvent($updated));

            return $updated;
        });
    }

    public function cancelBoost(int $boostId): Boost
    {
        $boost = $this->findOrFail($boostId);

        if ($boost->isExpired()) {
            throw new BoostTransitionException('Cannot cancel an already expired boost.');
        }

        if ($boost->isCancelled()) {
            return $boost;
        }

        return $this->database->transaction(function () use ($boost) {
            $updated = $this->boostRepository->update($boost->id, [
                'status' => BoostStatus::Cancelled->value,
            ]);

            event(new BoostCancelledEvent($updated));

            return $updated;
        });
    }
}