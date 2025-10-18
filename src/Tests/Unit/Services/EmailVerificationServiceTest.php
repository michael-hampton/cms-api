<?php

namespace App\Tests\Unit\Services;

use App\Models\Member;
use App\Services\EmailVerificationService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use PHPUnit\Framework\TestCase;

class EmailVerificationServiceTest extends FunctionalTestCase
{
    private EmailVerificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EmailVerificationService();
        $_SESSION['site_id'] = $this->siteId;
    }

    public function testGenerateVerificationTokenCreatesToken()
    {
        // Create test member
        $memberId = $this->database->insert('members', [
            'site_id' => $this->siteId,
            'email' => 'token-test@test-verification.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'first_name' => 'Token',
            'last_name' => 'Test',
            'is_active' => false,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        $member = Member::find($memberId);
        $this->assertNotNull($member);

        // Generate token
        $token = $this->service->generateVerificationToken($member);

        // Assert token is a string and has reasonable length
        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token)); // bin2hex(32 bytes) = 64 chars

        // Verify token was stored in database (hashed)
        $updatedMember = Member::find($memberId);
        $this->assertNotNull($updatedMember->email_verification_token);
        $this->assertEquals(64, strlen($updatedMember->email_verification_token)); // SHA256 hash length

        // Verify expiration was set
        $this->assertNotNull($updatedMember->email_verification_expires_at);

        // Verify expiration is in the future (within 24 hours)
        $expiresAt = strtotime($updatedMember->email_verification_expires_at);
        $now = time();
        $this->assertGreaterThan($now, $expiresAt);
        $this->assertLessThan($now + (25 * 3600), $expiresAt); // Less than 25 hours

        // Cleanup
        $this->database->delete('members', ['id' => $memberId]);
    }

    public function testGenerateVerificationTokenHashesToken()
    {
        // Create test member
        $memberId = $this->database->insert('members', [
            'site_id' => $this->siteId,
            'email' => 'hash-test@test-verification.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'first_name' => 'Hash',
            'last_name' => 'Test',
            'is_active' => false,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        $member = Member::find($memberId);
        $token = $this->service->generateVerificationToken($member);

        // Get stored token from database
        $updatedMember = Member::find($memberId);
        $storedHash = $updatedMember->email_verification_token;

        // Verify that stored token is NOT the same as plain token (it's hashed)
        $this->assertNotEquals($token, $storedHash);

        // Verify that hashing the plain token produces the stored hash
        $this->assertEquals(hash('sha256', $token), $storedHash);

        // Cleanup
        $this->database->delete('members', ['id' => $memberId]);
    }

    public function testVerifyWithValidToken()
    {
        // Create member with verification token
        $plainToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $plainToken);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $memberId = $this->database->insert('members', [
            'site_id' => $this->siteId,
            'email' => 'valid-verify@test-verification.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'first_name' => 'Valid',
            'last_name' => 'Verify',
            'is_active' => false,
            'email_verification_token' => $hashedToken,
            'email_verification_expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Verify the token
        $result = $this->service->verify($plainToken, $this->siteId);

        $this->assertTrue($result, 'Verification should succeed with valid token');

        // Check that member is now verified
        $member = Member::find($memberId);
        $this->assertNotNull($member->email_verified_at);
        $this->assertNull($member->email_verification_token);
        $this->assertNull($member->email_verification_expires_at);
        $this->assertTrue($member->is_active);

        // Cleanup
        $this->database->delete('members', ['id' => $memberId]);
    }

    public function testVerifyWithInvalidToken()
    {
        $result = $this->service->verify('invalid-token-' . uniqid(), $this->siteId);;

        $this->assertFalse($result, 'Verification should fail with invalid token');
    }

    public function testVerifyWithExpiredToken()
    {
        // Create member with expired verification token
        $plainToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $plainToken);
        $expiredAt = date('Y-m-d H:i:s', strtotime('-1 hour')); // Expired 1 hour ago

        $memberId = $this->database->insert('members', [
            'site_id' => $this->siteId,
            'email' => 'expired-verify@test-verification.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'first_name' => 'Expired',
            'last_name' => 'Verify',
            'is_active' => false,
            'email_verification_token' => $hashedToken,
            'email_verification_expires_at' => $expiredAt,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Try to verify with expired token
        $result = $this->service->verify($plainToken, $this->siteId);;

        $this->assertFalse($result, 'Verification should fail with expired token');

        // Verify member is still not verified
        $member = Member::find($memberId);
        $this->assertNull($member->email_verified_at);
        $this->assertFalse($member->is_active);

        // Cleanup
        $this->database->delete('members', ['id' => $memberId]);
    }

    public function testVerifyActivatesMember()
    {
        // Create inactive member with valid token
        $plainToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $plainToken);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $memberId = $this->database->insert('members', [
            'site_id' => $this->siteId,
            'email' => 'activate-test@test-verification.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'first_name' => 'Activate',
            'last_name' => 'Test',
            'is_active' => false,
            'email_verification_token' => $hashedToken,
            'email_verification_expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Before verification
        $memberBefore = Member::find($memberId);
        $this->assertFalse($memberBefore->is_active);
        $this->assertNull($memberBefore->email_verified_at);

        // Verify
        $this->service->verify($plainToken, $this->siteId);;

        // After verification
        $memberAfter = Member::find($memberId);
        $this->assertTrue($memberAfter->is_active, 'Member should be activated after verification');
        $this->assertNotNull($memberAfter->email_verified_at, 'email_verified_at should be set');

        // Cleanup
        $this->database->delete('members', ['id' => $memberId]);
    }

    public function testVerifyClearsVerificationData()
    {
        // Create member with verification token
        $plainToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $plainToken);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $memberId = $this->database->insert('members', [
            'site_id' => $this->siteId,
            'email' => 'clear-test@test-verification.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'first_name' => 'Clear',
            'last_name' => 'Test',
            'is_active' => false,
            'email_verification_token' => $hashedToken,
            'email_verification_expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Verify
        $this->service->verify($plainToken, $this->siteId);

        // Check that verification data is cleared
        $member = Member::find($memberId);
        $this->assertNull($member->email_verification_token, 'Token should be cleared');
        $this->assertNull($member->email_verification_expires_at, 'Expiration should be cleared');

        // Cleanup
        $this->database->delete('members', ['id' => $memberId]);
    }

    public function testSendVerificationEmailDoesNotThrowException()
    {
        // Create test member
        $memberId = $this->database->insert('members', [
            'site_id' => $this->siteId,
            'email' => 'email-test@test-verification.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'first_name' => 'Email',
            'last_name' => 'Test',
            'is_active' => false,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        $member = Member::find($memberId);
        $token = 'test-token-' . uniqid();

        // This test just ensures no exception is thrown
        // In production, you'd mock the mail function
        try {
            $this->service->sendVerificationEmail($member, $token);
            $this->assertTrue(true, 'Email sending should not throw exception');
        } catch (\Exception $e) {
            $this->fail('Email sending threw exception: ' . $e->getMessage());
        }

        // Cleanup
        $this->database->delete('members', ['id' => $memberId]);
    }

    public function testMultipleVerificationAttemptsWithSameToken()
    {
        // Create member with valid token
        $plainToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $plainToken);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $memberId = $this->database->insert('members', [
            'site_id' => $this->siteId,
            'email' => 'multiple-test@test-verification.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'first_name' => 'Multiple',
            'last_name' => 'Test',
            'is_active' => false,
            'email_verification_token' => $hashedToken,
            'email_verification_expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // First verification should succeed
        $firstResult = $this->service->verify($plainToken, $this->siteId);;
        $this->assertTrue($firstResult);

        // Second verification with same token should fail (token was cleared)
        $secondResult = $this->service->verify($plainToken, $this->siteId);
        $this->assertFalse($secondResult, 'Second verification attempt should fail');

        // Cleanup
        $this->database->delete('members', ['id' => $memberId]);
    }
}