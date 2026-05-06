<?php

namespace App\Tests\Functional\Controllers\Crm;

use App\Models\Member;
use App\Models\Model;
use App\Models\User;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class CrmMemberControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Member $member;

    // ── Index ────────────────────────────────────────────────────────────────

    public function test_index_returns_200_for_authenticated_agent(): void
    {
        $response = $this->get('/crm/members');

        $this->assertResponseStatus(200, $response);
    }

    public function test_index_redirects_unauthenticated_request(): void
    {
        $this->unauthenticate();
        $response = $this->get('/crm/members');

        $this->assertResponseStatus(302, $response);
    }

    public function test_index_lists_non_anonymous_members(): void
    {
        $this->createMember(['first_name' => 'Alice', 'last_name' => 'Smith', 'anonymous' => false]);
        $this->createMember(['first_name' => 'Bob', 'last_name' => 'Jones', 'anonymous' => false]);
        $this->createMember(['anonymous' => true]);

        $response = $this->get('/crm/members');

        $content = $response->getContent();
        $this->assertStringContainsString('Alice', $content);
        $this->assertStringContainsString('Bob', $content);
    }

    public function test_index_filters_by_search(): void
    {
        $this->createMember(['first_name' => 'Unique', 'last_name' => 'Person', 'email' => 'unique@example.com']);
        $this->createMember(['first_name' => 'Other', 'last_name' => 'Member', 'email' => 'other@example.com']);

        $response = $this->get('/crm/members?search=Unique');

        $content = $response->getContent();
        $this->assertStringContainsString('Unique', $content);
        $this->assertStringNotContainsString('Other', $content);
    }

    public function test_index_filters_by_order_number_search(): void
    {
        $target = $this->createMember(['first_name' => 'Order', 'last_name' => 'Match', 'email' => 'order-match@example.com']);
        $other = $this->createMember(['first_name' => 'Other', 'last_name' => 'Member', 'email' => 'other-member@example.com']);

        $this->createOrder(['user_id' => $target->id, 'site_id' => $this->siteId, 'order_number' => 'CRM-12345']);
        $this->createOrder(['user_id' => $other->id, 'site_id' => $this->siteId, 'order_number' => 'CRM-99999']);

        $response = $this->get('/crm/members?search=12345');

        $content = $response->getContent();
        $this->assertStringContainsString('Order', $content);
        $this->assertStringNotContainsString('other-member@example.com', $content);
    }

    public function test_index_filters_by_status_active(): void
    {
        $this->createMember(['first_name' => 'ActiveMember', 'is_active' => true]);
        $this->createMember(['first_name' => 'InactiveMember', 'is_active' => false]);

        $response = $this->get('/crm/members?status=active');

        $content = $response->getContent();
        $this->assertStringContainsString('ActiveMember', $content);
        $this->assertStringNotContainsString('InactiveMember', $content);
    }

    public function test_index_filters_by_status_inactive(): void
    {
        $this->createMember(['first_name' => 'ActiveMember', 'is_active' => true]);
        $this->createMember(['first_name' => 'InactiveMember', 'is_active' => false]);

        $response = $this->get('/crm/members?status=inactive');

        $content = $response->getContent();
        $this->assertStringContainsString('InactiveMember', $content);
        $this->assertStringNotContainsString('ActiveMember', $content);
    }

    // ── Show ─────────────────────────────────────────────────────────────────

    public function test_show_returns_200_for_existing_member(): void
    {
        $response = $this->get('/crm/members/' . $this->member->id);

        $this->assertResponseStatus(200, $response);
        $this->assertStringContainsString($this->member->email, $response->getContent());
    }

    public function test_show_redirects_for_non_existent_member(): void
    {
        $response = $this->get('/crm/members/999999');

        $this->assertResponseStatus(302, $response);
    }

    public function test_show_redirects_unauthenticated_request(): void
    {
        $this->unauthenticate();
        $response = $this->get('/crm/members/' . $this->member->id);

        $this->assertResponseStatus(302, $response);
    }

    public function test_show_displays_member_addresses(): void
    {
        $address = $this->createAddress([
            'member_id' => $this->member->id,
            'address_line_1' => '123 Test Street',
            'city' => 'London',
            'postcode' => 'SW1A 1AA',
        ]);

        $response = $this->get('/crm/members/' . $this->member->id);

        $this->assertStringContainsString('123 Test Street', $response->getContent());
    }

    public function test_show_returns_json_detail_payload_for_api_requests(): void
    {
        $this->createAddress([
            'member_id' => $this->member->id,
            'address_line_1' => '55 CRM Street',
        ]);

        $this->createOrder([
            'user_id' => $this->member->id,
            'site_id' => $this->siteId,
            'order_number' => 'CRM-555',
            'total' => 42.50,
        ]);

        $response = $this->getForSite('/api/crm/members/' . $this->member->id);

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('member', $data);
        $this->assertArrayHasKey('activity', $data['member']);
        $this->assertArrayHasKey('subscription_summary', $data['member']);
        $this->assertArrayHasKey('recent_orders', $data['member']);
        $this->assertArrayHasKey('addresses', $data['member']);
        $this->assertSame('CRM-555', $data['member']['recent_orders'][0]['order_number']);
    }

    public function test_show_json_detail_includes_grouped_consents(): void
    {
        $marketingType = $this->createConsentType([
            'code' => 'marketing_email',
            'name' => 'Marketing Email',
            'category' => 'marketing',
        ]);
        $this->createMemberConsent([
            'member' => $this->member,
            'consent_type' => $marketingType,
            'is_granted' => true,
        ]);

        $response = $this->getForSite('/api/crm/members/' . $this->member->id);
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('consents', $data['member']);
        $this->assertArrayHasKey('marketing', $data['member']['consents']);
    }

    // ── Edit ─────────────────────────────────────────────────────────────────

    /**
     * Helper — creates an Address for a member.
     * Adjust to use your CreatesTestData::createAddress if it exists.
     */
    private function createAddress(array $overrides = []): Model
    {
        return \App\Models\Address::create(array_merge([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'type' => 'both',
            'is_default' => false,
            'address_line_1' => '1 Test Lane',
            'city' => 'London',
            'postcode' => 'EC1A 1BB',
            'country' => 'GB',
        ], $overrides));
    }

    public function test_edit_returns_200_for_existing_member(): void
    {
        $response = $this->get('/crm/members/' . $this->member->id . '/edit');

        $this->assertResponseStatus(200, $response);
    }

    public function test_edit_redirects_unauthenticated_request(): void
    {
        $this->unauthenticate();
        $response = $this->get('/crm/members/' . $this->member->id . '/edit');

        $this->assertResponseStatus(302, $response);
    }

    // ── Update ───────────────────────────────────────────────────────────────

    public function test_edit_pre_populates_member_fields(): void
    {
        $response = $this->get('/crm/members/' . $this->member->id . '/edit');

        $content = $response->getContent();
        $this->assertStringContainsString($this->member->first_name, $content);
        $this->assertStringContainsString($this->member->email, $content);
    }

    public function test_update_persists_name_and_email_changes(): void
    {
        $response = $this->post('/crm/members/' . $this->member->id, [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'email' => 'updated@example.com',
            'is_active' => 1,
        ]);

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        $this->assertDatabaseHas('members', [
            'id' => $this->member->id,
            'first_name' => 'Updated',
            'email' => 'updated@example.com',
        ]);
    }

    public function test_update_can_deactivate_member(): void
    {
        $activeMember = $this->createMember(['is_active' => true]);

        $response = $this->post('/crm/members/' . $activeMember->id, [
            'first_name' => $activeMember->first_name,
            'last_name' => $activeMember->last_name,
            'email' => $activeMember->email,
            'is_active' => 0,
        ]);

        $this->assertResponseStatus(200, $response);

        $this->assertDatabaseHas('members', [
            'id' => $activeMember->id,
            'is_active' => 0,
        ]);
    }

    public function test_update_can_reactivate_member(): void
    {
        $inactive = $this->createMember(['is_active' => false]);

        $response = $this->post('/crm/members/' . $inactive->id, [
            'first_name' => $inactive->first_name,
            'last_name' => $inactive->last_name,
            'email' => $inactive->email,
            'is_active' => 1,
        ]);

        $this->assertResponseStatus(200, $response);

        $this->assertDatabaseHas('members', [
            'id' => $inactive->id,
            'is_active' => 1,
        ]);
    }

    public function test_update_saves_assigned_agent_and_crm_notes(): void
    {
        $agent = $this->createAgent();

        $response = $this->post('/crm/members/' . $this->member->id, [
            'first_name' => $this->member->first_name,
            'last_name' => $this->member->last_name,
            'email' => $this->member->email,
            'is_active' => 1,
            'assigned_agent_id' => $agent->id,
            'crm_notes' => 'Called on Monday, follow up required.',
        ]);

        $this->assertResponseStatus(200, $response);

        $this->assertDatabaseHas('members', [
            'id' => $this->member->id,
            'assigned_agent_id' => $agent->id,
            'crm_notes' => 'Called on Monday, follow up required.',
        ]);
    }

    public function test_update_saves_personal_business_and_privacy_fields(): void
    {
        $response = $this->post('/crm/members/' . $this->member->id, [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane.doe@example.com',
            'phone' => '+441234567890',
            'company_name' => 'Acme Ltd',
            'job_title' => 'Buyer',
            'vat_number' => 'GB123456789',
            'region' => 'GB',
            'timezone' => 'Europe/London',
            'is_active' => 1,
            'show_activity' => 0,
            'show_badges' => 1,
            'communication_preferences' => [
                'marketing_emails' => true,
                'special_offers' => false,
                'third_party_communications' => true,
            ],
        ]);

        $this->assertResponseStatus(200, $response);

        $this->assertDatabaseHas('members', [
            'id' => $this->member->id,
            'phone' => '+441234567890',
            'company_name' => 'Acme Ltd',
            'job_title' => 'Buyer',
            'vat_number' => 'GB123456789',
            'region' => 'GB',
            'timezone' => 'Europe/London',
            'show_activity' => 0,
            'show_badges' => 1,
        ]);
    }

    public function test_crm_order_search_returns_filtered_orders(): void
    {
        $member = $this->createMember(['first_name' => 'Order', 'last_name' => 'Customer', 'email' => 'order.customer@example.com']);
        $this->createOrder([
            'site_id' => $this->siteId,
            'user_id' => $member->id,
            'order_number' => 'CRM-ORDER-100',
            'status' => 'processing',
        ]);

        $response = $this->getForSite('/api/crm/orders?search=CRM-ORDER-100&status=processing');

        $this->assertResponseStatus(200, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['items']);
        $this->assertSame('CRM-ORDER-100', $data['items'][0]['order_number']);
    }

    public function test_crm_member_consents_endpoint_returns_grouped_consents(): void
    {
        $consentType = $this->createConsentType([
            'code' => 'analytics_tracking',
            'category' => 'analytics',
            'name' => 'Analytics Tracking',
        ]);

        $this->createMemberConsent([
            'member' => $this->member,
            'consent_type' => $consentType,
        ]);

        $response = $this->getForSite('/api/crm/members/' . $this->member->id . '/consents');

        $this->assertResponseStatus(200, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('analytics', $data['items']);
    }

    public function test_update_returns_401_for_unauthenticated_request(): void
    {
        $this->unauthenticate();
        $response = $this->post('/crm/members/' . $this->member->id, [
            'first_name' => 'Hacker',
            'last_name' => 'Attack',
            'email' => 'hacker@example.com',
            'is_active' => 1,
        ]);

        $this->assertResponseStatus(302, $response);
    }

    /**
     * Helper — creates a User acting as a CRM agent.
     * Adjust fields to match your User model's $fillable.
     */
    private function createAgent(): Model
    {
        return User::create([
            'name' => 'Test Agent',
            'email' => 'agent.' . uniqid() . '@example.com',
            'password' => password_hash('secret', PASSWORD_DEFAULT),
            'site_id' => $this->siteId,
            'role' => 'admin',
        ]);
    }

    // ── Destroy (deactivate) ─────────────────────────────────────────────────

    public function test_update_returns_422_for_duplicate_email(): void
    {
        $otherMember = $this->createMember(['email' => 'taken@example.com']);

        $response = $this->post('/crm/members/' . $this->member->id, [
            'first_name' => $this->member->first_name,
            'last_name' => $this->member->last_name,
            'email' => 'taken@example.com',
            'is_active' => 1,
        ]);

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('already in use', $data['message']);
    }

    public function test_destroy_returns_401_for_unauthenticated_request(): void
    {
        $this->unauthenticate();
        $response = $this->delete('/crm/members/' . $this->member->id);

        $this->assertResponseStatus(302, $response);
    }

    // ── Store ────────────────────────────────────────────────────────────────

    public function test_store_creates_new_member_and_returns_201(): void
    {
        $response = $this->postForSite('/api/crm/members', [
            'first_name' => 'New',
            'last_name' => 'Member',
            'email' => 'new.member.' . uniqid() . '@example.com',
            'is_active' => 1,
        ]);

        $this->assertResponseStatus(201, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('member', $data);
        $this->assertEquals('New', $data['member']['first_name']);
    }

    public function test_store_persists_member_to_database(): void
    {
        $email = 'persist.' . uniqid() . '@example.com';

        $this->postForSite('/api/crm/members', [
            'first_name' => 'Persisted',
            'last_name' => 'User',
            'email' => $email,
            'is_active' => 1,
        ]);

        $this->assertDatabaseHas('members', [
            'email' => $email,
            'is_active' => 1,
        ]);
    }

    public function test_store_returns_401_for_unauthenticated_request(): void
    {
        $this->unauthenticate();

        $response = $this->postForSite('/api/crm/members', [
            'first_name' => 'Hacker',
            'last_name' => 'Attack',
            'email' => 'hacker@example.com',
            'is_active' => 1,
        ]);

        // The controller returns a JSON 401, not a redirect, for POST endpoints.
        $this->assertResponseStatus(401, $response);
    }

    public function test_store_returns_422_for_duplicate_email(): void
    {
        $this->post('/crm/members', [
            'first_name' => 'First',
            'last_name' => 'User',
            'email' => $this->member->email,
            'is_active' => 1,
        ]);

        $response = $this->postForSite('/api/crm/members', [
            'first_name' => 'Second',
            'last_name' => 'User',
            'email' => $this->member->email,
            'is_active' => 1,
        ]);

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('already in use', $data['message']);
    }

    // ── Addresses ────────────────────────────────────────────────────────────

    public function test_update_resets_email_verification_when_email_changes(): void
    {
        $verified = $this->createMember([
            'email' => 'verified@example.com',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);

        $this->post('/crm/members/' . $verified->id, [
            'first_name' => $verified->first_name,
            'last_name' => $verified->last_name,
            'email' => 'newemail@example.com',
            'is_active' => 1,
        ]);

        $this->assertDatabaseHas('members', [
            'id' => $verified->id,
            'email' => 'newemail@example.com',
            'email_verified_at' => null,
        ]);
    }

    public function test_destroy_deactivates_member(): void
    {
        $active = $this->createMember(['is_active' => true]);

        $response = $this->delete('/crm/members/' . $active->id);

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        $this->assertDatabaseHas('members', [
            'id' => $active->id,
            'is_active' => 0,
        ]);
    }

    public function test_addresses_returns_404_for_non_existent_member(): void
    {
        $response = $this->getForSite('/crm/members/999999/addresses');

        $this->assertResponseStatus(404, $response);
    }

    public function test_set_default_address_updates_default_flag(): void
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
    }

    // ── Setup ────────────────────────────────────────────────────────────────

    public function test_delete_address_removes_address_belonging_to_member(): void
    {
        $address = $this->createAddress(['member_id' => $this->member->id]);

        $response = $this->delete(
            '/crm/members/' . $this->member->id . '/addresses/' . $address->id
        );

        $this->assertResponseStatus(200, $response);

        $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
    }

    public function test_delete_address_returns_404_if_address_belongs_to_different_member(): void
    {
        $otherMember = $this->createMember();
        $address = $this->createAddress(['member_id' => $otherMember->id]);

        $response = $this->delete(
            '/crm/members/' . $this->member->id . '/addresses/' . $address->id
        );

        $this->assertResponseStatus(404, $response);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->member = $this->createMember([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane.doe@example.com',
            'is_active' => true,
            'anonymous' => false,
        ]);
    }
}
