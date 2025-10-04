<?php
namespace App\Repositories;

use App\Models\EventSignup;
use App\Framework\Support\Str;

class EventSignupRepository extends Repository
{
    protected function getModelClass(): string
    {
        return EventSignup::class;
    }

    public function createSignup(array $data): EventSignup
    {
        $data['confirmation_token'] = Str::random(32);
        $data['status'] = 'pending';

        return $this->create($data);
    }

    public function getEventSignups(string $eventTitle): array
    {
        return EventSignup::where('event_title', $eventTitle)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    public function confirmSignup(string $token): bool
    {
        $signup = EventSignup::where('confirmation_token', $token)->first();

        if ($signup) {
            $signup->confirm();
            return true;
        }

        return false;
    }

    public function getSignupStats(string $eventTitle): array
    {
        $query = EventSignup::where('event_title', $eventTitle);

        return [
            'total' => $query->count(),
            'confirmed' => $query->where('status', 'confirmed')->count(),
            'pending' => $query->where('status', 'pending')->count(),
            'newsletter_subscribers' => $query->where('newsletter', true)->count()
        ];
    }
}