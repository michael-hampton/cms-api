<?php

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Tests\Functional\Controllers\FunctionalTestCase;

final class UnifiedSubscriptionMutationAuthenticationTest extends FunctionalTestCase
{
    public function test_all_write_routes_return_401_without_member_authentication(): void
    {
        $this->unauthenticateMember();

        $paths = [
            '/cancel',
            '/reactivate',
            '/pause',
            '/resume',
            '/auto-renew',
            '/billing-date/preview',
            '/billing-date',
            '/delivery/pause',
            '/delivery/resume',
            '/upgrades/preview',
            '/upgrades',
            '/preferences',
            '/delivery-addresses/1/default',
        ];

        foreach ($paths as $suffix) {
            $response = $this->makeRequest(
                'POST',
                '/' . $this->siteSlug . '/member/subscriptions/unified/42' . $suffix,
                [],
                ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
            );

            $this->assertResponseStatus(401, $response);
            self::assertFalse(json_decode($response->getContent(), true)['success'] ?? true);
        }
    }
}
