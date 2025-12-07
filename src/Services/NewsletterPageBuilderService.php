<?php

namespace App\Services;

use App\Framework\Support\Collection;
use App\Models\Newsletter;
use App\Models\Page;
use App\Repositories\PageRepository;

class NewsletterPageBuilderService
{
    public function __construct(
        private readonly PageRepository $pageRepository
    )
    {
    }

    /**
     * Get pages for newsletter based on filters
     */
    public function getPagesForNewsletter(Newsletter $newsletter, int $siteId): Collection
    {
        if (!$newsletter->isAutomated()) {
            return collect([]);
        }

        $filters = $newsletter->page_filters ?? [];

        // Build query for published pages
        $query = Page::with(['categories', 'tags', 'authors', 'metadata'])
            ->where('site_id', $siteId)
            ->where('status', 'published');

        // Apply date range filter (e.g., pages published since last newsletter)
        if ($newsletter->last_sent) {
            $query->where('published_at', '>=', $newsletter->last_sent->format('Y-m-d H:i:s'));
        } elseif (isset($filters['date_range_days'])) {
            $query->where('published_at', '>=', date('Y-m-d H:i:s', strtotime("-{$filters['date_range_days']} days")));
        }

        // Filter by categories
        if (!empty($filters['categories'])) {
            $query->whereHas('categories', function ($q) use ($filters) {
                $q->whereIn('categories.id', $filters['categories']);
            });
        }

        // Filter by tags
        if (!empty($filters['tags'])) {
            $query->whereHas('tags', function ($q) use ($filters) {
                $q->whereIn('tags.id', $filters['tags']);
            });
        }

        // Filter by page type
        if (!empty($filters['page_types'])) {
            $query->whereIn('page_type', $filters['page_types']);
        }

        // Filter by featured status
        if (isset($filters['featured_only']) && $filters['featured_only']) {
            $query->whereHas('metadata', function ($q) {
                $q->where('featured', true);
            });
        }

        // Apply sorting
        $sortBy = $newsletter->sort_by ?? 'published_at';
        $sortOrder = $newsletter->sort_order ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Apply limit
        if ($newsletter->max_pages) {
            $query->limit($newsletter->max_pages);
        }

        return $query->get();
    }

    /**
     * Build newsletter HTML from pages
     */
    public function buildNewsletterHtml(Newsletter $newsletter, Collection $pages, ?string $unsubscribeToken = null): string
    {
        $template = $newsletter->template ?? 'default';

        switch ($template) {
            case 'digest':
                return $this->buildDigestTemplate($newsletter, $pages, $unsubscribeToken);
            case 'featured':
                return $this->buildFeaturedTemplate($newsletter, $pages, $unsubscribeToken);
            case 'simple':
                return $this->buildSimpleTemplate($newsletter, $pages, $unsubscribeToken);
            default:
                return $this->buildDefaultTemplate($newsletter, $pages, $unsubscribeToken);
        }
    }

    private function buildDigestTemplate(Newsletter $newsletter, Collection $pages, ?string $unsubscribeToken): string
    {
        $html = [];

        $html[] = '<div style="max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px;">';
        $html[] = '<div style="background: white; padding: 30px; border-radius: 8px;">';

        // Header with date
        $html[] = '<h1 style="color: #333; margin-bottom: 10px;">' . htmlspecialchars($newsletter->title) . '</h1>';
        $html[] = '<p style="color: #666; font-size: 14px; margin-bottom: 30px;">' . date('F j, Y') . '</p>';

        // Summary
        $html[] = '<p style="color: #666; font-size: 16px; line-height: 1.6; margin-bottom: 30px;">';
        $html[] = 'Here are the ' . $pages->count() . ' latest articles from our site:';
        $html[] = '</p>';

        // Compact page list
        foreach ($pages as $page) {
            $html[] = $this->renderDigestItem($page);
        }

        $html[] = '</div>';
        $html[] = $this->renderFooter($unsubscribeToken);
        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function renderDigestItem(Page $page): string
    {
        $url = url($page->slug);
        $html = [];

        $html[] = '<div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee;">';
        $html[] = '<h3 style="margin: 0 0 5px 0;">';
        $html[] = '<a href="' . $url . '" style="color: #007bff; text-decoration: none;">';
        $html[] = htmlspecialchars($page->title);
        $html[] = '</a>';
        $html[] = '</h3>';

        if ($page->meta_description) {
            $html[] = '<p style="color: #666; font-size: 14px; margin: 0;">';
            $html[] = htmlspecialchars(substr($page->meta_description, 0, 120)) . '...';
            $html[] = '</p>';
        }
        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function renderFooter(?string $unsubscribeToken = null): string
    {
        $html = [];

        $html[] = '<div style="margin-top: 40px; padding-top: 20px; border-top: 2px solid #eee; text-align: center; color: #999; font-size: 12px;">';
        $html[] = '<p>You received this email because you are subscribed to our newsletter.</p>';

        if ($unsubscribeToken) {
            $unsubscribeUrl = url("/member/subscriptions/unsubscribe/{$unsubscribeToken}");
            $html[] = '<p><a href="' . $unsubscribeUrl . '" style="color: #999;">Unsubscribe</a> | ';
            $manageUrl = url("/member/subscriptions/manage/{$unsubscribeToken}");
            $html[] = '<a href="' . $manageUrl . '" style="color: #999;">Manage Preferences</a></p>';
        }

        $html[] = '<p>&copy; ' . date('Y') . ' ' . config('app.name', 'Our Site') . '. All rights reserved.</p>';
        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function buildFeaturedTemplate(Newsletter $newsletter, Collection $pages, ?string $unsubscribeToken): string
    {
        $html = [];

        $html[] = '<div style="max-width: 800px; margin: 0 auto; font-family: Arial, sans-serif;">';

        // First page as hero
        $featuredPage = $pages->first();
        if ($featuredPage) {
            $html[] = $this->renderHeroPage($featuredPage);
            $pages = $pages->slice(1);
        }

        // Rest as grid
        $html[] = '<div style="padding: 20px;">';
        $html[] = '<h2 style="color: #333; margin-bottom: 20px;">More Articles</h2>';

        foreach ($pages as $page) {
            $html[] = $this->renderCompactCard($page);
        }

        $html[] = '</div>';
        $html[] = $this->renderFooter($unsubscribeToken);
        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function renderHeroPage(Page $page): string
    {
        $url = url($page->slug);
        $html = [];

        $html[] = '<div style="position: relative; margin-bottom: 40px;">';

        if ($page->hero_image_id || $page->listing_image_id) {
            $imageId = $page->hero_image_id ?: $page->listing_image_id;
            $html[] = '<img src="' . url("/api/media/{$imageId}") . '" alt="' . htmlspecialchars($page->title) . '" style="width: 100%; height: 400px; object-fit: cover;">';
        }

        $html[] = '<div style="padding: 30px; background: white;">';
        $html[] = '<h1 style="margin: 0 0 15px 0; font-size: 32px;">';
        $html[] = '<a href="' . $url . '" style="color: #333; text-decoration: none;">';
        $html[] = htmlspecialchars($page->title);
        $html[] = '</a>';
        $html[] = '</h1>';

        if ($page->meta_description) {
            $html[] = '<p style="color: #666; font-size: 18px; line-height: 1.6; margin: 0 0 20px 0;">';
            $html[] = htmlspecialchars($page->meta_description);
            $html[] = '</p>';
        }

        $html[] = '<a href="' . $url . '" style="display: inline-block; padding: 12px 30px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; font-size: 16px;">Read Full Article</a>';
        $html[] = '</div>';
        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function renderCompactCard(Page $page): string
    {
        $url = url($page->slug);
        $html = [];

        $html[] = '<div style="margin-bottom: 20px; padding: 15px; border: 1px solid #eee; border-radius: 4px;">';
        $html[] = '<h3 style="margin: 0 0 10px 0; font-size: 18px;">';
        $html[] = '<a href="' . $url . '" style="color: #333; text-decoration: none;">';
        $html[] = htmlspecialchars($page->title);
        $html[] = '</a>';
        $html[] = '</h3>';

        if ($page->meta_description) {
            $html[] = '<p style="color: #666; font-size: 14px; margin: 0;">';
            $html[] = htmlspecialchars(substr($page->meta_description, 0, 100)) . '...';
            $html[] = '</p>';
        }
        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function buildSimpleTemplate(Newsletter $newsletter, Collection $pages, ?string $unsubscribeToken): string
    {
        $html = [];

        $html[] = '<div style="max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;">';
        $html[] = '<h2 style="color: #333;">' . htmlspecialchars($newsletter->title) . '</h2>';
        $html[] = '<ul style="list-style: none; padding: 0;">';

        foreach ($pages as $page) {
            $url = url($page->slug);
            $html[] = '<li style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee;">';
            $html[] = '<a href="' . $url . '" style="color: #007bff; text-decoration: none; font-weight: bold;">';
            $html[] = htmlspecialchars($page->title);
            $html[] = '</a>';

            if ($page->meta_description) {
                $html[] = '<p style="color: #666; margin: 5px 0 0 0; font-size: 14px;">';
                $html[] = htmlspecialchars(substr($page->meta_description, 0, 150));
                $html[] = '</p>';
            }
            $html[] = '</li>';
        }

        $html[] = '</ul>';
        $html[] = $this->renderFooter($unsubscribeToken);
        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function buildDefaultTemplate(Newsletter $newsletter, Collection $pages, ?string $unsubscribeToken): string
    {
        $html = [];

        // Header
        $html[] = '<div style="max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;">';
        $html[] = '<h1 style="color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px;">' .
            htmlspecialchars($newsletter->title) . '</h1>';

        // Pages
        foreach ($pages as $page) {
            $html[] = $this->renderPageCard($page);
        }

        // Footer
        $html[] = $this->renderFooter($unsubscribeToken);
        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function renderPageCard(Page $page): string
    {
        $url = url($page->slug);
        $html = [];

        $html[] = '<div style="margin-bottom: 30px; padding: 20px; border: 1px solid #eee; border-radius: 8px;">';

        // Image if available
        if ($page->listing_image_id || $page->hero_image_id) {
            $imageId = $page->listing_image_id ?: $page->hero_image_id;
            $html[] = '<img src="' . url("/api/media/{$imageId}") . '" alt="' . htmlspecialchars($page->title) . '" style="width: 100%; height: auto; border-radius: 4px; margin-bottom: 15px;">';
        }

        // Title
        $html[] = '<h2 style="margin: 0 0 10px 0;">';
        $html[] = '<a href="' . $url . '" style="color: #333; text-decoration: none;">';
        $html[] = htmlspecialchars($page->title);
        $html[] = '</a>';
        $html[] = '</h2>';

        // Meta
        $html[] = '<p style="color: #999; font-size: 14px; margin: 0 0 15px 0;">';
        if ($page->published_at) {
            $html[] = $page->published_at->format('F j, Y');
        }
        if ($page->authors && $page->authors->count() > 0) {
            $html[] = ' • By ' . htmlspecialchars($page->authors->first()->name);
        }
        $html[] = '</p>';

        // Description
        if ($page->meta_description || $page->listing_synopsis) {
            $description = $page->listing_synopsis ?: $page->meta_description;
            $html[] = '<p style="color: #666; line-height: 1.6; margin: 0 0 15px 0;">';
            $html[] = htmlspecialchars(substr($description, 0, 200));
            if (strlen($description) > 200) {
                $html[] = '...';
            }
            $html[] = '</p>';
        }

        // Read more button
        $html[] = '<a href="' . $url . '" style="display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px;">Read More</a>';

        $html[] = '</div>';

        return implode("\n", $html);
    }
}