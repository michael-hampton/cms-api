<?php

namespace App\Tests\Unit\Services;

use App\Models\Subscriber;
use App\Repositories\SubscriberRepository;
use App\Services\NewsletterSignupService;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class NewsletterSignupServiceTest extends FunctionalTestCase
{
    private NewsletterSignupService $service;
    private SubscriberRepository $repository;
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(SubscriberRepository::class);
        $this->service = new NewsletterSignupService($this->repository, $this->siteId);
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
        $this->repository->expects($this->once())
            ->method('findByEmail')
            ->with('new@example.com', $this->siteId)
            ->willReturn(null);

        $this->repository->expects($this->once())
            ->method('create')
            ->willReturn(new Subscriber(['email' => 'new@example.com']));

        $result = $this->service->signup('new@example.com');

        $this->assertTrue($result['success']);
        $this->assertEquals('new@example.com', $result['email']);
        $this->assertArrayHasKey('confirmation_token', $result);
    }

    public function testSignupWithExistingConfirmedEmail(): void
    {
        $subscriber = new Subscriber(['email' => 'existing@example.com', 'confirmed' => true]);

        $this->repository->expects($this->once())
            ->method('findByEmail')
            ->willReturn($subscriber);

        $result = $this->service->signup('existing@example.com');

        $this->assertFalse($result['success']);
        $this->assertEquals('Email already subscribed', $result['error']);
    }

    public function testSignupWithExistingPendingEmail(): void
    {
        $subscriber = new Subscriber(['email' => 'pending@example.com', 'confirmed' => false]);

        $this->repository->expects($this->once())
            ->method('findByEmail')
            ->willReturn($subscriber);

        $result = $this->service->signup('pending@example.com');

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

        $this->repository->expects($this->once())
            ->method('findByConfirmationToken')
            ->with('valid-token')
            ->willReturn($subscriber);

        $this->repository->expects($this->once())
            ->method('update')
            ->with($subscriber->id, ['confirmed' => true]);

        $result = $this->service->confirm('valid-token');

        $this->assertTrue($result['success']);
    }

    public function testConfirmWithInvalidToken(): void
    {
        $this->repository->expects($this->once())
            ->method('findByConfirmationToken')
            ->with('invalid-token')
            ->willReturn(null);

        $result = $this->service->confirm('invalid-token');

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid confirmation token', $result['error']);
    }

    public function testConfirmWithWrongSiteId(): void
    {
        $subscriber = new Subscriber([
            'email' => 'test@example.com',
            'confirmed' => false,
            'site_id' => 999, // Different site ID
            'id' => 5
        ]);

        $this->repository->expects($this->once())
            ->method('findByConfirmationToken')
            ->with('valid-token')
            ->willReturn($subscriber);

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

        $this->repository->expects($this->once())
            ->method('findByUnsubscribeToken')
            ->with('unsub-token')
            ->willReturn($subscriber);

        $this->repository->expects($this->once())
            ->method('delete')
            ->with($subscriber->id);

        $result = $this->service->unsubscribe('unsub-token');

        $this->assertTrue($result['success']);
    }

    public function testUnsubscribeWithInvalidToken(): void
    {
        $this->repository->expects($this->once())
            ->method('findByUnsubscribeToken')
            ->with('invalid-token')
            ->willReturn(null);

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

        $this->repository->expects($this->once())
            ->method('find')
            ->with(5)
            ->willReturn($subscriber);

        $this->repository->expects($this->once())
            ->method('delete')
            ->with(5);

        $result = $this->service->unsubscribeById(5, $this->siteId);

        $this->assertTrue($result['success']);
        $this->assertEquals('test@example.com', $result['email']);
    }

    public function testUnsubscribeByIdWithWrongSiteId(): void
    {
        $subscriber = new Subscriber([
            'email' => 'test@example.com',
            'site_id' => 999, // Different site ID
            'id' => 5
        ]);

        $this->repository->expects($this->once())
            ->method('find')
            ->with(5)
            ->willReturn($subscriber);

        $result = $this->service->unsubscribeById(5, $this->siteId);

        $this->assertFalse($result['success']);
        $this->assertEquals('Subscriber not found', $result['error']);
    }

    public function testUnsubscribeByIdWithNonExistentSubscriber(): void
    {
        $this->repository->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $result = $this->service->unsubscribeById(999, $this->siteId);

        $this->assertFalse($result['success']);
        $this->assertEquals('Subscriber not found', $result['error']);
    }

    public function testGetConfirmedSubscribers(): void
    {
        $confirmedEmails = ['user1@example.com', 'user2@example.com', 'user3@example.com'];

        $this->repository->expects($this->once())
            ->method('getConfirmedEmails')
            ->with($this->siteId)
            ->willReturn($confirmedEmails);

        $result = $this->service->getConfirmedSubscribers();

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertEquals($confirmedEmails, $result);
    }
}