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
}