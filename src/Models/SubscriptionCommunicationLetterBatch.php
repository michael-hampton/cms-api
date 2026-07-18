<?php

namespace App\Models;

use App\Enums\Subscriptions\LetterBatchStatus;

class SubscriptionCommunicationLetterBatch extends Model
{
    protected $table = 'subscription_communication_letter_batches';

    protected $fillable = [
        'status',
        'exported_at',
    ];

    protected $casts = [
        'status'      => LetterBatchStatus::class,
        'exported_at' => 'datetime',
    ];

    public function fulfilments()
    {
        return $this->hasMany(SubscriptionCommunicationLetterFulfilment::class, 'subscription_communication_letter_batch_id');
    }
}
