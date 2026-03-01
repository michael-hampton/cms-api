<?php

namespace App\Repositories\Newsletters;

use App\Framework\Support\Collection;
use App\Models\NewsletterIssue;

class NewsletterIssueRepository
{
    public function find(int $id): ?NewsletterIssue
    {
        return NewsletterIssue::find($id);
    }

    public function create(array $data): NewsletterIssue
    {
        return NewsletterIssue::create($data);
    }

    public function update(int $id, array $data): NewsletterIssue
    {
        $issue = NewsletterIssue::findOrFail($id);
        $issue->update($data);

        return $issue->fresh();
    }

    public function findByNewsletter(int $newsletterId, int $siteId): Collection
    {
        return NewsletterIssue::where('newsletter_id', $newsletterId)
            ->where('site_id', $siteId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findSendableByNewsletter(int $newsletterId): Collection
    {
        return NewsletterIssue::where('newsletter_id', $newsletterId)
            ->whereIn('status', ['draft', 'ready'])
            ->get();
    }
}