<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Models\Newsletter;

class NewsletterRepository extends Repository
{
    public function find(int $id, array $relations = []): ?Newsletter
    {
        return Newsletter::find($id);
    }

    public function getDueNewsletters(int $siteId): array
    {
        $newsletters = Newsletter::where('active', true)->where('site_id', $siteId)->get();
        $due = [];

        foreach ($newsletters as $newsletter) {
            if ($newsletter->shouldSend()) {
                $due[] = $newsletter;
            }
        }

        return $due;
    }

    public function getPublished(int $siteId): Collection
    {
        return Newsletter::where('site_id', $siteId)
            ->where('active', true)
            ->whereNotNull('last_sent')
            ->orderBy('last_sent', 'desc')
            ->limit(10)
            ->get();
    }

    public function getArchive(int $siteId, int $page = 1, int $perPage = 20): Collection
    {
        $offset = ($page - 1) * $perPage;

        return Newsletter::where('site_id', $siteId)
            ->where('active', true)
            ->whereNotNull('last_sent')
            ->orderBy('last_sent', 'desc')
            ->limit($perPage)
            ->offset($offset)
            ->get();
    }

    protected function getModelClass(): string
    {
        return Newsletter::class;
    }
}