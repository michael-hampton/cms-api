<?php

namespace App\Tests\Unit\Parsers;

use App\Framework\Authorization\MemberAuth;
use App\Framework\Support\SiteContext;
use App\Models\Member;
use App\Models\Page;
use App\Models\PageMetadata;
use App\Parsers\PageGridBlockParser;
use App\Repositories\PageRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class PageGridPrivatePageTest extends FunctionalTestCase
{
    private PageGridBlockParser $parser;
    private Page $privatePage;
    private Page $publicPage;
    private $pageRepository;

    public function testPublicPageShowsNormalButton()
    {
        // Not logged in
        MemberAuth::logout();

        $this->setpublicPageExpectations();

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

        // Should show normal "Read More" button
        $this->assertStringContainsString('Read More', $html);

        // Should NOT show subscribe button or private styling
        $this->assertStringNotContainsString('Subscribe to Access', $html);
        $this->assertStringNotContainsString('page-card-private', $html);
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

    public function testPrivatePageStylesIncluded()
    {
        $this->setPrivatePageExpectations();

        MemberAuth::logout();

        $data = [
            'title' => 'Page Grid',
            'layout' => 'grid',
            'pages' => [
                [
                    'title' => 'Private Page',
                    'slug' => 'private-page'
                ]
            ]
        ];

        $parsed = $this->parser->parse($data);
        $html = $this->parser->generateHtml($parsed);

        // Should include CSS styles for private pages
        $this->assertStringContainsString('<style>', $html);
        $this->assertStringContainsString('.page-card-private', $html);
        $this->assertStringContainsString('.private-overlay', $html);
        $this->assertStringContainsString('.private-badge', $html);
        $this->assertStringContainsString('.btn-subscribe-required', $html);
        $this->assertStringContainsString('backdrop-filter: blur', $html);
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
            'visibility' => 'public'
        ]);

        $this->privatePage = Page::create([
            'site_id' => SiteContext::getId(),
            'title' => 'Private Page',
            'slug' => 'private-page',
            'subtitle' => 'Private content',
            'status' => 'published'
        ]);

        PageMetadata::create([
            'page_id' => $this->privatePage->id,
            'visibility' => 'private'
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

        Member::where('email', 'test@example.com')->delete();
        Member::where('email', 'test2@example.com')->delete();

        MemberAuth::logout();

        parent::tearDown();
    }
}