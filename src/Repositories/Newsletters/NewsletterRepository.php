<?php

namespace App\Repositories\Newsletters;

use App\Framework\Support\Collection;
use App\Models\Member;
use App\Models\Model;
use App\Models\Newsletter;
use App\Models\Page;
use App\Repositories\Repository;

class NewsletterRepository extends Repository
{
    /**
     * Return newsletters that are due to send for a given site,
     * optionally filtered to only those visible to a specific member.
     *
     * When $member is provided the newsletter's region-set restrictions
     * are applied via Newsletter::isVisibleToMember():
     *  - Member with no territory   → receives all due newsletters
     *  - Member with territory      → receives only newsletters with no
     *                                  region sets, or those whose region
     *                                  sets include the member's territory
     */
    public function getDueNewsletters(int $siteId, ?Member $member = null): array
    {
        $query = Newsletter::with(['regionSets.territories'])
            ->where('active', true)
            ->where('site_id', $siteId);

        if ($member) {
            $query->visibleToMember($member);
        }

        $newsletters = $query->get();

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

    public function getPagesForNewsletter(Newsletter $newsletter, int $siteId, ?Member $member = null): Collection
    {
        if (!$newsletter->isAutomated()) {
            return collect([]);
        }

        $filters = $newsletter->page_filters ?? [];

        $query = Page::with(['categories', 'tags', 'authors', 'metadata', 'blocks'])
            ->where('site_id', $siteId)
            ->where('status', 'published')
            ->visibleToMember($member);

        if ($newsletter->last_sent) {
            $query->where('published_at', '>=', $newsletter->last_sent->format('Y-m-d H:i:s'));
        } elseif (isset($filters['date_range_days'])) {
            $query->where('published_at', '>=', date('Y-m-d H:i:s', strtotime("-{$filters['date_range_days']} days")));
        }

        if (!empty($filters['categories'])) {
            $query->whereHas('categories', function ($q) use ($filters) {
                $q->whereIn('categories.id', $filters['categories']);
            });
        }

        if (!empty($filters['tags'])) {
            $query->whereHas('tags', function ($q) use ($filters) {
                $q->whereIn('tags.id', $filters['tags']);
            });
        }

        if (!empty($filters['page_types'])) {
            $query->whereIn('page_type', $filters['page_types']);
        }

        if (isset($filters['featured_only']) && $filters['featured_only']) {
            $query->whereHas('metadata', function ($q) {
                $q->where('featured', true);
            });
        }

        $sortBy = $newsletter->sort_by ?? 'published_at';
        $sortOrder = $newsletter->sort_order ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        if ($newsletter->max_pages) {
            $query->limit($newsletter->max_pages);
        }

        return $query->get();
    }

    public function findBySlugAndSite(string $slug, int $siteId): ?Newsletter
    {
        return Newsletter::where('slug', $slug)
            ->where('site_id', $siteId)
            ->first();
    }

    /**
     * Find an active newsletter for a site, matching by slug or id — used
     * to resolve a premium_identifier that may be stored as either.
     */
    public function findActiveBySiteAndSlugOrId(int $siteId, string $slugOrId): ?Newsletter
    {
        return Newsletter::where('site_id', $siteId)
            ->where('active', true)
            ->where(function ($query) use ($slugOrId) {
                $query->where('slug', $slugOrId)
                    ->orWhere('id', $slugOrId);
            })
            ->first();
    }

    protected function getModelClass(): string
    {
        return Newsletter::class;
    }
}