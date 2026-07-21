<?php

namespace App\Tests\Unit\Services\PublicContent\Newsletter;

use App\Models\Member;
use App\Repositories\Newsletters\SubscriberRepository;
use App\Services\PublicContent\Newsletter\NewsletterWidgetStateResolver;
use Mockery;
use PHPUnit\Framework\TestCase;

final class NewsletterWidgetStateResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_anonymous_visitor_is_not_subscribed(): void
    {
        $subscribers = Mockery::mock(SubscriberRepository::class);
        $subscribers->shouldReceive('getActiveNewsletterIdsForMember')->never();

        $state = (new NewsletterWidgetStateResolver($subscribers))->resolve(1, 'brand', null);

        self::assertFalse($state->authenticated);
        self::assertFalse($state->subscribed);
        self::assertSame('/brand/member/login', $state->loginUrl);
    }

    public function test_member_with_active_subscription_is_subscribed(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->email = 'reader@example.com';

        $subscribers = Mockery::mock(SubscriberRepository::class);
        $subscribers->shouldReceive('getActiveNewsletterIdsForMember')
            ->once()
            ->with('reader@example.com', 7)
            ->andReturn([3]);

        $state = (new NewsletterWidgetStateResolver($subscribers))->resolve(7, 'brand', $member);

        self::assertTrue($state->authenticated);
        self::assertTrue($state->subscribed);
        self::assertSame('/brand/member/newsletters', $state->manageUrl);
        self::assertNull($state->loginUrl);
    }

    public function test_member_without_subscriptions_is_not_subscribed(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->email = 'reader@example.com';

        $subscribers = Mockery::mock(SubscriberRepository::class);
        $subscribers->shouldReceive('getActiveNewsletterIdsForMember')->once()->andReturn([]);

        $state = (new NewsletterWidgetStateResolver($subscribers))->resolve(7, 'brand', $member);

        self::assertTrue($state->authenticated);
        self::assertFalse($state->subscribed);
    }
}
