<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Framework\Support\Collection;
use App\Models\Newsletter;
use App\Models\NewsletterSend;
use App\Models\Page;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Newsletters\NewsletterSendPageViewRepository;
use App\Repositories\Newsletters\NewsletterSendRepository;
use App\Services\Newsletter\NewsletterStatisticsService;
use Exception;
use Mockery;
use PHPUnit\Framework\TestCase;

class NewsletterStatisticsServiceTest extends TestCase
{
    private NewsletterRepository $newsletterRepository;
    private NewsletterSendRepository $newsletterSendRepository;
    private NewsletterSendPageViewRepository $pageViewRepository;
    private $service;

    /**
     * Test that an exception is thrown if the newsletter doesn't exist.
     */
    public function testGetStatisticsThrowsExceptionIfNewsletterNotFound(): void
    {
        $this->newsletterRepository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Newsletter not found');

        $this->service->getNewsletterStatistics(1);
    }

    /**
     * Test successful calculation of statistics.
     */
    public function testGetNewsletterStatisticsSuccess(): void
    {
        $sendIds = [10, 11];

        $this->newsletterRepository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn(Mockery::mock(Newsletter::class)->makePartial());

        $newsletterSend = Mockery::mock(NewsletterSend::class)->makePartial();
        $newsletterSend->id = 1;

        $this->newsletterSendRepository
            ->shouldReceive('getSendsForNewsletter')
            ->with(1)
            ->once()
            ->andReturn(new Collection([$newsletterSend]));

        // Mock the new PageViewRepository
        $this->pageViewRepository
            ->shouldReceive('getViewStatistics')
            ->with(1)
            ->once()
            ->andReturn([
                'total_clicks' => 150
                , 'unique_clickers' => 0
                , 'top_clicked_pages' => []
                , 'failed_sends' => 0
                , 'pending_sends' => 0
                , 'unique_recipients' => 80
            ]);

        $this->pageViewRepository
            ->shouldReceive('getUniqueClickerCount')
            ->with($sendIds)
            ->once()
            ->andReturn(80);

        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;
        $page->title = 'Home';
        $page->slug = 'home';
        $page->click_count = 50;

        $this->pageViewRepository
            ->shouldReceive('getTopClickedPages')
            ->with([1])
            ->once()
            ->andReturn(new Collection([$page]));

        // Execute Service
        $result = $this->service->getNewsletterStatistics(1);

        // Assert
        $this->assertEquals(150, $result['total_clicks']);
        $this->assertEquals(80, $result['unique_clickers']);
        $this->assertCount(1, $result['top_clicked_pages']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->newsletterRepository = Mockery::mock(NewsletterRepository::class);
        $this->newsletterSendRepository = Mockery::mock(NewsletterSendRepository::class);
        $this->pageViewRepository = Mockery::mock(NewsletterSendPageViewRepository::class);

        $this->service = new NewsletterStatisticsService(
            $this->newsletterRepository,
            $this->newsletterSendRepository,
            $this->pageViewRepository
        );
    }
}