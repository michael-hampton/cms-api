<?php

namespace App\Repositories\Newsletters;

use App\Models\NewsletterCreationSchedule;
use App\Repositories\Repository;

class NewsletterCreationScheduleRepository extends Repository
{
    public function findByNewsletterId(int $newsletterId): ?NewsletterCreationSchedule
    {
        return NewsletterCreationSchedule::where('newsletter_id', $newsletterId)
            ->whereNot('status', 'cancelled')
            ->latest()
            ->first();
    }

    public function findActiveForNewsletter(int $newsletterId): ?NewsletterCreationSchedule
    {
        return NewsletterCreationSchedule::where('newsletter_id', $newsletterId)
            ->where('status', 'active')
            ->first();
    }

    public function hasActiveScheduleForNewsletter(int $newsletterId): bool
    {
        return NewsletterCreationSchedule::where('newsletter_id', $newsletterId)
            ->where('status', 'active')
            ->exists();
    }

    protected function getModelClass(): string
    {
        return NewsletterCreationSchedule::class;
    }
}