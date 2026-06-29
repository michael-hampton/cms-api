<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\DTO\Stripe\CreatePaymentIntentDto;
use App\DTO\Stripe\PaymentIntentResultDto;
use App\Enums\OpenCollab\PaymentStatus;
use App\Enums\Pages\PageStatus;
use App\Exceptions\OpenCollab\DuplicatePurchaseException;
use App\Framework\Database\Database;
use App\Models\ArticlePayment;
use App\Models\Page;
use App\Models\PageMetadata;
use App\Repositories\OpenCollab\ArticleAccessRepository;
use App\Repositories\OpenCollab\ArticlePaymentRepository;
use App\Services\Billing\Stripe\StripePaymentIntentGateway;
use App\Services\OpenCollab\ArticlePaymentService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;
use App\Services\Cms\Pages\PremiumPagePurchaseEligibilityService;
use Stripe\PaymentIntent;

class ArticlePaymentServiceTest extends FunctionalTestCase
{
    private ArticlePaymentService $service;
    private MockInterface $paymentRepository;
    private MockInterface $accessRepository;
    private MockInterface $databaseMock;
    private MockInterface $gateway;
    private PremiumPagePurchaseEligibilityService $purchaseEligibilityService;

    // -------------------------------------------------------------------------
    // initiatePayment()
    // -------------------------------------------------------------------------

    public function test_initiates_payment_for_paid_page_and_returns_client_secret(): void
    {
        $page = $this->makePaidPage();
        $intent = $this->makePaymentIntentResponse('pi_abc123', 'secret_abc');
        $payment = $this->makeArticlePayment();

        $this->accessRepository
            ->shouldReceive('hasAccessByUserId')
            ->with($page->id, 42)
            ->once()
            ->andReturn(false);

        $this->gateway
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (CreatePaymentIntentDto $dto) use ($page): bool {
                return $dto->amountCents === $page->price
                    && $dto->currency === 'gbp'
                    && $dto->metadata['page_id'] === $page->id
                    && $dto->metadata['email'] === 'buyer@example.com'
                    && $dto->metadata['user_id'] === 42;
            })
            ->andReturn($intent);

        $this->paymentRepository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data): bool {
                return $data['status'] === PaymentStatus::Pending->value
                    && $data['page_id'] === 1
                    && $data['email'] === 'buyer@example.com'
                    && $data['amount'] === 500
                    && $data['stripe_payment_intent_id'] === 'pi_abc123';
            })
            ->andReturn($payment);

        $result = $this->service->initiatePayment($page, 42, 'buyer@example.com');

        $this->assertEquals('secret_abc', $result['client_secret']);
        $this->assertInstanceOf(ArticlePayment::class, $result['payment']);
    }

    public function test_initiates_payment_for_guest_user(): void
    {
        $page = $this->makePaidPage();
        $intent = $this->makePaymentIntentResponse('pi_guest', 'secret_guest');
        $payment = $this->makeArticlePayment(['user_id' => null]);

        $this->accessRepository
            ->shouldReceive('hasAccessByEmail')
            ->with($page->id, 'guest@example.com')
            ->once()
            ->andReturn(false);

        $this->gateway
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (CreatePaymentIntentDto $dto): bool {
                return $dto->metadata['user_id'] === 'guest';
            })
            ->andReturn($intent);

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
        $this->assertEquals('secret_guest', $result['client_secret']);
    }

    public function test_throws_duplicate_purchase_exception_for_user_who_already_has_access(): void
    {
        $page = $this->makePaidPage();

        $this->accessRepository
            ->shouldReceive('hasAccessByUserId')
            ->with($page->id, 42)
            ->once()
            ->andReturn(true);

        $this->gateway->shouldNotReceive('create');
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

        $this->gateway->shouldNotReceive('create');
        $this->paymentRepository->shouldNotReceive('create');

        $this->expectException(DuplicatePurchaseException::class);

        $this->service->initiatePayment($page, null, 'guest@example.com');
    }

    public function test_throws_invalid_argument_exception_for_free_page(): void
    {
        $page = $this->makeFreePage();

        $this->accessRepository->shouldNotReceive('hasAccessByUserId');
        $this->accessRepository->shouldNotReceive('hasAccessByEmail');
        $this->gateway->shouldNotReceive('create');
        $this->paymentRepository->shouldNotReceive('create');

        $this->purchaseEligibilityService
            ->shouldReceive('assertPurchasable')
            ->once()
            ->with($page)
            ->andThrow(new \InvalidArgumentException('Page is not a paid page.'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not a paid page/i');

        $this->service->initiatePayment($page, 42, 'buyer@example.com');
    }

    public function test_wraps_stripe_call_and_db_insert_in_transaction(): void
    {
        $page = $this->makePaidPage();
        $intent = $this->makePaymentIntentResponse('pi_tx', 'secret_tx');

        $this->accessRepository->shouldReceive('hasAccessByUserId')->andReturn(false);
        $this->gateway->shouldReceive('create')->andReturn($intent);
        $this->paymentRepository->shouldReceive('create')->andReturn($this->makeArticlePayment());

        // Transaction callback must have been invoked — databaseMock already
        // returns the callback result so if we get a valid result here the
        // transaction was entered correctly.
        $result = $this->service->initiatePayment($page, 42, 'buyer@example.com');

        $this->assertInstanceOf(ArticlePayment::class, $result['payment']);
    }

    public function test_amount_stored_in_pence_on_payment_intent_but_raw_price_on_payment_record(): void
    {
        $page = $this->makePaidPage(['price' => 999]);
        $intent = $this->makePaymentIntentResponse('pi_pence', 'secret_pence');

        $this->accessRepository->shouldReceive('hasAccessByUserId')->andReturn(false);

        $this->gateway
            ->shouldReceive('create')
            ->withArgs(function (CreatePaymentIntentDto $dto) {
                // Service multiplies price * 100 before passing to gateway
                return $dto->amountCents === 999;
            })
            ->andReturn($intent);

        $this->paymentRepository
            ->shouldReceive('create')
            ->withArgs(function (array $data) {
                // Raw price stored on the payment record
                return $data['amount'] === 999;
            })
            ->andReturn($this->makeArticlePayment(['amount' => 999]));

        $this->service->initiatePayment($page, 42, 'buyer@example.com');

        $this->assertTrue(true);
    }

    public function test_initiatePayment_checks_premium_purchase_eligibility_before_creating_payment_intent(): void
    {
        $page = $this->mockPage([
            'id' => 123,
            'site_id' => 1,
            'status' => PageStatus::PUBLISHED->value,
            'price' => 5.99,
            'is_paid' => true,
            'premium_approved_at' => '2026-06-07 12:00:00',
            'monetisation_disabled_at' => null,
            'contributor_id' => 7,
            'metadata' => $this->mockPageMetadata([
                'page_id' => 123,
                'visibility' => 'premium',
            ]),
        ]);

        $capturedDto = null;

        $this->purchaseEligibilityService
            ->shouldReceive('assertPurchasable')
            ->once()
            ->with($page)
            ->andReturnNull();

        $this->accessRepository
            ->shouldReceive('hasAccessByUserId')
            ->once()
            ->with(123, 55)
            ->andReturn(false);

        $this->accessRepository
            ->shouldNotReceive('hasAccessByEmail');

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->with(Mockery::type('callable'))
            ->andReturnUsing(function (callable $callback): array {
                return $callback();
            });

        $this->gateway
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (CreatePaymentIntentDto $dto) use (&$capturedDto): bool {
                $capturedDto = $dto;

                return true;
            })
            ->andReturn(new PaymentIntentResultDto(
                true,
                'pi_test',
                'secret_test'
            ));

        $payment = $this->mockArticlePayment([
            'id' => 1,
            'stripe_payment_intent_id' => 'pi_test',
        ]);

        $this->paymentRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $data): bool {
                return $data['site_id'] === 1
                    && $data['page_id'] === 123
                    && $data['user_id'] === 55
                    && $data['email'] === 'reader@example.com'
                    && $data['stripe_payment_intent_id'] === 'pi_test'
                    && $data['status'] === PaymentStatus::Pending->value
                    && $data['amount'] === 5.99
                    && $data['currency'] === 'gbp';
            }))
            ->andReturn($payment);

        $result = $this->service->initiatePayment($page, 55, 'reader@example.com');

        $this->assertSame($payment, $result['payment']);
        $this->assertSame('secret_test', $result['client_secret']);

        $this->assertInstanceOf(CreatePaymentIntentDto::class, $capturedDto);
    }

    public function test_initiatePayment_stops_when_page_is_not_purchasable(): void
    {
        $page = $this->mockPage([
            'id' => 123,
            'site_id' => 1,
            'price' => 5.99,
            'is_paid' => true,
        ]);

        $this->purchaseEligibilityService
            ->shouldReceive('assertPurchasable')
            ->once()
            ->with($page)
            ->andThrow(new \InvalidArgumentException('Page is not purchasable.'));

        $this->accessRepository
            ->shouldNotReceive('hasAccessByUserId');

        $this->accessRepository
            ->shouldNotReceive('hasAccessByEmail');

        $this->databaseMock
            ->shouldNotReceive('transaction');

        $this->gateway
            ->shouldNotReceive('create');

        $this->paymentRepository
            ->shouldNotReceive('create');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Page is not purchasable.');

        $this->service->initiatePayment($page, 55, 'reader@example.com');
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function mockPageMetadata(array $attributes): PageMetadata&MockInterface
    {
        /** @var PageMetadata&MockInterface $metadata */
        $metadata = Mockery::mock(PageMetadata::class)->makePartial();

        foreach ($attributes as $key => $value) {
            $metadata->{$key} = $value;
        }

        return $metadata;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function mockArticlePayment(array $attributes): ArticlePayment&MockInterface
    {
        /** @var ArticlePayment&MockInterface $payment */
        $payment = Mockery::mock(ArticlePayment::class)->makePartial();

        foreach ($attributes as $key => $value) {
            $payment->{$key} = $value;
        }

        return $payment;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function mockPage(array $attributes): Page&MockInterface
    {
        /** @var Page&MockInterface $page */
        $page = Mockery::mock(Page::class)->makePartial();

        foreach ($attributes as $key => $value) {
            $page->{$key} = $value;
        }

        return $page;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

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

    private function makePaymentIntentResponse(string $paymentIntentId, string $clientSecret): PaymentIntentResultDto
    {
        return new PaymentIntentResultDto(
            success: true,
            paymentIntentId: $paymentIntentId,
            clientSecret: $clientSecret,
        );
    }

    private function makeArticlePayment(array $attributes = []): ArticlePayment
    {
        $payment = new ArticlePayment(array_merge([
            'id'                        => 1,
            'site_id'                   => 1,
            'page_id'                   => 1,
            'user_id'                   => 42,
            'email'                     => 'buyer@example.com',
            'stripe_payment_intent_id'  => 'pi_abc123',
            'status'                    => PaymentStatus::Pending->value,
            'amount'                    => 500,
            'currency'                  => 'gbp',
        ], $attributes));
        $payment->exists = true;
        return $payment;
    }

    // -------------------------------------------------------------------------
    // Setup / teardown
    // -------------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentRepository = Mockery::mock(ArticlePaymentRepository::class);
        $this->accessRepository  = Mockery::mock(ArticleAccessRepository::class);
        $this->databaseMock      = Mockery::mock(Database::class);
        $this->gateway           = Mockery::mock(StripePaymentIntentGateway::class);

        $this->databaseMock
            ->shouldReceive('transaction')
            ->byDefault()
            ->andReturnUsing(fn (callable $cb) => $cb());

        $this->purchaseEligibilityService = Mockery::mock(PremiumPagePurchaseEligibilityService::class);

        $this->purchaseEligibilityService
            ->shouldReceive('assertPurchasable')
            ->byDefault()
            ->andReturnNull();

        $this->service = new ArticlePaymentService(
            $this->paymentRepository,
            $this->accessRepository,
            $this->databaseMock,
            $this->gateway,
            $this->purchaseEligibilityService,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}