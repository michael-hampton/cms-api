<?php

namespace App\Models;

class SubscriptionPlanRegionSet extends Model
{
    protected $table = 'subscription_plan_region_sets';
    protected $fillable = [
        'region_set_id',
        'subscription_plan_id'
    ];

}