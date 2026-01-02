<?php
namespace App\Tests\Functional\Controllers;

use App\Framework\Authorization\MemberAuth;
use App\Models\Member;
use App\Models\MemberRole;

class MemberAuthControllerTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createDefaultMemberRole();
    }

    protected function tearDown(): void
    {
        MemberAuth::logout();
        $_SESSION = [];
        parent::tearDown();
    }

    private function createDefaultMemberRole()
    {
        $existing = MemberRole::where('slug', 'basic')
            ->where('site_id', $this->siteId)
            ->first();

        if (!$existing) {
            MemberRole::create([
                'site_id' => $this->siteId,
                'name' => 'Basic Member',
                'slug' => 'basic',
                'description' => 'Basic membership level'
            ]);
        }
    }

    public function testShowRegisterFormReturnsSuccessfully()
    {
        $this->unauthenticateMember();
        $response = $this->makeRequest('GET', '/member/register');

        $this->assertResponseStatus(200, $response);
        $this->assertStringContainsString('Registration', $response->getContent());
    }

    public function testRegisterCreatesNewMember()
    {
        $response = $this->makeRequest('POST','/member/register', [
            'first_name' => 'Test',
            'last_name' => 'Member',
            'email' => 'testmember@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1'
        ]);

        $this->assertResponseStatus(302, $response);

        // Verify member was created
        $member = Member::findByEmail('testmember@example.com', $this->siteId);

        $this->assertNotNull($member);
        $this->assertEquals('Test', $member->first_name);
        $this->assertEquals('Member', $member->last_name);
        $this->assertTrue($member->is_active);
        $this->assertEmpty($member->email_verified_at);
    }

    public function testRegisterFailsWithDuplicateEmail()
    {
        // Create first member
        Member::create([
            'site_id' => $this->siteId,
            'email' => 'duplicate@example.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'first_name' => 'First',
            'last_name' => 'Member',
            'is_active' => true
        ]);

        // Try to create duplicate
        $response = $this->makeRequest('POST', '/member/register', [
            'first_name' => 'Second',
            'last_name' => 'Member',
            'email' => 'duplicate@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1'
        ]);

        $this->assertResponseStatus(302, $response);
        // Check for error in session or response
    }

    public function testRegisterFailsWithMismatchedPasswords()
    {
        $response = $this->makeRequest('POST', '/member/register', [
            'first_name' => 'Test',
            'last_name' => 'Member',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'differentpassword',
            'terms' => '1'
        ]);

        $this->assertResponseStatus(302, $response);
    }

    public function testShowLoginFormReturnsSuccessfully()
    {
        $response = $this->getForSite('/member/login');

        $this->assertResponseStatus(200, $response);
        $this->assertStringContainsString('Login', $response->getContent());
    }

    public function testLoginWithValidCredentials()
    {
        // Create active member
        $member = Member::create([
            'site_id' => $this->siteId,
            'email' => 'active@example.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'first_name' => 'Active',
            'last_name' => 'Member',
            'is_active' => true,
            'email_verified_at' => date('Y-m-d H:i:s')
        ]);

        $response = $this->makeRequest('POST', '/member/login', [
            'email' => 'active@example.com',
            'password' => 'password123'
        ]);

        $this->assertResponseStatus(302, $response);

        // Verify redirect to dashboard
        $location = $response->getHeaders()['Location'] ?? '';
        $this->assertStringContainsString('dashboard', $location);
    }

    public function testLoginFailsWithInvalidCredentials()
    {
        $response = $this->makeRequest('POST', '/member/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword'
        ]);

        $this->assertResponseStatus(302, $response);
    }

    public function testLoginFailsWithInactiveAccount()
    {
        // Create inactive member
        Member::create([
            'site_id' => $this->siteId,
            'email' => 'inactive@example.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'first_name' => 'Inactive',
            'last_name' => 'Member',
            'is_active' => false
        ]);

        $response = $this->makeRequest('POST', '/member/login', [
            'email' => 'inactive@example.com',
            'password' => 'password123'
        ]);

        $this->assertResponseStatus(302, $response);
    }

    public function testVerifyEmailWithValidToken()
    {
        // Create member with verification token
        $token = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);

        $member = Member::create([
            'site_id' => $this->siteId,
            'email' => 'toverify@example.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'first_name' => 'To',
            'last_name' => 'Verify',
            'is_active' => false,
            'email_verification_token' => $hashedToken,
            'email_verification_expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours'))
        ]);

        $response = $this->makeRequest('GET', "/verify-email?token={$token}");

        $this->assertResponseStatus(302, $response);

        // Verify member is now activetestVerifyEmailWithValidToken
        $updatedMember = Member::find($member->id);

        $this->assertNotNull($updatedMember->email_verified_at);
        $this->assertTrue($updatedMember->is_active);
    }

    public function testVerifyEmailWithInvalidToken()
    {
        $response = $this->makeRequest('GET', '/verify-email?token=invalid-token');

        $this->assertResponseStatus(302, $response);
    }

    public function testForgotPasswordSendsEmail()
    {
        $member = Member::create([
            'site_id' => $this->siteId,
            'email' => 'forgot@example.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'first_name' => 'Forgot',
            'last_name' => 'Password',
            'is_active' => true
        ]);

        $response = $this->makeRequest('POST', '/member/forgot-password', [
            'email' => 'forgot@example.com'
        ]);

        $this->assertResponseStatus(302, $response);

        // Verify token was created
        $updatedMember = Member::find($member->id);
        $this->assertNotNull($updatedMember->password_reset_token);
    }

    public function testResetPasswordWithValidToken()
    {
        $token = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);

        $member = Member::create([
            'site_id' => $this->siteId,
            'email' => 'reset@example.com',
            'password' => password_hash('oldpassword', PASSWORD_DEFAULT),
            'first_name' => 'Reset',
            'last_name' => 'Password',
            'is_active' => true,
            'password_reset_token' => $hashedToken,
            'password_reset_expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour'))
        ]);

        $response = $this->makeRequest('POST', '/member/reset-password', [
            'token' => $token,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123'
        ]);

        $this->assertResponseStatus(302, $response);

        // Verify password was changed
        $updatedMember = Member::find($member->id);

        $this->assertTrue(password_verify('newpassword123', $updatedMember->password));
        $this->assertNull($updatedMember->password_reset_token);
    }

    public function testDashboardRequiresAuthentication()
    {
        $response = $this->makeRequest( 'GET','/member/dashboard');

        $this->assertResponseStatus(302, $response);

        $location = $response->getHeaders()['Location'] ?? '';
        $this->assertStringContainsString('login', $location);
    }

    public function testAuthenticatedMemberCanAccessDashboard()
    {
        // Create and authenticate member
        $member = Member::create([
            'site_id' => $this->siteId,
            'email' => 'dashboard@example.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'first_name' => 'Dashboard',
            'last_name' => 'User',
            'is_active' => true,
            'email_verified_at' => date('Y-m-d H:i:s')
        ]);

        // Simulate login
        $this->makeRequest('POST', '/member/login', [
            'email' => 'dashboard@example.com',
            'password' => 'password123'
        ]);

        $response = $this->makeRequest('GET', '/member/dashboard');

        $this->assertResponseStatus(200, $response);
        $this->assertStringContainsString('Dashboard', $response->getContent());
    }

    public function testLogoutClearsSession()
    {
        // Create and login member
        $member = Member::create([
            'site_id' => $this->siteId,
            'email' => 'logout@example.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'first_name' => 'Logout',
            'last_name' => 'Test',
            'is_active' => true
        ]);

        $this->makeRequest('POST', '/member/login', [
            'email' => 'logout@example.com',
            'password' => 'password123'
        ]);

        // Logout
        $response = $this->makeRequest('POST', '/member/logout');

        $this->assertResponseStatus(302, $response);

        // Verify can't access dashboard anymore
        $dashboardResponse = $this->makeRequest('GET', '/member/dashboard');
        $this->assertResponseStatus(302, $dashboardResponse);
    }
}