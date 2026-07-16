<?php

namespace App\Tests\Unit\Controllers\Billing;

use App\Controllers\Billing\SavedPaymentMethodsController;
use App\DTO\Billing\PaymentMethodDto;
use App\Events\Billing\DefaultPaymentMethodChanged;
use App\Events\Billing\PaymentMethodAdded;
use App\Events\Billing\PaymentMethodRemoved;
use App\Framework\Events\EventDispatcher;
use App\Framework\Http\Request;
use App\Models\Member;
use App\Services\Auth\Contracts\AuthenticatedMemberResolverInterface;
use App\Services\Billing\Stripe\StripeCustomerPaymentMethodService;
use App\Services\Billing\Stripe\StripePaymentMethodWarningService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

/**
 * These tests exist to prove SavedPaymentMethodsController is genuinely the
 * single shared implementation for both the PressStack account area and the
 * site-scoped member area - there is no per-area controller left to
 * duplicate this behaviour, so these are the only tests needed for either
 * frontend's JSON contract.
 */
class SavedPaymentMethodsControllerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private StripeCustomerPaymentMethodService $paymentMethodService;
    private StripePaymentMethodWarningService $statusResolver;
    private EventDispatcher $events;
    private AuthenticatedMemberResolverInterface $memberResolver;
    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentMethodService = Mockery::mock(StripeCustomerPaymentMethodService::class);
        $this->statusResolver = Mockery::mock(StripePaymentMethodWarningService::class);
        $this->events = Mockery::mock(EventDispatcher::class);
        $this->memberResolver = Mockery::mock(AuthenticatedMemberResolverInterface::class);

        $this->member = Mockery::mock(Member::class)->makePartial();
        $this->member->id = 42;
        $this->member->stripe_customer_id = 'cus_123';
    }

    private function controller(): SavedPaymentMethodsController
    {
        return new SavedPaymentMethodsController(
            $this->paymentMethodService,
            $this->statusResolver,
            $this->events,
            $this->memberResolver,
        );
    }

    public function test_list_returns_unauthorised_when_no_member_resolved(): void
    {
        $this->memberResolver->shouldReceive('resolve')->andReturnNull();
        $this->events->shouldNotReceive('dispatch');

        $response = $this->controller()->list(new Request());

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_list_returns_payment_methods_with_status_from_the_shared_resolver(): void
    {
        $method = new PaymentMethodDto(
            id: 'pm_1',
            type: 'card',
            brand: 'visa',
            last4: '4242',
            expMonth: 1,
            expYear: 2099,
            isDefault: true,
        );

        $this->memberResolver->shouldReceive('resolve')->andReturn($this->member);
        $this->paymentMethodService->shouldReceive('getCustomerPaymentMethods')
            ->once()
            ->with($this->member)
            ->andReturn([
                'success' => true,
                'payment_methods' => [$method],
                'default_payment_method_id' => 'pm_1',
            ]);
        $this->statusResolver->shouldReceive('statusFor')->once()->with($method)
            ->andReturn(\App\Enums\Billing\PaymentMethodStatus::Active);

        $response = $this->controller()->list(new Request());
        $payload = json_decode($response->getContent(), true);

        $this->assertTrue($payload['success']);
        $this->assertSame('active', $payload['payment_methods'][0]['status']);
        $this->assertSame('pm_1', $payload['payment_methods'][0]['id']);
    }

    public function test_store_finalises_setup_intent_and_dispatches_payment_method_added(): void
    {
        $method = new PaymentMethodDto('pm_new', 'card', 'visa', '4242', 1, 2099);

        $this->memberResolver->shouldReceive('resolve')->andReturn($this->member);
        $this->paymentMethodService->shouldReceive('finaliseSetupIntent')
            ->once()
            ->with($this->member, 'seti_123', true)
            ->andReturn(['success' => true, 'payment_method' => $method]);
        $this->statusResolver->shouldReceive('statusFor')->with($method)
            ->andReturn(\App\Enums\Billing\PaymentMethodStatus::Active);

        $this->events->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(fn ($event) => $event instanceof PaymentMethodAdded
                && $event->memberId === 42
                && $event->paymentMethodId === 'pm_new'
                && $event->setAsDefault === true));

        $response = $this->controller()->store(new Request([
            'setup_intent_id' => 'seti_123',
            'set_default' => true,
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertTrue($payload['success']);
    }

    public function test_store_does_not_dispatch_event_on_failure(): void
    {
        $this->memberResolver->shouldReceive('resolve')->andReturn($this->member);
        $this->paymentMethodService->shouldReceive('finaliseSetupIntent')
            ->once()
            ->andReturn(['success' => false, 'message' => 'Card declined.']);

        $this->events->shouldNotReceive('dispatch');

        $response = $this->controller()->store(new Request(['setup_intent_id' => 'seti_bad']));

        $this->assertSame(422, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertFalse($payload['success']);
        $this->assertSame('Card declined.', $payload['message']);
    }

    public function test_set_default_dispatches_default_payment_method_changed(): void
    {
        $this->memberResolver->shouldReceive('resolve')->andReturn($this->member);
        $this->paymentMethodService->shouldReceive('setDefaultPaymentMethod')
            ->once()
            ->with('cus_123', 'pm_1')
            ->andReturn(['success' => true]);

        $this->events->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(fn ($event) => $event instanceof DefaultPaymentMethodChanged
                && $event->paymentMethodId === 'pm_1'));

        $response = $this->controller()->setDefault(new Request(), 'pm_1');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_destroy_blocks_removal_of_last_payment_method_in_use(): void
    {
        $this->memberResolver->shouldReceive('resolve')->andReturn($this->member);
        $this->paymentMethodService->shouldReceive('removePaymentMethod')
            ->once()
            ->with($this->member, 'pm_1')
            ->andReturn([
                'success' => false,
                'message' => 'Cannot remove your only payment method while you have active recurring billing.',
                'error_code' => 'last_payment_method',
            ]);

        $this->events->shouldNotReceive('dispatch');

        $response = $this->controller()->destroy(new Request(), 'pm_1');
        $payload = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertFalse($payload['success']);
    }

    public function test_destroy_dispatches_payment_method_removed_on_success(): void
    {
        $this->memberResolver->shouldReceive('resolve')->andReturn($this->member);
        $this->paymentMethodService->shouldReceive('removePaymentMethod')
            ->once()
            ->with($this->member, 'pm_1')
            ->andReturn(['success' => true]);

        $this->events->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(fn ($event) => $event instanceof PaymentMethodRemoved
                && $event->paymentMethodId === 'pm_1'));

        $response = $this->controller()->destroy(new Request(), 'pm_1');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_replace_adds_new_card_and_removes_old_one_dispatching_both_events(): void
    {
        $newMethod = new PaymentMethodDto('pm_new', 'card', 'visa', '4242', 1, 2099);

        $this->memberResolver->shouldReceive('resolve')->andReturn($this->member);
        $this->paymentMethodService->shouldReceive('finaliseSetupIntent')
            ->once()
            ->with($this->member, 'seti_new', false)
            ->andReturn(['success' => true, 'payment_method' => $newMethod]);
        $this->paymentMethodService->shouldReceive('removePaymentMethod')
            ->once()
            ->with($this->member, 'pm_old')
            ->andReturn(['success' => true]);
        $this->statusResolver->shouldReceive('statusFor')->andReturn(\App\Enums\Billing\PaymentMethodStatus::Active);

        $this->events->shouldReceive('dispatch')->once()
            ->with(Mockery::type(PaymentMethodAdded::class));
        $this->events->shouldReceive('dispatch')->once()
            ->with(Mockery::type(PaymentMethodRemoved::class));

        $response = $this->controller()->replace(
            new Request(['setup_intent_id' => 'seti_new', 'set_default' => false]),
            'pm_old',
        );

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertTrue($payload['success']);
    }

    public function test_replace_keeps_old_card_when_still_in_use_but_still_reports_success(): void
    {
        $newMethod = new PaymentMethodDto('pm_new', 'card', 'visa', '4242', 1, 2099);

        $this->memberResolver->shouldReceive('resolve')->andReturn($this->member);
        $this->paymentMethodService->shouldReceive('finaliseSetupIntent')
            ->once()
            ->andReturn(['success' => true, 'payment_method' => $newMethod]);
        $this->paymentMethodService->shouldReceive('removePaymentMethod')
            ->once()
            ->andReturn(['success' => false, 'message' => 'Card still in use.']);
        $this->statusResolver->shouldReceive('statusFor')->andReturn(\App\Enums\Billing\PaymentMethodStatus::Active);

        // Only the "added" event should fire - the old card was never removed.
        $this->events->shouldReceive('dispatch')->once()->with(Mockery::type(PaymentMethodAdded::class));
        $this->events->shouldNotReceive('dispatch')->with(Mockery::type(PaymentMethodRemoved::class));

        $response = $this->controller()->replace(
            new Request(['setup_intent_id' => 'seti_new']),
            'pm_old',
        );

        $payload = json_decode($response->getContent(), true);
        $this->assertTrue($payload['success']);
        $this->assertStringContainsString('still in use', $payload['message']);
    }
}
