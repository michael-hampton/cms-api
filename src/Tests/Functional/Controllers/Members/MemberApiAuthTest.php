<?php

namespace App\Tests\Functional\Controllers\Members;

use App\Models\Member;
use App\Models\MemberRole;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class MemberApiAuthTest extends FunctionalTestCase
{
    public function testMemberApiLoginReturnsBearerToken(): void
    {
        $email = 'member-api-' . uniqid() . '@example.com';

        Member::create([
            'site_id' => $this->siteId,
            'email' => $email,
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'first_name' => 'Member',
            'last_name' => 'Api',
            'is_active' => true,
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);

        $response = $this->postForSiteUnauthenticated('/api/member/auth/login', [
            'email' => $email,
            'password' => 'password123',
        ], [], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $this->assertResponseStatus(200, $response);

        $payload = json_decode($response->getContent(), true);
        $this->assertTrue($payload['success']);
        $this->assertNotEmpty($payload['token']);
        $this->assertSame($email, $payload['member']['email']);
    }

    public function testMemberDashboardEndpointsRequireValidMemberToken(): void
    {
        $response = $this->getForSiteUnauthenticated('/api/member/dashboard/overview');

        $this->assertResponseStatus(401, $response);
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!MemberRole::where('slug', 'basic')->where('site_id', $this->siteId)->first()) {
            MemberRole::create([
                'site_id' => $this->siteId,
                'name' => 'Basic Member',
                'slug' => 'basic',
                'description' => 'Basic membership level',
            ]);
        }
    }
}
