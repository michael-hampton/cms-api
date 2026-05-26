<?php

namespace App\Enums;

enum AttachmentableType: string
{
    case PAYMENT        = 'payment';
    case MANUAL_PAYMENT = 'manual_payment';
    case REFUND         = 'refund';
    case ORDER          = 'order';
    case SUBSCRIPTION   = 'subscription';
    case MEMBER         = 'member';

    public function modelClass(): string
    {
        return match($this) {
            self::PAYMENT        => \App\Models\Payment::class,
            self::MANUAL_PAYMENT => \App\Models\ManualPayment::class,
            self::REFUND         => \App\Models\Payment::class, // refunds are payment records with status=refunded
            self::ORDER          => \App\Models\Order::class,
            self::SUBSCRIPTION   => \App\Models\Subscription::class,
            self::MEMBER         => \App\Models\Member::class,
        };
    }
}