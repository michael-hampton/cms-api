<?php

namespace App\Tests\Unit\Services\Billing;

use App\Enums\ManualPaymentType;
use App\Framework\Database\Database;
use App\Models\Member;
use App\Models\Payment;
use App\Repositories\Billing\PaymentRepository;
use App\Repositories\Members\MemberRepository;
use App\Services\Billing\ManualPaymentService;
use Exception;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class ManualPaymentServiceTest extends TestCase
{
    private PaymentRepository&MockInterface $paymentRepository;
    private MemberRepository&MockInterface  $memberRepository;
    private Database&MockInterface          $database;
    private ManualPaymentService            $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentRepository = Mockery::mock(PaymentRepository::class);
        $this->memberRepository  = Mockery::mock(MemberRepository::class);
        $this->database          = Mockery::mock(Database::class);

        $this->database
            ->shouldReceive('transaction')
            ->andReturnUsing(fn(callable $cb) => $cb());

        $this->service = new ManualPaymentService(
            $this->paymentRepository,
            $this->memberRepository,
            $this->database,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── create ────────────────────────────────────────────────────────────────

    public function test_create_persists_payment_record_and_returns_it(): void
    {
        $member  = $this->makeMember();
        $payment = $this->makePayment();

        $this->memberRepository->shouldReceive('find')->with(1)->andReturn($member);
        $this->paymentRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::subset([
                'member_id'   => 1,
                'site_id'     => 2,
                'amount'      => 49.99,
                'currency'    => 'GBP',
                'created_by'  => 10,
            ]))
            ->andReturn($payment);

        $result = $this->service->create(1, 2, 10, $this->validPayload());

        $this->assertSame($payment, $result);
    }

    public function test_create_wraps_writes_in_a_transaction(): void
    {
        $this->memberRepository->shouldReceive('find')->andReturn($this->makeMember());
        $this->paymentRepository->shouldReceive('create')->andReturn($this->makePayment());

        $this->service->create(1, 2, 10, $this->validPayload());

        $this->database->shouldHaveReceived('transaction')->once();

        $this->assertTrue(true);
    }

    public function test_create_throws_when_member_not_found(): void
    {
        $this->memberRepository->shouldReceive('find')->with(99)->andReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Member not found');

        $this->service->create(99, 2, 10, $this->validPayload());
    }

    public function test_create_throws_when_amount_is_zero(): void
    {
        $this->memberRepository->shouldReceive('find')->andReturn($this->makeMember());

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Amount must be greater than zero');

        $this->service->create(1, 2, 10, $this->validPayload(['amount' => 0]));
    }

    public function test_create_throws_when_amount_is_negative(): void
    {
        $this->memberRepository->shouldReceive('find')->andReturn($this->makeMember());

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Amount must be greater than zero');

        $this->service->create(1, 2, 10, $this->validPayload(['amount' => -10.00]));
    }

    public function test_create_throws_for_invalid_payment_type(): void
    {
        $this->memberRepository->shouldReceive('find')->andReturn($this->makeMember());

        $this->expectException(\ValueError::class);

        $this->service->create(1, 2, 10, $this->validPayload(['type' => 'not_a_type']));
    }

    public function test_create_rounds_amount_to_two_decimal_places(): void
    {
        $this->memberRepository->shouldReceive('find')->andReturn($this->makeMember());
        $this->paymentRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::subset(['amount' => 10.56]))
            ->andReturn($this->makePayment());

        $this->service->create(1, 2, 10, $this->validPayload(['amount' => 10.555]));

        $this->addToAssertionCount(1);
    }

    public function test_create_uppercases_currency(): void
    {
        $this->memberRepository->shouldReceive('find')->andReturn($this->makeMember());
        $this->paymentRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::subset(['currency' => 'USD']))
            ->andReturn($this->makePayment());

        $this->service->create(1, 2, 10, $this->validPayload(['currency' => 'usd']));

        $this->addToAssertionCount(1);
    }

    public function test_create_defaults_currency_to_gbp_when_not_provided(): void
    {
        $payload = $this->validPayload();
        unset($payload['currency']);

        $this->memberRepository->shouldReceive('find')->andReturn($this->makeMember());
        $this->paymentRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::subset(['currency' => 'GBP']))
            ->andReturn($this->makePayment());

        $this->service->create(1, 2, 10, $payload);

        $this->addToAssertionCount(1);
    }

    public function test_create_passes_through_nullable_optional_fields(): void
    {
        $this->memberRepository->shouldReceive('find')->andReturn($this->makeMember());
        $this->paymentRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::subset([
                'reference'       => null,
                'notes'           => null,
                'subscription_id' => null,
                'order_id'        => null,
            ]))
            ->andReturn($this->makePayment());

        $payload = $this->validPayload();
        unset($payload['reference'], $payload['notes'], $payload['subscription_id'], $payload['order_id']);

        $this->service->create(1, 2, 10, $payload);

        $this->addToAssertionCount(1);
    }

    // ── delete ────────────────────────────────────────────────────────────────

    public function test_delete_removes_payment_record(): void
    {
        $payment            = $this->makePayment();
        $payment->member_id = 1;

        $this->paymentRepository->shouldReceive('find')->with(5)->andReturn($payment);
        $this->paymentRepository->shouldReceive('delete')->with(5)->once();

        $this->service->delete(5, 1);

        $this->addToAssertionCount(1);
    }

    public function test_delete_wraps_in_a_transaction(): void
    {
        $payment            = $this->makePayment();
        $payment->member_id = 1;

        $this->paymentRepository->shouldReceive('find')->andReturn($payment);
        $this->paymentRepository->shouldReceive('delete');

        $this->service->delete(5, 1);

        $this->database->shouldHaveReceived('transaction')->once();

        $this->assertTrue(true);
    }

    public function test_delete_throws_when_payment_not_found(): void
    {
        $this->paymentRepository->shouldReceive('find')->with(99)->andReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Manual payment not found');

        $this->service->delete(99, 1);
    }

    public function test_delete_throws_when_payment_belongs_to_different_member(): void
    {
        $payment            = $this->makePayment();
        $payment->member_id = 999;

        $this->paymentRepository->shouldReceive('find')->andReturn($payment);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('does not belong to this member');

        $this->service->delete(5, 1);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'type'            => ManualPaymentType::CASH->value,
            'amount'          => 49.99,
            'currency'        => 'GBP',
            'reference'       => 'REF-001',
            'notes'           => 'Paid at reception',
            'received_at'     => '2024-01-15 10:00:00',
            'subscription_id' => null,
            'order_id'        => null,
        ], $overrides);
    }

    private function makeMember(): Member
    {
        $member     = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        return $member;
    }

    private function makePayment(): Payment
    {
        $payment            = Mockery::mock(Payment::class)->makePartial();
        $payment->id        = 5;
        $payment->member_id = 1;
        $payment->amount    = 49.99;

        return $payment;
    }
}