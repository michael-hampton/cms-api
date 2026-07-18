<?php

namespace App\Models;

class SubscriptionCommunicationLetterCode extends Model
{
    protected $table = 'subscription_communication_letter_codes';

    protected $fillable = [
        'subscription_communication_id',
        'letter_code',
        'description',
    ];
}
