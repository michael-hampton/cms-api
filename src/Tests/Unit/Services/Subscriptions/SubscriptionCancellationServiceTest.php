<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Framework\Database\Database;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\PaymentRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\Payment\StripePaymentProcessor;
use App\Services\Subscriptions\SubscriptionCancellationService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery as m;

class SubscriptionCancellationServiceTest extends FunctionalTestCase
{
    use CreatesTestData;

    private $subscriptionRepository;
    private $paymentRepository;
    private $stripeProcessor;
    private $databaseMock;
    private SubscriptionCancellationService $service;

    public function testCancelSubscriptionWithStripeAtPeriodEnd(): void
    {
        $subscriptionId = 1;

        $mockPlan = m::mock(SubscriptionPlan::class)->makePartial();
        $mockPlan->billing_period = 'monthly';

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = $subscriptionId;
        $mockSubscription->status = 'active';
        $mockSubscription->payment_subscription_id = 'sub_stripe123';
        $mockSubscription->plan = $mockPlan;
        $mockSubscription->shouldReceive('hasStripeSubscription')->andReturn(true);
        $mockSubscription->shouldReceive('getStripeSubscriptionId')->andReturn('sub_stripe123');

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->twice()
            ->with($subscriptionId)
            ->andReturn($mockSubscription);

        $this->stripeProcessor->shouldReceive('cancelSubscription')
            ->once()
            ->with('sub_stripe123', true)
            ->andReturn([
                'success' => true,
                'status' => 'active',
                'cancel_at_period_end' => true
            ]);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with($subscriptionId, m::on(function ($data) {
                // Must have auto_renew set to false
                if (!isset($data['auto_renew']) || $data['auto_renew'] !== false) {
                    return false;
                }

                // Must have cancelled_at set (any DateTime is fine)
                if (!isset($data['cancelled_at']) || empty($data['cancelled_at'])) {
                    return false;
                }

                // end_date must NOT be set
                if (isset($data['end_date'])) {
                    return false;
                }

                // Do NOT check status; it may be unset for cancel at period end
                return true;
            }))
            ->andReturn($mockSubscription);


        $result = $this->service->cancelSubscription($subscriptionId, [
            'cancel_at_period_end' => true
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame($mockSubscription, $result['subscription']);
    }

    public function testCancelSubscriptionImmediately(): void
    {
        $subscriptionId = 1;

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = $subscriptionId;
        $mockSubscription->status = 'active';
        $mockSubscription->payment_subscription_id = 'sub_stripe123';
        $mockSubscription->shouldReceive('hasStripeSubscription')->andReturn(true);
        $mockSubscription->shouldReceive('getStripeSubscriptionId')->andReturn('sub_stripe123');

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->twice()
            ->with($subscriptionId)
            ->andReturn($mockSubscription);

        $this->stripeProcessor->shouldReceive('cancelSubscription')
            ->once()
            ->with('sub_stripe123', false)
            ->andReturn([
                'success' => true,
                'status' => 'canceled'
            ]);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with($subscriptionId, m::on(function ($data) {
                return $data['status'] === 'cancelled'
                    && $data['auto_renew'] === false
                    && isset($data['end_date']);
            }))
            ->andReturn($mockSubscription);

        $this->subscriptionRepository->shouldReceive('revokeAllPremiumAccess')
            ->once()
            ->with($subscriptionId);

        $result = $this->service->cancelSubscription($subscriptionId, [
            'cancel_at_period_end' => false
        ]);

        $this->assertTrue($result['success']);
    }

    public function testReactivateSubscription(): void
    {
        $subscriptionId = 1;
        $_ENV['APP_ENV'] = 'production';

        $mockPlan = m::mock(SubscriptionPlan::class)->makePartial();
        $mockPlan->billing_period = 'monthly';

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = $subscriptionId;
        $mockSubscription->status = 'cancelled';
        $mockSubscription->payment_subscription_id = 'sub_stripe123';
        $mockSubscription->plan = $mockPlan;
        $mockSubscription->shouldReceive('hasStripeSubscription')->andReturn(true);
        $mockSubscription->shouldReceive('getStripeSubscriptionId')->andReturn('sub_stripe123');

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->twice()
            ->with($subscriptionId)
            ->andReturn($mockSubscription);

        $this->stripeProcessor->shouldReceive('reactivateSubscription')
            ->once()
            ->with('sub_stripe123')
            ->andReturn([
                'success' => true,
                'status' => 'active'
            ]);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with($subscriptionId, m::on(function ($data) {
                return $data['status'] === 'active'
                    && $data['auto_renew'] === true
                    && isset($data['end_date'])
                    && isset($data['next_billing_date']);
            }))
            ->andReturn($mockSubscription);

        $result = $this->service->reactivateSubscription($subscriptionId);

        $this->assertTrue($result['success']);

        $_ENV['APP_ENV'] = 'testing';
    }

    public function testReactivateSubscriptionThrowsIfAlreadyActive(): void
    {
        $subscriptionId = 1;

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = $subscriptionId;
        $mockSubscription->status = 'active'; // Already active

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->once()
            ->with($subscriptionId)
            ->andReturn($mockSubscription);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Can only reactivate cancelled subscriptions');

        $this->service->reactivateSubscription($subscriptionId);
    }

    public function testReactivateSubscriptionThrowsIfSubscriptionAlreadyEnded(): void
    {
        $subscriptionId = 1;

        $mockPlan = m::mock(SubscriptionPlan::class)->makePartial();
        $mockPlan->billing_period = 'monthly';

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = $subscriptionId;
        $mockSubscription->status = 'cancelled';
        $mockSubscription->end_date = new \DateTime('-1 day'); // Already ended
        $mockSubscription->payment_subscription_id = 'sub_stripe123';
        $mockSubscription->plan = $mockPlan;
        $mockSubscription->shouldReceive('hasStripeSubscription')->andReturn(true);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->once()
            ->with($subscriptionId)
            ->andReturn($mockSubscription);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Subscription entitlement period has ended. Please purchase a new subscription.');

        $this->service->reactivateSubscription($subscriptionId);
    }

    public function test_cancel_subscription_throws_exception_when_not_found(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Subscription not found');

        $this->service->cancelSubscription(999);
    }

    public function test_cancel_subscription_throws_exception_when_already_cancelled(): void
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->status = 'cancelled';

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($subscription);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Subscription is already cancelled');

        $this->service->cancelSubscription(1);
    }

    public function test_cancel_subscription_immediately(): void
    {
        $subscriptionId = 1;

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = $subscriptionId;
        $mockSubscription->status = 'active';
        $mockSubscription->type = 'paid';
        $mockSubscription->payment_subscription_id = 'sub_stripe123';
        $mockSubscription->shouldReceive('hasStripeSubscription')->andReturn(true);
        $mockSubscription->shouldReceive('getStripeSubscriptionId')->andReturn('sub_stripe123');
        $mockSubscription->shouldReceive('closeWindow')->once();

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->twice()
            ->with($subscriptionId)
            ->andReturn($mockSubscription);

        $this->subscriptionRepository->shouldReceive('revokeAllPremiumAccess')
            ->once()
            ->with($subscriptionId);

        $this->stripeProcessor->shouldReceive('cancelSubscription')
            ->once()
            ->with('sub_stripe123', false)
            ->andReturn([
                'success' => true,
                'status' => 'canceled'
            ]);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with($subscriptionId, m::on(function ($data) {
                return $data['status'] === 'cancelled'
                    && $data['auto_renew'] === false
                    && isset($data['end_date']);
            }))
            ->andReturn($mockSubscription);

        $result = $this->service->cancelSubscription($subscriptionId, [
            'cancel_at_period_end' => false
        ]);

        $this->assertTrue($result['success']);
    }

    public function test_cancel_subscription_throws_exception_when_stripe_fails(): void
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->status = 'active';
        $subscription->type = 'paid';
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(true);
        $subscription->shouldReceive('getStripeSubscriptionId')->andReturn('sub_123');
        // $subscription->shouldReceive('closeWindow')->once();

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($subscription);

        $this->stripeProcessor->shouldReceive('cancelSubscription')
            ->once()
            ->andReturn([
                'success' => false,
                'message' => 'Stripe API error'
            ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to cancel Stripe subscription: Stripe API error');

        $this->service->cancelSubscription(1);
    }

    public function test_cancel_subscription_with_refund(): void
    {
        $member = $this->createMember();

        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->status = 'active';
        $subscription->type = 'paid';
        $subscription->price = 100;
        $subscription->currency = 'USD';
        $subscription->site_id = 1;
        $subscription->member_id = $member->id;
        $subscription->start_date = (new \DateTime())->modify('-10 days');
        $subscription->end_date = (new \DateTime())->modify('+20 days');
        $subscription->last_payment_date = (new \DateTime())->modify('-10 days');
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(false);
        $subscription->shouldReceive('closeWindow');

        $lastPayment = m::mock(Payment::class)->makePartial();
        $lastPayment->id = 1;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->twice()
            ->with(1)
            ->andReturn($subscription);

        $this->subscriptionRepository->shouldReceive('revokeAllPremiumAccess')
            ->once()
            ->with(1);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->andReturn($subscription);

        $this->paymentRepository->shouldReceive('getLastSubscriptionPayment')
            ->with(1)
            ->once()
            ->andReturn($lastPayment);

        $this->paymentRepository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) {
                return $data['subscription_id'] === 1
                    && $data['amount'] < 0  // Negative for refund
                    && isset($data['metadata']['refund_type'])
                    && $data['metadata']['refund_type'] === 'pro_rated_cancellation';
            }));

        $result = $this->service->cancelSubscription(1, [
            'cancel_at_period_end' => false,
            'create_refund' => true
        ]);

        $this->assertTrue($result['success']);
    }

    public function test_reactivate_subscription_throws_exception_when_not_found(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Subscription not found');

        $this->service->reactivateSubscription(999);
    }

    public function test_reactivate_subscription_throws_exception_when_already_active(): void
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->status = 'active';

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($subscription);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Can only reactivate cancelled subscriptions');

        $this->service->reactivateSubscription(1);
    }

    public function test_reactivate_subscription_successfully(): void
    {
        $subscriptionId = 1;
        $_ENV['APP_ENV'] = 'production';

        $mockPlan = m::mock(SubscriptionPlan::class)->makePartial();
        $mockPlan->billing_period = 'monthly';

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = $subscriptionId;
        $mockSubscription->status = 'cancelled';
        $mockSubscription->end_date = new \DateTime('+5 days');
        $mockSubscription->payment_subscription_id = 'sub_stripe123';
        $mockSubscription->plan = $mockPlan;
        $mockSubscription->shouldReceive('hasStripeSubscription')->andReturn(true);
        $mockSubscription->shouldReceive('getStripeSubscriptionId')->andReturn('sub_stripe123');

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->twice()
            ->with($subscriptionId)
            ->andReturn($mockSubscription);

        $this->stripeProcessor->shouldReceive('reactivateSubscription')
            ->once()
            ->with('sub_stripe123')
            ->andReturn([
                'success' => true,
                'status' => 'active'
            ]);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with($subscriptionId, m::on(function ($data) {
                return $data['status'] === 'active'
                    && $data['auto_renew'] === true
                    && isset($data['end_date'])
                    && isset($data['next_billing_date']);
            }))
            ->andReturn($mockSubscription);

        $result = $this->service->reactivateSubscription($subscriptionId);

        $this->assertTrue($result['success']);

        $_ENV['APP_ENV'] = 'testing';
    }

    public function test_reactivate_subscription_without_stripe(): void
    {
        $mockPlan = m::mock(SubscriptionPlan::class)->makePartial();
        $mockPlan->billing_period = 'monthly';

        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->status = 'cancelled';
        $subscription->end_date = new \DateTime('+5 days');
        $subscription->plan = $mockPlan;
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(false);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->twice()
            ->with(1)
            ->andReturn($subscription);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->andReturn($subscription);

        $result = $this->service->reactivateSubscription(1);

        $this->assertTrue($result['success']);
    }

    public function test_reactivate_subscription_throws_exception_when_stripe_fails(): void
    {
        $_ENV['APP_ENV'] = 'production';

        $mockPlan = m::mock(SubscriptionPlan::class)->makePartial();
        $mockPlan->billing_period = 'monthly';

        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->status = 'cancelled';
        $subscription->end_date = new \DateTime('+5 days');
        $subscription->plan = $mockPlan;
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(true);
        $subscription->shouldReceive('getStripeSubscriptionId')->andReturn('sub_123');

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($subscription);

        $this->stripeProcessor->shouldReceive('reactivateSubscription')
            ->once()
            ->andReturn([
                'success' => false,
                'message' => 'Cannot reactivate',
                'error_code' => 'subscription_already_canceled'
            ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('This subscription cannot be reactivated');

        $this->service->reactivateSubscription(1);

        $_ENV['APP_ENV'] = 'testing';
    }

    public function test_reactivate_subscription_calculates_correct_end_date_for_quarterly(): void
    {
        $mockPlan = m::mock(SubscriptionPlan::class)->makePartial();
        $mockPlan->billing_period = 'quarterly';

        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->status = 'cancelled';
        $subscription->end_date = new \DateTime('+5 days');
        $subscription->plan = $mockPlan;
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(false);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->twice()
            ->with(1)
            ->andReturn($subscription);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with(1, m::on(function ($data) {
                // Verify end date is approximately 3 months from now
                $endDate = new \DateTime($data['end_date']);
                $expectedDate = (new \DateTime())->modify('+3 months');
                $diff = abs($endDate->getTimestamp() - $expectedDate->getTimestamp());
                return $diff < 86400; // Within 1 day
            }))
            ->andReturn($subscription);

        $result = $this->service->reactivateSubscription(1);
        $this->assertTrue($result['success']);
    }

    public function test_reactivate_subscription_calculates_correct_end_date_for_yearly(): void
    {
        $mockPlan = m::mock(SubscriptionPlan::class)->makePartial();
        $mockPlan->billing_period = 'yearly';

        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->status = 'cancelled';
        $subscription->end_date = new \DateTime('+5 days');
        $subscription->plan = $mockPlan;
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(false);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->twice()
            ->with(1)
            ->andReturn($subscription);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with(1, m::on(function ($data) {
                // Verify end date is approximately 1 year from now
                $endDate = new \DateTime($data['end_date']);
                $expectedDate = (new \DateTime())->modify('+1 year');
                $diff = abs($endDate->getTimestamp() - $expectedDate->getTimestamp());
                return $diff < 86400; // Within 1 day
            }))
            ->andReturn($subscription);

        $result = $this->service->reactivateSubscription(1);
        $this->assertTrue($result['success']);
    }

    public function test_reactivate_subscription_handles_lifetime_plan(): void
    {
        $mockPlan = m::mock(SubscriptionPlan::class)->makePartial();
        $mockPlan->billing_period = 'lifetime';

        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->status = 'cancelled';
        $subscription->end_date = new \DateTime('+5 days');
        $subscription->plan = $mockPlan;
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(false);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->twice()
            ->with(1)
            ->andReturn($subscription);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with(1, m::on(function ($data) {
                // Lifetime plans should have null end_date
                return $data['end_date'] === null;
            }))
            ->andReturn($subscription);

        $result = $this->service->reactivateSubscription(1);
        $this->assertTrue($result['success']);
    }

    public function test_reactivate_subscription_throws_exception_when_already_ended(): void
    {
        $mockPlan = m::mock(SubscriptionPlan::class)->makePartial();
        $mockPlan->billing_period = 'monthly';

        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->status = 'cancelled';
        $subscription->end_date = new \DateTime('-1 day');
        $subscription->payment_subscription_id = 'sub_stripe123';
        $subscription->plan = $mockPlan;
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(true);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($subscription);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Subscription entitlement period has ended. Please purchase a new subscription.');

        $this->service->reactivateSubscription(1);
    }

    public function test_cancel_subscription_without_stripe(): void
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->status = 'active';
        $subscription->type = 'free';
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(false);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->twice()
            ->with(1)
            ->andReturn($subscription);

        $this->subscriptionRepository->shouldReceive('revokeAllPremiumAccess')
            ->once()
            ->with(1);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->andReturn($subscription);

        $result = $this->service->cancelSubscription(1, [
            'cancel_at_period_end' => false
        ]);

        $this->assertTrue($result['success']);
    }

    public function test_cancel_subscription_with_stripe_at_period_end(): void
    {
        $subscriptionId = 1;

        $mockPlan = m::mock(SubscriptionPlan::class)->makePartial();
        $mockPlan->billing_period = 'monthly';

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = $subscriptionId;
        $mockSubscription->status = 'active';
        $mockSubscription->type = 'paid';
        $mockSubscription->payment_subscription_id = 'sub_stripe123';
        $mockSubscription->plan = $mockPlan;
        $mockSubscription->shouldReceive('hasStripeSubscription')->andReturn(true);
        $mockSubscription->shouldReceive('getStripeSubscriptionId')->andReturn('sub_stripe123');
        $mockSubscription->shouldReceive('closeWindow')->once();

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->twice()
            ->with($subscriptionId)
            ->andReturn($mockSubscription);

        $this->stripeProcessor->shouldReceive('cancelSubscription')
            ->once()
            ->with('sub_stripe123', true)
            ->andReturn([
                'success' => true,
                'status' => 'active',
                'cancel_at_period_end' => true
            ]);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with($subscriptionId, m::on(function ($data) {
                return isset($data['auto_renew']) && $data['auto_renew'] === false
                    && isset($data['cancelled_at'])
                    && !isset($data['end_date']);
            }))
            ->andReturn($mockSubscription);

        $result = $this->service->cancelSubscription($subscriptionId, [
            'cancel_at_period_end' => true
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame($mockSubscription, $result['subscription']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = m::mock(SubscriptionRepository::class);
        $this->paymentRepository = m::mock(PaymentRepository::class);
        $this->stripeProcessor = m::mock(StripePaymentProcessor::class);
        $this->databaseMock = m::mock(Database::class);

        $this->service = new SubscriptionCancellationService(
            $this->subscriptionRepository,
            $this->paymentRepository,
            $this->stripeProcessor,
            $this->databaseMock
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}