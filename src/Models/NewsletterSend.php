<?php

namespace App\Models;

class NewsletterSend extends Model
{
    protected $table = 'newsletter_sends';
    protected $fillable = ['newsletter_id', 'sent_at', 'recipient_count'];
    protected $casts = [
        'sent_at' => 'datetime'
    ];

    protected $timestamps = false;

    public function newsletter()
    {
        return $this->belongsTo(Newsletter::class);
    }
}