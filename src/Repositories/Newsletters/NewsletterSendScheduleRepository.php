<?php

namespace App\Repositories\Newsletters;

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

    protected function getModelClass(): string
    {
        return NewsletterSendSchedule::class;
    }
}