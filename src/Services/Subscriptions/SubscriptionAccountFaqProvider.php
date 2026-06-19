<?php

namespace App\Services\Subscriptions;

final class SubscriptionAccountFaqProvider
{
    public function all(): array
    {
        return [
            [
                'key' => 'cancel',
                'question' => 'What happens when I cancel?',
                'answer' => 'Cancellation stops the next renewal. Your access continues until the end date shown on the subscription.',
            ],
            [
                'key' => 'reactivate',
                'question' => 'Can I reactivate a cancelled renewal?',
                'answer' => 'Yes, while the current entitlement is still live and the cancellation remains reversible.',
            ],
            [
                'key' => 'renew',
                'question' => 'What is the difference between renew and resubscribe?',
                'answer' => 'Renew extends an eligible current term. Resubscribe starts a new purchase after the old entitlement has ended.',
            ],
            [
                'key' => 'payment',
                'question' => 'How do I fix a failed subscription payment?',
                'answer' => 'Use Settle payment on the affected subscription. Payment is only confirmed after Stripe and the account backend report it as paid.',
            ],
            [
                'key' => 'cards',
                'question' => 'Why can’t I remove a payment method?',
                'answer' => 'A card cannot be removed when it would leave active recurring billing or an outstanding invoice without a usable payment method.',
            ],
        ];
    }
}
