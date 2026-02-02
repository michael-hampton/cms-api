<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Framework\Database\Database;
use App\Models\Member;
use App\Models\Model;
use App\Models\Payment;
use App\Models\SingleContentAccess;
use App\Repositories\Billing\PaymentRepository;
use App\Repositories\Subscriptions\SingleContentAccessRepository;
use App\Services\Billing\PaymentProviders\StripePaymentProcessor;
use App\Services\Subscriptions\SingleContentAccessService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery as m;

class SingleContentAccessServiceTest extends FunctionalTestCase
{
    private SingleContentAccessService $service;
    private $repositoryMock;
    private $stripeProcessorMock;
    private Model $testMember;
    private $paymentRepositoryMock;
    private $databaseMock;

    public function testPurchaseAccessCreatesPaymentIntent(): void
    {
        $this->stripeProcessorMock
            ->shouldReceive('createPaymentIntentWithCustomer')
            ->once()
            ->with(m::on(function ($data) {
                return $data['amount'] === 9.99
                    && $data['currency'] === 'USD'
                    && $data['metadata']['content_type'] === 'page'
                    && $data['metadata']['content_id'] === 1;
            }))
            ->andReturn([
                'success' => true,
                'payment_intent_id' => 'pi_test123',
                'client_secret' => 'pi_test123_secret'
            ]);

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->repositoryMock->shouldReceive('hasActiveAccess')
            ->once()
            ->andReturn(false);

        $mockAccess = m::mock(SingleContentAccess::class)->makePartial();
        $mockAccess->id = 1;
        $mockAccess->access_token = 'test_token';

        $this->repositoryMock
            ->shouldReceive('createAccess')
            ->once()
            ->andReturn($mockAccess);

        $result = $this->service->purchaseAccess(
            $this->testMember->id,
            $this->siteId,
            'page',
            1,
            9.99,
            'USD',
            7
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('pi_test123', $result['payment_intent_id']);
        $this->assertEquals('pi_test123_secret', $result['client_secret']);
        $this->assertEquals('test_token', $result['access_token']);
    }

    public function testPurchaseAccessFailsWhenPaymentIntentFails(): void
    {
        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->repositoryMock->shouldReceive('hasActiveAccess')
            ->once()
            ->andReturn(false);

        $this->stripeProcessorMock
            ->shouldReceive('createPaymentIntentWithCustomer')
            ->once()
            ->andReturn([
                'success' => false,
                'message' => 'Payment failed'
            ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Payment failed');

        $this->service->purchaseAccess(
            $this->testMember->id,
            $this->siteId,
            'page',
            1,
            9.99,
            'USD',
            7
        );
    }

    public function testCompleteAccessPurchaseSucceeds(): void
    {
        $mockAccess = m::mock(SingleContentAccess::class)->makePartial();
        $mockAccess->id = 1;
        $mockAccess->member_id = $this->testMember->id;
        $mockAccess->price = 9.99;
        $mockAccess->currency = 'USD';
        $mockAccess->site_id = $this->siteId;
        $mockAccess->content_type = 'page';
        $mockAccess->content_id = 1;

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->stripeProcessorMock
            ->shouldReceive('confirmPaymentIntent')
            ->once()
            ->with('pi_test123')
            ->andReturn([
                'success' => true,
                'status' => 'succeeded'
            ]);

        $this->repositoryMock
            ->shouldReceive('findByPaymentIntent')
            ->once()
            ->with('pi_test123')
            ->andReturn($mockAccess);

        $mockPayment = m::mock(Payment::class)->makePartial();
        $mockPayment->id = 1;

        $this->paymentRepositoryMock
            ->shouldReceive('create')
            ->once()
            ->andReturn($mockPayment);

        $mockAccess->shouldReceive('update')
            ->once()
            ->with(m::on(function ($data) {
                return $data['is_active'] === true
                    && isset($data['payment_id']);
            }))
            ->andReturn(true);

        $result = $this->service->completeAccessPurchase('pi_test123');

        $this->assertTrue($result['success']);
    }

    public function testCompleteAccessPurchaseFailsForInvalidToken(): void
    {
        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->stripeProcessorMock
            ->shouldReceive('confirmPaymentIntent')
            ->once()
            ->with('pi_test123')
            ->andReturn([
                'success' => true,
                'status' => 'succeeded'
            ]);

        $this->repositoryMock
            ->shouldReceive('findByPaymentIntent')
            ->once()
            ->with('pi_test123')
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Access record not found');

        $this->service->completeAccessPurchase('pi_test123');
    }

    public function testCheckAccessReturnsTrueForValidAccess(): void
    {
        $mockAccess = m::mock(SingleContentAccess::class)->makePartial();
        $mockAccess->shouldReceive('isValid')->andReturn(true);
        $mockAccess->expires_at = new \DateTime('+7 days');

        $this->repositoryMock
            ->shouldReceive('getActiveAccess')
            ->with($this->testMember->id, 'page', 1, $this->siteId)
            ->once()
            ->andReturn($mockAccess);

        $result = $this->service->checkAccess(
            $this->testMember->id,
            'page',
            1,
            $this->siteId
        );

        $this->assertTrue($result['has_access']);
    }

    public function testCheckAccessReturnsFalseWhenNoAccess(): void
    {
        $this->repositoryMock
            ->shouldReceive('getActiveAccess')
            ->with($this->testMember->id, 'newsletter', 5, $this->siteId)
            ->once()
            ->andReturn(null);

        $result = $this->service->checkAccess(
            $this->testMember->id,
            'newsletter',
            5,
            $this->siteId
        );

        $this->assertFalse($result['has_access']);
    }

    public function testGetMemberActiveAccessReturnsCollection(): void
    {
        $mockAccess1 = m::mock(SingleContentAccess::class)->makePartial();
        $mockAccess1->id = 1;
        $mockAccess1->content_type = 'page';
        $mockAccess1->content_id = 1;
        $mockAccess1->access_token = 'token1';
        $mockAccess1->purchased_at = new \DateTime();
        $mockAccess1->expires_at = new \DateTime('+7 days');
        $mockAccess1->shouldReceive('isValid')->andReturn(true);
        $mockAccess1->shouldReceive('getContent')->andReturn((object)['title' => 'Test Page']);

        $mockAccess2 = m::mock(SingleContentAccess::class)->makePartial();
        $mockAccess2->id = 2;
        $mockAccess2->content_type = 'newsletter';
        $mockAccess2->content_id = 2;
        $mockAccess2->access_token = 'token2';
        $mockAccess2->purchased_at = new \DateTime();
        $mockAccess2->expires_at = new \DateTime('+30 days');
        $mockAccess2->shouldReceive('isValid')->andReturn(true);
        $mockAccess2->shouldReceive('getContent')->andReturn((object)['title' => 'Test Newsletter']);

        $mockCollection = new \App\Framework\Support\Collection([$mockAccess1, $mockAccess2]);

        $this->repositoryMock
            ->shouldReceive('getMemberActiveAccess')
            ->with($this->testMember->id, $this->siteId)
            ->once()
            ->andReturn($mockCollection);

        $result = $this->service->getMemberActiveAccess($this->testMember->id, $this->siteId);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testGetContentAccessDetailsReturnsCorrectPricing(): void
    {
        $details = $this->service->getContentAccessDetails('page', 1);

        $this->assertIsArray($details);
        $this->assertArrayHasKey('price', $details);
        $this->assertArrayHasKey('currency', $details);
        $this->assertArrayHasKey('duration_days', $details);
    }

    public function testPurchaseAccessCalculatesCorrectExpiryDate(): void
    {
        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->repositoryMock->shouldReceive('hasActiveAccess')
            ->once()
            ->andReturn(false);

        $this->stripeProcessorMock
            ->shouldReceive('createPaymentIntentWithCustomer')
            ->once()
            ->andReturn([
                'success' => true,
                'payment_intent_id' => 'pi_test123',
                'client_secret' => 'pi_test123_secret'
            ]);

        $capturedAccessData = null;
        $mockAccess = m::mock(SingleContentAccess::class)->makePartial();
        $mockAccess->id = 1;
        $mockAccess->access_token = 'test_token';

        $this->repositoryMock
            ->shouldReceive('createAccess')
            ->once()
            ->with(m::capture($capturedAccessData))
            ->andReturn($mockAccess);

        $this->service->purchaseAccess(
            $this->testMember->id,
            $this->siteId,
            'newsletter',
            2,
            4.99,
            'USD',
            30 // 30 days
        );

        $this->assertNotNull($capturedAccessData);
        $this->assertEquals(30, $capturedAccessData['duration_days']);

        // Check that expires_at is approximately 30 days from now
        $expiresAt = new \DateTime($capturedAccessData['expires_at']);
        $expectedExpiry = now_datetime()->modify('+30 days');
        $diff = $expiresAt->diff($expectedExpiry);

        $this->assertLessThan(60, $diff->s); // Within 60 seconds
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->repositoryMock = m::mock(SingleContentAccessRepository::class);
        $this->stripeProcessorMock = m::mock(StripePaymentProcessor::class);
        $this->paymentRepositoryMock = m::mock(PaymentRepository::class);
        $this->databaseMock = m::mock(Database::class);

        $this->service = new SingleContentAccessService(
            $this->repositoryMock,
            $this->paymentRepositoryMock,
            $this->stripeProcessorMock,
            $this->databaseMock
        );

        $this->testMember = Member::create([
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'site_id' => $this->siteId,
            'email_verified_at' => now_datetime()
        ]);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    private function setupTransactionExpectations(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });
    }
}