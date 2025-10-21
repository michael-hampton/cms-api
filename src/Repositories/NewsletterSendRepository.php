<?php

namespace App\Repositories;

use App\Models\NewsletterSend;

class NewsletterSendRepository extends Repository
{
    public function getByNewsletterId(int $newsletterId): array
    {
        return NewsletterSend::where('newsletter_id', $newsletterId)->get()->toArray();
    }

    protected function getModelClass(): string
    {
       return NewsletterSend::class;
    }
}