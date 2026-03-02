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

    public function findOrFail(int $id): NewsletterIssue
    {
        $issue = $this->find($id);

        if ($issue === null) {
            throw new \RuntimeException("NewsletterIssue {$id} not found.");
        }

        return $issue;
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
            ->orderBy('id', 'desc')
            ->get();
    }

    public function findSendableByNewsletter(int $newsletterId): Collection
    {
        return NewsletterIssue::where('newsletter_id', $newsletterId)
            ->whereIn('status', ['draft', 'ready'])
            ->get();
    }

    /**
     * Return the highest issue_number for a given newsletter, or 0 if none exist.
     * Used to derive the next sequential number on creation.
     */
    public function getMaxIssueNumber(int $newsletterId): int
    {
        return (int)NewsletterIssue::where('newsletter_id', $newsletterId)
            ->max('issue_number');
    }
}