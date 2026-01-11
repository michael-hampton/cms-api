<?php

namespace App\Repositories\Newsletters;

use App\Models\NewsletterSend;
use App\Repositories\Repository;

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