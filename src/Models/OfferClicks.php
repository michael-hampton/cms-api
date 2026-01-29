<?php

namespace App\Models;

class OfferClicks extends Model
{
    protected $table = 'offer_clicks';

    protected $fillable = [
        'offer_id', 'member_id', 'ip_address'
    ];

}