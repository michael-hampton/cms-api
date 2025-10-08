<?php

namespace App\Models;

class ReviewHelpful extends Model
{
    protected $table = 'review_helpful';

    protected $fillable = [
        'review_id',
        'user_id',
        'session_id',
        'is_helpful',
        'site_id',
        'created_at'
    ];

    protected $casts = [
        'is_helpful' => 'boolean',
        'created_at' => 'datetime',
    ];

    public $timestamps = false;

    public function review()
    {
        return $this->belongsTo(Review::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}