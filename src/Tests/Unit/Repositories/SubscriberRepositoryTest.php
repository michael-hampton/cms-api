<?php

namespace App\Tests\Unit\Repositories;

use App\Models\Subscriber;
use App\Repositories\Subscriptions\SubscriberRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class SubscriberRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private SubscriberRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new SubscriberRepository();
    }

    public function test_find_by_email_returns_subscriber(): void
    {
        // Arrange
        $subscriber = Subscriber::create([
            'email' => 'test@example.com',
            'confirmed' => true,
            'confirmation_token' => 'token123',
            'unsubscribe_token' => 'unsub123',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        // Act
        $result = $this->repository->findByEmail('test@example.com', $this->siteId);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($subscriber->id, $result->id);
        $this->assertEquals('test@example.com', $result->email);
    }

    public function test_find_by_email_returns_null_when_not_found(): void
    {
        // Act
        $result = $this->repository->findByEmail('nonexistent@example.com', $this->siteId);

        // Assert
        $this->assertNull($result);
    }

    public function test_find_by_email_filters_by_site(): void
    {
        // Arrange
        $otherSite = $this->createSite();

        Subscriber::create([
            'email' => 'test@example.com',
            'confirmed' => true,
            'confirmation_token' => 'token123',
            'unsubscribe_token' => 'unsub123',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $otherSite->id
        ]);

        // Act
        $result = $this->repository->findByEmail('test@example.com', $this->siteId);

        // Assert
        $this->assertNull($result);
    }

    public function test_find_by_confirmation_token_returns_subscriber(): void
    {
        // Arrange
        $subscriber = Subscriber::create([
            'email' => 'test@example.com',
            'confirmed' => false,
            'confirmation_token' => 'unique-token-123',
            'unsubscribe_token' => 'unsub123',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        // Act
        $result = $this->repository->findByConfirmationToken('unique-token-123');

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($subscriber->id, $result->id);
    }

    public function test_find_by_confirmation_token_returns_null_when_not_found(): void
    {
        // Act
        $result = $this->repository->findByConfirmationToken('nonexistent-token');

        // Assert
        $this->assertNull($result);
    }

    public function test_find_by_unsubscribe_token_returns_subscriber(): void
    {
        // Arrange
        $subscriber = Subscriber::create([
            'email' => 'test@example.com',
            'confirmed' => true,
            'confirmation_token' => 'token123',
            'unsubscribe_token' => 'unique-unsub-token',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        // Act
        $result = $this->repository->findByUnsubscribeToken('unique-unsub-token');

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($subscriber->id, $result->id);
    }

    public function test_get_confirmed_emails_returns_only_confirmed(): void
    {
        // Arrange
        Subscriber::create([
            'email' => 'confirmed1@example.com',
            'confirmed' => true,
            'confirmation_token' => 'token1',
            'unsubscribe_token' => 'unsub1',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        Subscriber::create([
            'email' => 'confirmed2@example.com',
            'confirmed' => true,
            'confirmation_token' => 'token2',
            'unsubscribe_token' => 'unsub2',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        Subscriber::create([
            'email' => 'unconfirmed@example.com',
            'confirmed' => false,
            'confirmation_token' => 'token3',
            'unsubscribe_token' => 'unsub3',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        // Act
        $result = $this->repository->getConfirmedEmails($this->siteId);

        // Assert
        $this->assertCount(2, $result);
        $this->assertContains('confirmed1@example.com', $result);
        $this->assertContains('confirmed2@example.com', $result);
        $this->assertNotContains('unconfirmed@example.com', $result);
    }

    public function test_get_newsletters_for_member_returns_all_subscriptions(): void
    {
        // Arrange
        $email = 'member@example.com';

        Subscriber::create([
            'email' => $email,
            'confirmed' => true,
            'confirmation_token' => 'token1',
            'unsubscribe_token' => 'unsub1',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        Subscriber::create([
            'email' => 'member2@example.com',
            'confirmed' => false,
            'confirmation_token' => 'token2',
            'unsubscribe_token' => 'unsub2',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        // Act
        $result = $this->repository->getNewslettersForMember($email, $this->siteId);

        // Assert
        $this->assertCount(1, $result);
    }

    public function test_get_newsletters_for_member_filters_by_site(): void
    {
        // Arrange
        $email = 'member@example.com';
        $otherSite = $this->createSite();

        Subscriber::create([
            'email' => $email,
            'confirmed' => true,
            'confirmation_token' => 'token1',
            'unsubscribe_token' => 'unsub1',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        Subscriber::create([
            'email' => $email,
            'confirmed' => true,
            'confirmation_token' => 'token2',
            'unsubscribe_token' => 'unsub2',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $otherSite->id
        ]);

        // Act
        $result = $this->repository->getNewslettersForMember($email, $this->siteId);

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals($this->siteId, $result->first()->site_id);
    }

    public function test_find_by_email_and_newsletter_returns_subscriber(): void
    {
        // Arrange
        $newsletter = $this->createNewsletter();

        $subscriber = Subscriber::create([
            'email' => 'test@example.com',
            'newsletter_id' => $newsletter->id,
            'confirmed' => true,
            'confirmation_token' => 'token123',
            'unsubscribe_token' => 'unsub123',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        // Act
        $result = $this->repository->findByEmailAndNewsletter('test@example.com', $newsletter->id, $this->siteId);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($subscriber->id, $result->id);
        $this->assertEquals('test@example.com', $result->email);
        $this->assertEquals($newsletter->id, $result->newsletter_id);
    }

    public function test_find_by_email_and_newsletter_returns_null_when_not_found(): void
    {
        // Arrange
        $newsletter = $this->createNewsletter();

        // Act
        $result = $this->repository->findByEmailAndNewsletter('nonexistent@example.com', $newsletter->id, $this->siteId);

        // Assert
        $this->assertNull($result);
    }

    public function test_find_by_email_and_newsletter_returns_null_for_different_newsletter(): void
    {
        // Arrange
        $newsletter1 = $this->createNewsletter();
        $newsletter2 = $this->createNewsletter();

        Subscriber::create([
            'email' => 'test@example.com',
            'newsletter_id' => $newsletter1->id,
            'confirmed' => true,
            'confirmation_token' => 'token123',
            'unsubscribe_token' => 'unsub123',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        // Act
        $result = $this->repository->findByEmailAndNewsletter('test@example.com', $newsletter2->id, $this->siteId);

        // Assert
        $this->assertNull($result);
    }

    public function test_find_by_email_and_newsletter_filters_by_site(): void
    {
        // Arrange
        $newsletter = $this->createNewsletter();
        $otherSite = $this->createSite();

        Subscriber::create([
            'email' => 'test@example.com',
            'newsletter_id' => $newsletter->id,
            'confirmed' => true,
            'confirmation_token' => 'token123',
            'unsubscribe_token' => 'unsub123',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $otherSite->id
        ]);

        // Act
        $result = $this->repository->findByEmailAndNewsletter('test@example.com', $newsletter->id, $this->siteId);

        // Assert
        $this->assertNull($result);
    }

    public function test_create_returns_new_subscriber(): void
    {
        // Arrange
        $newsletter = $this->createNewsletter();

        $data = [
            'email' => 'newsubscriber@example.com',
            'newsletter_id' => $newsletter->id,
            'confirmed' => true,
            'confirmation_token' => 'token123',
            'unsubscribe_token' => 'unsub123',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ];

        // Act
        $result = $this->repository->create($data);

        // Assert
        $this->assertInstanceOf(Subscriber::class, $result);
        $this->assertEquals('newsubscriber@example.com', $result->email);
        $this->assertEquals($newsletter->id, $result->newsletter_id);
        $this->assertTrue($result->confirmed);
        $this->assertEquals($this->siteId, $result->site_id);
    }

    public function test_create_persists_to_database(): void
    {
        // Arrange
        $newsletter = $this->createNewsletter();

        $data = [
            'email' => 'persist@example.com',
            'newsletter_id' => $newsletter->id,
            'confirmed' => false,
            'confirmation_token' => 'token456',
            'unsubscribe_token' => 'unsub456',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ];

        // Act
        $subscriber = $this->repository->create($data);

        // Assert - verify it's in the database
        $found = Subscriber::find($subscriber->id);
        $this->assertNotNull($found);
        $this->assertEquals('persist@example.com', $found->email);
        $this->assertEquals($newsletter->id, $found->newsletter_id);
    }

    public function test_find_existing_returns_active_subscriber(): void
    {
        $newsletter = $this->createNewsletter();

        $subscriber = Subscriber::create([
            'email' => 'test@example.com',
            'newsletter_id' => $newsletter->id,
            'confirmed' => true,
            'confirmation_token' => 'token123',
            'unsubscribe_token' => 'unsub123',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
            'unsubscribed_at' => null
        ]);

        $result = $this->repository->findExisting('test@example.com', $newsletter->id, $this->siteId);

        $this->assertNotNull($result);
        $this->assertEquals($subscriber->id, $result->id);
        $this->assertTrue($result->isActive());
    }

    public function test_find_existing_returns_unsubscribed_subscriber(): void
    {
        $newsletter = $this->createNewsletter();

        $subscriber = Subscriber::create([
            'email' => 'test@example.com',
            'newsletter_id' => $newsletter->id,
            'confirmed' => true,
            'confirmation_token' => 'token123',
            'unsubscribe_token' => 'unsub123',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
            'unsubscribed_at' => date('Y-m-d H:i:s')
        ]);

        $result = $this->repository->findExisting('test@example.com', $newsletter->id, $this->siteId);

        $this->assertNotNull($result);
        $this->assertEquals($subscriber->id, $result->id);
        $this->assertFalse($result->isActive());
    }

    public function test_unsubscribe_sets_unsubscribed_at(): void
    {
        $subscriber = Subscriber::create([
            'email' => 'test@example.com',
            'confirmed' => true,
            'confirmation_token' => 'token123',
            'unsubscribe_token' => 'unsub123',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
            'unsubscribed_at' => null
        ]);

        $result = $this->repository->unsubscribe($subscriber->id);

        $this->assertTrue($result);

        $updated = Subscriber::find($subscriber->id);
        $this->assertNotNull($updated->unsubscribed_at);
        $this->assertFalse($updated->isActive());
    }

    public function test_resubscribe_clears_unsubscribed_at(): void
    {
        $subscriber = Subscriber::create([
            'email' => 'test@example.com',
            'confirmed' => true,
            'confirmation_token' => 'token123',
            'unsubscribe_token' => 'unsub123',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
            'unsubscribed_at' => date('Y-m-d H:i:s')
        ]);

        $result = $this->repository->resubscribe($subscriber->id);

        $this->assertTrue($result);

        $updated = Subscriber::find($subscriber->id);
        $this->assertNull($updated->unsubscribed_at);
        $this->assertTrue($updated->isActive());
    }

    public function test_resubscribe_updates_campaign_id(): void
    {
        $campaign = $this->createCampaign();
        $campaign2 = $this->createCampaign();

        $subscriber = Subscriber::create([
            'email' => 'test@example.com',
            'confirmed' => true,
            'confirmation_token' => 'token123',
            'unsubscribe_token' => 'unsub123',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
            'unsubscribed_at' => date('Y-m-d H:i:s'),
            'campaign_id' => $campaign->id
        ]);

        $result = $this->repository->resubscribe($subscriber->id, $campaign2->id);

        $this->assertTrue($result);

        $updated = Subscriber::find($subscriber->id);
        $this->assertEquals(2, $updated->campaign_id);
    }

    public function test_find_by_email_excludes_unsubscribed(): void
    {
        Subscriber::create([
            'email' => 'test@example.com',
            'confirmed' => true,
            'confirmation_token' => 'token123',
            'unsubscribe_token' => 'unsub123',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
            'unsubscribed_at' => date('Y-m-d H:i:s')
        ]);

        $result = $this->repository->findByEmail('test@example.com', $this->siteId);

        $this->assertNull($result);
    }

    public function test_get_confirmed_emails_excludes_unsubscribed(): void
    {
        Subscriber::create([
            'email' => 'confirmed-active@example.com',
            'confirmed' => true,
            'confirmation_token' => 'token1',
            'unsubscribe_token' => 'unsub1',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
            'unsubscribed_at' => null
        ]);

        Subscriber::create([
            'email' => 'confirmed-unsubscribed@example.com',
            'confirmed' => true,
            'confirmation_token' => 'token2',
            'unsubscribe_token' => 'unsub2',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
            'unsubscribed_at' => date('Y-m-d H:i:s')
        ]);

        $result = $this->repository->getConfirmedEmails($this->siteId);

        $this->assertCount(1, $result);
        $this->assertContains('confirmed-active@example.com', $result);
        $this->assertNotContains('confirmed-unsubscribed@example.com', $result);
    }
}