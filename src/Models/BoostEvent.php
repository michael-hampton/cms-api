<?php

namespace App\Models;

class BoostEvent extends Model
{
    public $timestamps = false;
    protected $table = 'boost_events';
    protected $fillable = [
        'boost_id',
        'type',
        'session_hash',
        'occurred_at',
        'metadata',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function boost()
    {
        return $this->belongsTo(Boost::class);
    }
}