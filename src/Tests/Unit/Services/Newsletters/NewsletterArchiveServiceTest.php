<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Models\Newsletter;
use App\Models\NewsletterSend;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Newsletters\NewsletterSendRepository;
use App\Services\Newsletter\NewsletterArchiveService;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class NewsletterArchiveServiceTest extends FunctionalTestCase
{
    private NewsletterArchiveService $service;

    public function testSearchNewslettersByTitle(): void
    {
        // Create newsletters
        Newsletter::create([
            'title' => 'Tech Weekly Update',
            'site_id' => $this->siteId,
            'active' => true,
            'last_sent' => date('Y-m-d H:i:s'),
            'interval' => 'weekly',
            'content' => '{}'
        ]);

        Newsletter::create([
            'title' => 'Business Monthly Digest',
            'site_id' => $this->siteId,
            'active' => true,
            'last_sent' => date('Y-m-d H:i:s'),
            'interval' => 'monthly',
            'content' => '{}'
        ]);

        $result = $this->service->searchNewsletters($this->siteId, [
            'search' => 'Tech'
        ]);

        $this->assertCount(1, $result['newsletters']);
        $this->assertEquals('Tech Weekly Update', $result['newsletters']->first()->title);
    }

    public function testSearchNewslettersByContent(): void
    {
        Newsletter::create([
            'title' => 'Newsletter 1',
            'content' => 'Important announcement about product launches',
            'site_id' => $this->siteId,
            'active' => true,
            'last_sent' => date('Y-m-d H:i:s'),
            'interval' => 'weekly'
        ]);

        Newsletter::create([
            'title' => 'Newsletter 2',
            'content' => 'Regular updates and news',
            'site_id' => $this->siteId,
            'active' => true,
            'last_sent' => date('Y-m-d H:i:s'),
            'interval' => 'weekly'
        ]);

        $result = $this->service->searchNewsletters($this->siteId, [
            'search' => 'announcement'
        ]);

        $this->assertCount(1, $result['newsletters']);
        $this->assertEquals('Newsletter 1', $result['newsletters']->first()->title);
    }

    public function testFilterByDateRange(): void
    {
        Newsletter::create([
            'title' => 'Old Newsletter',
            'site_id' => $this->siteId,
            'active' => true,
            'last_sent' => date('Y-m-d H:i:s', strtotime('-3 months')),
            'interval' => 'weekly',
            'content' => '{}'
        ]);

        Newsletter::create([
            'title' => 'Recent Newsletter',
            'site_id' => $this->siteId,
            'active' => true,
            'last_sent' => date('Y-m-d H:i:s', strtotime('-1 week')),
            'interval' => 'weekly',
            'content' => '{}'
        ]);

        $result = $this->service->searchNewsletters($this->siteId, [
            'date_from' => date('Y-m-d', strtotime('-1 month'))
        ]);

        $this->assertCount(1, $result['newsletters']);
        $this->assertEquals('Recent Newsletter', $result['newsletters']->first()->title);
    }

    public function testFilterByInterval(): void
    {
        Newsletter::create([
            'title' => 'Weekly Newsletter',
            'site_id' => $this->siteId,
            'active' => true,
            'last_sent' => date('Y-m-d H:i:s'),
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'content' => '{}'
        ]);

        Newsletter::create([
            'title' => 'Monthly Newsletter',
            'site_id' => $this->siteId,
            'active' => true,
            'last_sent' => date('Y-m-d H:i:s'),
            'interval' => Newsletter::INTERVAL_MONTHLY,
            'content' => '{}'
        ]);

        $result = $this->service->searchNewsletters($this->siteId, [
            'interval' => Newsletter::INTERVAL_WEEKLY
        ]);

        $this->assertCount(1, $result['newsletters']);
        $this->assertEquals('Weekly Newsletter', $result['newsletters']->first()->title);
    }

    public function testPaginationWorks(): void
    {
        // Create 25 newsletters
        for ($i = 1; $i <= 25; $i++) {
            Newsletter::create([
                'title' => "Newsletter $i",
                'site_id' => $this->siteId,
                'active' => true,
                'last_sent' => date('Y-m-d H:i:s', strtotime("-$i days")),
                'interval' => 'weekly',
                'content' => '{}'
            ]);
        }

        $page1 = $this->service->searchNewsletters($this->siteId, [], 1, 10);
        $page2 = $this->service->searchNewsletters($this->siteId, [], 2, 10);

        $this->assertCount(10, $page1['newsletters']);
        $this->assertCount(10, $page2['newsletters']);
        $this->assertEquals(25, $page1['pagination']['total']);
        $this->assertEquals(3, $page1['pagination']['total_pages']);
        $this->assertTrue($page1['pagination']['has_more']);
        $this->assertTrue($page2['pagination']['has_more']);
    }

    public function testSortingByTitle(): void
    {
        Newsletter::create([
            'title' => 'Zebra Newsletter',
            'site_id' => $this->siteId,
            'active' => true,
            'last_sent' => date('Y-m-d H:i:s'),
            'interval' => 'weekly',
            'content' => '{}'
        ]);

        Newsletter::create([
            'title' => 'Alpha Newsletter',
            'site_id' => $this->siteId,
            'active' => true,
            'last_sent' => date('Y-m-d H:i:s'),
            'interval' => 'weekly',
            'content' => '{}'
        ]);

        $result = $this->service->searchNewsletters($this->siteId, [
            'sort_by' => 'title',
            'sort_order' => 'asc'
        ]);

        $this->assertEquals('Alpha Newsletter', $result['newsletters']->first()->title);
        $this->assertEquals('Zebra Newsletter', $result['newsletters']->last()->title);
    }

    public function testGetFilterOptionsReturnsAvailableIntervals(): void
    {
        Newsletter::create([
            'title' => 'Weekly',
            'site_id' => $this->siteId,
            'active' => true,
            'last_sent' => date('Y-m-d H:i:s'),
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'content' => '{}'
        ]);

        Newsletter::create([
            'title' => 'Monthly',
            'site_id' => $this->siteId,
            'active' => true,
            'last_sent' => date('Y-m-d H:i:s'),
            'interval' => Newsletter::INTERVAL_MONTHLY,
            'content' => '{}'
        ]);

        $options = $this->service->getFilterOptions($this->siteId);

        $this->assertContains(Newsletter::INTERVAL_WEEKLY, $options['intervals']);
        $this->assertContains(Newsletter::INTERVAL_MONTHLY, $options['intervals']);
    }

    public function testGetFilterOptionsReturnsDateRange(): void
    {
        Newsletter::create([
            'title' => 'Old',
            'site_id' => $this->siteId,
            'active' => true,
            'last_sent' => date('Y-m-d H:i:s', strtotime('2020-01-01')),
            'interval' => 'weekly',
            'content' => '{}'
        ]);

        Newsletter::create([
            'title' => 'New',
            'site_id' => $this->siteId,
            'active' => true,
            'last_sent' => date('Y-m-d H:i:s'),
            'interval' => 'weekly',
            'content' => '{}'
        ]);

        $options = $this->service->getFilterOptions($this->siteId);

        $this->assertNotNull($options['date_range']['min']);
        $this->assertNotNull($options['date_range']['max']);
        $this->assertEquals('2020-01-01', $options['date_range']['min']);
    }

    public function testGetAppliedFiltersFormatsCorrectly(): void
    {
        $result = $this->service->searchNewsletters($this->siteId, [
            'search' => 'test',
            'interval' => 'weekly',
            'date_from' => '2024-01-01',
            'date_to' => '2024-12-31'
        ]);

        $applied = $result['filters_applied'];

        $this->assertCount(3, $applied);
        $this->assertEquals('search', $applied[0]['type']);
        $this->assertEquals('test', $applied[0]['value']);
    }

    public function testGetNewsletterYearsReturnsUniqueYears(): void
    {
        Newsletter::create([
            'title' => '2022 Newsletter',
            'site_id' => $this->siteId,
            'active' => true,
            'last_sent' => date('Y-m-d H:i:s', strtotime('2022-06-15')),
            'interval' => 'weekly',
            'content' => '{}'
        ]);

        Newsletter::create([
            'title' => '2023 Newsletter',
            'site_id' => $this->siteId,
            'active' => true,
            'last_sent' => date('Y-m-d H:i:s', strtotime('2023-06-15')),
            'interval' => 'weekly',
            'content' => '{}'
        ]);

        $years = $this->service->getNewsletterYears($this->siteId);

        $this->assertContains('2022', $years);
        $this->assertContains('2023', $years);
        // Most recent first
        $this->assertEquals('2023', $years[0]);
    }

    public function testGetNewslettersByMonthFiltersCorrectly(): void
    {
        Newsletter::create([
            'title' => 'January 2024',
            'site_id' => $this->siteId,
            'active' => true,
            'last_sent' => date('Y-m-d H:i:s', strtotime('2024-01-15')),
            'interval' => 'weekly',
            'content' => '{}'
        ]);

        Newsletter::create([
            'title' => 'February 2024',
            'site_id' => $this->siteId,
            'active' => true,
            'last_sent' => date('Y-m-d H:i:s', strtotime('2024-02-15')),
            'interval' => 'weekly',
            'content' => '{}'
        ]);

        $newsletters = $this->service->getNewslettersByMonth($this->siteId, 2024, 1);

        $this->assertCount(1, $newsletters);
        $this->assertEquals('January 2024', $newsletters->first()->title);
    }

    public function testExcludesInactiveNewsletters(): void
    {
        Newsletter::create([
            'title' => 'Active Newsletter',
            'site_id' => $this->siteId,
            'active' => true,
            'last_sent' => date('Y-m-d H:i:s'),
            'interval' => 'weekly',
            'content' => '{}'
        ]);

        Newsletter::create([
            'title' => 'Inactive Newsletter',
            'site_id' => $this->siteId,
            'active' => false,
            'last_sent' => date('Y-m-d H:i:s'),
            'interval' => 'weekly',
            'content' => '{}'
        ]);

        $result = $this->service->searchNewsletters($this->siteId);

        $this->assertCount(1, $result['newsletters']);
        $this->assertEquals('Active Newsletter', $result['newsletters']->first()->title);
    }

    public function testExcludesNeverSentNewsletters(): void
    {
        Newsletter::create([
            'title' => 'Sent Newsletter',
            'site_id' => $this->siteId,
            'active' => true,
            'last_sent' => date('Y-m-d H:i:s'),
            'interval' => 'weekly',
            'content' => '{}'
        ]);

        Newsletter::create([
            'title' => 'Never Sent Newsletter',
            'site_id' => $this->siteId,
            'active' => true,
            'last_sent' => null,
            'interval' => 'weekly',
            'content' => '{}'
        ]);

        $result = $this->service->searchNewsletters($this->siteId);

        $this->assertCount(1, $result['newsletters']);
        $this->assertEquals('Sent Newsletter', $result['newsletters']->first()->title);
    }

    public function testGetNewsletterArchiveReturnsAllEditions(): void
    {
        // Create newsletter with multiple sends
        $send1 = NewsletterSend::create([
            'newsletter_id' => $this->newsletter->id,
            'sent_at' => date('Y-m-d H:i:s', strtotime('2023-01-15')),
            'recipient_count' => 100,
            'content_snapshot' => [
                ['id' => 1, 'title' => 'Page 1'],
                ['id' => 2, 'title' => 'Page 2']
            ]
        ]);

        $send2 = NewsletterSend::create([
            'newsletter_id' => $this->newsletter->id,
            'sent_at' => date('Y-m-d H:i:s', strtotime('2023-06-20')),
            'recipient_count' => 150,
            'content_snapshot' => [
                ['id' => 3, 'title' => 'Page 3']
            ]
        ]);

        $send3 = NewsletterSend::create([
            'newsletter_id' => $this->newsletter->id,
            'sent_at' => date('Y-m-d H:i:s', strtotime('2024-01-10')),
            'recipient_count' => 200,
            'content_snapshot' => []
        ]);

        $result = $this->service->getNewsletterArchive($this->newsletter->id);

        $this->assertArrayHasKey('grouped_editions', $result);
        $this->assertArrayHasKey('latest_edition', $result);
        $this->assertArrayHasKey('total_editions', $result);

        $this->assertEquals(3, $result['total_editions']);

        $this->assertEquals($send3->id, $result['latest_edition']->id);

        // Check grouping by year
        $this->assertArrayHasKey('2023', $result['grouped_editions']);
        $this->assertArrayHasKey('2024', $result['grouped_editions']);
        $this->assertCount(2, $result['grouped_editions']['2023']);
        $this->assertCount(1, $result['grouped_editions']['2024']);
    }

    public function testGetNewsletterArchiveIncludesPageCounts(): void
    {
        $send = NewsletterSend::create([
            'newsletter_id' => $this->newsletter->id,
            'sent_at' => now_datetime()->format('Y-m-d H:i:s'),
            'recipient_count' => 100,
            'content_snapshot' => [
                ['id' => 1, 'title' => 'Page 1'],
                ['id' => 2, 'title' => 'Page 2'],
                ['id' => 3, 'title' => 'Page 3']
            ]
        ]);

        $result = $this->service->getNewsletterArchive($this->newsletter->id);

        $year = date('Y');

        $this->assertEquals(3, $result['grouped_editions'][$year][0]->page_count);
    }

    public function testGetNewsletterArchiveHandlesEmptySends(): void
    {
        $result = $this->service->getNewsletterArchive($this->newsletter->id);

        $this->assertEquals(0, $result['total_editions']);
        $this->assertNull($result['latest_edition']);
        $this->assertEmpty($result['grouped_editions']);
    }

    public function testGetNewsletterArchiveReturnsErrorForInvalidNewsletter(): void
    {
        $result = $this->service->getNewsletterArchive(99999);

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('Newsletter not found', $result['error']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'site_id' => $this->siteId,
            'active' => true,
            'interval' => 'weekly',
            'content' => '{}'
        ]);

        $this->service = new NewsletterArchiveService(
            new NewsletterRepository(),
            new NewsletterSendRepository()
        );
    }
}