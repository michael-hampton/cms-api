<?php

namespace App\Tests\Unit\Authorization;

use App\Framework\Authorization\MemberAuth;
use App\Framework\Session\Session;
use App\Models\Member;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use PHPUnit\Framework\TestCase;

class MemberAuthTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Start session for testing
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Clear any existing auth state
        MemberAuth::logout();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        MemberAuth::logout();
        $_SESSION = [];
        $this->cleanupDatabase();
        parent::tearDown();
    }

    public function testAttemptWithValidCredentials()
    {
        // Create a test member
        $password = 'testpassword123';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $member = Member::where('email', 'validmember@example.com')->first();

        if(!empty($member)) {
            $memberId = $member->id;
        } else {
            $memberId = $this->database->insert('members', [
                'site_id' => $this->siteId,
                'email' => 'validmember@example.com',
                'password' => $hashedPassword,
                'first_name' => 'Valid',
                'last_name' => 'Member',
                'is_active' => 1,
                'email_verified_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }

        // Mock SiteContext
        $_SESSION['site_id'] = $this->siteId;

        // Attempt login
        $result = MemberAuth::attempt([
            'email' => 'validmember@example.com',
            'password' => $password
        ], 1);

        $this->assertTrue($result, 'Login should succeed with valid credentials');
        $this->assertTrue(MemberAuth::check(), 'Member should be authenticated');

        $member = MemberAuth::member();
        $this->assertNotNull($member);
        $this->assertEquals('validmember@example.com', $member->email);
        $this->assertEquals('Valid', $member->firstName);
        $this->assertEquals('Member', $member->lastName);

        // Verify session data
        $this->assertEquals($memberId, Session::get('member_id'));
        $this->assertEquals('validmember@example.com', Session::get('member_email'));
        $this->assertTrue(Session::get('member_authenticated'));

        // Cleanup
        $this->database->delete('members', ['id' => $memberId]);
    }

    public function testAttemptWithInvalidPassword()
    {
        // Create a test member
        $password = 'correctpassword';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $memberId = $this->database->insert('members', [
            'site_id' => $this->siteId,
            'email' => 'testmember@example.com',
            'password' => $hashedPassword,
            'first_name' => 'Test',
            'last_name' => 'Member',
            'is_active' => 1,
            'email_verified_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        $_SESSION['site_id'] = $this->siteId;

        // Attempt login with wrong password
        $result = MemberAuth::attempt([
            'email' => 'testmember@example.com',
            'password' => 'wrongpassword'
        ], 1);

        $this->assertFalse($result, 'Login should fail with invalid password');
        $this->assertFalse(MemberAuth::check(), 'Member should not be authenticated');
        $this->assertNull(MemberAuth::member());

        // Cleanup
        $this->database->delete('members', ['id' => $memberId]);
    }

    public function testAttemptWithNonexistentEmail()
    {
        $_SESSION['site_id'] = $this->siteId;

        $result = MemberAuth::attempt([
            'email' => 'nonexistent@example.com',
            'password' => 'password123'
        ], 1);

        $this->assertFalse($result);
        $this->assertFalse(MemberAuth::check());
    }

    public function testAttemptWithInactiveAccount()
    {
        // Create inactive member
        $password = 'testpassword';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $memberId = $this->database->insert('members', [
            'site_id' => $this->siteId,
            'email' => 'inactive@example.com',
            'password' => $hashedPassword,
            'first_name' => 'Inactive',
            'last_name' => 'Member',
            'is_active' => 0, // Inactive
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        $_SESSION['site_id'] = $this->siteId;

        $result = MemberAuth::attempt([
            'email' => 'inactive@example.com',
            'password' => $password
        ], 1);

        $this->assertFalse($result, 'Login should fail for inactive account');
        $this->assertFalse(MemberAuth::check());

        // Cleanup
        $this->database->delete('members', ['id' => $memberId]);
    }

    public function testAttemptWithUnverifiedEmail()
    {
        // Create member without email verification
        $password = 'testpassword';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $memberId = $this->database->insert('members', [
            'site_id' => $this->siteId,
            'email' => 'unverified@example.com',
            'password' => $hashedPassword,
            'first_name' => 'Unverified',
            'last_name' => 'Member',
            'is_active' => 1,
            'email_verified_at' => null, // Not verified
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        $_SESSION['site_id'] = $this->siteId;

        $result = MemberAuth::attempt([
            'email' => 'unverified@example.com',
            'password' => $password
        ], 1);

        $this->assertFalse($result, 'Login should fail for unverified email');
        $this->assertFalse(MemberAuth::check());

        // Cleanup
        $this->database->delete('members', ['id' => $memberId]);
    }

    public function testAttemptWithInvalidCredentials()
    {
        $result = MemberAuth::attempt([
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword'
        ], 1);

        $this->assertFalse($result);
    }

    public function testCheckReturnsFalseWhenNotAuthenticated()
    {
        $this->assertFalse(MemberAuth::check());
    }

    public function testCheckReturnsTrueWhenAuthenticated()
    {
        // Create and login member
        $password = 'testpassword';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $memberId = $this->database->insert('members', [
            'site_id' => $this->siteId,
            'email' => 'authenticated@example.com',
            'password' => $hashedPassword,
            'first_name' => 'Auth',
            'last_name' => 'Member',
            'is_active' => 1,
            'email_verified_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        $_SESSION['site_id'] = $this->siteId;

        MemberAuth::attempt([
            'email' => 'authenticated@example.com',
            'password' => $password
        ], 1);

        $this->assertTrue(MemberAuth::check());

        // Cleanup
        $this->database->delete('members', ['id' => $memberId]);
    }

    public function testGuestReturnsFalseWhenAuthenticated()
    {
        // Create and login member
        $password = 'testpassword';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $memberId = $this->database->insert('members', [
            'site_id' => $this->siteId,
            'email' => 'guest@example.com',
            'password' => $hashedPassword,
            'first_name' => 'Guest',
            'last_name' => 'Test',
            'is_active' => 1,
            'email_verified_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        $_SESSION['site_id'] = $this->siteId;

        MemberAuth::attempt([
            'email' => 'guest@example.com',
            'password' => $password
        ], 1);

        $this->assertFalse(MemberAuth::guest());

        // Cleanup
        $this->database->delete('members', ['id' => $memberId]);
    }

    public function testLogout()
    {
        // Create and login member
        $password = 'testpassword';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $memberId = $this->database->insert('members', [
            'site_id' => $this->siteId,
            'email' => 'logout@example.com',
            'password' => $hashedPassword,
            'first_name' => 'Logout',
            'last_name' => 'Test',
            'is_active' => 1,
            'email_verified_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        $_SESSION['site_id'] = $this->siteId;

        MemberAuth::attempt([
            'email' => 'logout@example.com',
            'password' => $password
        ], 1);

        $this->assertTrue(MemberAuth::check());

        // Logout
        MemberAuth::logout();

        $this->assertFalse(MemberAuth::check());
        $this->assertNull(MemberAuth::member());
        $this->assertNull(Session::get('member_id'));
        $this->assertNull(Session::get('member_authenticated'));

        // Cleanup
        $this->database->delete('members', ['id' => $memberId]);
    }

    public function testIdReturnsMemberIdWhenAuthenticated()
    {
        // Create and login member
        $password = 'testpassword';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $memberId = $this->database->insert('members', [
            'site_id' => $this->siteId,
            'email' => 'idtest@example.com',
            'password' => $hashedPassword,
            'first_name' => 'ID',
            'last_name' => 'Test',
            'is_active' => 1,
            'email_verified_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        $_SESSION['site_id'] = $this->siteId;

        MemberAuth::attempt([
            'email' => 'idtest@example.com',
            'password' => $password
        ], 1);

        $this->assertEquals($memberId, MemberAuth::id());

        // Cleanup
        $this->database->delete('members', ['id' => $memberId]);
    }

    public function testMemberHasRoles()
    {
        // Create member
        $password = 'testpassword';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $memberId = $this->database->insert('members', [
            'site_id' => $this->siteId,
            'email' => 'roletest@example.com',
            'password' => $hashedPassword,
            'first_name' => 'Role',
            'last_name' => 'Test',
            'is_active' => 1,
            'email_verified_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Create roles
        $basicRoleId = $this->database->insert('member_roles', [
            'site_id' => $this->siteId,
            'name' => 'Basic Member',
            'slug' => 'basic',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        $premiumRoleId = $this->database->insert('member_roles', [
            'site_id' => $this->siteId,
            'name' => 'Premium Member',
            'slug' => 'premium',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Assign roles
        $this->database->insert('member_role_assignments', [
            'member_id' => $memberId,
            'role_id' => $basicRoleId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        $this->database->insert('member_role_assignments', [
            'member_id' => $memberId,
            'role_id' => $premiumRoleId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        $_SESSION['site_id'] = $this->siteId;

        // Login
        MemberAuth::attempt([
            'email' => 'roletest@example.com',
            'password' => $password
        ], 1);

        $member = MemberAuth::member();

        $this->assertNotNull($member);
        $this->assertTrue($member->hasRole('basic'));
        $this->assertTrue($member->hasRole('premium'));
        $this->assertFalse($member->hasRole('vip'));
        $this->assertTrue($member->hasAnyRole(['basic', 'vip']));
        $this->assertFalse($member->hasAnyRole(['vip', 'admin']));

        // Cleanup
        $this->database->delete('member_role_assignments', ['member_id' => $memberId]);
        $this->database->delete('member_roles', ['id' => $basicRoleId]);
        $this->database->delete('member_roles', ['id' => $premiumRoleId]);
        $this->database->delete('members', ['id' => $memberId]);
    }

    public function testSessionPersistence()
    {
        // Create and login member
        $password = 'testpassword';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $memberId = $this->database->insert('members', [
            'site_id' => $this->siteId,
            'email' => 'session@example.com',
            'password' => $hashedPassword,
            'first_name' => 'Session',
            'last_name' => 'Test',
            'is_active' => 1,
            'email_verified_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        $_SESSION['site_id'] = $this->siteId;

        MemberAuth::attempt([
            'email' => 'session@example.com',
            'password' => $password
        ], 1);

        // Clear static property to simulate new request
        $reflection = new \ReflectionClass(MemberAuth::class);
        $property = $reflection->getProperty('member');
        $property->setAccessible(true);
        $property->setValue(null, null);

        // Member should still be authenticated from session
        $this->assertTrue(MemberAuth::check());
        $member = MemberAuth::member();
        $this->assertNotNull($member);
        $this->assertEquals('session@example.com', $member->email);

        // Cleanup
        $this->database->delete('members', ['id' => $memberId]);
    }

//    public function testExpiredRoleIsNotActive()
//    {
//        // Create member
//        $password = 'testpassword';
//        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
//
//        $memberId = $this->database->insert('members', [
//            'site_id' => $this->siteId,
//            'email' => 'expired@example.com',
//            'password' => $hashedPassword,
//            'first_name' => 'Expired',
//            'last_name' => 'Role',
//            'is_active' => 1,
//            'email_verified_at' => date('Y-m-d H:i:s'),
//            'created_at' => date('Y-m-d H:i:s'),
//            'updated_at' => date('Y-m-d H:i:s')
//        ]);
//
//        // Create role
//        $roleId = $this->database->insert('member_roles', [
//            'site_id' => $this->siteId,
//            'name' => 'Trial Member',
//            'slug' => 'trial',
//            'created_at' => date('Y-m-d H:i:s'),
//            'updated_at' => date('Y-m-d H:i:s')
//        ]);
//
//        // Assign expired role
//        $expiredDate = date('Y-m-d H:i:s', strtotime('-1 day'));
//        $this->database->insert('member_role_assignments', [
//            'member_id' => $memberId,
//            'role_id' => $roleId,
//            'expires_at' => $expiredDate,
//            'created_at' => date('Y-m-d H:i:s'),
//            'updated_at' => date('Y-m-d H:i:s')
//        ]);
//
//        $_SESSION['site_id'] = $this->siteId;
//
//        // Login
//        MemberAuth::attempt([
//            'email' => 'expired@example.com',
//            'password' => $password
//        ], 1);
//
//        $member = MemberAuth::member();
//
//        // Check that expired role is not active
//        $this->assertNotNull($member);
//        $this->assertFalse($member->hasRole('trial'), 'Expired role should not be active');
//
//        // Cleanup
//        $this->database->delete('member_role_assignments', ['member_id' => $memberId]);
//        $this->database->delete('member_roles', ['id' => $roleId]);
//        $this->database->delete('members', ['id' => $memberId]);
//    }
}