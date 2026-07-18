<?php

namespace App\Models;

use App\Enums\Subscriptions\LetterFulfilmentStatus;

class SubscriptionCommunicationLetterFulfilment extends Model
{
    protected $table = 'subscription_communication_letter_fulfilments';

    protected $fillable = [
        'subscription_communication_letter_batch_id',
        'subscription_communication_delivery_id',
        'subscription_id',
        'letter_code',
        'full_name',
        'address_line_1',
        'address_line_2',
        'city',
        'postcode',
        'country',
        'address_snapshot',
        'status',
    ];

    protected $casts = [
        'address_snapshot' => 'array',
        'status'            => LetterFulfilmentStatus::class,
    ];

    public function batch()
    {
        return $this->belongsTo(SubscriptionCommunicationLetterBatch::class, 'subscription_communication_letter_batch_id');
    }
}
