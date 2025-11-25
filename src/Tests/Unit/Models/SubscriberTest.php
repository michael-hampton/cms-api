<?php

namespace App\Tests\Unit\Models;

use App\Models\Site;
use App\Models\Subscriber;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class SubscriberTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_can_create_subscriber(): void
    {
        $subscriber = Subscriber::create([
            'email' => 'test@example.com',
            'confirmed' => true,
            'confirmation_token' => 'token123',
            'unsubscribe_token' => 'unsub123',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $this->assertNotNull($subscriber);
        $this->assertEquals('test@example.com', $subscriber->email);
        $this->assertTrue($subscriber->confirmed);
    }

    public function test_is_confirmed_returns_true_when_confirmed(): void
    {
        $subscriber = Subscriber::create([
            'email' => 'test@example.com',
            'confirmed' => true,
            'confirmation_token' => 'token123',
            'unsubscribe_token' => 'unsub123',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $this->assertTrue($subscriber->isConfirmed());
    }

    public function test_is_confirmed_returns_false_when_not_confirmed(): void
    {
        $subscriber = Subscriber::create([
            'email' => 'test@example.com',
            'confirmed' => false,
            'confirmation_token' => 'token123',
            'unsubscribe_token' => 'unsub123',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $this->assertFalse($subscriber->isConfirmed());
    }

    public function test_find_by_email_returns_subscriber(): void
    {
        Subscriber::create([
            'email' => 'test@example.com',
            'confirmed' => true,
            'confirmation_token' => 'token123',
            'unsubscribe_token' => 'unsub123',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $found = Subscriber::findByEmail('test@example.com', $this->siteId);

        $this->assertNotNull($found);
        $this->assertEquals('test@example.com', $found->email);
    }

    public function test_find_by_email_returns_null_when_not_found(): void
    {
        $found = Subscriber::findByEmail('nonexistent@example.com', $this->siteId);

        $this->assertNull($found);
    }

    public function test_find_by_email_filters_by_site(): void
    {
        $otherSite = Site::create(['name' => 'Other Site', 'domain' => 'other.com']);

        Subscriber::create([
            'email' => 'test@example.com',
            'confirmed' => true,
            'confirmation_token' => 'token123',
            'unsubscribe_token' => 'unsub123',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $otherSite->id
        ]);

        $found = Subscriber::findByEmail('test@example.com', $this->siteId);

        $this->assertNull($found);
    }

    public function test_find_by_confirmation_token_returns_subscriber(): void
    {
        $subscriber = Subscriber::create([
            'email' => 'test@example.com',
            'confirmed' => false,
            'confirmation_token' => 'unique-token-123',
            'unsubscribe_token' => 'unsub123',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $found = Subscriber::findByConfirmationToken('unique-token-123');

        $this->assertNotNull($found);
        $this->assertEquals($subscriber->id, $found->id);
    }

    public function test_find_by_confirmation_token_returns_null_when_not_found(): void
    {
        $found = Subscriber::findByConfirmationToken('nonexistent-token');

        $this->assertNull($found);
    }

    public function test_find_by_unsubscribe_token_returns_subscriber(): void
    {
        $subscriber = Subscriber::create([
            'email' => 'test@example.com',
            'confirmed' => true,
            'confirmation_token' => 'token123',
            'unsubscribe_token' => 'unique-unsub-token',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $found = Subscriber::findByUnsubscribeToken('unique-unsub-token');

        $this->assertNotNull($found);
        $this->assertEquals($subscriber->id, $found->id);
    }

    public function test_get_confirmed_emails_returns_only_confirmed(): void
    {
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

        $emails = Subscriber::getConfirmedEmails($this->siteId);

        $this->assertCount(2, $emails);
        $this->assertContains('confirmed1@example.com', $emails);
        $this->assertContains('confirmed2@example.com', $emails);
        $this->assertNotContains('unconfirmed@example.com', $emails);
    }

    public function test_confirmed_casts_to_boolean(): void
    {
        $subscriber = Subscriber::create([
            'email' => 'test@example.com',
            'confirmed' => 1,
            'confirmation_token' => 'token123',
            'unsubscribe_token' => 'unsub123',
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $this->assertIsBool($subscriber->confirmed);
        $this->assertTrue($subscriber->confirmed);
    }

    public function test_subscribed_at_casts_to_datetime(): void
    {
        $subscriber = Subscriber::create([
            'email' => 'test@example.com',
            'confirmed' => true,
            'confirmation_token' => 'token123',
            'unsubscribe_token' => 'unsub123',
            'subscribed_at' => '2024-01-01 10:00:00',
            'site_id' => $this->siteId
        ]);

        $this->assertInstanceOf(\DateTime::class, $subscriber->subscribed_at);
    }
}