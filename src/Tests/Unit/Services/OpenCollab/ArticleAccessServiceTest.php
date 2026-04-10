<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Events\OpenCollab\ArticlePurchasedEvent;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Support\Logger;
use App\Models\ArticlePayment;
use App\Models\Page;
use App\Repositories\OpenCollab\ArticleAccessRepository;
use App\Repositories\OpenCollab\ArticlePaymentRepository;
use App\Services\OpenCollab\ArticleAccessService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class ArticleAccessServiceTest extends FunctionalTestCase
{
    private ArticleAccessService $service;
    private MockInterface $accessRepository;
    private MockInterface $paymentRepository;
    private MockInterface $eventDispatcher;
    private MockInterface $databaseMock;
    private MockInterface $logger;

    public function test_can_view_returns_true_for_free_page(): void
    {
        $page = $this->makeFreePage();

        $this->accessRepository->shouldNotReceive('hasAccessByUserId');
        $this->accessRepository->shouldNotReceive('hasAccessByEmail');

        $this->assertTrue($this->service->canView($page, 1, 'reader@example.com'));
    }

    private function makeFreePage(): Page
    {
        $page = new Page(['id' => 2, 'site_id' => 1, 'is_paid' => false, 'price' => 0]);
        $page->exists = true;
        return $page;
    }

    // -------------------------------------------------------------------------
    // canView()
    // -------------------------------------------------------------------------

    public function test_can_view_returns_true_for_user_with_access_to_paid_page(): void
    {
        $page = $this->makePaidPage();

        $this->accessRepository
            ->shouldReceive('hasAccessByUserId')
            ->with($page->id, 42)
            ->once()
            ->andReturn(true);

        $this->assertTrue($this->service->canView($page, 42, null));
    }

    private function makePaidPage(): Page
    {
        $page = new Page(['id' => 1, 'site_id' => 1, 'is_paid' => true, 'price' => 500]);
        $page->exists = true;
        return $page;
    }

    public function test_can_view_returns_false_for_user_without_access_to_paid_page(): void
    {
        $page = $this->makePaidPage();

        $this->accessRepository
            ->shouldReceive('hasAccessByUserId')
            ->andReturn(false);

        $this->assertFalse($this->service->canView($page, 42, null));
    }

    public function test_can_view_falls_through_to_email_check_when_user_id_is_null(): void
    {
        $page = $this->makePaidPage();

        $this->accessRepository
            ->shouldReceive('hasAccessByEmail')
            ->with($page->id, 'guest@example.com')
            ->once()
            ->andReturn(true);

        $this->assertTrue($this->service->canView($page, null, 'guest@example.com'));
    }

    public function test_can_view_returns_false_for_guest_without_access(): void
    {
        $page = $this->makePaidPage();

        $this->accessRepository
            ->shouldReceive('hasAccessByEmail')
            ->andReturn(false);

        $this->assertFalse($this->service->canView($page, null, 'guest@example.com'));
    }

    // -------------------------------------------------------------------------
    // grantAccessFromPayment()
    // -------------------------------------------------------------------------

    public function test_grants_access_and_emits_event_on_success(): void
    {
        $payment = $this->makePendingPayment();

        $this->paymentRepository
            ->shouldReceive('findByPaymentIntentId')
            ->with('pi_test')
            ->once()
            ->andReturn($payment);

        $this->paymentRepository
            ->shouldReceive('updateStatus')
            ->with($payment->id, 'succeeded')
            ->once();

        $this->accessRepository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data) use ($payment): bool {
                return $data['page_id'] === $payment->page_id
                    && $data['email'] === $payment->email;
            });

        // Simulate model refresh returning the same object.
        $payment->shouldReceive('refresh')->andReturnSelf();

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->withArgs(fn($event) => $event instanceof ArticlePurchasedEvent);

        $this->service->grantAccessFromPayment('pi_test');
        $this->assertTrue(true);
    }

    private function makePendingPayment(): MockInterface
    {
        $payment = Mockery::mock(ArticlePayment::class)->makePartial();
        $payment->id = 1;
        $payment->site_id = 1;
        $payment->page_id = 1;
        $payment->user_id = 42;
        $payment->email = 'buyer@example.com';
        $payment->status = 'pending';
        $payment->amount = 500;
        $payment->shouldReceive('hasSucceeded')->andReturn(false);
        return $payment;
    }

    public function test_throws_runtime_exception_when_payment_intent_not_found(): void
    {
        $this->paymentRepository
            ->shouldReceive('findByPaymentIntentId')
            ->andReturn(null);

        $this->accessRepository->shouldNotReceive('create');
        $this->eventDispatcher->shouldNotReceive('dispatch');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/not found/i');

        $this->service->grantAccessFromPayment('pi_ghost');
    }

    public function test_is_idempotent_when_payment_already_succeeded(): void
    {
        $payment = $this->makeSucceededPayment();

        $this->paymentRepository
            ->shouldReceive('findByPaymentIntentId')
            ->andReturn($payment);

        $this->logger
            ->shouldReceive('info')
            ->once()
            ->withArgs(fn(string $msg) => str_contains($msg, 'already-succeeded'));

        $this->paymentRepository->shouldNotReceive('updateStatus');
        $this->accessRepository->shouldNotReceive('create');
        $this->eventDispatcher->shouldNotReceive('dispatch');

        $this->service->grantAccessFromPayment('pi_already_done');
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // recordPaymentFailure()
    // -------------------------------------------------------------------------

    private function makeSucceededPayment(): MockInterface
    {
        $payment = Mockery::mock(ArticlePayment::class)->makePartial();
        $payment->id = 1;
        $payment->status = 'succeeded';
        $payment->shouldReceive('hasSucceeded')->andReturn(true);
        return $payment;
    }

    public function test_wraps_access_grant_in_transaction(): void
    {
        $this->databaseMock->shouldNotReceive('transaction')->byDefault();

        $payment = $this->makePendingPayment();
        $payment->shouldReceive('refresh')->andReturnSelf();

        $this->paymentRepository->shouldReceive('findByPaymentIntentId')->andReturn($payment);
        $this->paymentRepository->shouldReceive('updateStatus');
        $this->accessRepository->shouldReceive('create');
        $this->eventDispatcher->shouldReceive('dispatch');

        $this->service->grantAccessFromPayment('pi_test');
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function test_records_payment_failure(): void
    {
        $payment = $this->makePendingPayment();

        $this->paymentRepository
            ->shouldReceive('findByPaymentIntentId')
            ->with('pi_failed')
            ->andReturn($payment);

        $this->paymentRepository
            ->shouldReceive('updateStatus')
            ->with($payment->id, 'failed')
            ->once();

        $this->service->recordPaymentFailure('pi_failed');
        $this->assertTrue(true);
    }

    public function test_logs_warning_for_unknown_payment_intent_on_failure(): void
    {
        $this->paymentRepository
            ->shouldReceive('findByPaymentIntentId')
            ->andReturn(null);

        $this->logger
            ->shouldReceive('warning')
            ->once()
            ->withArgs(fn(string $msg) => str_contains($msg, 'unknown payment intent'));

        $this->paymentRepository->shouldNotReceive('updateStatus');

        $this->service->recordPaymentFailure('pi_ghost');
        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->accessRepository = Mockery::mock(ArticleAccessRepository::class);
        $this->paymentRepository = Mockery::mock(ArticlePaymentRepository::class);
        $this->eventDispatcher = Mockery::mock(EventDispatcher::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->logger = Mockery::mock(Logger::class);

        $this->databaseMock
            ->shouldReceive('transaction')
            ->andReturnUsing(fn(callable $cb) => $cb());

        $this->service = new ArticleAccessService(
            $this->accessRepository,
            $this->paymentRepository,
            $this->eventDispatcher,
            $this->databaseMock,
            $this->logger,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}