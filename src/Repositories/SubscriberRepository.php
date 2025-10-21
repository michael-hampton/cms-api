<?php

namespace App\Repositories;

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

//    public function create(array $data): Subscriber
//    {
//        $result = Subscriber::create($data);
//        return new Subscriber($result);
//    }
//
//    public function update(Subscriber $subscriber, array $data): Subscriber
//    {
//        $subscriber->update($data);
//        return $subscriber;
//    }
//
//    public function delete(Subscriber $subscriber): bool
//    {
//        return $subscriber->delete();
//    }

    protected function getModelClass(): string
    {
       return Subscriber::class;
    }
}