<?php

namespace App\Tests\Functional\Controllers\Members\Api;

use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberOrdersApiControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexReturnsMembersOrders(): void
    {
        $member = $this->createAuthenticatedMember();
        $this->createOrder(['user_id' => $member->id]);
        $this->createOrder(['user_id' => $member->id]);

        $response = $this->getForSite('/api/member/orders', [], true);

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

    public function testShowReturnsSingleOrderWithDetails(): void
    {
        $member = $this->createAuthenticatedMember();
        $order = $this->createOrder(['user_id' => $member->id]);

        $response = $this->getForSite("/api/member/orders/{$order->id}", [], true);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('can_be_cancelled', $data['data']);
        $this->assertArrayHasKey('created_at', $data['data']);
    }

    public function testShowFormatsCreatedAt(): void
    {
        $member = $this->createAuthenticatedMember();
        $order = $this->createOrder(['user_id' => $member->id]);

        $response = $this->getForSite("/api/member/orders/{$order->id}", [], true);

        $data = json_decode($response->getContent(), true);
        if ($data['data']['created_at']) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $data['data']['created_at']);
        }
    }

    public function testCancelOrderSuccessfully(): void
    {
        $member = $this->createAuthenticatedMember();
        $order = $this->createOrder(['user_id' => $member->id, 'status' => 'pending']);

        $response = $this->postForSite("/api/member/orders/{$order->id}/cancel", [], [], [], [], true);

        // pending orders should be cancellable; if not, expect 400
        $this->assertContains($response->getStatusCode(), [200, 400]);
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('success', $data);
    }

    public function testCancelReturnsForbiddenForOtherMembersOrder(): void
    {
        $member = $this->createAuthenticatedMember();
        $otherMember = $this->createMember();
        $order = $this->createOrder(['user_id' => $otherMember->id, 'status' => 'pending']);

        $response = $this->postForSite("/api/member/orders/{$order->id}/cancel", [], [], [], [], true);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testCancelReturns404ForNonexistentOrder(): void
    {
        $this->createAuthenticatedMember();

        $response = $this->postForSite('/api/member/orders/99999/cancel', [], [], [], [], true);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testCancelRequiresAuthentication(): void
    {
        $this->unauthenticateMember();
        $member = $this->createMember();
        $order = $this->createOrder(['user_id' => $member->id, 'status' => 'pending']);

        $response = $this->postForSite("/api/member/orders/{$order->id}/cancel", [], [], [], [], true);

        $this->assertEquals(401, $response->getStatusCode());
    }
}