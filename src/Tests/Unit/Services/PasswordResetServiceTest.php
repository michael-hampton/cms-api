<?php

namespace App\Tests\Unit\Services;

use App\Models\Member;
use App\Services\PasswordResetService;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class PasswordResetServiceTest extends FunctionalTestCase
{
    private PasswordResetService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PasswordResetService();
        $_SESSION['site_id'] = $this->siteId;
    }

    public function testGenerateResetTokenCreatesToken()
    {
        // Create test member
        $memberId = $this->database->insert('members', [
            'site_id' => $this->siteId,
            'email' => 'reset-token@test.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'first_name' => 'Reset',
            'last_name' => 'Test',
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        $member = Member::find($memberId);
        $this->assertNotNull($member);

        // Generate token
        $token = $this->service->generateResetToken($member);

        // Assert token is a string and has reasonable length
        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token)); // bin2hex(32 bytes) = 64 chars

        // Verify token was stored in database (hashed)
        $updatedMember = Member::find($memberId);
        $this->assertNotNull($updatedMember->password_reset_token);
        $this->assertEquals(64, strlen($updatedMember->password_reset_token)); // SHA256 hash length

        // Verify expiration was set
        $this->assertNotNull($updatedMember->password_reset_expires_at);

        // Verify expiration is in the future (within 1 hour)
        $expiresAt = strtotime($updatedMember->password_reset_expires_at);
        $now = time();
        $this->assertGreaterThan($now, $expiresAt);
        $this->assertLessThan($now + (2 * 3600), $expiresAt); // Less than 2 hours

        // Cleanup
        $this->database->delete('members', ['id' => $memberId]);
    }

    public function testGenerateResetTokenHashesToken()
    {
        $memberId = $this->database->insert('members', [
            'site_id' => $this->siteId,
            'email' => 'hash-reset@test.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'first_name' => 'Hash',
            'last_name' => 'Reset',
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        $member = Member::find($memberId);
        $token = $this->service->generateResetToken($member);

        // Get stored token from database
        $updatedMember = Member::find($memberId);
        $storedHash = $updatedMember->password_reset_token;

        // Verify that stored token is NOT the same as plain token (it's hashed)
        $this->assertNotEquals($token, $storedHash);

        // Verify that hashing the plain token produces the stored hash
        $this->assertEquals(hash('sha256', $token), $storedHash);

        // Cleanup
        $this->database->delete('members', ['id' => $memberId]);
    }

    public function testValidateTokenWithValidToken()
    {
        // Create member with reset token
        $plainToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $plainToken);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $memberId = $this->database->insert('members', [
            'site_id' => $this->siteId,
            'email' => 'valid-reset@test.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'first_name' => 'Valid',
            'last_name' => 'Reset',
            'is_active' => true,
            'password_reset_token' => $hashedToken,
            'password_reset_expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Validate the token
        $member = $this->service->validateToken($plainToken, $this->siteId);

        $this->assertNotNull($member, 'Valid token should return member');
        $this->assertEquals($memberId, $member->id);

        // Cleanup
        $this->database->delete('members', ['id' => $memberId]);
    }

    public function testValidateTokenWithInvalidToken()
    {
        $result = $this->service->validateToken('invalid-token-' . uniqid(), $this->siteId);;

        $this->assertNull($result, 'Invalid token should return null');
    }

    public function testValidateTokenWithExpiredToken()
    {
        $plainToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $plainToken);
        $expiredAt = date('Y-m-d H:i:s', strtotime('-1 hour')); // Expired 1 hour ago

        $memberId = $this->database->insert('members', [
            'site_id' => $this->siteId,
            'email' => 'expired-reset@test.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'first_name' => 'Expired',
            'last_name' => 'Reset',
            'is_active' => true,
            'password_reset_token' => $hashedToken,
            'password_reset_expires_at' => $expiredAt,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Try to validate expired token
        $result = $this->service->validateToken($plainToken, $this->siteId);;

        $this->assertNull($result, 'Expired token should return null');

        // Cleanup
        $this->database->delete('members', ['id' => $memberId]);
    }

    public function testResetPasswordWithValidToken()
    {
        $oldPassword = 'oldpassword123';
        $newPassword = 'newpassword456';

        $plainToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $plainToken);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $memberId = $this->database->insert('members', [
            'site_id' => $this->siteId,
            'email' => 'password-reset@test.com',
            'password' => password_hash($oldPassword, PASSWORD_DEFAULT),
            'first_name' => 'Password',
            'last_name' => 'Reset',
            'is_active' => true,
            'password_reset_token' => $hashedToken,
            'password_reset_expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Reset password
        $result = $this->service->resetPassword($plainToken, $newPassword, $this->siteId);

        $this->assertTrue($result, 'Password reset should succeed');

        // Check password was updated
        $member = Member::find($memberId);
        $this->assertTrue(
            password_verify($newPassword, $member->password),
            'New password should be set'
        );
        $this->assertFalse(
            password_verify($oldPassword, $member->password),
            'Old password should not work'
        );

        // Check token was cleared
        $this->assertNull($member->password_reset_token);
        $this->assertNull($member->password_reset_expires_at);

        // Cleanup
        $this->database->delete('members', ['id' => $memberId]);
    }

    public function testResetPasswordWithInvalidToken()
    {
        $result = $this->service->resetPassword('invalid-token', 'newpassword', $this->siteId);;

        $this->assertFalse($result, 'Reset should fail with invalid token');
    }

    public function testResetPasswordClearsResetData()
    {
        $plainToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $plainToken);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $memberId = $this->database->insert('members', [
            'site_id' => $this->siteId,
            'email' => 'clear-reset@test.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'first_name' => 'Clear',
            'last_name' => 'Reset',
            'is_active' => true,
            'password_reset_token' => $hashedToken,
            'password_reset_expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Reset password
        $this->service->resetPassword($plainToken, 'newpassword123', $this->siteId);;

        // Check that reset data is cleared
        $member = Member::find($memberId);
        $this->assertNull($member->password_reset_token, 'Token should be cleared');
        $this->assertNull($member->password_reset_expires_at, 'Expiration should be cleared');

        // Cleanup
        $this->database->delete('members', ['id' => $memberId]);
    }

    public function testMultipleResetAttemptsWithSameToken()
    {
        $plainToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $plainToken);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $memberId = $this->database->insert('members', [
            'site_id' => $this->siteId,
            'email' => 'multiple-reset@test.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'first_name' => 'Multiple',
            'last_name' => 'Reset',
            'is_active' => true,
            'password_reset_token' => $hashedToken,
            'password_reset_expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // First reset should succeed
        $firstResult = $this->service->resetPassword($plainToken, 'newpassword1', $this->siteId);;
        $this->assertTrue($firstResult);

        // Second reset with same token should fail (token was cleared)
        $secondResult = $this->service->resetPassword($plainToken, 'newpassword2', $this->siteId);;;
        $this->assertFalse($secondResult, 'Second reset attempt should fail');

        // Cleanup
        $this->database->delete('members', ['id' => $memberId]);
    }

    public function testSendResetEmailDoesNotThrowException()
    {
        $memberId = $this->database->insert('members', [
            'site_id' => $this->siteId,
            'email' => 'email-reset@test.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'first_name' => 'Email',
            'last_name' => 'Reset',
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        $member = Member::find($memberId);
        $token = 'test-token-' . uniqid();

        try {
            $this->service->sendResetEmail($member, $token);
            $this->assertTrue(true, 'Email sending should not throw exception');
        } catch (\Exception $e) {
            $this->fail('Email sending threw exception: ' . $e->getMessage());
        }

        // Cleanup
        $this->database->delete('members', ['id' => $memberId]);
    }
}