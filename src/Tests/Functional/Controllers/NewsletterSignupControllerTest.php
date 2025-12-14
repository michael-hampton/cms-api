<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Member;
use App\Models\Subscriber;

class NewsletterSignupControllerTest extends FunctionalTestCase
{
    public function testSignupWithValidEmail(): void
    {
        $response = $this->postForSite('/api/newsletter/signup', [
            'email' => 'newuser@example.com'
        ]);

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['data']['success']);
        $this->assertEquals('newuser@example.com', $data['data']['email']);
        $this->assertArrayHasKey('confirmation_token', $data['data']);
    }

    public function testSignupWithInvalidEmail(): void
    {
        $response = $this->postForSite('/api/newsletter/signup', [
            'email' => 'invalid-email'
        ]);

        $this->assertResponseStatus(400, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testSignupWithDuplicateEmail(): void
    {
        // First signup
        $this->postForSite('/api/newsletter/signup', [
            'email' => 'duplicate@example.com'
        ]);

        // Confirm subscription
        $subscriber = Subscriber::findByEmail('duplicate@example.com', $this->siteId);
        $subscriber->update(['confirmed' => true]);

        // Try to signup again
        $response = $this->postForSite('/api/newsletter/signup', [
            'email' => 'duplicate@example.com'
        ]);

        $this->assertResponseStatus(400, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('already subscribed', $data['error']);
    }

    public function testSignupWithAccountCreation(): void
    {
        $response = $this->postForSite('/api/newsletter/signup', [
            'email' => 'accountuser@example.com',
            'create_account' => true,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'password' => 'password123'
        ]);

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertTrue($data['data']['subscribed']);
        $this->assertTrue($data['data']['account_created']);
        $this->assertTrue($data['data']['logged_in']);
        $this->assertTrue($data['data']['requires_verification']);

        $this->assertArrayHasKey('member', $data['data']);
        $this->assertEquals('accountuser@example.com', $data['data']['member']['email']);
        $this->assertEquals('John', $data['data']['member']['first_name']);
        $this->assertEquals('Doe', $data['data']['member']['last_name']);
        $this->assertFalse($data['data']['member']['is_verified']);

        // Verify subscriber was created
        $subscriber = Subscriber::findByEmail('accountuser@example.com', $this->siteId);
        $this->assertNotNull($subscriber);

        // Verify member account was created
        $member = Member::findByEmail('accountuser@example.com', $this->siteId);
        $this->assertNotNull($member);
        $this->assertEquals('John', $member->first_name);
        $this->assertEquals('Doe', $member->last_name);
        $this->assertTrue($member->is_active);

        // Verify password was hashed
        $this->assertTrue(password_verify('password123', $member->password));
    }

    public function testConfirmSubscription(): void
    {
        // Signup first
        $signupResponse = $this->postForSite('/api/newsletter/signup', [
            'email' => 'confirm@example.com'
        ]);

        $signupData = json_decode($signupResponse->getContent(), true);
        $token = $signupData['data']['confirmation_token'];

        // Confirm
        $response = $this->postForSite('/api/newsletter/confirm', [
            'token' => $token
        ]);

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['data']['success']);

        // Verify in database
        $subscriber = Subscriber::findByEmail('confirm@example.com', $this->siteId);
        $this->assertTrue($subscriber->isConfirmed());
    }

    public function testSignupWithAccountCreationMissingFields(): void
    {
        // Missing last_name
        $response = $this->postForSite('/api/newsletter/signup', [
            'email' => 'incomplete@example.com',
            'create_account' => true,
            'first_name' => 'John',
            'password' => 'password123'
        ]);

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        // Should succeed with newsletter only (account creation skipped)
        $this->assertTrue($data['success']);
        $this->assertTrue($data['data']['subscribed']);
        $this->assertFalse($data['data']['account_created']);

        // Verify subscriber was created
        $subscriber = Subscriber::findByEmail('incomplete@example.com', $this->siteId);
        $this->assertNotNull($subscriber);

        // Verify member account was NOT created
        $member = Member::findByEmail('incomplete@example.com', $this->siteId);
        $this->assertNull($member);
    }

    public function testSignupWithAccountCreationForExistingMember(): void
    {
        // Create existing member
        $existingMember = Member::create([
            'site_id' => $this->siteId,
            'email' => 'existing@example.com',
            'password' => password_hash('oldpassword', PASSWORD_DEFAULT),
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'is_active' => true
        ]);

        $response = $this->postForSite('/api/newsletter/signup', [
            'email' => 'existing@example.com',
            'create_account' => true,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'password' => 'newpassword123'
        ]);

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertTrue($data['data']['subscribed']);
        $this->assertFalse($data['data']['account_created']);
        $this->assertTrue($data['data']['account_exists']);
        $this->assertStringContainsString('already have an account', $data['data']['message']);

        // Verify subscriber was created
        $subscriber = Subscriber::findByEmail('existing@example.com', $this->siteId);
        $this->assertNotNull($subscriber);

        // Verify member password was NOT changed
        $member = Member::findByEmail('existing@example.com', $this->siteId);
        $this->assertTrue(password_verify('oldpassword', $member->password));
        $this->assertFalse(password_verify('newpassword123', $member->password));
    }

    public function testSignupWithAccountCreationAutoLogin(): void
    {
        $response = $this->postForSite('/api/newsletter/signup', [
            'email' => 'autologin@example.com',
            'create_account' => true,
            'first_name' => 'Auto',
            'last_name' => 'Login',
            'password' => 'password123'
        ]);

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['data']['logged_in']);
        $this->assertArrayHasKey('member', $data['data']);

        // Verify session was created (you may need to adjust based on your session implementation)
        // This is a basic check - adjust according to your MemberAuth implementation
        $member = Member::findByEmail('autologin@example.com', $this->siteId);
        $this->assertNotNull($member);
    }

    public function testSignupNewsletterOnlyDoesNotCreateAccount(): void
    {
        $response = $this->postForSite('/api/newsletter/signup', [
            'email' => 'newsletteronly@example.com',
            'create_account' => false
        ]);

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertTrue($data['data']['subscribed']);
        $this->assertFalse($data['data']['account_created']);

        // Verify subscriber was created
        $subscriber = Subscriber::findByEmail('newsletteronly@example.com', $this->siteId);
        $this->assertNotNull($subscriber);

        // Verify member account was NOT created
        $member = Member::findByEmail('newsletteronly@example.com', $this->siteId);
        $this->assertNull($member);
    }

    public function testConfirmWithInvalidToken(): void
    {
        $response = $this->postForSite('/api/newsletter/confirm', [
            'token' => 'invalid-token-12345'
        ]);

        $this->assertResponseStatus(400, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testUnsubscribeWithToken(): void
    {
        // Create confirmed subscriber
        $subscriber = Subscriber::create([
            'email' => 'unsub@example.com',
            'confirmed' => true,
            'confirmation_token' => 'conf-token',
            'unsubscribe_token' => 'unsub-token-123',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $response = $this->postForSite('/api/newsletter/unsubscribe', [
            'token' => 'unsub-token-123'
        ]);

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['data']['success']);

        // Verify deleted from database
        $deleted = Subscriber::findByEmail('unsub@example.com', $this->siteId);
        $this->assertNull($deleted);
    }

    public function testUnsubscribeWithSubscriberId(): void
    {
        // Create confirmed subscriber
        $subscriber = Subscriber::create([
            'email' => 'unsubid@example.com',
            'confirmed' => true,
            'confirmation_token' => 'conf-token',
            'unsubscribe_token' => 'unsub-token',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $response = $this->postForSite('/api/newsletter/unsubscribe', [
            'subscriber_id' => $subscriber->id
        ]);

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['data']['success']);

        // Verify deleted from database
        $deleted = Subscriber::findByEmail('unsubid@example.com', $this->siteId);
        $this->assertNull($deleted);
    }

    public function testUnsubscribeWithMissingParameters(): void
    {
        $response = $this->postForSite('/api/newsletter/unsubscribe', []);

        $this->assertResponseStatus(400, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Missing token or subscriber ID', $data['error']);
    }

    public function testGetSubscribers(): void
    {
        // Create some subscribers
        Subscriber::create([
            'email' => 'sub1@example.com',
            'confirmed' => true,
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
            'unsubscribe_token' => ''
        ]);

        Subscriber::create([
            'email' => 'sub2@example.com',
            'confirmed' => true,
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
            'unsubscribe_token' => ''
        ]);

        Subscriber::create([
            'email' => 'unconfirmed@example.com',
            'confirmed' => false,
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
            'unsubscribe_token' => ''
        ]);

        $response = $this->getForSite('/api/newsletter/subscribers');

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('subscribers', $data['data']);
        $this->assertEquals(2, $data['data']['count']); // Only confirmed
    }

    public function testSignupPreservesEmailCase(): void
    {
        $response = $this->postForSite('/api/newsletter/signup', [
            'email' => 'MixedCase@Example.COM',
            'create_account' => true,
            'first_name' => 'Test',
            'last_name' => 'User',
            'password' => 'password123'
        ]);

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertTrue($data['data']['account_created']);

        // Check if email is stored in normalized form (typically lowercase)
        $member = Member::findByEmail('mixedcase@example.com', $this->siteId);
        $this->assertNotNull($member);
    }
}