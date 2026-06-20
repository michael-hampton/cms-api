<?php

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

final class UnifiedSubscriptionInvalidScopeTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_missing_subscription_and_invalid_site_return_404_response_shape(): void
    {
        $member = $this->createMember();
        $this->actingAsMember($member);

        $missing = $this->get('/' . $this->siteSlug . '/member/subscriptions/unified/999999/history');
        $invalidSite = $this->get('/missing-site-' . uniqid() . '/member/subscriptions/unified/999999/history');

        $this->assertResponseStatus(404, $missing);
        $this->assertResponseStatus(404, $invalidSite);
        self::assertSame('Subscription not found.', json_decode($missing->getContent(), true)['message'] ?? null);
        self::assertSame('Site not found.', json_decode($invalidSite->getContent(), true)['message'] ?? null);
    }
}
