<?php

namespace App\Repositories\Newsletters;

use App\Framework\Support\Collection;
use App\Models\NewsletterIssue;
use App\Repositories\Repository;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;

class NewsletterIssueRepository extends Repository
{

    public function findOrFail(int $id): NewsletterIssue
    {
        $issue = $this->find($id);

        if ($issue === null) {
            throw new \RuntimeException("NewsletterIssue {$id} not found.");
        }

        return $issue;
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

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $configuration = SearchConfigurationFactory::create('newsletter_issue');
        $engine = new SearchEngine($configuration);

        // Replace with however your repository accesses its base query builder,
        // e.g. Campaign::query() or $this->model->newQuery()
        return $engine->search($this->query(), $criteria);
    }

    protected function getModelClass(): string
    {
        return NewsletterIssue::class;
    }
}