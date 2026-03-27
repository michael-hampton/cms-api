<?php

namespace App\Tests\Functional\Controllers\Crm;

use App\Models\Address;
use App\Models\Member;
use App\Models\Model;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class CrmAddressControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Member $member;

    // ── Create (GET) ──────────────────────────────────────────────────────────

    public function test_create_returns_200_for_existing_member(): void
    {
        $response = $this->get('/crm/members/' . $this->member->id . '/addresses/create');

        $this->assertResponseStatus(200, $response);
    }

    public function test_create_contains_all_address_type_options(): void
    {
        $response = $this->get('/crm/members/' . $this->member->id . '/addresses/create');

        $content = $response->getContent();
        $this->assertStringContainsString('shipping', $content);
        $this->assertStringContainsString('billing', $content);
        $this->assertStringContainsString('both', $content);
    }

    public function test_create_displays_member_name_in_heading(): void
    {
        $response = $this->get('/crm/members/' . $this->member->id . '/addresses/create');

        $this->assertStringContainsString(
            $this->member->first_name,
            $response->getContent()
        );
    }

//    public function test_create_redirects_unauthenticated_agent(): void
//    {
//        $response = $this->getForSiteUnauthenticated('/crm/members/' . $this->member->id . '/addresses/create');
//
//        $this->assertResponseStatus(302, $response);
//    }

    public function test_create_redirects_when_member_not_found(): void
    {
        $response = $this->get('/crm/members/999999/addresses/create');

        $this->assertResponseStatus(302, $response);
    }

    // ── Store (POST) ──────────────────────────────────────────────────────────

    public function test_store_creates_address_for_member(): void
    {
        $response = $this->post(
            '/crm/members/' . $this->member->id . '/addresses',
            $this->validAddressPayload()
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('address', $data);

        $this->assertDatabaseHas('addresses', [
            'member_id' => $this->member->id,
            'address_line_1' => '10 Test Street',
            'city' => 'London',
        ]);
    }

    private function validAddressPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'both',
            'label' => 'Home',
            'address_line_1' => '10 Test Street',
            'address_line_2' => '',
            'city' => 'London',
            'state' => '',
            'postcode' => 'EC1A 1BB',
            'country' => 'GB',
        ], $overrides);
    }

    public function test_store_sets_first_address_as_default(): void
    {
        // No existing addresses for this member
        $this->post(
            '/crm/members/' . $this->member->id . '/addresses',
            $this->validAddressPayload()
        );

        $this->assertDatabaseHas('addresses', [
            'member_id' => $this->member->id,
            'is_default' => 1,
        ]);
    }

    public function test_store_does_not_set_default_when_address_already_exists(): void
    {
        // Pre-existing address
        $this->createAddress(['member_id' => $this->member->id, 'type' => 'both', 'is_default' => true]);

        $this->postForSite(
            '/crm/members/' . $this->member->id . '/addresses',
            $this->validAddressPayload()
        );

        $defaults = Address::where('member_id', $this->member->id)
            ->where('is_default', true)
            ->count();

        $this->assertEquals(1, $defaults);
    }

    private function createAddress(array $overrides = []): Model
    {
        return Address::create(array_merge([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'type' => 'both',
            'is_default' => false,
            'address_line_1' => '1 Default Lane',
            'city' => 'London',
            'postcode' => 'SW1A 1AA',
            'country' => 'GB',
        ], $overrides));
    }

    public function test_store_accepts_all_three_address_types(): void
    {
        foreach (['shipping', 'billing', 'both'] as $type) {
            $payload = $this->validAddressPayload();
            $payload['type'] = $type;
            $payload['city'] = ucfirst($type) . 'City';

            $response = $this->post(
                '/crm/members/' . $this->member->id . '/addresses',
                $payload
            );

            $this->assertResponseStatus(200, $response);

            $this->assertDatabaseHas('addresses', [
                'member_id' => $this->member->id,
                'type' => $type,
            ]);
        }
    }

//    public function test_store_returns_401_for_unauthenticated_agent(): void
//    {
//        $response = $this->postForSiteUnauthenticated(
//            '/crm/members/' . $this->member->id . '/addresses',
//            $this->validAddressPayload()
//        );
//
//        $this->assertResponseStatus(401, $response);
//    }

    public function test_store_returns_422_when_required_fields_missing(): void
    {
        $response = $this->post(
            '/crm/members/' . $this->member->id . '/addresses',
            ['type' => 'both']   // missing address_line_1, city, country
        );

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    // ── Edit (GET) ────────────────────────────────────────────────────────────

    public function test_store_returns_422_for_invalid_address_type(): void
    {
        $payload = $this->validAddressPayload();
        $payload['type'] = 'invalid_type';

        $response = $this->post(
            '/crm/members/' . $this->member->id . '/addresses',
            $payload
        );

        $this->assertResponseStatus(422, $response);
    }

    public function test_store_returns_404_when_member_not_found(): void
    {
        $response = $this->post(
            '/crm/members/999999/addresses',
            $this->validAddressPayload()
        );

        $this->assertResponseStatus(404, $response);
    }

//    public function test_edit_redirects_unauthenticated_agent(): void
//    {
//        $address = $this->createAddress(['member_id' => $this->member->id]);
//
//        $response = $this->getForSiteUnauthenticated(
//            '/crm/members/' . $this->member->id . '/addresses/' . $address->id . '/edit'
//        );
//
//        $this->assertResponseStatus(302, $response);
//    }

    public function test_edit_returns_200_for_existing_address(): void
    {
        $address = $this->createAddress(['member_id' => $this->member->id]);

        $response = $this->get(
            '/crm/members/' . $this->member->id . '/addresses/' . $address->id . '/edit'
        );

        $this->assertResponseStatus(200, $response);
    }

    public function test_edit_pre_populates_address_fields(): void
    {
        $address = $this->createAddress([
            'member_id' => $this->member->id,
            'address_line_1' => '42 Prepopulate Road',
            'city' => 'Manchester',
            'postcode' => 'M1 1AA',
        ]);

        $response = $this->get(
            '/crm/members/' . $this->member->id . '/addresses/' . $address->id . '/edit'
        );

        $content = $response->getContent();
        $this->assertStringContainsString('42 Prepopulate Road', $content);
        $this->assertStringContainsString('Manchester', $content);
        $this->assertStringContainsString('M1 1AA', $content);
    }

    // ── Update (POST) ─────────────────────────────────────────────────────────

    public function test_edit_redirects_when_address_belongs_to_different_member(): void
    {
        $otherMember = $this->createMember();
        $address = $this->createAddress(['member_id' => $otherMember->id]);

        $response = $this->get(
            '/crm/members/' . $this->member->id . '/addresses/' . $address->id . '/edit'
        );

        $this->assertResponseStatus(302, $response);
    }

    public function test_edit_redirects_when_member_not_found(): void
    {
        $address = $this->createAddress(['member_id' => $this->member->id]);

        $response = $this->get(
            '/crm/members/999999/addresses/' . $address->id . '/edit'
        );

        $this->assertResponseStatus(302, $response);
    }

    public function test_update_persists_changed_fields(): void
    {
        $address = $this->createAddress([
            'member_id' => $this->member->id,
            'address_line_1' => 'Old Street',
            'city' => 'OldCity',
            'type' => 'shipping',
        ]);

        $response = $this->post(
            '/crm/members/' . $this->member->id . '/addresses/' . $address->id,
            [
                'type' => 'billing',
                'address_line_1' => 'New Street',
                'city' => 'NewCity',
                'country' => 'GB',
            ]
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        $this->assertDatabaseHas('addresses', [
            'id' => $address->id,
            'type' => 'billing',
            'address_line_1' => 'New Street',
            'city' => 'NewCity',
        ]);
    }

//    public function test_update_returns_401_for_unauthenticated_agent(): void
//    {
//        $address = $this->createAddress(['member_id' => $this->member->id]);
//
//        $response = $this->post(
//            '/crm/members/' . $this->member->id . '/addresses/' . $address->id,
//            $this->validAddressPayload()
//        );
//
//        $this->assertResponseStatus(401, $response);
//    }

    public function test_update_returns_422_when_required_fields_missing(): void
    {
        $address = $this->createAddress(['member_id' => $this->member->id]);

        $response = $this->post(
            '/crm/members/' . $this->member->id . '/addresses/' . $address->id,
            ['type' => 'both']   // missing address_line_1, city, country
        );

        $this->assertResponseStatus(422, $response);
    }

    // ── Destroy (DELETE) ──────────────────────────────────────────────────────

    public function test_update_returns_404_when_address_belongs_to_different_member(): void
    {
        $otherMember = $this->createMember();
        $address = $this->createAddress(['member_id' => $otherMember->id]);

        $response = $this->post(
            '/crm/members/' . $this->member->id . '/addresses/' . $address->id,
            $this->validAddressPayload()
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_update_returns_404_when_member_not_found(): void
    {
        $address = $this->createAddress(['member_id' => $this->member->id]);

        $response = $this->postForSite(
            '/crm/members/999999/addresses/' . $address->id,
            $this->validAddressPayload()
        );

        $this->assertResponseStatus(404, $response);
    }

//    public function test_destroy_returns_401_for_unauthenticated_agent(): void
//    {
//        $address = $this->createAddress(['member_id' => $this->member->id]);
//
//        $response = $this->delete(
//            '/crm/members/' . $this->member->id . '/addresses/' . $address->id
//        );
//
//        $this->assertResponseStatus(401, $response);
//    }

    public function test_destroy_deletes_address_belonging_to_member(): void
    {
        $address = $this->createAddress(['member_id' => $this->member->id]);

        $response = $this->delete(
            '/crm/members/' . $this->member->id . '/addresses/' . $address->id
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
    }

    // ── Set Default (POST) ────────────────────────────────────────────────────

    public function test_destroy_returns_404_when_address_belongs_to_different_member(): void
    {
        $otherMember = $this->createMember();
        $address = $this->createAddress(['member_id' => $otherMember->id]);

        $response = $this->delete(
            '/crm/members/' . $this->member->id . '/addresses/' . $address->id
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_destroy_returns_404_when_member_not_found(): void
    {
        $address = $this->createAddress(['member_id' => $this->member->id]);

        $response = $this->delete(
            '/crm/members/999999/addresses/' . $address->id
        );

        $this->assertResponseStatus(404, $response);
    }

//    public function test_set_default_returns_401_for_unauthenticated_agent(): void
//    {
//        $address = $this->createAddress(['member_id' => $this->member->id]);
//
//        $response = $this->post(
//            '/crm/members/' . $this->member->id . '/addresses/' . $address->id . '/default'
//        );
//
//        $this->assertResponseStatus(401, $response);
//    }

    public function test_set_default_marks_address_as_default(): void
    {
        $addr1 = $this->createAddress(['member_id' => $this->member->id, 'is_default' => true, 'type' => 'shipping']);
        $addr2 = $this->createAddress(['member_id' => $this->member->id, 'is_default' => false, 'type' => 'shipping']);

        $response = $this->post(
            '/crm/members/' . $this->member->id . '/addresses/' . $addr2->id . '/default'
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        $this->assertDatabaseHas('addresses', ['id' => $addr2->id, 'is_default' => 1]);
        $this->assertDatabaseHas('addresses', ['id' => $addr1->id, 'is_default' => 0]);
    }

    // ── Setup / helpers ───────────────────────────────────────────────────────

    public function test_set_default_returns_404_when_address_belongs_to_different_member(): void
    {
        $otherMember = $this->createMember();
        $address = $this->createAddress(['member_id' => $otherMember->id]);

        $response = $this->post(
            '/crm/members/' . $this->member->id . '/addresses/' . $address->id . '/default'
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_set_default_returns_404_when_member_not_found(): void
    {
        $address = $this->createAddress(['member_id' => $this->member->id]);

        $response = $this->postForSite(
            '/crm/members/999999/addresses/' . $address->id . '/default'
        );

        $this->assertResponseStatus(404, $response);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = $this->createMember([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane.doe.' . uniqid() . '@example.com',
            'is_active' => true,
            'anonymous' => false,
        ]);
    }
}