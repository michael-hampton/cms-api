<?php

namespace App\Repositories\Newsletters;

use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\Subscriber;
use App\Repositories\Repository;

class SubscriberRepository extends Repository
{
    public function findByEmail(string $email, int $siteId): ?Subscriber
    {
        return Subscriber::where('email', $email)
            ->active()
            ->where('site_id', $siteId)
            ->first();
    }

    public function findByConfirmationToken(string $token): ?Subscriber
    {
        return Subscriber::active()
            ->where('confirmation_token', $token)
            ->first();
    }

    public function findByUnsubscribeToken(string $token): ?Subscriber
    {
        return Subscriber::where('unsubscribe_token', $token)->first();
    }

    public function getConfirmedEmails(int $siteId): array
    {
        $results = Subscriber::active()
            ->where('confirmed', true)
            ->where('site_id', $siteId)
            ->get()
            ->toArray();

        return array_column($results, 'email');
    }

    public function getNewslettersForMember(string $email, ?int $siteId = null): Collection
    {
        $siteId = $siteId ?? $this->siteId;

        return Subscriber::active()
            ->where('email', $email)
            ->where('site_id', $siteId)
            ->get();
    }

    public function getAllNewslettersForMember(string $email, ?int $siteId = null): Collection
    {
        $siteId = $siteId ?? $this->siteId;

        return Subscriber::where('email', $email)
            ->where('site_id', $siteId)
            ->get();
    }


    public function findByEmailAndNewsletter(string $email, int $newsletterId, int $siteId): ?Subscriber
    {
        return Subscriber::active()
            ->where('email', $email)
            ->where('newsletter_id', $newsletterId)
            ->where('site_id', $siteId)
            ->first();
    }

    /**
     * Find existing subscription (active or unsubscribed)
     * Used for checking duplicates and enabling resubscription
     */
    public function findExisting(string $email, int $newsletterId, int $siteId): ?Subscriber
    {
        return Subscriber::where('email', $email)
            ->where('newsletter_id', $newsletterId)
            ->where('site_id', $siteId)
            ->first();
    }

    public function create(array $data): Model
    {
        return Subscriber::create($data);
    }

    protected function getModelClass(): string
    {
        return Subscriber::class;
    }

    public function findByCampaign(int $campaignId): Collection
    {
        return Subscriber::where('campaign_id', $campaignId)->get();
    }

    public function getConfirmedEmailsByCampaign(int $campaignId): array
    {
        $results = Subscriber::where('confirmed', true)
            ->where('campaign_id', $campaignId)
            ->get()
            ->toArray();

        return array_column($results, 'email');
    }

    /**
     * Unsubscribe a subscriber by setting unsubscribed_at
     */
    public function unsubscribe(int $subscriberId): bool
    {
        $subscriber = $this->find($subscriberId);

        if (!$subscriber) {
            return false;
        }

        return $subscriber->unsubscribe();
    }

    /**
     * Resubscribe a subscriber by clearing unsubscribed_at
     */
    public function resubscribe(int $subscriberId, ?int $campaignId = null): bool
    {
        $subscriber = $this->find($subscriberId);

        if (!$subscriber) {
            return false;
        }

        return $subscriber->resubscribe($campaignId);
    }

    /**
     * Find subscribers by multiple email addresses
     *
     * @param array $emails
     * @param int $siteId
     * @return Collection
     */
    public function findByEmails(array $emails, int $siteId): Collection
    {
        return Subscriber::whereIn('email', $emails)
            ->where('site_id', $siteId)
            ->get();
    }

    /**
     * Return the newsletter IDs the member is actively subscribed to.
     *
     * "Active" = confirmed = true AND unsubscribed_at IS NULL.
     *
     * @return int[]
     */
    public function getActiveNewsletterIdsForMember(string $email, int $siteId): array
    {
        return Subscriber::where('email', $email)
            ->where('site_id', $siteId)
            ->where('confirmed', true)
            ->whereNull('unsubscribed_at')
            ->whereNotNull('newsletter_id')
            ->get()
            ->pluck('newsletter_id')
            ->map(fn($id) => (int)$id)
            ->all();
    }

    /**
     * Return all active subscriber email addresses for a given newsletter.
     * Used by the co-subscription pass in NewsletterRelationshipBuilder.
     *
     * @return string[]
     */
    public function getActiveEmailsForNewsletter(int $newsletterId, int $siteId): array
    {
        return Subscriber::where('newsletter_id', $newsletterId)
            ->where('site_id', $siteId)
            ->where('confirmed', true)
            ->whereNull('unsubscribed_at')
            ->get()
            ->pluck('email')
            ->all();
    }

    /**
     * Count how many emails from the given set are also actively subscribed
     * to the target newsletter. Used to compute co-subscription overlap fraction.
     *
     * @param string[] $emails
     */
    public function countEmailOverlap(array $emails, int $targetNewsletterId, int $siteId): int
    {
        if (empty($emails)) {
            return 0;
        }

        return Subscriber::whereIn('email', $emails)
            ->where('newsletter_id', $targetNewsletterId)
            ->where('site_id', $siteId)
            ->where('confirmed', true)
            ->whereNull('unsubscribed_at')
            ->count();
    }
}