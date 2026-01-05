<?php

namespace App\Tests\Functional;

use App\Models\Newsletter;
use App\Models\Site;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class NewsletterWebControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexDisplaysPublishedNewsletters(): void
    {
        $newsletter1 = Newsletter::create([
            'site_id' => $this->siteId,
            'title' => 'Newsletter 1',
            'content' => 'Preview text 1',
            'status' => 'published',
            'created_at' => date('Y-m-d H:i:s'),
            'interval' => 'monthly',
            'last_sent' => now_datetime()->subMonths(1)->format('Y-m-d H:i:s'),
            'active' => true
        ]);

        $newsletter2 = Newsletter::create([
            'site_id' => $this->siteId,
            'title' => 'Newsletter 2',
            'content' => 'Preview text 2',
            'status' => 'published',
            'created_at' => date('Y-m-d H:i:s'),
            'interval' => 'weekly',
            'last_sent' => now_datetime()->subMonths(1)->format('Y-m-d H:i:s'),
            'active' => true
        ]);

        $response = $this->getForSite('/newsletters');

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();

        $this->assertStringContainsString('Newsletter 1', $content);
        $this->assertStringContainsString('Newsletter 2', $content);
        $this->assertStringContainsString('Our Newsletters', $content);
    }

    public function testIndexDoesNotDisplayDraftNewsletters(): void
    {
        Newsletter::create([
            'site_id' => $this->siteId,
            'title' => 'Published Newsletter',
            'status' => 'published',
            'created_at' => date('Y-m-d H:i:s'),
            'content' => 'test',
            'interval' => 'monthly',
            'last_sent' => now_datetime()->subMonths(1)->format('Y-m-d H:i:s'),
            'active' => true
        ]);

        Newsletter::create([
            'site_id' => $this->siteId,
            'title' => 'Draft Newsletter',
            'status' => 'draft',
            'created_at' => date('Y-m-d H:i:s'),
            'content' => 'test',
            'interval' => 'monthly',
            'active' => true
        ]);

        $response = $this->getForSite('/newsletters');

        $content = $response->getContent();
        $this->assertStringContainsString('Published Newsletter', $content);
        $this->assertStringNotContainsString('Draft Newsletter', $content);
    }

    public function testIndexShowsEmptyStateWhenNoNewsletters(): void
    {
        $response = $this->getForSite('/newsletters');

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();

        $this->assertStringContainsString('No Newsletters Yet', $content);
    }

    public function testShowDisplaysNewsletterContent(): void
    {
        $newsletter = Newsletter::create([
            'site_id' => $this->siteId,
            'title' => 'Test Newsletter',
            'preview_text' => 'Preview text',
            'content' => '<p>Newsletter content</p>',
            'status' => 'published',
            'created_at' => date('Y-m-d H:i:s'),
            'interval' => 'monthly'
        ]);

        $response = $this->getForSite('/newsletters/' . $newsletter->id);

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();

        $this->assertStringContainsString('Test Newsletter', $content);
    }

    public function testShowReturns404ForNonExistentNewsletter(): void
    {
        $response = $this->getForSite('/newsletters/99999');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testShowReturns403ForDifferentSite(): void
    {
        $otherSite = Site::create(['name' => 'Other Site', 'slug' => 'other-site']);

        $newsletter = Newsletter::create([
            'site_id' => $otherSite->id,
            'title' => 'Other Site Newsletter',
            'status' => 'published',
            'created_at' => date('Y-m-d H:i:s'),
            'content' => '<p>Content</p>',
            'interval' => 'monthly'
        ]);

        $response = $this->getForSite('/newsletters/' . $newsletter->id);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testArchiveDisplaysAllNewsletters(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            Newsletter::create([
                'site_id' => $this->siteId,
                'title' => "Newsletter $i",
                'status' => 'published',
                'created_at' => date('Y-m-d H:i:s', strtotime("-$i days")),
                'content' => 'test',
                'interval' => 'monthly',
                'last_sent' => now_datetime()->subMonths(1)->format('Y-m-d H:i:s'),
                'active' => true
            ]);
        }

        $response = $this->getForSite('/newsletters/archive');

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();

        $this->assertStringContainsString('Newsletter Archive', $content);
        $this->assertStringContainsString('Newsletter 1', $content);
        $this->assertStringContainsString('Newsletter 5', $content);
    }

    public function testArchiveShowsEmptyStateWhenNoNewsletters(): void
    {
        $response = $this->getForSite('/newsletters/archive');

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();

        $this->assertStringContainsString('No Archived Newsletters', $content);
    }

    public function testShowWithUnsubscribeToken(): void
    {
        $newsletter = Newsletter::create([
            'site_id' => $this->siteId,
            'title' => 'Test Newsletter',
            'content' => '<p>Content</p>',
            'status' => 'published',
            'created_at' => date('Y-m-d H:i:s'),
            'interval' => 'monthly'
        ]);

        $token = bin2hex(random_bytes(16));

        $response = $this->getForSite('/newsletters/' . $newsletter->id . '?token=' . $token);

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();

        $this->assertStringContainsString('Unsubscribe from future emails', $content);
        $this->assertStringContainsString($token, $content);
    }

    public function testDownloadPdfReturnsSuccessfully(): void
    {
        $newsletter = Newsletter::create([
            'site_id' => $this->siteId,
            'title' => 'Test Newsletter for PDF',
            'content' => 'This is test content',
            'content_type' => Newsletter::CONTENT_TYPE_MANUAL,
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'last_sent' => now()
        ]);

        $response = $this->getForSite("newsletters/{$newsletter->id}/download");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('application/pdf', $response->getHeader('Content-Type') ?? '');

        // Cleanup
        $newsletter->delete();
    }

    public function testDownloadPdfReturns404ForNonExistentNewsletter(): void
    {
        $response = $this->getForSite('newsletters/99999/download');

        $this->assertEquals(404, $response->getStatusCode());
    }
}