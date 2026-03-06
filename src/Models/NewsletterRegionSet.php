<?php

namespace App\Models;

class NewsletterRegionSet extends Model
{
    protected $table = 'newsletter_region_sets';
    protected $fillable = [
        'region_set_id',
        'newsletter_id'
    ];
}