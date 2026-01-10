<?php

namespace App\Tests\Functional\Controllers\Members\Newsletters;

use App\Models\Campaign;
use App\Models\Member;
use App\Models\Newsletter;
use App\Models\Subscriber;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class NewsletterSignupControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function setUp(): void
    {
        parent::setUp();
        $this->createNewsletter(['is_default' => true]);
    }

    public function testSignupWithValidEmail(): void
    {
        $response = $this->postForSite('/api/newsletter/signup', [
            'email' => 'newuser@example.com'
        ]);

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
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
            'site_id' => $this->siteId,
            'unsubscribed_at' => null // ADDED
        ]);

        $response = $this->postForSite('/api/newsletter/unsubscribe', [
            'token' => 'unsub-token-123'
        ]);

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['data']['success']);

        // CHANGED: Verify it still exists but is unsubscribed
        $stillExists = Subscriber::find($subscriber->id);

        $this->assertNotNull($stillExists);
        $this->assertNotNull($stillExists->unsubscribed_at);
        $this->assertFalse($stillExists->isActive());
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
            'site_id' => $this->siteId,
            'unsubscribed_at' => null // ADDED
        ]);

        $response = $this->postForSite('/api/newsletter/unsubscribe', [
            'subscriber_id' => $subscriber->id
        ]);

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['data']['success']);

        // CHANGED: Verify it still exists but is unsubscribed
        $stillExists = Subscriber::find($subscriber->id);
        $this->assertNotNull($stillExists);
        $this->assertNotNull($stillExists->unsubscribed_at);
        $this->assertFalse($stillExists->isActive());
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

    public function testSignupWithNewsletterIdSpecified(): void
    {
        // Create two newsletters
        $newsletter1 = \App\Models\Newsletter::create([
            'site_id' => $this->siteId,
            'title' => 'Newsletter 1',
            'interval' => 'weekly',
            'active' => true,
            'is_default' => true,
            'content' => 'This is the newsletter content.',
        ]);

        $newsletter2 = \App\Models\Newsletter::create([
            'site_id' => $this->siteId,
            'title' => 'Newsletter 2',
            'interval' => 'daily',
            'active' => true,
            'is_default' => false,
            'content' => 'This is the newsletter content.',
        ]);

        // Subscribe to newsletter 2 specifically
        $response = $this->postForSite('/api/newsletter/signup', [
            'email' => 'specific@example.com',
            'newsletter_id' => $newsletter2->id
        ]);

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals($newsletter2->id, $data['data']['newsletter_id']);

        // Verify subscriber is linked to correct newsletter
        $subscriber = Subscriber::findByEmail('specific@example.com', $this->siteId);
        $this->assertEquals($newsletter2->id, $subscriber->newsletter_id);
    }

    public function testSignupWithoutNewsletterIdUsesDefault(): void
    {
        Newsletter::first()->delete();

        // Create default newsletter
        $defaultNewsletter = \App\Models\Newsletter::create([
            'site_id' => $this->siteId,
            'title' => 'Default Newsletter',
            'interval' => 'weekly',
            'active' => true,
            'is_default' => true,
            'content' => 'This is the newsletter content.',
        ]);

        $response = $this->postForSite('/api/newsletter/signup', [
            'email' => 'default@example.com'
        ]);

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals($defaultNewsletter->id, $data['data']['newsletter_id']);

        // Verify subscriber is linked to default newsletter
        $subscriber = Subscriber::findByEmail('default@example.com', $this->siteId);
        $this->assertEquals($defaultNewsletter->id, $subscriber->newsletter_id);
    }

    public function testSignupWithCampaign(): void
    {
        $newsletter = $this->createNewsletter(['is_default' => true]);

        $campaign = Campaign::create([
            'site_id' => $this->siteId,
            'name' => 'Test Campaign',
            'slug' => 'test-campaign',
            'newsletter_id' => $newsletter->id,
            'is_active' => true
        ]);

        $response = $this->postForSite('/api/newsletter/signup', [
            'email' => 'campaign@example.com',
            'campaign' => 'test-campaign'
        ]);

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals($campaign->id, $data['data']['campaign_id']);

        // Verify subscriber was created with campaign
        $subscriber = Subscriber::findByEmail('campaign@example.com', $this->siteId);
        $this->assertNotNull($subscriber);
        $this->assertEquals($campaign->id, $subscriber->campaign_id);
    }

    public function testSignupWithInvalidCampaign(): void
    {
        $response = $this->postForSite('/api/newsletter/signup', [
            'email' => 'test@example.com',
            'campaign' => 'nonexistent-campaign'
        ]);

        $this->assertResponseStatus(200, $response); // Still succeeds, uses default newsletter

        $data = json_decode($response->getContent(), true);
        $this->assertNull($data['data']['campaign_id']);
    }

    public function testSignupWithEndedCampaign(): void
    {
        $newsletter = $this->createNewsletter();

        $campaign = Campaign::create([
            'site_id' => $this->siteId,
            'name' => 'Ended Campaign',
            'slug' => 'ended',
            'newsletter_id' => $newsletter->id,
            'is_active' => true,
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ]);
        $response = $this->postForSite('/api/newsletter/signup', [
            'email' => 'test@example.com',
            'campaign' => 'ended'
        ]);

        $this->assertResponseStatus(200, $response); // Falls back to default

        $data = json_decode($response->getContent(), true);
        $this->assertNull($data['data']['campaign_id']);
    }

    public function testSignupCampaignOverridesNewsletterIdParameter(): void
    {
        $newsletter1 = $this->createNewsletter(['title' => 'Newsletter 1']);
        $newsletter2 = $this->createNewsletter(['title' => 'Newsletter 2']);
        $campaign = Campaign::create([
            'site_id' => $this->siteId,
            'name' => 'Campaign',
            'slug' => 'campaign',
            'newsletter_id' => $newsletter1->id,
            'is_active' => true
        ]);

        $response = $this->postForSite('/api/newsletter/signup', [
            'email' => 'test@example.com',
            'campaign' => 'campaign',
            'newsletter_id' => $newsletter2->id // Should be ignored
        ]);

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

// Campaign's newsletter should be used
        $this->assertEquals($newsletter1->id, $data['data']['newsletter_id']);
        $this->assertEquals($campaign->id, $data['data']['campaign_id']);
    }

    public function testResubscribeAfterUnsubscribe(): void
    {
        $newsletter = Newsletter::first();

        // Create unsubscribed subscriber
        Subscriber::create([
            'email' => 'test@example.com',
            'confirmed' => true,
            'confirmation_token' => 'token123',
            'unsubscribe_token' => 'unsub123',
            'subscribed_at' => date('Y-m-d H:i:s', strtotime('-1 month')),
            'site_id' => $this->siteId,
            'newsletter_id' => $newsletter->id,
            'unsubscribed_at' => date('Y-m-d H:i:s', strtotime('-1 week'))
        ]);

        // Attempt to sign up again
        $response = $this->postForSite('/api/newsletter/signup', [
            'email' => 'test@example.com',
            'newsletter_id' => $newsletter->id
        ]);

        $this->assertResponseStatus(200, $response);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertTrue($data['data']['resubscribed'] ?? false);

        // Verify unsubscribed_at is cleared
        $subscriber = Subscriber::where('email', 'test@example.com')
            ->where('site_id', $this->siteId)
            ->first();

        $this->assertNotNull($subscriber);
        $this->assertNull($subscriber->unsubscribed_at);
        $this->assertTrue($subscriber->isActive());
    }

    public function testGetSubscribersExcludesUnsubscribed(): void
    {
        $newsletter = Newsletter::first();

        // Create active subscriber
        Subscriber::create([
            'email' => 'active@example.com',
            'confirmed' => true,
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
            'newsletter_id' => $newsletter->id,
            'unsubscribe_token' => 'token1',
            'unsubscribed_at' => null
        ]);

        // Create unsubscribed subscriber
        Subscriber::create([
            'email' => 'unsubscribed@example.com',
            'confirmed' => true,
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
            'newsletter_id' => $newsletter->id,
            'unsubscribe_token' => 'token2',
            'unsubscribed_at' => date('Y-m-d H:i:s')
        ]);

        $response = $this->getForSite('/api/newsletter/subscribers');

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(1, $data['data']['count']);
        $this->assertContains('active@example.com', $data['data']['subscribers']);
        $this->assertNotContains('unsubscribed@example.com', $data['data']['subscribers']);
    }
}