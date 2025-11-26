<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Models\Subscriber;

class SubscriberRepository extends Repository
{
    public function findByEmail(string $email, int $siteId): ?Subscriber
    {
        return Subscriber::where('email', $email)->where('site_id', $siteId)->first();
    }

    public function findByConfirmationToken(string $token): ?Subscriber
    {
        return Subscriber::where('confirmation_token', $token)->first();
    }

    public function findByUnsubscribeToken(string $token): ?Subscriber
    {
       return Subscriber::where('unsubscribe_token', $token)->first();
    }

    public function getConfirmedEmails(int $siteId): array
    {
        $results =  Subscriber::where('confirmed', true)
            ->where('site_id', $siteId)
            ->get()
            ->toArray();

        return array_column($results, 'email');
    }

    public function getNewslettersForMember(string $email, ?int $siteId = null): Collection
    {
        $siteId = $siteId ?? $this->siteId;

        return Subscriber::where('email', $email)
            ->where('site_id', $siteId)
            ->get();
    }


    public function findByEmailAndNewsletter(string $email, int $newsletterId, int $siteId): ?Subscriber
    {
        return Subscriber::where('email', $email)
            ->where('newsletter_id', $newsletterId)
            ->where('site_id', $siteId)
            ->first();
    }

    public function create(array $data): Subscriber
    {
        return Subscriber::create($data);
    }

    protected function getModelClass(): string
    {
       return Subscriber::class;
    }
}