<?php

namespace App\Repositories\Newsletters;

use App\Framework\Support\Collection;
use App\Models\NewsletterSend;
use App\Repositories\Repository;

class NewsletterSendRepository extends Repository
{
    public function getByNewsletterId(int $newsletterId): array
    {
        return NewsletterSend::where('newsletter_id', $newsletterId)->get()->toArray();
    }

    /**
     * Get a specific send by newsletter ID and send ID
     */
    public function findByNewsletterAndSendId(int $newsletterId, int $sendId): ?NewsletterSend
    {
        return NewsletterSend::where('newsletter_id', $newsletterId)
            ->where('id', $sendId)
            ->first();
    }

    /**
     * Get the latest send for a newsletter
     */
    public function getLatestSendForNewsletter(int $newsletterId): ?NewsletterSend
    {
        return NewsletterSend::where('newsletter_id', $newsletterId)
            ->orderBy('sent_at', 'desc')
            ->first();
    }

    /**
     * Get all sends for a newsletter ordered by date
     */
    public function getSendsForNewsletter(int $newsletterId): Collection
    {
        return NewsletterSend::where('newsletter_id', $newsletterId)
            ->orderBy('sent_at', 'desc')
            ->get();
    }

    public function getSendsByNewsletterIds(array $newsletterIds)
    {
        return NewsletterSend::whereIn('newsletter_id', $newsletterIds)
            ->orderByDesc('sent_at')
            ->get();
    }

    protected function getModelClass(): string
    {
        return NewsletterSend::class;
    }
}