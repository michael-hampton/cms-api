<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Enums\OpenCollab\PaymentStatus;
use App\Exceptions\OpenCollab\DuplicatePurchaseException;
use App\Framework\Database\Database;
use App\Models\ArticlePayment;
use App\Models\Page;
use App\Repositories\OpenCollab\ArticleAccessRepository;
use App\Repositories\OpenCollab\ArticlePaymentRepository;
use App\Services\Billing\PaymentProviders\PaymentIntentGateway;
use App\Services\OpenCollab\ArticlePaymentService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;
use Stripe\Service\PaymentIntentService;

class ArticlePaymentServiceTest extends FunctionalTestCase
{
    private ArticlePaymentService $service;
    private MockInterface $paymentRepository;
    private MockInterface $accessRepository;
    private MockInterface $databaseMock;
    private MockInterface $stripe;
    private MockInterface $stripePaymentIntents;

    public function test_initiates_payment_for_paid_page_and_returns_client_secret(): void
    {
        $page = $this->makePaidPage();
        $intent = $this->makePaymentIntent('pi_abc123', 'secret_abc');
        $payment = $this->makeArticlePayment();

        $this->accessRepository
            ->shouldReceive('hasAccessByUserId')
            ->with($page->id, 42)
            ->once()
            ->andReturn(false);

        $this->stripe
            ->shouldReceive('create')
            ->once()
            ->andReturn($intent);

        $this->paymentRepository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data): bool {
                return $data['status'] === PaymentStatus::Pending->value
                    && $data['page_id'] === 1
                    && $data['email'] === 'buyer@example.com'
                    && $data['amount'] === 500;
            })
            ->andReturn($payment);

        $result = $this->service->initiatePayment($page, 42, 'buyer@example.com');

        $this->assertEquals('secret_abc', $result['client_secret']);
        $this->assertInstanceOf(ArticlePayment::class, $result['payment']);
    }

    private function makePaidPage(array $attributes = []): Page
    {
        $page = new Page(array_merge([
            'id' => 1,
            'site_id' => 1,
            'title' => 'Paid Article',
            'status' => 'published',
            'is_paid' => true,
            'price' => 500,
        ], $attributes));
        $page->exists = true;
        return $page;
    }

    // -------------------------------------------------------------------------
    // initiatePayment()
    // -------------------------------------------------------------------------

    private function makePaymentIntent(string $id, string $clientSecret)
    {
        return (object)[
            'id' => $id,
            'client_secret' => $clientSecret,
        ];
    }

    private function makeArticlePayment(array $attributes = []): ArticlePayment
    {
        $payment = new ArticlePayment(array_merge([
            'id' => 1,
            'site_id' => 1,
            'page_id' => 1,
            'user_id' => 42,
            'email' => 'buyer@example.com',
            'stripe_payment_intent_id' => 'pi_abc123',
            'status' => PaymentStatus::Pending->value,
            'amount' => 500,
            'currency' => 'gbp',
        ], $attributes));
        $payment->exists = true;
        return $payment;
    }

    public function test_initiates_payment_for_guest_user(): void
    {
        $page = $this->makePaidPage();
        $intent = $this->makePaymentIntent('pi_guest', 'secret_guest');
        $payment = $this->makeArticlePayment(['user_id' => null]);

        $this->accessRepository
            ->shouldReceive('hasAccessByEmail')
            ->with($page->id, 'guest@example.com')
            ->once()
            ->andReturn(false);

        $this->stripe->shouldReceive('create')->andReturn($intent);

        $this->paymentRepository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data): bool {
                return $data['user_id'] === null
                    && $data['email'] === 'guest@example.com';
            })
            ->andReturn($payment);

        $result = $this->service->initiatePayment($page, null, 'guest@example.com');

        $this->assertArrayHasKey('client_secret', $result);
    }

    public function test_throws_duplicate_purchase_exception_for_user_who_already_has_access(): void
    {
        $page = $this->makePaidPage();

        $this->accessRepository
            ->shouldReceive('hasAccessByUserId')
            ->with($page->id, 42)
            ->once()
            ->andReturn(true);

        $this->stripePaymentIntents->shouldNotReceive('create');
        $this->paymentRepository->shouldNotReceive('create');

        $this->expectException(DuplicatePurchaseException::class);

        $this->service->initiatePayment($page, 42, 'buyer@example.com');
    }

    public function test_throws_duplicate_purchase_exception_for_guest_who_already_has_access(): void
    {
        $page = $this->makePaidPage();

        $this->accessRepository
            ->shouldReceive('hasAccessByEmail')
            ->with($page->id, 'guest@example.com')
            ->once()
            ->andReturn(true);

        $this->stripePaymentIntents->shouldNotReceive('create');
        $this->paymentRepository->shouldNotReceive('create');

        $this->expectException(DuplicatePurchaseException::class);

        $this->service->initiatePayment($page, null, 'guest@example.com');
    }

    public function test_throws_invalid_argument_exception_for_free_page(): void
    {
        $page = $this->makeFreePage();

        $this->accessRepository->shouldNotReceive('hasAccessByUserId');
        $this->stripePaymentIntents->shouldNotReceive('create');
        $this->paymentRepository->shouldNotReceive('create');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not a paid page/i');

        $this->service->initiatePayment($page, 42, 'buyer@example.com');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeFreePage(): Page
    {
        $page = new Page([
            'id' => 2,
            'site_id' => 1,
            'title' => 'Free Article',
            'status' => 'published',
            'is_paid' => false,
            'price' => 0,
        ]);
        $page->exists = true;
        return $page;
    }

    public function test_wraps_stripe_call_and_db_insert_in_transaction(): void
    {
        $this->databaseMock->shouldNotReceive('transaction')->byDefault();

        $page = $this->makePaidPage();
        $intent = $this->makePaymentIntent('pi_tx', 'secret_tx');

        $this->accessRepository->shouldReceive('hasAccessByUserId')->andReturn(false);
        $this->stripe->shouldReceive('create')->andReturn($intent);
        $this->paymentRepository->shouldReceive('create')->andReturn($this->makeArticlePayment());

        $payment = $this->service->initiatePayment($page, 42, 'buyer@example.com');

        $this->assertInstanceOf(ArticlePayment::class, $payment['payment']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentRepository = Mockery::mock(ArticlePaymentRepository::class);
        $this->accessRepository = Mockery::mock(ArticleAccessRepository::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->stripe = Mockery::mock(PaymentIntentGateway::class);
        $this->stripePaymentIntents = Mockery::mock(PaymentIntentService::class);

        $this->stripe->paymentIntents = $this->stripePaymentIntents;

        $this->databaseMock
            ->shouldReceive('transaction')
            ->andReturnUsing(fn(callable $cb) => $cb());

        $this->service = new ArticlePaymentService(
            $this->paymentRepository,
            $this->accessRepository,
            $this->databaseMock,
            $this->stripe,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}