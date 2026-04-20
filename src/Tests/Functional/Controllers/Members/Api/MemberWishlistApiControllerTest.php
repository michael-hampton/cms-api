<?php

namespace App\Tests\Functional\Controllers\Members\Api;

use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberWishlistApiControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexReturnsWishlistItems(): void
    {
        $this->createAuthenticatedMember();

        $response = $this->getForSite('/api/member/wishlist', [], true);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
    }

    private function createAuthenticatedMember(array $overrides = [])
    {
        $member = $this->createMember($overrides);
        $this->actingAsMember($member);
        return $member;
    }

    public function testIndexReturnsUnauthorizedWhenNotLoggedIn(): void
    {
        $this->unauthenticateMember();

        $response = $this->getForSite('/api/member/wishlist', [], true);

        $this->assertEquals(401, $response->getStatusCode());
    }
}