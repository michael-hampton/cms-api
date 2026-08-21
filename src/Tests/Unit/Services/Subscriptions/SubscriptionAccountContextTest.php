<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Models\Site;
use App\Services\Subscriptions\SubscriptionAccountContext;
use Mockery;
use PHPUnit\Framework\TestCase;

final class SubscriptionAccountContextTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_press_stack_context_is_global_without_acquisition(): void
    {
        $context = SubscriptionAccountContext::pressStack();

        self::assertSame('press_stack', $context->mode);
        self::assertFalse($context->isSiteScoped);
        self::assertFalse($context->canAcquireSubscription);
        self::assertFalse($context->showSubscriptionModal);
        self::assertNull($context->site);
        self::assertNull($context->siteSlug);
        self::assertSame('/member/login', $context->loginUrl);
    }

    public function test_member_context_is_site_scoped_with_acquisition(): void
    {
        $site = Mockery::mock(Site::class);
        $context = SubscriptionAccountContext::memberArea($site, 'example');

        self::assertSame('member', $context->mode);
        self::assertTrue($context->isSiteScoped);
        self::assertTrue($context->canAcquireSubscription);
        self::assertTrue($context->showSubscriptionModal);
        self::assertSame($site, $context->site);
        self::assertSame('example', $context->siteSlug);
        self::assertSame('/example/member/login', $context->loginUrl);
    }
}
