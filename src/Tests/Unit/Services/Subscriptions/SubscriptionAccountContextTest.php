<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Models\Site;
use App\Services\Subscriptions\SubscriptionAccountContext;
use PHPUnit\Framework\TestCase;

final class SubscriptionAccountContextTest extends TestCase
{
    public function test_press_stack_context_is_global_without_acquisition(): void
    {
        $context = SubscriptionAccountContext::pressStack();

        self::assertSame('press_stack', $context->mode);
        self::assertFalse($context->isSiteScoped);
        self::assertFalse($context->canAcquireSubscription);
        self::assertFalse($context->showSubscriptionModal);
        self::assertNull($context->site);
        self::assertNull($context->siteSlug);
    }

    public function test_member_context_is_site_scoped_with_acquisition(): void
    {
        $site = new Site();
        $site->slug = 'example';

        $context = SubscriptionAccountContext::memberArea($site);

        self::assertSame('member', $context->mode);
        self::assertTrue($context->isSiteScoped);
        self::assertTrue($context->canAcquireSubscription);
        self::assertTrue($context->showSubscriptionModal);
        self::assertSame($site, $context->site);
        self::assertSame('example', $context->siteSlug);
    }
}
