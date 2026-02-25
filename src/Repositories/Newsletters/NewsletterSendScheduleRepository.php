<?php

namespace App\Repositories\Newsletters;

use App\Framework\Support\Collection;
use App\Models\NewsletterSendSchedule;
use App\Repositories\Repository;

class NewsletterSendScheduleRepository extends Repository
{
    public function findByNewsletterId(int $newsletterId): ?NewsletterSendSchedule
    {
        return NewsletterSendSchedule::where('newsletter_id', $newsletterId)
            ->whereNot('status', 'cancelled')
            ->latest()
            ->first();
    }

    public function findActiveForNewsletter(int $newsletterId): ?NewsletterSendSchedule
    {
        return NewsletterSendSchedule::where('newsletter_id', $newsletterId)
            ->where('status', 'active')
            ->first();
    }

    public function hasActiveScheduleForNewsletter(int $newsletterId): bool
    {
        return NewsletterSendSchedule::where('newsletter_id', $newsletterId)
            ->where('status', 'active')
            ->exists();
    }

    public function getDueSchedules(?int $siteId = null): Collection
    {
        $query = NewsletterSendSchedule::runnable();

        if ($siteId !== null) {
            $query->where('site_id', $siteId);
        }

        return $query->get();
    }

    protected function getModelClass(): string
    {
        return NewsletterSendSchedule::class;
    }
}