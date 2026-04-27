<?php

namespace App\Services;

use App\Models\UserNotification;

class NotificationFormatter
{
    public function format(UserNotification $notification): array
    {
        return match ($notification->type) {

            'article_approved' => [
                'title' => 'Article approved',
                'message' => 'Your article has been approved and is now live.',
                'action_url' => '/articles/' . $notification->data['article_id'],
            ],

            'article_rejected' => [
                'title' => 'Article rejected',
                'message' => 'Your article was rejected. Check feedback.',
                'action_url' => '/articles/' . $notification->data['article_id'],
            ],

            'payout_processed' => [
                'title' => 'Payout processed',
                'message' => '£' . number_format($notification->data['amount'], 2) . ' has been sent to your account.',
                'action_url' => '/contributor/earnings',
            ],

            'dispute_raised' => [
                'title' => 'Dispute opened',
                'message' => 'A new dispute has been raised on your content.',
                'action_url' => '/open-collab/disputes/' . $notification->data['dispute_id'],
            ],

            'dispute_resolved' => [
                'title' => 'Dispute resolved',
                'message' => 'Your dispute has been resolved.',
                'action_url' => '/open-collab/disputes/' . $notification->data['dispute_id'],
            ],

            default => [
                'title' => 'Notification',
                'message' => 'You have a new update.',
                'action_url' => null,
            ],
        };
    }
}