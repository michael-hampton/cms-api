<?php

namespace App\Mail;

use App\Framework\Mail\Mailable;
use App\Framework\Support\SiteContext;
use App\Models\GiftedArticle;

class GiftedArticleMail extends Mailable
{
    public function __construct(
        private readonly GiftedArticle $gift,
        private readonly string        $shareLink,
        private readonly string        $recipientEmail,
        private readonly ?string       $personalMessage = null
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        $site = SiteContext::get();

        return $this
            ->to($this->recipientEmail)
            ->subject("Someone shared an article with you on {$site->name}")
            ->markdown('emails.gifts.article')
            ->with([
                'gift' => $this->gift,
                'shareLink' => $this->shareLink,
                'personalMessage' => $this->personalMessage,
                'siteName' => $site->name,
                'articleTitle' => $this->gift->page->title ?? 'an article',
            ]);
    }
}