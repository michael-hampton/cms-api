<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Stripe\CreatePaymentIntentDto;
use App\DTO\Stripe\PaymentIntentResultDto;
use App\Framework\Database\Database;
use App\Models\Member;
use App\Models\Model;
use App\Models\Payment;
use App\Models\SingleContentAccess;
use App\Repositories\Billing\PaymentRepository;
use App\Repositories\Subscriptions\SingleContentAccessRepository;
use App\Services\Billing\PaymentProviders\StripePaymentProcessor;
use App\Services\Billing\Stripe\Contracts\StripeCustomerGatewayInterface;
use App\Services\Billing\Stripe\Contracts\StripePaymentIntentGatewayInterface;
use App\Services\Billing\Stripe\StripeCustomerGateway;
use App\Services\Billing\Stripe\StripePaymentIntentGateway;
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
    private StripePaymentIntentGateway $stripePaymentIntentGateway;
    private StripeCustomerGateway $stripeCustomerGateway;

    public function testPurchaseAccessCreatesPaymentIntent(): void
    {
        $paymentIntentResult = new PaymentIntentResultDto(
            true,
            'pi_test123',
            'pi_test123_secret'
        );

        $this->stripeCustomerGateway
            ->shouldReceive('getOrCreate')
            ->once()
            ->with(m::type(Member::class))
            ->andReturn('cus_test123');

        $this->stripePaymentIntentGateway
            ->shouldReceive('createWithCustomer')
            ->once()
            ->withArgs(function (CreatePaymentIntentDto $dto) {
                return $dto->amountCents === 999
                    && $dto->currency === 'USD'
                    && $dto->stripeCustomerId === 'cus_test123'
                    && $dto->metadata['member_id'] === 1
                    && $dto->metadata['site_id'] === 1
                    && $dto->metadata['content_type'] === 'page'
                    && $dto->metadata['content_id'] === 1
                    && $dto->metadata['single_content_access'] === true;
            })
            ->andReturn($paymentIntentResult);

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->repositoryMock
            ->shouldReceive('hasActiveAccess')
            ->once()
            ->with(
                $this->testMember->id,
                'page',
                1,
                $this->siteId
            )
            ->andReturn(false);

        $mockAccess = m::mock(SingleContentAccess::class)->makePartial();
        $mockAccess->id = 1;
        $mockAccess->access_token = 'test_token';

        $this->repositoryMock
            ->shouldReceive('createAccess')
            ->once()
            ->with(m::on(function (array $data) {
                return $data['member_id'] === 1
                    && $data['site_id'] === 1
                    && $data['content_type'] === 'page'
                    && $data['content_id'] === 1
                    && $data['price'] === 9.99
                    && $data['currency'] === 'USD'
                    && $data['duration_days'] === 7
                    && $data['is_active'] === false
                    && $data['metadata']['payment_intent_id'] === 'pi_test123';
            }))
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
        $this->assertEquals(1, $result['access_id']);
        $this->assertEquals('pi_test123', $result['payment_intent_id']);
        $this->assertEquals('pi_test123_secret', $result['client_secret']);
        $this->assertEquals('test_token', $result['access_token']);
        $this->assertArrayHasKey('expires_at', $result);
    }

    public function testPurchaseAccessFailsWhenPaymentIntentFails(): void
    {
        $failedPaymentResult = new PaymentIntentResultDto(
            false,
            null,
            null,
            'Payment failed'
        );

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->repositoryMock
            ->shouldReceive('hasActiveAccess')
            ->once()
            ->with(
                $this->testMember->id,
                'page',
                1,
                $this->siteId
            )
            ->andReturn(false);

        $this->stripeCustomerGateway
            ->shouldReceive('getOrCreate')
            ->once()
            ->with(m::type(Member::class))
            ->andReturn('cus_test123');

        $this->stripePaymentIntentGateway
            ->shouldReceive('createWithCustomer')
            ->once()
            ->withArgs(function (CreatePaymentIntentDto $dto) {
                return $dto->amountCents === 999
                    && $dto->currency === 'USD'
                    && $dto->stripeCustomerId === 'cus_test123'
                    && $dto->metadata['member_id'] === 1
                    && $dto->metadata['site_id'] === 1
                    && $dto->metadata['content_type'] === 'page'
                    && $dto->metadata['content_id'] === 1
                    && $dto->metadata['single_content_access'] === true;
            })
            ->andReturn($failedPaymentResult);

        $this->repositoryMock
            ->shouldNotReceive('createAccess');

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
        $paymentIntentResult = new PaymentIntentResultDto(
            success: true,
            paymentIntentId: 'pi_test123',
            clientSecret: null,
            status: 'succeeded',
        );

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

        $this->stripePaymentIntentGateway
            ->shouldReceive('retrieve')
            ->once()
            ->with('pi_test123')
            ->andReturn($paymentIntentResult);

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

        $mockAccess
            ->shouldReceive('update')
            ->once()
            ->with(m::on(function ($data) {
                return $data['is_active'] === true
                    && $data['payment_id'] === 1;
            }))
            ->andReturn(true);

        $result = $this->service->completeAccessPurchase(
            'pi_test123'
        );

        $this->assertTrue($result['success']);
        $this->assertSame($mockAccess, $result['access']);
        $this->assertSame($mockPayment, $result['payment']);
    }

    public function testCompleteAccessPurchaseFailsForInvalidToken(): void
    {
        $paymentIntentResult = new PaymentIntentResultDto(
            success: true,
            paymentIntentId: 'pi_test123',
            clientSecret: null,
            status: 'succeeded',
        );

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->stripePaymentIntentGateway
            ->shouldReceive('retrieve')
            ->once()
            ->with('pi_test123')
            ->andReturn($paymentIntentResult);

        $this->repositoryMock
            ->shouldReceive('findByPaymentIntent')
            ->once()
            ->with('pi_test123')
            ->andReturn(null);

        $this->paymentRepositoryMock
            ->shouldNotReceive('create');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'Access record not found'
        );

        $this->service->completeAccessPurchase(
            'pi_test123'
        );
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
        $paymentIntentResult = new PaymentIntentResultDto(
            true,
            'pi_test123',
            'pi_test123_secret'
        );

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->repositoryMock
            ->shouldReceive('hasActiveAccess')
            ->once()
            ->with(
                $this->testMember->id,
                'newsletter',
                2,
                $this->siteId
            )
            ->andReturn(false);

        $this->stripeCustomerGateway
            ->shouldReceive('getOrCreate')
            ->once()
            ->with(m::type(Member::class))
            ->andReturn('cus_test123');

        $this->stripePaymentIntentGateway
            ->shouldReceive('createWithCustomer')
            ->once()
            ->withArgs(function (CreatePaymentIntentDto $dto) {
                return $dto->amountCents === 499
                    && $dto->currency === 'USD'
                    && $dto->stripeCustomerId === 'cus_test123'
                    && $dto->metadata['member_id'] === 1
                    && $dto->metadata['site_id'] === 1
                    && $dto->metadata['content_type'] === 'newsletter'
                    && $dto->metadata['content_id'] === 2
                    && $dto->metadata['single_content_access'] === true;
            })
            ->andReturn($paymentIntentResult);

        $capturedAccessData = null;

        $mockAccess = m::mock(SingleContentAccess::class)
            ->makePartial();

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
            30
        );

        $this->assertNotNull(
            $capturedAccessData
        );

        $this->assertEquals(
            30,
            $capturedAccessData['duration_days']
        );

        $expiresAt = new \DateTime(
            $capturedAccessData['expires_at']
        );

        $expectedExpiry = now_datetime()
            ->modify('+30 days');

        $differenceInSeconds = abs(
            $expiresAt->getTimestamp()
            - $expectedExpiry->getTimestamp()
        );

        $this->assertLessThan(
            60,
            $differenceInSeconds
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->repositoryMock = m::mock(SingleContentAccessRepository::class);
        $this->stripeProcessorMock = m::mock(StripePaymentProcessor::class);
        $this->paymentRepositoryMock = m::mock(PaymentRepository::class);
        $this->databaseMock = m::mock(Database::class);
        $this->stripePaymentIntentGateway = m::mock(StripePaymentIntentGateway::class);
        $this->stripeCustomerGateway = m::mock(StripeCustomerGateway::class);

        $this->service = new SingleContentAccessService(
            $this->repositoryMock,
            $this->paymentRepositoryMock,
            $this->stripePaymentIntentGateway,
            $this->stripeCustomerGateway,
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