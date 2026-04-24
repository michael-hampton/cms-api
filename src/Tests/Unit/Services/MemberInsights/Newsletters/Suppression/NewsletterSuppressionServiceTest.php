<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\MemberInsights\Newsletters\Suppression;

use App\Models\Member;
use App\Repositories\Newsletters\SubscriberRepository;
use App\Services\MemberInsights\Newsletters\Suppression\NewsletterSuppressionService;
use App\Services\MemberInsights\Newsletters\Suppression\SuppressionSet;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;


class NewsletterSuppressionServiceTest extends TestCase
{
    private SubscriberRepository&MockInterface $subscriberRepository;
    private NewsletterSuppressionService $service;

    public function test_returns_suppression_set_with_active_newsletter_ids(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->email = 'user@example.com';

        $this->subscriberRepository
            ->shouldReceive('getActiveNewsletterIdsForMember')
            ->once()
            ->with('user@example.com', 1)
            ->andReturn([10, 20, 30]);

        $result = $this->service->buildSuppressionSet($member, siteId: 1);

        $this->assertInstanceOf(SuppressionSet::class, $result);
        $this->assertSame([10, 20, 30], $result->ids());
        $this->assertTrue($result->contains(10));
        $this->assertTrue($result->contains(20));
        $this->assertFalse($result->contains(99));
    }

    public function test_returns_empty_suppression_set_when_member_has_no_subscriptions(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->email = 'new@example.com';

        $this->subscriberRepository
            ->shouldReceive('getActiveNewsletterIdsForMember')
            ->once()
            ->with('new@example.com', 1)
            ->andReturn([]);

        $result = $this->service->buildSuppressionSet($member, siteId: 1);

        $this->assertInstanceOf(SuppressionSet::class, $result);
        $this->assertTrue($result->isEmpty());
        $this->assertSame(0, $result->count());
    }

    public function test_passes_correct_site_id_to_repository(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->email = 'user@example.com';

        $this->subscriberRepository
            ->shouldReceive('getActiveNewsletterIdsForMember')
            ->once()
            ->with('user@example.com', 42)
            ->andReturn([5]);

        $result = $this->service->buildSuppressionSet($member, siteId: 42);

        $this->assertSame(1, $result->count());
    }

    public function test_deduplicates_newsletter_ids_from_repository(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->email = 'user@example.com';

        // Repository should not return duplicates, but the value object
        // handles it defensively.
        $this->subscriberRepository
            ->shouldReceive('getActiveNewsletterIdsForMember')
            ->once()
            ->andReturn([10, 10, 20]);

        $result = $this->service->buildSuppressionSet($member, siteId: 1);

        $this->assertSame(2, $result->count());
        $this->assertSame([10, 20], $result->ids());
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriberRepository = Mockery::mock(SubscriberRepository::class);
        $this->service = new NewsletterSuppressionService($this->subscriberRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}