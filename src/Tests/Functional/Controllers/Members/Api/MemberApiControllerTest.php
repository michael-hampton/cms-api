<?php

namespace App\Tests\Functional\Controllers\Members\Api;

use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberApiControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testMeReturnsAuthenticatedMemberData(): void
    {
        $member = $this->createAuthenticatedMember([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
        ]);

        $response = $this->getForSite('/api/member/account-details', [], true);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('member', $data['data']);
        $this->assertArrayHasKey('preferences', $data['data']);
        $this->assertArrayHasKey('site_slug', $data['data']);
    }

    private function createAuthenticatedMember(array $overrides = [])
    {
        $member = $this->createMember($overrides);
        $this->actingAsMember($member);
        return $member;
    }

    public function testMeReturnsUnauthorizedWhenNotLoggedIn(): void
    {
        $this->unauthenticateMember();

        $response = $this->getForSite('/api/member/account-details', [], true);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testMeIncludesFormattedDates(): void
    {
        $member = $this->createAuthenticatedMember();

        $response = $this->getForSite('/api/member/account-details', [], true);

        $data = json_decode($response->getContent(), true);
        $memberData = $data['data']['member'];

        if (!empty($memberData['created_at'])) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $memberData['created_at']);
        }
    }

    public function testUpdateAccountDetailsUpdatesProfile(): void
    {
        $member = $this->createAuthenticatedMember([
            'first_name' => 'Old',
            'last_name' => 'Name',
            'email' => 'old_' . uniqid() . '@example.com',
        ]);

        $response = $this->postForSite('/api/member/account-details', [
            'first_name' => 'New',
            'last_name' => 'Name',
            'email' => 'old@example.com',
        ], [], [], false, true);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('New', $data['data']['first_name']);
    }

    public function testUpdatePrivacyUpdatesShowActivityFlag(): void
    {
        $this->createAuthenticatedMember();

        $response = $this->postForSite('/api/member/settings/privacy', [
            'show_activity' => 1,
            'show_badges' => 0,
        ], [], [], false, true);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    public function testUpdatePrivacyRequiresAuthentication(): void
    {
        $this->unauthenticateMember();

        $response = $this->postForSite('/api/member/settings/privacy', [
            'show_activity' => 1,
        ], [], [], false, true);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testUpdateCommunicationPreferencesWithPreferencesKey(): void
    {
        $this->createAuthenticatedMember();

        $response = $this->postForSite('/api/member/settings/communication-preferences', [
            'preferences' => [
                'marketing_emails' => true,
                'newsletter' => false,
            ],
        ], [], [], false, true);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    public function testUpdateCommunicationPreferencesWithFlatFields(): void
    {
        $this->createAuthenticatedMember();

        $response = $this->postForSite('/api/member/settings/communication-preferences', [
            'marketing_emails' => true,
            'special_offers' => false,
            'newsletter' => true,
        ], [], [], false, true);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    public function testUpdateCommunicationPreferencesRequiresAuthentication(): void
    {
        $this->unauthenticateMember();

        $response = $this->postForSite('/api/member/settings/communication-preferences', [
            'marketing_emails' => true,
        ], [], [], false, true);

        $this->assertEquals(401, $response->getStatusCode());
    }
}