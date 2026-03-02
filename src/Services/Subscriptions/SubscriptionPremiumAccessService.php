<?php

namespace App\Services\Subscriptions;

use App\Exceptions\Subscriptions\PremiumAccessAlreadyGrantedException;
use App\Exceptions\Subscriptions\PremiumAccessNotFoundException;
use App\Exceptions\Subscriptions\SubscriptionNotFoundException;
use App\Framework\Support\Collection;
use App\Models\SubscriptionPremiumAccess;
use App\Repositories\Subscriptions\SubscriptionPremiumAccessRepository;

class SubscriptionPremiumAccessService
{
    public function __construct(
        private readonly SubscriptionPremiumAccessRepository $accessRepository
    )
    {
    }

    public function grant(
        int     $subscriptionId,
        string  $type,
        string  $identifier,
        ?string $expiresAt = null
    ): SubscriptionPremiumAccess
    {
        $subscription = $this->accessRepository->findSubscription($subscriptionId);

        if (!$subscription) {
            throw new SubscriptionNotFoundException("Subscription {$subscriptionId} not found");
        }

        $existing = $this->accessRepository->findExisting($subscriptionId, $type, $identifier);

        if ($existing && $existing->is_active) {
            throw new PremiumAccessAlreadyGrantedException(
                "Active premium access '{$type}:{$identifier}' already exists for subscription {$subscriptionId}"
            );
        }

        return $this->accessRepository->create([
            'subscription_id' => $subscriptionId,
            'premium_type' => $type,
            'premium_identifier' => $identifier,
            'granted_at' => now(),
            'expires_at' => $expiresAt,
            'is_active' => true,
        ]);
    }

    public function revoke(int $subscriptionId, string $type, string $identifier): bool
    {
        $subscription = $this->accessRepository->findSubscription($subscriptionId);

        if (!$subscription) {
            throw new SubscriptionNotFoundException("Subscription {$subscriptionId} not found");
        }

        return $this->accessRepository->deactivate($subscriptionId, $type, $identifier);
    }

    public function update(int $id, array $data): SubscriptionPremiumAccess
    {
        $allowedFields = ['expires_at', 'is_active'];
        $filtered = array_intersect_key($data, array_flip($allowedFields));

        $access = $this->accessRepository->update($id, $filtered);

        if (!$access) {
            throw new PremiumAccessNotFoundException("Premium access record {$id} not found");
        }

        return $access;
    }

    public function delete(int $id): bool
    {
        $deleted = $this->accessRepository->delete($id);

        if (!$deleted) {
            throw new PremiumAccessNotFoundException("Premium access record {$id} not found");
        }

        return true;
    }

    public function getForSubscription(int $subscriptionId): Collection
    {
        return $this->accessRepository->findBySubscription($subscriptionId);
    }

    public function getActiveForSubscription(int $subscriptionId): Collection
    {
        return $this->accessRepository->findActiveBySubscription($subscriptionId);
    }
}