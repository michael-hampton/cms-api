<?php

namespace App\Tests\Unit\Parsers;

use App\Framework\Authorization\MemberAuth;
use App\Framework\Support\SiteContext;
use App\Models\Member;
use App\Models\Model;
use App\Models\Page;
use App\Models\PageMetadata;
use App\Models\Subscription;
use App\Models\SubscriptionWindow;
use App\Parsers\PageGridBlockParser;
use App\Repositories\Cms\Pages\PageRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;

class PageGridPrivatePageTest extends FunctionalTestCase
{
    use CreatesTestData;

    private PageGridBlockParser $parser;
    private Page $privatePage;
    private Page $publicPage;
    private $pageRepository;

    public function testPublicPageShowsNormalButton()
    {
        // Not logged in
        MemberAuth::logout();

        $this->setPublicPageExpectations();

        $data = [
            'title' => 'Page Grid',
            'layout' => 'grid',
            'columns' => 3,
            'showActions' => true,
            'pages' => [
                [
                    'title' => 'Public Page',
                    'slug' => 'public-page',
                    'excerpt' => 'This is public',
                    'image' => ['src' => '/image.jpg', 'alt' => 'Image'],
                    'actions' => [
                        ['text' => 'Read More', 'url' => '/public-page', 'style' => 'primary']
                    ]
                ]
            ]
        ];

        $parsed = $this->parser->parse($data);
        $html = $this->parser->generateHtml($parsed);

        // Should show normal "Read More" button
        $this->assertStringContainsString('Read More', $html);
        $this->assertStringNotContainsString('Subscribe to Access', $html);
        $this->assertStringNotContainsString('page-card-private', $html);
    }

    private function setPublicPageExpectations()
    {
        $this->pageRepository->shouldReceive('findBySlug')->andReturn($this->publicPage);
        $this->pageRepository->shouldReceive('getMetaDataForPage')->with($this->publicPage->id)->andReturn($this->publicPage->metadata);

    }

    public function testPrivatePageShowsSubscribeButtonWhenNotLoggedIn()
    {
        // Not logged in
        MemberAuth::logout();

        $this->setPrivatePageExpectations();

        $data = [
            'title' => 'Page Grid',
            'layout' => 'grid',
            'columns' => 3,
            'showActions' => true,
            'pages' => [
                [
                    'title' => 'Private Page',
                    'slug' => 'private-page',
                    'excerpt' => 'This is private',
                    'image' => ['src' => '/image.jpg', 'alt' => 'Image'],
                    'actions' => [
                        ['text' => 'Read More', 'url' => '/private-page', 'style' => 'primary']
                    ]
                ]
            ]
        ];

        $parsed = $this->parser->parse($data);
        $html = $this->parser->generateHtml($parsed);

        // Should show "Subscribe to Access" button
        $this->assertStringContainsString('Subscribe to Access', $html);
        $this->assertStringContainsString('btn-subscribe-required', $html);
        $this->assertStringContainsString('page-card-private', $html);
        $this->assertStringContainsString('showSubscriptionModal()', $html);

        // Should NOT show normal "Read More" button
        $this->assertStringNotContainsString('Read More', $html);
    }

    private function setPrivatePageExpectations()
    {
        $this->pageRepository->shouldReceive('findBySlug')->andReturn($this->privatePage);
        $this->pageRepository->shouldReceive('getMetaDataForPage')->with($this->privatePage->id)->andReturn($this->privatePage->metadata);
    }

    public function testPrivatePageShowsNormalButtonWhenLoggedIn()
    {
        $this->setPrivatePageExpectations();

        // Create and log in member
        $member = Member::create([
            'email' => 'test@example.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'first_name' => 'Test',
            'last_name' => 'User',
            'is_active' => true,
            'email_verified_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        MemberAuth::login($member);

        $subscription = $this->createSubscription($member);
        $this->createSubscriptionWindow($member, $subscription);

        $data = [
            'title' => 'Page Grid',
            'layout' => 'grid',
            'columns' => 3,
            'showActions' => true,
            'pages' => [
                [
                    'title' => 'Private Page',
                    'slug' => 'private-page',
                    'excerpt' => 'This is private',
                    'image' => ['src' => '/image.jpg', 'alt' => 'Image'],
                    'actions' => [
                        ['text' => 'Read More', 'url' => '/private-page', 'style' => 'primary']
                    ]
                ]
            ]
        ];

        $parsed = $this->parser->parse($data);
        $html = $this->parser->generateHtml($parsed, $this->siteId);

        // Should show normal "Read More" button
        $this->assertStringContainsString('Read More', $html);

        // Should NOT show subscribe button or private styling
        $this->assertStringNotContainsString('Subscribe to Access', $html);
        $this->assertStringNotContainsString('page-card-private', $html);
    }

    private function createSubscription(Member $member): Model
    {
        return Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'status' => 'expired',
            'type' => 'paid',
            'start_date' => now_datetime()->format('Y-m-d H:i:s'),
            'end_date' => now_datetime()->addMonths(2)->format('Y-m-d H:i:s'),
            'plan_name' => 'Test',
            'price' => 29.99,
            'currency' => 'USD'
        ]);
    }

    private function createSubscriptionWindow(Member $member, Subscription $subscription): Model
    {
        return SubscriptionWindow::create([
            'member_id' => $member->id,
            'subscription_id' => $subscription->id,
            'site_id' => $this->siteId,
            'window_start' => now_datetime()->subDays(6)->format('Y-m-d H:i:s'),
            'window_end' => now_datetime()->addMonths(2)->format('Y-m-d H:i:s'),
            'type' => 'paid'
        ]);
    }

    public function testPrivatePageHasOverlayAndBadge()
    {
        $this->setPrivatePageExpectations();

        MemberAuth::logout();

        $data = [
            'title' => 'Page Grid',
            'layout' => 'grid',
            'showImage' => true,
            'pages' => [
                [
                    'title' => 'Private Page',
                    'slug' => 'private-page',
                    'excerpt' => 'This is private',
                    'image' => ['src' => '/image.jpg', 'alt' => 'Image']
                ]
            ]
        ];

        $parsed = $this->parser->parse($data);
        $html = $this->parser->generateHtml($parsed);

        // Should have overlay and badge
        $this->assertStringContainsString('private-overlay', $html);
        $this->assertStringContainsString('private-badge', $html);
        $this->assertStringContainsString('🔒 Members Only', $html);
    }

    public function testPrivatePageExcerptIsFaded()
    {
        $this->setPrivatePageExpectations();
        MemberAuth::logout();

        $data = [
            'title' => 'Page Grid',
            'layout' => 'grid',
            'showExcerpt' => true,
            'pages' => [
                [
                    'title' => 'Private Page',
                    'slug' => 'private-page',
                    'excerpt' => 'This is a long private excerpt that should be faded out'
                ]
            ]
        ];

        $parsed = $this->parser->parse($data);
        $html = $this->parser->generateHtml($parsed);

        // Should have faded excerpt styling
        $this->assertStringContainsString('page-excerpt-faded', $html);
        $this->assertStringContainsString('page-content-faded', $html);
    }

    public function testPrivatePageTitleIsNotLinkedWhenNotLoggedIn()
    {
        $this->setPrivatePageExpectations();
        MemberAuth::logout();

        $data = [
            'title' => 'Page Grid',
            'layout' => 'grid',
            'pages' => [
                [
                    'title' => 'Private Page',
                    'slug' => 'private-page',
                    'excerpt' => 'This is private'
                ]
            ]
        ];

        $parsed = $this->parser->parse($data);
        $html = $this->parser->generateHtml($parsed);

        // Title should be plain text, not a link
        $this->assertStringContainsString('Private Page', $html);

        // Check that the title is NOT wrapped in an anchor tag by looking for the pattern
        $this->assertStringNotContainsString('<a href="/private-page">Private Page</a>', $html);
    }

    public function testPrivatePageTitleIsLinkedWhenLoggedIn()
    {
        $this->setPrivatePageExpectations();
        $member = Member::create([
            'site_id' => $this->siteId,
            'email' => 'test2@example.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'first_name' => 'Test',
            'last_name' => 'User',
            'is_active' => true,
            'email_verified_at' => date('Y-m-d H:i:s')
        ]);

        MemberAuth::login($member);
        $subscription = $this->createSubscription($member);
        $this->createSubscriptionWindow($member, $subscription);

        $data = [
            'title' => 'Page Grid',
            'layout' => 'grid',
            'pages' => [
                [
                    'title' => 'Private Page',
                    'slug' => 'private-page',
                    'excerpt' => 'This is private'
                ]
            ]
        ];

        $parsed = $this->parser->parse($data);
        $html = $this->parser->generateHtml($parsed);

        // Title should be a clickable link
        $this->assertStringContainsString('<a href=', $html);
        $this->assertStringContainsString('private-page', $html);
    }

    public function testPageWithoutMetadataIsTreatedAsPublic()
    {
        MemberAuth::logout();

        // Create page without metadata
        $pageWithoutMeta = Page::create([
            'site_id' => SiteContext::getId(),
            'title' => 'No Metadata Page',
            'slug' => 'no-metadata-page',
            'subtitle' => 'Content',
            'status' => 'published'
        ]);

        $this->pageRepository->shouldReceive('findBySlug')->andReturn($pageWithoutMeta);
        $this->pageRepository->shouldReceive('getMetaDataForPage')->with($pageWithoutMeta->id)->andReturn(null);

        $data = [
            'title' => 'Page Grid',
            'layout' => 'grid',
            'showActions' => true,
            'pages' => [
                [
                    'title' => 'No Metadata Page',
                    'slug' => 'no-metadata-page',
                    'excerpt' => 'No metadata',
                    'actions' => [
                        ['text' => 'Read More', 'url' => '/no-metadata-page', 'style' => 'primary']
                    ]
                ]
            ]
        ];

        $parsed = $this->parser->parse($data);
        $html = $this->parser->generateHtml($parsed);

        // Should be treated as public
        $this->assertStringContainsString('Read More', $html);
        $this->assertStringNotContainsString('Subscribe to Access', $html);
        $this->assertStringNotContainsString('page-card-private', $html);
    }

    public function testPageGridRespectsHistoricalSubscriptionWindows()
    {
        $member = $this->createMember();
        MemberAuth::login($member);

        // Create expired subscription Jan 1-31
        $subscription = $this->createSubscription($member);

        // Create window
        $this->createSubscriptionWindow($member, $subscription);

        // Page published during window
        $pageInWindow = $this->createPageWithAccess('premium', now_datetime()->subDays(2)->format('Y-m-d H:i:s'));

        // Page published after window
        $pageAfterWindow = $this->createPageWithAccess('premium', now_datetime()->subDays(2)->format('Y-m-d H:i:s'));

        $this->pageRepository->shouldReceive('findBySlug')
            ->with($pageInWindow->slug, $this->siteId)
            ->andReturn($pageInWindow);

        $this->pageRepository->shouldReceive('getMetaDataForPage')
            ->with($pageInWindow->id)
            ->andReturn($pageInWindow->metadata);

        $data = [
            'title' => 'Page Grid',
            'layout' => 'grid',
            'showActions' => true,
            'pages' => [
                [
                    'title' => $pageInWindow->title,
                    'slug' => $pageInWindow->slug,
                    'excerpt' => 'Should be accessible',
                    'actions' => [
                        ['text' => 'Read More', 'url' => '/' . $pageInWindow->slug, 'style' => 'primary']
                    ]
                ]
            ]
        ];

        $parsed = $this->parser->parse($data);
        $html = $this->parser->generateHtml($parsed, 1, $this->siteId);

        // Should show normal read button (has historical access)
        $this->assertStringContainsString('Read More', $html);
        $this->assertStringNotContainsString('Subscribe to Access', $html);
    }

    private function createPageWithAccess(string $accessLevel, ?string $publishedAt = null): Page
    {
        $page = Page::create([
            'site_id' => $this->siteId,
            'title' => 'Test Page ' . uniqid(),
            'slug' => 'test-' . uniqid(),
            'status' => 'published',
            'published_at' => $publishedAt ?? date('Y-m-d H:i:s')
        ]);

        PageMetadata::create([
            'page_id' => $page->id,
            'visibility' => $accessLevel
        ]);

        return $page->load(['metadata']);
    }

    public function testPageGridShowsSubscribeButtonForContentAfterSubscription()
    {
        $member = $this->createMember();
        MemberAuth::login($member);

        // Create expired subscription that ended in January
        $subscription = $this->createSubscription($member);

        $this->createSubscriptionWindow($member, $subscription);

        // Page published in February (after subscription)
        $pageAfter = $this->createPageWithAccess('premium', '2025-02-15 12:00:00');

        $this->pageRepository->shouldReceive('findBySlug')
            ->with($pageAfter->slug, $this->siteId)
            ->andReturn($pageAfter);

        $this->pageRepository->shouldReceive('getMetaDataForPage')
            ->with($pageAfter->id)
            ->andReturn($pageAfter->metadata);

        $data = [
            'title' => 'Page Grid',
            'layout' => 'grid',
            'showActions' => true,
            'pages' => [
                [
                    'title' => $pageAfter->title,
                    'slug' => $pageAfter->slug,
                    'excerpt' => 'Should require resubscription',
                    'actions' => [
                        ['text' => 'Read More', 'url' => '/' . $pageAfter->slug, 'style' => 'primary']
                    ]
                ]
            ]
        ];

        $parsed = $this->parser->parse($data);
        $html = $this->parser->generateHtml($parsed, 1, $this->siteId);

        // Should show subscribe button (no historical access)
        $this->assertStringContainsString('Subscribe to Access', $html);
        $this->assertStringNotContainsString('Read More', $html);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Create test pages
        $this->publicPage = Page::create([
            'site_id' => SiteContext::getId(),
            'title' => 'Public Page',
            'slug' => 'public-page',
            'subtitle' => 'Public content',
            'status' => 'published'
        ]);

        PageMetadata::create([
            'page_id' => $this->publicPage->id,
            'visibility' => 'free'
        ]);

        $this->privatePage = Page::create([
            'site_id' => $this->siteId,
            'title' => 'Private Page',
            'slug' => 'private-page',
            'subtitle' => 'Private content',
            'status' => 'published',
            'published_at' => now_datetime()->subDays(5)->format('Y-m-d H:i:s')
        ]);

        PageMetadata::create([
            'page_id' => $this->privatePage->id,
            'visibility' => 'premium'
        ]);

        $this->pageRepository = Mockery::mock(PageRepository::class);

        $this->parser = new PageGridBlockParser($this->pageRepository);
    }

    protected function tearDown(): void
    {
        // Clean up
        if (isset($this->publicPage)) {
            PageMetadata::where('page_id', $this->publicPage->id)->delete();
            $this->publicPage->delete();
        }

        if (isset($this->privatePage)) {
            PageMetadata::where('page_id', $this->privatePage->id)->delete();
            $this->privatePage->delete();
        }

        //Member::where('email', 'test@example.com')->delete();
        //Member::where('email', 'test2@example.com')->delete();

        MemberAuth::logout();

        parent::tearDown();
    }
}