<?php

namespace App\Repositories\Subscriptions;

use App\Models\SubscriptionWindow;
use App\Repositories\Repository;

class SubscriptionWindowRepository extends Repository
{
    /**
     * Returns true if the subscription has at least one window that covers
     * the given date.
     */
    public function hasActiveWindowForDate(int $subscriptionId, \DateTime $date): bool
    {
        return SubscriptionWindow::where('subscription_id', $subscriptionId)
            ->where('window_start', '<=', $date->format('Y-m-d H:i:s'))
            ->where(function ($query) use ($date) {
                $query->where('window_end', '>=', $date->format('Y-m-d H:i:s'))
                    ->orWhereNull('window_end');
            })
            ->exists();
    }

    protected function getModelClass(): string
    {
        return SubscriptionWindow::class;
    }
}