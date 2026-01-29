<?php

namespace App\Repositories\Newsletters;

use App\Framework\Support\Collection;
use App\Models\Member;
use App\Models\Model;
use App\Models\Newsletter;
use App\Repositories\Repository;

class NewsletterRepository extends Repository
{
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

    public function getActive(int $siteId): Collection
    {
        return Newsletter::where('site_id', $siteId)
            ->where('active', true)
            ->orderBy('title', 'asc')
            ->get();
    }

    public function getDefaultNewsletterForSite(int $siteId): ?Model
    {
        return Newsletter::where('site_id', $siteId)
            ->where('active', true)
            ->where('is_default', true)
            ->first();
    }

    public function getNewslettersForMember(Member $member): Collection
    {
        $newsletterSubscriptions = $member->newsletters;
        $newsletterIds = $newsletterSubscriptions->pluck('newsletter_id')->toArray();

        return Newsletter::whereIn('id', $newsletterIds)->get();
    }

    public function getNewslettersById(array $newsletterIds): Collection
    {
        return Newsletter::whereIn('id', $newsletterIds)->get();
    }

    public function findBySite(int $siteId): Collection
    {
        return Newsletter::where('site_id', $siteId)->get();
    }

    protected function getModelClass(): string
    {
        return Newsletter::class;
    }
}