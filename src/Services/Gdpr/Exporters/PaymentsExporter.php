<?php

namespace App\Services\Gdpr\Exporters;

use App\Models\Member;
use App\Models\Payment;

final class PaymentsExporter implements MemberDataExporter
{
    public function key(): string
    {
        return 'payments';
    }

    public function export(Member $member): array
    {
        // Payments are linked via orders (order.user_id = member.id)
        // We join through orders to find all payments for this member.
        $payments = Payment::whereIn(
            'order_id',
            \App\Models\Order::where('user_id', $member->id)
                ->get()
                ->pluck('id')
                ->toArray()
        )
            ->orderBy('created_at', 'desc')
            ->get();

        // Also include subscription payments linked directly
        $subPayments = Payment::where('subscription_id', '!=', null)
            ->whereIn(
                'subscription_id',
                \App\Models\Subscription::where('member_id', $member->id)
                    ->get()
                    ->pluck('id')
                    ->toArray()
            )
            ->whereNotIn('id', $payments->pluck('id')->toArray())
            ->orderBy('created_at', 'desc')
            ->get();

        return $payments->merge($subPayments)
            ->map(fn(Payment $p) => [
                'id'               => $p->id,
                'order_id'         => $p->order_id,
                'subscription_id'  => $p->subscription_id,
                'payment_method'   => $p->payment_method,
                'payment_provider' => $p->payment_provider,
                'transaction_id'   => $p->transaction_id,
                'payment_intent_id'=> $p->payment_intent_id,
                'status'           => $p->status,
                'amount'           => $p->amount,
                'currency'         => $p->currency,
                'paid_at'          => $p->paid_at?->format('Y-m-d H:i:s'),
                'created_at'       => $p->created_at?->format('Y-m-d H:i:s'),
            ])
            ->values()
            ->toArray();
    }
}