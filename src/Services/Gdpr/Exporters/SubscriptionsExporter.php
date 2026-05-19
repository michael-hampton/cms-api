<?php

namespace App\Services\Gdpr\Exporters;

use App\Models\Member;
use App\Models\Subscription;

final class SubscriptionsExporter implements MemberDataExporter
{
    public function key(): string
    {
        return 'subscriptions';
    }

    public function export(Member $member): array
    {
        return Subscription::where('member_id', $member->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn(Subscription $s) => [
                'id'                     => $s->id,
                'plan_id'                => $s->plan_id,
                'plan_name'              => $s->plan_name,
                'status'                 => $s->status,
                'type'                   => $s->type,
                'delivery_type'          => $s->delivery_type,
                'price'                  => $s->price,
                'currency'               => $s->currency,
                'start_date'             => $s->start_date?->format('Y-m-d H:i:s'),
                'end_date'               => $s->end_date?->format('Y-m-d H:i:s'),
                'next_billing_date'      => $s->next_billing_date?->format('Y-m-d H:i:s'),
                'cancelled_at'           => $s->cancelled_at?->format('Y-m-d H:i:s'),
                'auto_renew'             => $s->auto_renew,
                'payment_subscription_id'=> $s->payment_subscription_id,
                'stripe_customer_id'     => $s->stripe_customer_id,
                'created_at'             => $s->created_at?->format('Y-m-d H:i:s'),
            ])
            ->toArray();
    }
}