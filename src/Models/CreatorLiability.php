<?php

namespace App\Models;

class CreatorLiability extends Model
{
    protected $table = 'oc_creator_liabilities';

    protected $fillable = [
        'user_id',
        'site_id',
        'source_type',
        'source_id',
        'amount',
        'remaining_amount',
        'currency',
        'status',
        'reason',
        'created_by',
        'settled_at',
        'written_off_by',
        'write_off_reason',
        'created_at',
        'updated_at',
    ];
}