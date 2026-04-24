<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Models\Newsletter;
use App\Models\Subscriber;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Newsletters\SubscriberRepository;
use App\Services\Newsletter\NewsletterSignupService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class NewsletterSignupServiceTest extends FunctionalTestCase
{
    private NewsletterSignupService $service;
    private SubscriberRepository $repository;
    private readonly NewsletterRepository $newsletterRepository;
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(SubscriberRepository::class);
        $this->newsletterRepository = Mockery::mock(NewsletterRepository::class);
        $this->service = new NewsletterSignupService(
            $this->newsletterRepository,
            $this->repository
        );
    }

    public function testValidateEmailWithValidEmail(): void
    {
        $this->assertTrue($this->service->validateEmail('test@example.com'));
    }

    public function testValidateEmailWithInvalidEmail(): void
    {
        $this->assertFalse($this->service->validateEmail('invalid-email'));
        $this->assertFalse($this->service->validateEmail('test@'));
        $this->assertFalse($this->service->validateEmail('@example.com'));
    }

    public function testGenerateTokenCreatesUniqueTokens(): void
    {
        $token1 = $this->service->generateToken('test@example.com', 'confirm');
        $token2 = $this->service->generateToken('test@example.com', 'confirm');

        $this->assertNotEquals($token1, $token2);
        $this->assertEquals(64, strlen($token1)); // SHA256 hex length
    }

    public function testSignupWithInvalidEmail(): void
    {
        $result = $this->service->signup('invalid-email');

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid email format', $result['error']);
    }

    public function testSignupWithNewEmail(): void
    {
        // CHANGED: Use findExisting instead of findByEmailAndNewsletter
        $this->repository
            ->shouldReceive('findExisting')
            ->once()
            ->with('new@example.com', 1, $this->siteId)
            ->andReturn(null);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->andReturn(new Subscriber(['email' => 'new@example.com', 'id' => 1]));

        $newsletter = Mockery::mock(Newsletter::class)->makePartial();
        $newsletter->id = 1;

        $this->newsletterRepository
            ->shouldReceive('getDefaultNewsletterForSite')
            ->once()
            ->with($this->siteId)
            ->andReturn($newsletter);

        $result = $this->service->signup('new@example.com', false, null, $this->siteId);

        $this->assertTrue($result['success']);
        $this->assertEquals('new@example.com', $result['email']);
        $this->assertArrayHasKey('confirmation_token', $result);
    }

    public function testSignupWithExistingConfirmedEmail(): void
    {
        $subscriber = Mockery::mock(Subscriber::class)->makePartial();
        $subscriber->email = 'existing@example.com';
        $subscriber->confirmed = true;
        $subscriber->shouldReceive('isActive')->andReturn(true);
        $subscriber->shouldReceive('isConfirmed')->andReturn(true);

        // CHANGED: Use findExisting instead of findByEmailAndNewsletter
        $this->repository
            ->shouldReceive('findExisting')
            ->once()
            ->with('existing@example.com', 1, $this->siteId)
            ->andReturn($subscriber);

        $result = $this->service->signup('existing@example.com', false, 1, $this->siteId);

        $this->assertFalse($result['success']);
        $this->assertEquals('Email already subscribed', $result['error']);
    }

    public function testSignupWithExistingPendingEmail(): void
    {
        $subscriber = Mockery::mock(Subscriber::class)->makePartial();
        $subscriber->email = 'pending@example.com';
        $subscriber->confirmed = false;
        $subscriber->shouldReceive('isActive')->andReturn(true);
        $subscriber->shouldReceive('isConfirmed')->andReturn(false);

        // CHANGED: Use findExisting instead of findByEmailAndNewsletter
        $this->repository
            ->shouldReceive('findExisting')
            ->once()
            ->with('pending@example.com', 1, $this->siteId)
            ->andReturn($subscriber);

        $result = $this->service->signup('pending@example.com', false, 1, $this->siteId);

        $this->assertFalse($result['success']);
        $this->assertEquals('Confirmation pending', $result['error']);
    }

    public function testConfirmWithValidToken(): void
    {
        $subscriber = new Subscriber([
            'email' => 'test@example.com',
            'confirmed' => false,
            'site_id' => $this->siteId,
            'id' => 5
        ]);

        $this->repository
            ->shouldReceive('findByConfirmationToken')
            ->once()
            ->with('valid-token')
            ->andReturn($subscriber);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with(5, ['confirmed' => true]);

        $result = $this->service->confirm('valid-token', $this->siteId);

        $this->assertTrue($result['success']);
    }

    public function testConfirmWithInvalidToken(): void
    {
        $this->repository
            ->shouldReceive('findByConfirmationToken')
            ->once()
            ->with('invalid-token')
            ->andReturn(null);

        $result = $this->service->confirm('invalid-token');

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid confirmation token', $result['error']);
    }

    public function testConfirmWithWrongSiteId(): void
    {
        $subscriber = new Subscriber([
            'email' => 'test@example.com',
            'confirmed' => false,
            'site_id' => 999,
            'id' => 5
        ]);

        $this->repository
            ->shouldReceive('findByConfirmationToken')
            ->once()
            ->with('valid-token')
            ->andReturn($subscriber);

        $result = $this->service->confirm('valid-token');

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid confirmation token', $result['error']);
    }

    public function testUnsubscribeWithValidToken(): void
    {
        $subscriber = new Subscriber([
            'email' => 'test@example.com',
            'site_id' => $this->siteId,
            'id' => 5
        ]);

        $this->repository
            ->shouldReceive('findByUnsubscribeToken')
            ->once()
            ->with('unsub-token')
            ->andReturn($subscriber);

        // CHANGED: from delete to unsubscribe
        $this->repository
            ->shouldReceive('unsubscribe')
            ->once()
            ->with(5)
            ->andReturn(true);

        $result = $this->service->unsubscribe('unsub-token', $this->siteId);

        $this->assertTrue($result['success']);
    }

    public function testUnsubscribeWithInvalidToken(): void
    {
        $this->repository
            ->shouldReceive('findByUnsubscribeToken')
            ->once()
            ->with('invalid-token')
            ->andReturn(null);

        $result = $this->service->unsubscribe('invalid-token');

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid unsubscribe token', $result['error']);
    }

    public function testUnsubscribeById(): void
    {
        $subscriber = new Subscriber([
            'email' => 'test@example.com',
            'site_id' => $this->siteId,
            'id' => 5
        ]);

        $this->repository
            ->shouldReceive('find')
            ->once()
            ->with(5)
            ->andReturn($subscriber);

        // CHANGED: from delete to unsubscribe
        $this->repository
            ->shouldReceive('unsubscribe')
            ->once()
            ->with(5)
            ->andReturn(true);

        $result = $this->service->unsubscribeById(5, $this->siteId);

        $this->assertTrue($result['success']);
        $this->assertEquals('test@example.com', $result['email']);
    }

    public function testUnsubscribeByIdWithWrongSiteId(): void
    {
        $subscriber = new Subscriber([
            'email' => 'test@example.com',
            'site_id' => 999,
            'id' => 5
        ]);

        $this->repository
            ->shouldReceive('find')
            ->once()
            ->with(5)
            ->andReturn($subscriber);

        $result = $this->service->unsubscribeById(5, $this->siteId);

        $this->assertFalse($result['success']);
        $this->assertEquals('Subscriber not found', $result['error']);
    }

    public function testUnsubscribeByIdWithNonExistentSubscriber(): void
    {
        $this->repository
            ->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        $result = $this->service->unsubscribeById(999, $this->siteId);

        $this->assertFalse($result['success']);
        $this->assertEquals('Subscriber not found', $result['error']);
    }

    public function testGetConfirmedSubscribers(): void
    {
        $emails = ['user1@example.com', 'user2@example.com', 'user3@example.com'];

        $this->repository
            ->shouldReceive('getConfirmedEmails')
            ->once()
            ->with($this->siteId)
            ->andReturn($emails);

        $result = $this->service->getConfirmedSubscribers($this->siteId);

        $this->assertCount(3, $result);
        $this->assertEquals($emails, $result);
    }

    public function testSignupWithoutNewsletterIdUsesDefault(): void
    {
        $this->repository
            ->shouldReceive('findExisting')
            ->once()
            ->with('test@example.com', 1, $this->siteId)
            ->andReturn(null);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->andReturn(new Subscriber([
                'email' => 'test@example.com',
                'newsletter_id' => 1,
                'id' => 1
            ]));

        $newsletter = Mockery::mock(Newsletter::class)->makePartial();
        $newsletter->id = 1;

        $this->newsletterRepository
            ->shouldReceive('getDefaultNewsletterForSite')
            ->once()
            ->with($this->siteId)
            ->andReturn($newsletter);

        $result = $this->service->signup('test@example.com', true, null, $this->siteId);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('newsletter_id', $result);
    }

    public function testSignupWithNoDefaultNewsletterFails(): void
    {
        $this->newsletterRepository->shouldReceive('getDefaultNewsletterForSite')
            ->with($this->siteId)
            ->andReturn(null);

        $result = $this->service->signup('test@example.com', true, null, $this->siteId);

        $this->assertFalse($result['success']);
        $this->assertEquals('No newsletter available for subscription', $result['error']);
    }

    public function testSignupAllowsMultipleNewslettersForSameEmail(): void
    {
        $this->repository
            ->shouldReceive('findExisting')
            ->once()
            ->with('test@example.com', $this->siteId, 1)
            ->andReturn(null);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->andReturn(new Subscriber([
                'email' => 'test@example.com',
                'newsletter_id' => 1,
                'id' => 1
            ]));

        $result = $this->service->signup('test@example.com', true, 1, $this->siteId);

        $this->assertTrue($result['success']);
    }

    public function testSignupResubscribesPreviouslyUnsubscribedUser(): void
    {
        $existingSubscriber = Mockery::mock(Subscriber::class)->makePartial();
        $existingSubscriber->id = 1;
        $existingSubscriber->email = 'test@example.com';
        $existingSubscriber->confirmed = true;
        $existingSubscriber->unsubscribed_at = date('Y-m-d H:i:s');
        $existingSubscriber->confirmation_token = 'existing-token';
        $existingSubscriber->newsletter_id = 1;

        $existingSubscriber->shouldReceive('isActive')->andReturn(false);
        $existingSubscriber->shouldReceive('isConfirmed')->andReturn(true);
        $existingSubscriber->shouldReceive('resubscribe')
            ->with(null)
            ->once()
            ->andReturn(true);
        $existingSubscriber->shouldReceive('update')
            ->once()
            ->andReturn(true);

        $this->repository->shouldReceive('findExisting')
            ->with('test@example.com', 1, $this->siteId)
            ->once()
            ->andReturn($existingSubscriber);

        $result = $this->service->signup('test@example.com', true, 1, $this->siteId);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['resubscribed']);
        $this->assertEquals('test@example.com', $result['email']);
        $this->assertEquals(1, $result['newsletter_id']);
    }

    public function testSignupWithCampaignIdUpdatesCampaignOnResubscribe(): void
    {
        $existingSubscriber = Mockery::mock(Subscriber::class)->makePartial();
        $existingSubscriber->id = 1;
        $existingSubscriber->email = 'test@example.com';
        $existingSubscriber->unsubscribed_at = date('Y-m-d H:i:s');
        $existingSubscriber->campaign_id = 1;
        $existingSubscriber->confirmation_token = 'token';

        $existingSubscriber->shouldReceive('isActive')->andReturn(false);
        $existingSubscriber->shouldReceive('isConfirmed')->andReturn(true);
        $existingSubscriber->shouldReceive('resubscribe')
            ->with(2)
            ->once()
            ->andReturn(true);
        $existingSubscriber->shouldReceive('update')->andReturn(true);

        $this->repository->shouldReceive('findExisting')
            ->once()
            ->andReturn($existingSubscriber);

        $result = $this->service->signup('test@example.com', true, 1, $this->siteId, 2);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['resubscribed']);
    }
}