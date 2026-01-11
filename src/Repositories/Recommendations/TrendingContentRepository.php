<?php

namespace App\Repositories\Recommendations;

use App\Framework\Support\Collection;
use App\Models\Comment;
use App\Models\Page;
use App\Models\PageLike;
use App\Models\PageView;
use App\Models\TrendingContent;
use App\Repositories\Repository;

class TrendingContentRepository extends Repository
{
    public function calculateTrendingScores(int $siteId): void
    {
        $oneDayAgo = now_datetime()->subHours(24);

        // Get all published pages for this site
        $pages = Page::where('site_id', $siteId)
            ->where('status', 'published')
            ->get();

        foreach ($pages as $page) {
            $viewCount = PageView::where('page_id', $page->id)
                ->where('viewed_at', '>=', $oneDayAgo->format('Y-m-d H:i:s'))
                ->count();

            $likeCount = PageLike::where('page_id', $page->id)
                ->where('liked_at', '>=', $oneDayAgo->format('Y-m-d H:i:s'))
                ->count();

            $commentCount = Comment::where('page_id', $page->id)
                ->where('status', 'approved')
                ->where('created_at', '>=', $oneDayAgo->format('Y-m-d H:i:s'))
                ->count();

            // Calculate trending score
            // Formula: (views * 1) + (likes * 5) + (comments * 10)
            $trendingScore = ($viewCount * 1) + ($likeCount * 5) + ($commentCount * 10);

            // Only track if there's any activity
            if ($trendingScore > 0) {
                TrendingContent::updateOrCreate(
                    [
                        'page_id' => $page->id,
                        'site_id' => $siteId
                    ],
                    [
                        'view_count_24h' => $viewCount,
                        'like_count_24h' => $likeCount,
                        'comment_count_24h' => $commentCount,
                        'trending_score' => $trendingScore,
                        'last_calculated_at' => now_datetime()->format('Y-m-d H:i:s')
                    ]
                );
            }
        }

        // Clean up old entries with no activity
        TrendingContent::where('site_id', $siteId)
            ->where('trending_score', 0)
            ->where('last_calculated_at', '<', now_datetime()->subDays(7)->format('Y-m-d H:i:s'))
            ->delete();
    }

    public function getTrendingPages(int $siteId, int $limit = 6): Collection
    {
        return TrendingContent::where('site_id', $siteId)
            ->where('trending_score', '>', 0)
            ->orderBy('trending_score', 'desc')
            ->limit($limit)
            ->with(['page.categories', 'page.metadata'])
            ->get()
            ->map(function ($trending) {
                $page = $trending->page;
                $page->trending_score = $trending->trending_score;
                $page->view_count_24h = $trending->view_count_24h;
                $page->comment_count_24h = $trending->comment_count_24h;
                $page->like_count_24h = $trending->like_count_24h;
                return $page;
            });
    }

    public function getTrendingConversations(int $siteId, int $limit = 6): Collection
    {
        return TrendingContent::where('site_id', $siteId)
            ->where('comment_count_24h', '>', 0)
            ->orderBy('comment_count_24h', 'desc')
            ->orderBy('like_count_24h', 'desc')
            ->limit($limit)
            ->with(['page.categories', 'page.metadata'])
            ->get()
            ->map(function ($trending) {
                $page = $trending->page;
                $page->trending_score = $trending->trending_score;
                $page->view_count_24h = $trending->view_count_24h;
                $page->comment_count_24h = $trending->comment_count_24h;
                $page->like_count_24h = $trending->like_count_24h;
                return $page;
            });
    }

    protected function getModelClass(): string
    {
        return TrendingContent::class;
    }
}