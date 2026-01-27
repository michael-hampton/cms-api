<?php

namespace App\Models;

class NewsletterSendPageView extends Model
{
    protected $table = 'newsletter_send_page_views';

    protected $fillable = [
        'newsletter_send_id',
        'page_id',
        'email',
        'clicked_at',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'clicked_at' => 'datetime',
    ];

    protected $timestamps = false;

    public function newsletterSend()
    {
        return $this->belongsTo(NewsletterSend::class);
    }

    public function page()
    {
        return $this->belongsTo(Page::class);
    }
}