<?php

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Framework\Http\Response;
use App\Models\Address;
use App\Models\Member;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ShopAccountAddressApiControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = $this->createMember([
            'email' => 'press-stack-address-member@example.com',
            'first_name' => 'Press',
            'last_name' => 'Stack',
            'display_name' => 'Press Stack',
            'is_active' => true,
            'status' => 'active',
        ]);

        $this->actingAsMember($this->member);
    }

    public function testIndexReturnsAuthenticatedMembersAddressesAndCountries(): void
    {
        $address = $this->memberAddress([
            'address_line_1' => '10 PressStack Street',
            'type' => 'both',
        ]);
        $this->createAddress([
            'member_id' => $this->createMember(['email' => 'other-press-stack-address@example.com'])->id,
            'address_line_1' => 'Other Member Street',
        ]);

        $response = $this->getAccount('/press-stack/account/addresses/search');
        $data = $this->responseJson($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('countries', $data);
        $this->assertCount(1, $data['items']);
        $this->assertSame($address->id, $data['items'][0]['id']);
        $this->assertSame('10 PressStack Street', $data['items'][0]['address_line_1']);
    }

    public function testStoreCreatesNonSiteScopedPressStackAddress(): void
    {
        $response = $this->postAccount('/press-stack/account/addresses', [
            'member_id' => $this->member->id,
            'type' => 'shipping',
            'label' => 'Home',
            'is_default' => 1,
            'address_line_1' => '1 Global Address Street',
            'city' => 'London',
            'postcode' => 'SW1A 1AA',
            'country' => 'GB',
        ]);
        $data = $this->responseJson($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('countries', $data);
        $this->assertSame('1 Global Address Street', $data['address']['address_line_1']);

        $address = Address::find($data['address']['id']);
        $this->assertNotNull($address);
        $this->assertSame($this->member->id, (int) $address->member_id);
        $this->assertNull($address->site_id);
        $this->assertTrue((bool) $address->is_default);
    }

    public function testUpdateModifiesOwnedPressStackAddress(): void
    {
        $address = $this->memberAddress([
            'address_line_1' => 'Old PressStack Street',
            'type' => 'shipping',
        ]);

        $response = $this->putAccount("/press-stack/account/addresses/{$address->id}", [
            'address_line_1' => 'Updated PressStack Street',
            'country' => 'GB',
        ]);
        $data = $this->responseJson($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertSame('Updated PressStack Street', $data['address']['address_line_1']);
        $this->assertArrayHasKey('countries', $data);
    }

    public function testUpdateRejectsOtherMembersAddress(): void
    {
        $otherMember = $this->createMember(['email' => 'other-owner-address@example.com']);
        $address = $this->createAddress([
            'member_id' => $otherMember->id,
            'address_line_1' => 'Other Owner Street',
        ]);

        $response = $this->putAccount("/press-stack/account/addresses/{$address->id}", [
            'address_line_1' => 'Should Not Save',
        ]);
        $data = $this->responseJson($response);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertFalse($data['success']);
        $this->assertSame('Other Owner Street', Address::find($address->id)->address_line_1);
    }

    public function testDestroyDeletesOwnedPressStackAddress(): void
    {
        $address = $this->memberAddress();

        $response = $this->deleteAccount("/press-stack/account/addresses/{$address->id}");
        $data = $this->responseJson($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertNull(Address::find($address->id));
    }

    public function testSetDefaultClearsPreviousDefaultForSameType(): void
    {
        $oldDefault = $this->memberAddress([
            'type' => 'billing',
            'is_default' => true,
        ]);
        $newDefault = $this->memberAddress([
            'type' => 'billing',
            'is_default' => false,
        ]);

        $response = $this->postAccount("/press-stack/account/addresses/{$newDefault->id}/set-default");
        $data = $this->responseJson($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertTrue((bool) Address::find($newDefault->id)->is_default);
        $this->assertFalse((bool) Address::find($oldDefault->id)->is_default);
    }

    private function memberAddress(array $overrides = []): Address
    {
        return $this->createAddress(array_merge([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'label' => 'Test address',
            'address_line_1' => '1 Test Street',
            'city' => 'London',
            'postcode' => 'SW1A 1AA',
            'country' => 'GB',
            'type' => 'shipping',
            'is_default' => false,
        ], $overrides));
    }

    private function getAccount(string $uri): Response
    {
        return $this->makeRequest('GET', $uri, [], $this->getDefaultHeaders(['Accept' => 'application/json'], true));
    }

    private function postAccount(string $uri, array $data = []): Response
    {
        return $this->makeRequest('POST', $uri, $data, $this->getDefaultHeaders(['Accept' => 'application/json'], true));
    }

    private function putAccount(string $uri, array $data = []): Response
    {
        return $this->makeRequest('PUT', $uri, $data, $this->getDefaultHeaders(['Accept' => 'application/json'], true));
    }

    private function deleteAccount(string $uri): Response
    {
        return $this->makeRequest('DELETE', $uri, [], $this->getDefaultHeaders(['Accept' => 'application/json'], true));
    }

    private function responseJson(Response $response): array
    {
        $decoded = json_decode($response->getContent(), true);
        $this->assertIsArray($decoded, 'Expected JSON response. Body: ' . $response->getContent());

        if (isset($decoded['data']) && is_array($decoded['data'])) {
            return array_merge($decoded, $decoded['data']);
        }

        return $decoded;
    }
}
