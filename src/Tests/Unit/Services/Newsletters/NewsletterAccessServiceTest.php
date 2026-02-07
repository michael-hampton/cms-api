<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\DTO\Newsletters\NewsletterAccessResult as NewsletterAccessDTO;
use App\Enums\Newsletters\NewsletterAccessResult as NewsletterAccessResultEnum;
use App\Models\Newsletter;
use App\Models\Subscription;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Newsletter\NewsletterAccessService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class NewsletterAccessServiceTest extends FunctionalTestCase
{
    private NewsletterAccessService $service;
    private NewsletterRepository $newsletterRepository;
    private SubscriptionRepository $subscriptionRepository;

    public function testCheckAccessWithNonExistentNewsletter(): void
    {
        $this->newsletterRepository
            ->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $result = $this->service->checkAccess(999, 1, $this->siteId);

        $this->assertFalse($result['has_access']);
        $this->assertEquals(NewsletterAccessResultEnum::NOT_FOUND->value, $result['reason']);
    }

    public function testCheckAccessWithFreeNewsletter(): void
    {
        $newsletter = Mockery::mock(Newsletter::class)->makePartial();
        $newsletter->id = 1;
        $newsletter->site_id = $this->siteId;
        $newsletter->shouldReceive('isPremium')->andReturn(false);

        $this->newsletterRepository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($newsletter);

        $result = $this->service->checkAccess(1, null, $this->siteId);

        $this->assertTrue($result['has_access']);
        $this->assertEquals(NewsletterAccessResultEnum::FREE->value, $result['reason']);
    }

    public function testCheckAccessWithPaidNewsletterNoAuth(): void
    {
        $newsletter = Mockery::mock(Newsletter::class)->makePartial();
        $newsletter->id = 1;
        $newsletter->site_id = $this->siteId;
        $newsletter->shouldReceive('isPremium')->andReturn(true);

        $this->newsletterRepository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($newsletter);

        $result = $this->service->checkAccess(1, null, $this->siteId);

        $this->assertFalse($result['has_access']);
        $this->assertEquals(NewsletterAccessResultEnum::AUTHENTICATION_REQUIRED->value, $result['reason']);
    }

    public function testCheckAccessWithPaidNewsletterNoSubscription(): void
    {
        $newsletter = Mockery::mock(Newsletter::class)->makePartial();
        $newsletter->id = 1;
        $newsletter->site_id = $this->siteId;
        $newsletter->shouldReceive('isPremium')->andReturn(true);

        $this->newsletterRepository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($newsletter);

        $this->subscriptionRepository
            ->shouldReceive('getActiveSubscriptionForMember')
            ->with(1, $this->siteId)
            ->once()
            ->andReturn(null);

        $result = $this->service->checkAccess(1, 1, $this->siteId);

        $this->assertFalse($result['has_access']);
        $this->assertEquals(NewsletterAccessResultEnum::NO_SUBSCRIPTION->value, $result['reason']);
    }

    public function testCheckAccessWithPaidNewsletterValidSubscription(): void
    {
        $newsletter = Mockery::mock(Newsletter::class)->makePartial();
        $newsletter->id = 1;
        $newsletter->site_id = $this->siteId;
        $newsletter->shouldReceive('isPremium')->andReturn(true);
        $newsletter->access_level = null;

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('canAccessNewsletter')
            ->with($newsletter)
            ->once()
            ->andReturn(NewsletterAccessDTO::allowed());

        $this->newsletterRepository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($newsletter);

        $this->subscriptionRepository
            ->shouldReceive('getActiveSubscriptionForMember')
            ->with(1, $this->siteId)
            ->once()
            ->andReturn($subscription);

        $result = $this->service->checkAccess(1, 1, $this->siteId);

        $this->assertTrue($result['has_access']);
        $this->assertEquals(NewsletterAccessResultEnum::SUBSCRIPTION->value, $result['reason']);
    }

    public function testCheckAccessWithInsufficientAccessLevel(): void
    {
        $newsletter = Mockery::mock(Newsletter::class)->makePartial();
        $newsletter->id = 1;
        $newsletter->site_id = $this->siteId;
        $newsletter->shouldReceive('isPremium')->andReturn(true);
        $newsletter->access_level = 'premium';

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('canAccessNewsletter')
            ->with($newsletter)
            ->once()
            ->andReturn(NewsletterAccessDTO::denied('insufficient_level', 'Upgrade your subscription'));

        $this->newsletterRepository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($newsletter);

        $this->subscriptionRepository
            ->shouldReceive('getActiveSubscriptionForMember')
            ->with(1, $this->siteId)
            ->once()
            ->andReturn($subscription);

        $result = $this->service->checkAccess(1, 1, $this->siteId);

        $this->assertFalse($result['has_access']);
        $this->assertEquals(NewsletterAccessResultEnum::INSUFFICIENT_LEVEL->value, $result['reason']);
        $this->assertEquals('premium', $result['required_level']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->newsletterRepository = Mockery::mock(NewsletterRepository::class);
        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);

        $this->service = new NewsletterAccessService(
            $this->newsletterRepository,
            $this->subscriptionRepository
        );
    }
}