<?php

namespace App\Models;

class NewsletterSend extends Model
{
    protected $table = 'newsletter_sends';
    protected $fillable = [
        'newsletter_id',
        'sent_at',
        'recipient_count',
        'content_snapshot',
        'html_snapshot'
    ];
    protected $casts = [
        'sent_at' => 'datetime',
        'content_snapshot' => 'array',
    ];

    protected $timestamps = false;

    public function newsletter()
    {
        return $this->belongsTo(Newsletter::class);
    }

    public function pageViews()
    {
        return $this->hasMany(NewsletterSendPageView::class, 'newsletter_send_id');
    }
}