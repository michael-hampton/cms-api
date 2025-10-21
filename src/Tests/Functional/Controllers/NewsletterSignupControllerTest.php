<?php

namespace App\Tests\Functional\Controllers;

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

    public function testConfirmWithInvalidToken(): void
    {
        $response = $this->postForSite('/api/newsletter/confirm', [
            'token' => 'invalid-token-12345'
        ]);

        $this->assertResponseStatus(400, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testUnsubscribe(): void
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
}