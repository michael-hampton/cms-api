<?php

namespace App\Tests\Functional\Controllers\Members\Api;

use App\Models\Address;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberAddressApiControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexReturnsAddressesAndCountriesForAuthenticatedMember(): void
    {
        $member = $this->createAuthenticatedMember();
        $this->createAddress(['member_id' => $member->id, 'address_line_1' => '1 Test Street']);
        $this->createAddress(['member_id' => $member->id, 'address_line_1' => '2 Test Street']);

        $response = $this->getForSite('/api/member/addresses/search', [], true);
        $data = $this->responseData($response);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('countries', $data);
        $this->assertCount(2, $data['items']);
    }

    public function testIndexRedirectsWhenNotLoggedIn(): void
    {
        $this->unauthenticateMember();

        $response = $this->getForSite('/api/member/addresses/search', [], true);

        $this->assertContains($response->getStatusCode(), [302, 401]);
    }

    public function testStoreCreatesNewSiteScopedAddress(): void
    {
        $member = $this->createAuthenticatedMember();

        $response = $this->postForSite('/api/member/addresses', [
            'address_line_1' => '123 Test Street',
            'city' => 'London',
            'postcode' => 'SW1A 1AA',
            'country' => 'GB',
            'type' => 'shipping',
            'member_id' => $member->id,
        ], [], [], false, true);
        $data = $this->responseData($response);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('countries', $data);
        $this->assertSame('123 Test Street', $data['address']['address_line_1']);

        $address = Address::find($data['address']['id']);
        $this->assertNotNull($address);
        $this->assertSame($member->id, (int) $address->member_id);
        $this->assertSame($this->siteId, (int) $address->site_id);
    }

    public function testStoreRequiresAuthentication(): void
    {
        $this->unauthenticateMember();

        $response = $this->postForSite('/api/member/addresses', [
            'address_line_1' => '123 Test Street',
            'city' => 'London',
            'postcode' => 'SW1A 1AA',
            'country' => 'GB',
            'type' => 'shipping',
            'member_id' => 999,
        ], [], [], false, true);

        $this->assertContains($response->getStatusCode(), [302, 401]);
    }

    public function testUpdateModifiesOwnedAddress(): void
    {
        $member = $this->createAuthenticatedMember();
        $address = $this->createAddress([
            'member_id' => $member->id,
            'address_line_1' => 'Old Street',
            'city' => 'London',
            'postcode' => 'SW1A 1AA',
            'country' => 'GB',
            'type' => 'shipping',
        ]);

        $response = $this->putForSite("/api/member/addresses/{$address->id}", [
            'address_line_1' => 'New Street',
            'city' => 'London',
            'postcode' => 'SW1A 1AA',
            'country' => 'GB',
        ], [], [], true);
        $data = $this->responseData($response);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertSame('New Street', $data['address']['address_line_1']);
        $this->assertArrayHasKey('countries', $data);
    }

    public function testUpdateReturnsErrorForOtherMembersAddress(): void
    {
        $this->createAuthenticatedMember();
        $otherMember = $this->createMember();
        $address = $this->createAddress(['member_id' => $otherMember->id]);

        $response = $this->putForSite("/api/member/addresses/{$address->id}", [
            'address_line_1' => 'Hacked Street',
        ], [], [], true);
        $data = $this->responseData($response);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertFalse($data['success']);
    }

    public function testUpdateRequiresAuthentication(): void
    {
        $this->unauthenticateMember();
        $member = $this->createMember();
        $address = $this->createAddress(['member_id' => $member->id]);

        $response = $this->putForSite("/api/member/addresses/{$address->id}", [
            'address_line_1' => 'Street',
        ], [], [], true);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testDestroyDeletesOwnedAddress(): void
    {
        $member = $this->createAuthenticatedMember();
        $address = $this->createAddress(['member_id' => $member->id]);

        $response = $this->deleteForSite("/api/member/addresses/{$address->id}", [], true);
        $data = $this->responseData($response);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertNull(Address::find($address->id));
    }

    public function testDestroyReturnsErrorForOtherMembersAddress(): void
    {
        $this->createAuthenticatedMember();
        $otherMember = $this->createMember();
        $address = $this->createAddress(['member_id' => $otherMember->id]);

        $response = $this->deleteForSite("/api/member/addresses/{$address->id}", [], true);
        $data = $this->responseData($response);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertFalse($data['success']);
    }

    public function testSetDefaultUpdatesDefaultAddressAndClearsPreviousDefaultForSameType(): void
    {
        $member = $this->createAuthenticatedMember();
        $oldDefault = $this->createAddress([
            'member_id' => $member->id,
            'type' => 'shipping',
            'is_default' => true,
        ]);
        $newDefault = $this->createAddress([
            'member_id' => $member->id,
            'type' => 'shipping',
            'is_default' => false,
        ]);

        $response = $this->postForSite("/api/member/addresses/{$newDefault->id}/set-default", [], [], [], false, true);
        $data = $this->responseData($response);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertTrue((bool) Address::find($newDefault->id)->is_default);
        $this->assertFalse((bool) Address::find($oldDefault->id)->is_default);
    }

    private function createAuthenticatedMember(array $overrides = [])
    {
        $member = $this->createMember($overrides);
        $this->actingAsMember($member);

        return $member;
    }

    private function responseData($response): array
    {
        $decoded = json_decode($response->getContent(), true);
        $this->assertIsArray($decoded, 'Expected JSON response. Body: ' . $response->getContent());

        if (isset($decoded['data']) && is_array($decoded['data'])) {
            return array_merge($decoded, $decoded['data']);
        }

        return $decoded;
    }
}
