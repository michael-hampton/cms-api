<?php
// src/Tests/Unit/Services/NewsletterPageBuilderServiceTest.php

namespace App\Tests\Unit\Services;

use App\Models\Author;
use App\Models\Category;
use App\Models\Newsletter;
use App\Models\Page;
use App\Models\PageAuthor;
use App\Models\PageCategory;
use App\Models\PageMetadata;
use App\Models\PageTag;
use App\Models\Tag;
use App\Repositories\PageRepository;
use App\Services\NewsletterPageBuilderService;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class NewsletterPageBuilderServiceTest extends RepositoryTestCase
{
    use CreatesTestData;

    private NewsletterPageBuilderService $service;

    public function test_gets_pages_for_automated_newsletter(): void
    {
        // Arrange
        $page1 = Page::create([
            'title' => 'Test Page 1',
            'slug' => 'test-page-1',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'site_id' => $this->siteId
        ]);

        $page2 = Page::create([
            'title' => 'Test Page 2',
            'slug' => 'test-page-2',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Weekly Digest',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'page_filters' => ['date_range_days' => 7],
            'max_pages' => 10,
            'content' => 'test-page-1,test-page-2'
        ]);

        // Act
        $pages = $this->service->getPagesForNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertCount(2, $pages);
        $this->assertEquals($page1->id, $pages->first()->id);
    }

    public function test_filters_pages_by_category(): void
    {
        // Arrange
        $category = Category::create([
            'name' => 'News',
            'slug' => 'news',
            'site_id' => $this->siteId
        ]);

        $page1 = Page::create([
            'title' => 'News Page',
            'slug' => 'news-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        PageCategory::create([
            'page_id' => $page1->id,
            'category_id' => $category->id,
            'site_id' => $this->siteId
        ]);

        $page2 = Page::create([
            'title' => 'Other Page',
            'slug' => 'other-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'News Digest',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'page_filters' => [
                'categories' => [$category->id],
                'date_range_days' => 7
            ],
            'content' => 'news-page'
        ]);
        // Act
        $pages = $this->service->getPagesForNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertCount(1, $pages);
        $this->assertEquals($page1->id, $pages->first()->id);
    }

    public function test_filters_pages_by_tag(): void
    {
        // Arrange
        $tag = Tag::create([
            'name' => 'Technology',
            'slug' => 'technology',
            'site_id' => $this->siteId
        ]);

        $page1 = Page::create([
            'title' => 'Tech Page',
            'slug' => 'tech-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        PageTag::create([
            'page_id' => $page1->id,
            'tag_id' => $tag->id,
            'site_id' => $this->siteId
        ]);

        $page2 = Page::create([
            'title' => 'Other Page',
            'slug' => 'other-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Tech Digest',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'page_filters' => [
                'tags' => [$tag->id],
                'date_range_days' => 7
            ],
            'content' => 'tech-page'
        ]);

        // Act
        $pages = $this->service->getPagesForNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertCount(1, $pages);
        $this->assertEquals($page1->id, $pages->first()->id);
    }

    public function test_filters_pages_by_page_type(): void
    {
        // Arrange
        $page1 = Page::create([
            'title' => 'Article Page',
            'slug' => 'article-page',
            'status' => 'published',
            'page_type' => 'article',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $page2 = Page::create([
            'title' => 'Review Page',
            'slug' => 'review-page',
            'status' => 'published',
            'page_type' => 'review',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Articles Digest',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'page_filters' => [
                'page_types' => ['article'],
                'date_range_days' => 7
            ],
            'content' => 'article-page'
        ]);

        // Act
        $pages = $this->service->getPagesForNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertCount(1, $pages);
        $this->assertEquals($page1->id, $pages->first()->id);
    }

    public function test_filters_pages_since_last_send(): void
    {
        // Arrange
        $oldPage = Page::create([
            'title' => 'Old Page',
            'slug' => 'old-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s', strtotime('-10 days')),
            'site_id' => $this->siteId
        ]);

        $newPage = Page::create([
            'title' => 'New Page',
            'slug' => 'new-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Weekly Digest',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'last_sent' => date('Y-m-d H:i:s', strtotime('-3 days')),
            'content' => 'old-page,new-page'
        ]);

        // Act
        $pages = $this->service->getPagesForNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertCount(1, $pages);
        $this->assertEquals($newPage->id, $pages->first()->id);
    }

    public function test_limits_pages_by_max_pages(): void
    {
        // Arrange
        for ($i = 1; $i <= 5; $i++) {
            Page::create([
                'title' => "Page {$i}",
                'slug' => "page-{$i}",
                'status' => 'published',
                'published_at' => date('Y-m-d H:i:s', strtotime("-{$i} days")),
                'site_id' => $this->siteId
            ]);
        }

        $newsletter = Newsletter::create([
            'title' => 'Limited Digest',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'page_filters' => ['date_range_days' => 10],
            'max_pages' => 3,
            'content' => 'page-1,page-2,page-3'
        ]);

        // Act
        $pages = $this->service->getPagesForNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertCount(3, $pages);
    }

    public function test_filters_featured_pages_only(): void
    {
        // Arrange
        $featuredPage = Page::create([
            'title' => 'Featured Page',
            'slug' => 'featured-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        PageMetadata::create([
            'page_id' => $featuredPage->id,
            'featured' => true,
            'site_id' => $this->siteId
        ]);

        $regularPage = Page::create([
            'title' => 'Regular Page',
            'slug' => 'regular-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Featured Digest',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'page_filters' => [
                'featured_only' => true,
                'date_range_days' => 7
            ],
            'content' => 'featured-page'
        ]);

        // Act
        $pages = $this->service->getPagesForNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertCount(1, $pages);
        $this->assertEquals($featuredPage->id, $pages->first()->id);
    }

    public function test_sorts_pages_by_custom_field(): void
    {
        // Arrange
        $page1 = Page::create([
            'title' => 'Zulu Page',
            'slug' => 'zulu-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'site_id' => $this->siteId
        ]);

        $page2 = Page::create([
            'title' => 'Alpha Page',
            'slug' => 'alpha-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Sorted Digest',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'page_filters' => ['date_range_days' => 7],
            'sort_by' => 'title',
            'sort_order' => 'asc',
            'content' => 'sorted-pages'
        ]);

        // Act
        $pages = $this->service->getPagesForNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertCount(2, $pages);
        $this->assertEquals('Alpha Page', $pages->first()->title);
        $this->assertEquals('Zulu Page', $pages->last()->title);
    }

    public function test_excludes_draft_pages(): void
    {
        // Arrange
        $publishedPage = Page::create([
            'title' => 'Published Page',
            'slug' => 'published-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $draftPage = Page::create([
            'title' => 'Draft Page',
            'slug' => 'draft-page',
            'status' => 'draft',
            'published_at' => null,
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Published Only',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'page_filters' => ['date_range_days' => 7],
            'content' => 'published-page'
        ]);

        // Act
        $pages = $this->service->getPagesForNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertCount(1, $pages);
        $this->assertEquals($publishedPage->id, $pages->first()->id);
    }

    public function test_builds_default_template_html(): void
    {
        // Arrange
        $page = Page::create([
            'title' => 'Test Article',
            'slug' => 'test-article',
            'status' => 'published',
            'meta_description' => 'This is a test article description',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Weekly Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test-token'
        ]);

        $pages = collect([$page]);

        // Act
        $html = $this->service->buildNewsletterHtml($newsletter, $pages, 'test-token');

        // Assert
        $this->assertStringContainsString('Test Article', $html);
        $this->assertStringContainsString('This is a test article description', $html);
        $this->assertStringContainsString('test-token', $html);
    }

    public function test_builds_digest_template_html(): void
    {
        // Arrange
        $page1 = Page::create([
            'title' => 'Article 1',
            'slug' => 'article-1',
            'status' => 'published',
            'meta_description' => 'Description 1',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $page2 = Page::create([
            'title' => 'Article 2',
            'slug' => 'article-2',
            'status' => 'published',
            'meta_description' => 'Description 2',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Daily Digest',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'digest',
            'interval' => Newsletter::INTERVAL_DAILY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => '2 latest articles'
        ]);

        $pages = collect([$page1, $page2]);

        // Act
        $html = $this->service->buildNewsletterHtml($newsletter, $pages);

        // Assert
        $this->assertStringContainsString('2 latest articles', $html);
        $this->assertStringContainsString('Article 1', $html);
        $this->assertStringContainsString('Article 2', $html);
    }

    public function test_builds_featured_template_html(): void
    {
        // Arrange
        $featuredPage = Page::create([
            'title' => 'Featured Article',
            'slug' => 'featured-article',
            'status' => 'published',
            'meta_description' => 'This is the featured article',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $page2 = Page::create([
            'title' => 'Article 2',
            'slug' => 'article-2',
            'status' => 'published',
            'meta_description' => 'Second article',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $page3 = Page::create([
            'title' => 'Article 3',
            'slug' => 'article-3',
            'status' => 'published',
            'meta_description' => 'Third article',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Featured Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'featured',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'featured-content'
        ]);

        $pages = collect([$featuredPage, $page2, $page3]);

        // Act
        $html = $this->service->buildNewsletterHtml($newsletter, $pages, 'test-token');

        // Assert
        // Featured page should be displayed as hero
        $this->assertStringContainsString('Featured Article', $html);
        $this->assertStringContainsString('This is the featured article', $html);

        // Other pages should be in compact cards
        $this->assertStringContainsString('More Articles', $html);
        $this->assertStringContainsString('Article 2', $html);
        $this->assertStringContainsString('Article 3', $html);

        // Should contain unsubscribe link
        $this->assertStringContainsString('test-token', $html);
    }

    public function test_builds_simple_template_html(): void
    {
        // Arrange
        $page1 = Page::create([
            'title' => 'Simple Article 1',
            'slug' => 'simple-article-1',
            'status' => 'published',
            'meta_description' => 'Description for simple article 1',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $page2 = Page::create([
            'title' => 'Simple Article 2',
            'slug' => 'simple-article-2',
            'status' => 'published',
            'meta_description' => 'Description for simple article 2',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Simple Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'simple',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'simple-content'
        ]);

        $pages = collect([$page1, $page2]);

        // Act
        $html = $this->service->buildNewsletterHtml($newsletter, $pages, 'simple-token');

        // Assert
        $this->assertStringContainsString('Simple Newsletter', $html);
        $this->assertStringContainsString('Simple Article 1', $html);
        $this->assertStringContainsString('Simple Article 2', $html);
        $this->assertStringContainsString('Description for simple article 1', $html);
        $this->assertStringContainsString('simple-token', $html);
        $this->assertStringContainsString('<ul', $html);
        $this->assertStringContainsString('<li', $html);
    }

    public function test_default_template_includes_page_images(): void
    {
        // Arrange
        $page = Page::create([
            'title' => 'Article with Image',
            'slug' => 'article-with-image',
            'status' => 'published',
            'meta_description' => 'Article description',
            'listing_image_id' => 123,
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Image Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'image-content'
        ]);

        $pages = collect([$page]);

        // Act
        $html = $this->service->buildNewsletterHtml($newsletter, $pages);

        // Assert
        $this->assertStringContainsString('/api/media/123', $html);
        $this->assertStringContainsString('<img', $html);
    }

    public function test_default_template_includes_author_info(): void
    {
        // Arrange
        $author = Author::create([
            'name' => 'John Doe',
            'slug' => 'john-doe',
            'site_id' => $this->siteId
        ]);

        $page = Page::create([
            'title' => 'Article by John',
            'slug' => 'article-by-john',
            'status' => 'published',
            'meta_description' => 'Article description',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        PageAuthor::create([
            'page_id' => $page->id,
            'author_id' => $author->id,
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Author Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'author-content'
        ]);

        // Reload page with relationships
        $page = Page::with(['authors'])->find($page->id);
        $pages = collect([$page]);

        // Act
        $html = $this->service->buildNewsletterHtml($newsletter, $pages);

        // Assert
        $this->assertStringContainsString('John Doe', $html);
        $this->assertStringContainsString('By', $html);
    }

    public function test_footer_includes_unsubscribe_and_manage_links(): void
    {
        // Arrange
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);

        $pages = collect([$page]);

        // Act
        $html = $this->service->buildNewsletterHtml($newsletter, $pages, 'unique-token-123');

        // Assert
        $this->assertStringContainsString('/member/subscriptions/unsubscribe/unique-token-123', $html);
        $this->assertStringContainsString('/member/subscriptions/manage/unique-token-123', $html);
        $this->assertStringContainsString('Unsubscribe', $html);
        $this->assertStringContainsString('Manage Preferences', $html);
    }

    public function test_footer_without_unsubscribe_token(): void
    {
        // Arrange
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);

        $pages = collect([$page]);

        // Act
        $html = $this->service->buildNewsletterHtml($newsletter, $pages, null);

        // Assert
        $this->assertStringNotContainsString('/member/subscriptions/unsubscribe/', $html);
        $this->assertStringNotContainsString('Manage Preferences', $html);
        $this->assertStringContainsString('You received this email', $html);
    }

    public function test_returns_empty_collection_for_manual_newsletter(): void
    {
        // Arrange
        $newsletter = Newsletter::create([
            'title' => 'Manual Newsletter',
            'content' => json_encode([['type' => 'paragraph', 'content' => 'Manual content']]),
            'content_type' => Newsletter::CONTENT_TYPE_MANUAL,
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId
        ]);

        // Act
        $pages = $this->service->getPagesForNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertCount(0, $pages);
    }

    public function test_featured_template_handles_single_page(): void
    {
        // Arrange
        $page = Page::create([
            'title' => 'Single Featured Article',
            'slug' => 'single-featured',
            'status' => 'published',
            'meta_description' => 'Only article',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Featured Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'featured',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'featured'
        ]);

        $pages = collect([$page]);

        // Act
        $html = $this->service->buildNewsletterHtml($newsletter, $pages);

        // Assert
        $this->assertStringContainsString('Single Featured Article', $html);
        // Should not have "More Articles" section when only one page
        $this->assertStringContainsString('More Articles', $html); // Section header is always present
    }

    public function test_handles_page_with_long_description(): void
    {
        // Arrange
        $longDescription = str_repeat('This is a very long description. ', 50);

        $page = Page::create([
            'title' => 'Long Description Page',
            'slug' => 'long-description',
            'status' => 'published',
            'meta_description' => $longDescription,
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);

        $pages = collect([$page]);

        // Act
        $html = $this->service->buildNewsletterHtml($newsletter, $pages);
        // Assert
        // Description should be truncated to 200 characters in default template
        $this->assertStringContainsString('...', $html);
        $this->assertLessThan(strlen($longDescription), strlen($html));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NewsletterPageBuilderService(new PageRepository());
    }
}