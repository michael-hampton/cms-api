<?php

namespace App\Tests\Unit\Controllers\Billing;

use App\Controllers\Billing\SavedPaymentMethodsController;
use App\DTO\Billing\PaymentMethodDto;
use App\Enums\Billing\PaymentMethodStatus;
use App\Events\Billing\DefaultPaymentMethodChanged;
use App\Events\Billing\PaymentMethodAdded;
use App\Events\Billing\PaymentMethodRemoved;
use App\Events\Billing\SubscriptionPaymentMethodChanged;
use App\Framework\Events\EventDispatcher;
use App\Framework\Http\Request;
use App\Models\Member;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Auth\Contracts\AuthenticatedMemberResolverInterface;
use App\Services\Billing\Stripe\PaymentMethodSubscriptionUsageResolver;
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
    private PaymentMethodSubscriptionUsageResolver $usageResolver;
    private EventDispatcher $events;
    private AuthenticatedMemberResolverInterface $memberResolver;
    private SubscriptionRepository $subscriptions;
    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentMethodService = Mockery::mock(StripeCustomerPaymentMethodService::class);
        $this->statusResolver = Mockery::mock(StripePaymentMethodWarningService::class);
        $this->usageResolver = Mockery::mock(PaymentMethodSubscriptionUsageResolver::class);
        $this->events = Mockery::mock(EventDispatcher::class);
        $this->memberResolver = Mockery::mock(AuthenticatedMemberResolverInterface::class);
        $this->subscriptions = Mockery::mock(SubscriptionRepository::class);

        $this->member = Mockery::mock(Member::class)->makePartial();
        $this->member->id = 42;
        $this->member->stripe_customer_id = 'cus_123';
    }

    private function controller(): SavedPaymentMethodsController
    {
        return new SavedPaymentMethodsController(
            $this->paymentMethodService,
            $this->statusResolver,
            $this->usageResolver,
            $this->events,
            $this->memberResolver,
            $this->subscriptions,
        );
    }

    public function test_list_returns_unauthorised_when_no_member_resolved(): void
    {
        $this->memberResolver->shouldReceive('resolve')->andReturnNull();
        $this->events->shouldNotReceive('dispatch');

        $response = $this->controller()->list(new Request());

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_list_returns_payment_methods_with_status_and_usage_from_shared_resolvers(): void
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
            ->andReturn(PaymentMethodStatus::Active);
        $this->usageResolver->shouldReceive('usageByPaymentMethod')->once()->with($this->member)
            ->andReturn(['pm_1' => ['count' => 2, 'subscriptions' => [['id' => 7], ['id' => 8]]]]);

        $response = $this->controller()->list(new Request());
        $payload = json_decode($response->getContent(), true);

        $this->assertTrue($payload['success']);
        $this->assertSame('active', $payload['payment_methods'][0]['status']);
        $this->assertSame(2, $payload['payment_methods'][0]['subscription_count']);
        $this->assertTrue($payload['payment_methods'][0]['in_use']);
        $this->assertSame([7, 8], $payload['payment_methods'][0]['subscription_ids']);
    }

    public function test_store_requires_name_on_card(): void
    {
        $this->memberResolver->shouldReceive('resolve')->andReturn($this->member);
        $this->paymentMethodService->shouldNotReceive('finaliseSetupIntent');
        $this->events->shouldNotReceive('dispatch');

        $response = $this->controller()->store(new Request(['setup_intent_id' => 'seti_123']));
        $payload = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertFalse($payload['success']);
        $this->assertStringContainsString('Name on card', $payload['message']);
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
            ->andReturn(PaymentMethodStatus::Active);

        $this->events->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(fn ($event) => $event instanceof PaymentMethodAdded
                && $event->memberId === 42
                && $event->paymentMethodId === 'pm_new'
                && $event->setAsDefault === true));

        $response = $this->controller()->store(new Request([
            'setup_intent_id' => 'seti_123',
            'set_default' => true,
            'name_on_card' => 'Jane Doe',
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

        $response = $this->controller()->store(new Request([
            'setup_intent_id' => 'seti_bad',
            'name_on_card' => 'Jane Doe',
        ]));

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

    public function test_destroy_blocks_removal_when_card_pays_for_subscriptions(): void
    {
        $this->memberResolver->shouldReceive('resolve')->andReturn($this->member);
        $this->usageResolver->shouldReceive('usageByPaymentMethod')->once()->with($this->member)
            ->andReturn(['pm_1' => ['count' => 3, 'subscriptions' => [['id' => 1], ['id' => 2], ['id' => 3]]]]);

        $this->paymentMethodService->shouldNotReceive('removePaymentMethod');
        $this->events->shouldNotReceive('dispatch');

        $response = $this->controller()->destroy(new Request(), 'pm_1');
        $payload = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertFalse($payload['success']);
        $this->assertSame('in_use', $payload['error_code']);
        $this->assertStringContainsString('3 subscriptions', $payload['message']);
    }

    public function test_destroy_dispatches_payment_method_removed_when_unused(): void
    {
        $this->memberResolver->shouldReceive('resolve')->andReturn($this->member);
        $this->usageResolver->shouldReceive('usageByPaymentMethod')->once()->with($this->member)
            ->andReturn([]);
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

    public function test_replace_moves_subscriptions_to_new_card_before_removing_old_one(): void
    {
        $newMethod = new PaymentMethodDto('pm_new', 'card', 'visa', '4242', 1, 2099);

        $this->memberResolver->shouldReceive('resolve')->andReturn($this->member);
        $this->usageResolver->shouldReceive('usageByPaymentMethod')->once()->with($this->member)
            ->andReturn(['pm_old' => ['count' => 1, 'subscriptions' => [
                ['id' => 5, 'stripe_subscription_id' => 'sub_5'],
            ]]]);

        $this->paymentMethodService->shouldReceive('finaliseSetupIntent')
            ->once()
            ->with($this->member, 'seti_new', false)
            ->andReturn(['success' => true, 'payment_method' => $newMethod]);

        $this->usageResolver->shouldReceive('reassignSubscriptions')
            ->once()
            ->with(['sub_5'], 'pm_new');

        $this->paymentMethodService->shouldReceive('removePaymentMethod')
            ->once()
            ->with($this->member, 'pm_old')
            ->andReturn(['success' => true]);
        $this->statusResolver->shouldReceive('statusFor')->andReturn(PaymentMethodStatus::Active);

        $this->events->shouldReceive('dispatch')->once()->with(Mockery::type(PaymentMethodAdded::class));
        $this->events->shouldReceive('dispatch')->once()->with(Mockery::type(SubscriptionPaymentMethodChanged::class));
        $this->events->shouldReceive('dispatch')->once()->with(Mockery::type(PaymentMethodRemoved::class));

        $response = $this->controller()->replace(
            new Request(['setup_intent_id' => 'seti_new', 'set_default' => false, 'name_on_card' => 'Jane Doe']),
            'pm_old',
        );

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertTrue($payload['success']);
        $this->assertStringContainsString('1 subscription', $payload['message']);
    }

    public function test_replace_requires_name_on_card(): void
    {
        $this->memberResolver->shouldReceive('resolve')->andReturn($this->member);
        $this->usageResolver->shouldNotReceive('usageByPaymentMethod');
        $this->paymentMethodService->shouldNotReceive('finaliseSetupIntent');

        $response = $this->controller()->replace(
            new Request(['setup_intent_id' => 'seti_new']),
            'pm_old',
        );

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_change_subscription_payment_method_reassigns_and_dispatches_event(): void
    {
        $subscription = Mockery::mock(\App\Models\Subscription::class)->makePartial();
        $subscription->id = 9;
        $subscription->member_id = 42;
        $subscription->stripe_subscription_id = 'sub_9';

        $this->memberResolver->shouldReceive('resolve')->andReturn($this->member);
        $this->subscriptions->shouldReceive('find')->once()->with(9)->andReturn($subscription);
        $this->paymentMethodService->shouldReceive('verifyPaymentMethodOwnership')
            ->once()->with($this->member, 'pm_1')->andReturn(true);
        $this->usageResolver->shouldReceive('reassignSubscriptions')
            ->once()->with(['sub_9'], 'pm_1');

        $this->events->shouldReceive('dispatch')->once()
            ->with(Mockery::on(fn ($event) => $event instanceof SubscriptionPaymentMethodChanged
                && $event->subscriptionId === 9
                && $event->paymentMethodId === 'pm_1'));

        $response = $this->controller()->changeSubscriptionPaymentMethod(
            new Request(['payment_method_id' => 'pm_1']),
            '9',
        );

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertTrue($payload['success']);
    }

    public function test_change_subscription_payment_method_rejects_subscription_belonging_to_another_member(): void
    {
        $subscription = Mockery::mock(\App\Models\Subscription::class)->makePartial();
        $subscription->id = 9;
        $subscription->member_id = 999; // not this member
        $subscription->stripe_subscription_id = 'sub_9';

        $this->memberResolver->shouldReceive('resolve')->andReturn($this->member);
        $this->subscriptions->shouldReceive('find')->once()->with(9)->andReturn($subscription);
        $this->usageResolver->shouldNotReceive('reassignSubscriptions');
        $this->events->shouldNotReceive('dispatch');

        $response = $this->controller()->changeSubscriptionPaymentMethod(
            new Request(['payment_method_id' => 'pm_1']),
            '9',
        );

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_change_subscription_payment_method_rejects_payment_method_not_owned_by_member(): void
    {
        $subscription = Mockery::mock(\App\Models\Subscription::class)->makePartial();
        $subscription->id = 9;
        $subscription->member_id = 42;
        $subscription->stripe_subscription_id = 'sub_9';

        $this->memberResolver->shouldReceive('resolve')->andReturn($this->member);
        $this->subscriptions->shouldReceive('find')->once()->with(9)->andReturn($subscription);
        $this->paymentMethodService->shouldReceive('verifyPaymentMethodOwnership')
            ->once()->with($this->member, 'pm_stolen')->andReturn(false);
        $this->usageResolver->shouldNotReceive('reassignSubscriptions');
        $this->events->shouldNotReceive('dispatch');

        $response = $this->controller()->changeSubscriptionPaymentMethod(
            new Request(['payment_method_id' => 'pm_stolen']),
            '9',
        );

        $this->assertSame(403, $response->getStatusCode());
    }
}