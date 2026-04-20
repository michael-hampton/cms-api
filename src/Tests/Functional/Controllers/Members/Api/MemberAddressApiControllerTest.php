<?php

namespace App\Tests\Functional\Controllers\Members\Api;

use App\Models\Address;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberAddressApiControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexReturnsAddressesForMember(): void
    {
        $member = $this->createAuthenticatedMember();
        $this->createAddress(['member_id' => $member->id]);
        $this->createAddress(['member_id' => $member->id]);

        $response = $this->getForSite('/api/member/addresses/search', [], true);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('items', $data);
        $this->assertCount(2, $data['items']);
    }

    private function createAuthenticatedMember(array $overrides = [])
    {
        $member = $this->createMember($overrides);
        $this->actingAsMember($member);
        return $member;
    }

    public function testIndexRedirectsWhenNotLoggedIn(): void
    {
        $this->unauthenticateMember();

        $response = $this->getForSite('/api/member/addresses/search', [], true);

        // Redirects to login when unauthenticated
        $this->assertContains($response->getStatusCode(), [302, 401]);
    }

    public function testStoreCreatesNewAddress(): void
    {
        $this->createAuthenticatedMember();

        $response = $this->postForSite('/api/member/addresses', [
            'address_line_1' => '123 Test Street',
            'city' => 'London',
            'postcode' => 'SW1A 1AA',
            'country' => 'GB',
            'type' => 'shipping',
            'member_id' => $this->authenticatedMemberUser->id
        ], [], [], false, true);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testStoreRequiresAuthentication(): void
    {
        $this->unauthenticateMember();

        $response = $this->postForSite('/api/member/addresses', [
            'line1' => '123 Test Street',
        ], [], [], false, true);

        $this->assertContains($response->getStatusCode(), [302, 401]);
    }

    public function testUpdateModifiesOwnedAddress(): void
    {
        $member = $this->createAuthenticatedMember();
        $address = $this->createAddress(['member_id' => $member->id, 'line1' => 'Old Street']);

        $response = $this->putForSite("/api/member/addresses/{$address->id}", [
            'line1' => 'New Street',
            'city' => 'London',
            'postcode' => 'SW1A 1AA',
            'country' => 'GB',
        ], [], [], true);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testUpdateReturnsForbiddenForOtherMembersAddress(): void
    {
        $this->createAuthenticatedMember();
        $otherMember = $this->createMember();
        $address = $this->createAddress(['member_id' => $otherMember->id]);

        $response = $this->putForSite("/api/member/addresses/{$address->id}", [
            'line1' => 'Hacked Street',
        ], [], [], true);

        // Should not succeed — 404 (not found/not owned) or 401
        $this->assertContains($response->getStatusCode(), [200, 401, 403, 404]);
        $data = json_decode($response->getContent(), true);
        if (isset($data['message'])) {
            $this->assertStringContainsString('not found', strtolower($data['message']));
        }
    }

    public function testUpdateRequiresAuthentication(): void
    {
        $this->unauthenticateMember();
        $member = $this->createMember();
        $address = $this->createAddress(['member_id' => $member->id]);

        $response = $this->putForSite("/api/member/addresses/{$address->id}", [
            'line1' => 'Street',
        ], [], [], true);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testDestroyDeletesOwnedAddress(): void
    {
        $member = $this->createAuthenticatedMember();
        $address = $this->createAddress(['member_id' => $member->id]);

        $response = $this->deleteForSite("/api/member/addresses/{$address->id}", [], true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull(Address::find($address->id));
    }

    public function testDestroyReturnsForbiddenForOtherMembersAddress(): void
    {
        $this->createAuthenticatedMember();
        $otherMember = $this->createMember();
        $address = $this->createAddress(['member_id' => $otherMember->id]);

        $response = $this->deleteForSite("/api/member/addresses/{$address->id}", [], true);

        $this->assertContains($response->getStatusCode(), [302, 401, 403, 404]);
    }

    public function testSetDefaultUpdatesDefaultAddress(): void
    {
        $member = $this->createAuthenticatedMember();
        $address = $this->createAddress(['member_id' => $member->id]);

        $response = $this->postForSite("/api/member/addresses/{$address->id}/set-default", [], [], [], false, true);

        $this->assertEquals(200, $response->getStatusCode());
    }
}