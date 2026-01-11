<?php

namespace App\Repositories\Recommendations;

use App\Framework\Support\Collection;
use App\Models\Comment;
use App\Models\MemberReadingPreference;
use App\Models\Page;
use App\Models\PageLike;
use App\Models\PageView;
use App\Repositories\Repository;

class ContentRecommendationRepository extends Repository
{
    public function updatePreferencesFromActivity(int $memberId, int $siteId): void
    {
        $preferences = $this->getOrCreatePreferences($memberId, $siteId);

        // Get member's viewed pages
        $viewedPageIds = PageView::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->where('viewed_at', '>=', now_datetime()->subDays(90)->toDateString())
            ->pluck('page_id');

        if (empty($viewedPageIds)) {
            return;
        }

        // Get categories, tags, and authors from viewed pages
        $pages = Page::with(['categories', 'tags', 'pageAuthors'])
            ->whereIn('id', $viewedPageIds)
            ->get();

        $categoryFrequency = [];
        $tagFrequency = [];
        $authorFrequency = [];

        foreach ($pages as $page) {
            // Count categories
            foreach ($page->categories as $category) {
                $categoryFrequency[$category->id] = ($categoryFrequency[$category->id] ?? 0) + 1;
            }

            // Count tags
            foreach ($page->tags as $tag) {
                $tagFrequency[$tag->id] = ($tagFrequency[$tag->id] ?? 0) + 1;
            }

            // Count authors
            foreach ($page->pageAuthors as $pageAuthor) {
                $authorFrequency[$pageAuthor->author_id] = ($authorFrequency[$pageAuthor->author_id] ?? 0) + 1;
            }
        }

        // Get top preferences (minimum 2 occurrences)
        $topCategories = array_keys(array_filter($categoryFrequency, fn($count) => $count >= 2));
        $topTags = array_keys(array_filter($tagFrequency, fn($count) => $count >= 2));
        $topAuthors = array_keys(array_filter($authorFrequency, fn($count) => $count >= 2));

        // Calculate engagement score
        $engagementScore = $this->calculateEngagementScore($memberId, $siteId);

        $preferences->update([
            'preferred_categories' => array_slice($topCategories, 0, 10),
            'preferred_tags' => array_slice($topTags, 0, 10),
            'preferred_authors' => array_slice($topAuthors, 0, 10),
            'engagement_score' => $engagementScore
        ]);
    }

    public function getOrCreatePreferences(int $memberId, int $siteId): MemberReadingPreference
    {
        $preferences = MemberReadingPreference::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->first();

        if (!$preferences) {
            $preferences = MemberReadingPreference::create([
                'member_id' => $memberId,
                'site_id' => $siteId,
                'preferred_categories' => [],
                'preferred_tags' => [],
                'preferred_authors' => [],
                'engagement_score' => 0
            ]);
        }

        return $preferences;
    }

    private function calculateEngagementScore(int $memberId, int $siteId): int
    {
        $thirtyDaysAgo = now_datetime()->subDays(30);

        $views = PageView::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->where('viewed_at', '>=', $thirtyDaysAgo->toDateString())
            ->count();

        $likes = PageLike::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->where('liked_at', '>=', $thirtyDaysAgo->toDateString())
            ->count();

        $comments = Comment::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->where('created_at', '>=', $thirtyDaysAgo->toDateString())
            ->count();

        // Weighted scoring: views=1, likes=3, comments=5
        return ($views * 1) + ($likes * 3) + ($comments * 5);
    }

    public function getRecommendedPages(int $memberId, int $siteId, int $limit = 6): Collection
    {
        $preferences = $this->getOrCreatePreferences($memberId, $siteId);

        // Get pages member hasn't viewed
        $viewedPageIds = PageView::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->pluck('page_id');

        $query = Page::where('site_id', $siteId)
            ->where('status', 'published');

        if (!empty($viewedPageIds)) {
            $query->whereNotIn('id', $viewedPageIds);
        }

        // Build recommendation query based on preferences
        if (!empty($preferences->preferred_categories) ||
            !empty($preferences->preferred_tags) ||
            !empty($preferences->preferred_authors)) {

            $query->where(function ($q) use ($preferences) {
                // Match preferred categories
                if (!empty($preferences->preferred_categories)) {
                    $q->orWhereHas('categories', function ($cq) use ($preferences) {
                        $cq->whereIn('categories.id', $preferences->preferred_categories);
                    });
                }

                // Match preferred tags
                if (!empty($preferences->preferred_tags)) {
                    $q->orWhereHas('tags', function ($tq) use ($preferences) {
                        $tq->whereIn('tags.id', $preferences->preferred_tags);
                    });
                }

                // Match preferred authors
                if (!empty($preferences->preferred_authors)) {
                    $q->orWhereHas('pageAuthors', function ($aq) use ($preferences) {
                        $aq->whereIn('author_id', $preferences->preferred_authors);
                    });
                }
            });
        }

        return $query->with(['categories', 'tags', 'pageAuthors.author', 'metadata'])
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();
    }

    protected function getModelClass(): string
    {
        return MemberReadingPreference::class;
    }
}