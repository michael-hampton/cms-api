<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Framework\Database\Database;
use App\Models\IssueDelivery;
use App\Models\Subscription;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\SubscriptionDeliveryService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery as m;

class SubscriptionDeliveryServiceTest extends FunctionalTestCase
{
    private $subscriptionRepository;
    private $databaseMock;
    private SubscriptionDeliveryService $service;
    private $issueDeliveryRepository;

    public function test_pause_delivery_throws_exception_when_subscription_not_found(): void
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

        $this->service->pauseDelivery(
            999,
            new \DateTime('+1 day'),
            new \DateTime('+7 days')
        );
    }

    public function test_pause_delivery_validates_end_date_after_start_date(): void
    {
        $subscription = m::mock(Subscription::class);
        $subscription->shouldReceive('canPauseDelivery')
            ->once()
            ->andReturn(true);

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
        $this->expectExceptionMessage('End date must be after start date');

        $pauseStart = new \DateTime('+7 days');
        $pauseEnd = new \DateTime('+1 day');

        $this->service->pauseDelivery(1, $pauseStart, $pauseEnd);
    }

    public function test_pause_delivery_validates_start_date_not_in_past(): void
    {
        $subscription = m::mock(Subscription::class);
        $subscription->shouldReceive('canPauseDelivery')
            ->once()
            ->andReturn(true);

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
        $this->expectExceptionMessage('Start date cannot be in the past');

        $pauseStart = new \DateTime('-5 days');
        $pauseEnd = new \DateTime('+7 days');

        $this->service->pauseDelivery(1, $pauseStart, $pauseEnd);
    }

    public function test_pause_delivery_throws_exception_for_non_print_subscription(): void
    {
        $subscription = m::mock(Subscription::class);
        $subscription->shouldReceive('canPauseDelivery')
            ->once()
            ->andReturn(false);

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
        $this->expectExceptionMessage('This subscription cannot be paused');

        $this->service->pauseDelivery(
            1,
            new \DateTime('+1 day'),
            new \DateTime('+7 days')
        );
    }

    public function test_pause_delivery_validates_dates(): void
    {
        $subscription = m::mock(Subscription::class);
        $subscription->shouldReceive('canPauseDelivery')
            ->once()
            ->andReturn(true);

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
        $this->expectExceptionMessage('End date must be after start date');

        $pauseStart = new \DateTime('+7 days');
        $pauseEnd = new \DateTime('+1 day');

        $this->service->pauseDelivery(1, $pauseStart, $pauseEnd);
    }

    public function test_pause_delivery_throws_exception_if_already_paused(): void
    {
        $subscription = m::mock(Subscription::class);
        $subscription->shouldReceive('canPauseDelivery')
            ->once()
            ->andReturn(false);
//        $subscription->shouldReceive('isDeliveryPaused')
//            ->once()
//            ->andReturn(true);

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
        $this->expectExceptionMessage('his subscription cannot be paused');

        $this->service->pauseDelivery(
            1,
            new \DateTime('+1 day'),
            new \DateTime('+7 days')
        );
    }

    public function test_pause_delivery_enforces_maximum_duration(): void
    {
        $subscription = m::mock(Subscription::class);
        $subscription->shouldReceive('canPauseDelivery')
            ->once()
            ->andReturn(true);

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
        $this->expectExceptionMessage('Pause period cannot exceed 90 days');

        $pauseStart = new \DateTime('+1 day');
        $pauseEnd = new \DateTime('+100 days');

        $this->service->pauseDelivery(1, $pauseStart, $pauseEnd);
    }

    public function test_pause_delivery_successfully(): void
    {
        $subscription = m::mock(Subscription::class);
        $subscription->shouldReceive('canPauseDelivery')
            ->once()
            ->andReturn(true);

        $this->issueDeliveryRepository->shouldReceive('getUpcomingDeliveries')
            ->with(1)
            ->once()
            ->andReturn(collect([]));

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($subscription);

        $pauseStart = new \DateTime('+1 day');
        $pauseEnd = new \DateTime('+14 days');

        $this->subscriptionRepository->shouldReceive('update')
            ->with(1, m::on(function ($data) use ($pauseStart, $pauseEnd) {
                return $data['delivery_paused'] === true
                    && $data['delivery_pause_start'] === $pauseStart->format('Y-m-d')
                    && $data['delivery_pause_end'] === $pauseEnd->format('Y-m-d');
            }))
            ->once()
            ->andReturn($subscription);

        $result = $this->service->pauseDelivery(1, $pauseStart, $pauseEnd, 'Holiday');

        $this->assertTrue($result['success']);
        $this->assertEquals('Delivery paused successfully', $result['message']);
        $this->assertEquals(13, $result['paused_days']);
    }

    public function test_pause_delivery_adjusts_deliveries_within_pause_period(): void
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('canPauseDelivery')->once()->andReturn(true);
        //$subscription->shouldReceive('isDeliveryPaused')->once()->andReturn(false);

        $pauseStart = new \DateTime('+5 days');
        $pauseEnd = new \DateTime('+15 days');

        // Delivery within pause period
        $delivery1 = m::mock(IssueDelivery::class)->makePartial();
        $delivery1->id = 1;
        $delivery1->estimated_delivery_date = new \DateTime('+10 days');
        $delivery1->metadata = [];

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')->with(1)->once()->andReturn($subscription);
        $this->subscriptionRepository->shouldReceive('update')->once()->andReturn($subscription);

        $this->issueDeliveryRepository->shouldReceive('getUpcomingDeliveries')
            ->with(1)
            ->once()
            ->andReturn(collect([$delivery1]));

        $this->issueDeliveryRepository->shouldReceive('update')
            ->with(1, m::on(function ($data) use ($pauseEnd) {
                $expectedDate = (clone $pauseEnd)->modify('+1 day')->format('Y-m-d H:i:s');
                return $data['estimated_delivery_date'] === $expectedDate
                    && isset($data['metadata']['paused'])
                    && $data['metadata']['paused'] === true;
            }))
            ->once();

        $result = $this->service->pauseDelivery(1, $pauseStart, $pauseEnd);
        $this->assertTrue($result['success']);
    }

    public function test_resume_delivery_throws_exception_when_subscription_not_found(): void
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

        $this->service->resumeDelivery(999);
    }

    public function test_pause_delivery_adjusts_future_deliveries_after_pause_period(): void
    {
        $subscription = m::mock(Subscription::class);
        $subscription->shouldReceive('canPauseDelivery')->once()->andReturn(true);
        //$subscription->shouldReceive('isDeliveryPaused')->once()->andReturn(false);

        $pauseStart = new \DateTime('+5 days');
        $pauseEnd = new \DateTime('+15 days');

        // Delivery after pause period
        $delivery1 = m::mock(IssueDelivery::class)->makePartial();
        $delivery1->id = 1;
        $delivery1->estimated_delivery_date = new \DateTime('+20 days');
        $delivery1->metadata = [];

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')->with(1)->once()->andReturn($subscription);
        $this->subscriptionRepository->shouldReceive('update')->once()->andReturn($subscription);

        $this->issueDeliveryRepository->shouldReceive('getUpcomingDeliveries')
            ->with(1)
            ->once()
            ->andReturn(collect([$delivery1]));

        $this->issueDeliveryRepository->shouldReceive('update')
            ->with(1, m::on(function ($data) {
                return isset($data['metadata']['adjusted_for_pause'])
                    && $data['metadata']['adjusted_for_pause'] === true
                    && isset($data['metadata']['pause_days']);
            }))
            ->once();

        $result = $this->service->pauseDelivery(1, $pauseStart, $pauseEnd);
        $this->assertTrue($result['success']);
    }

    public function test_resume_delivery_throws_exception_when_not_paused(): void
    {
        $subscription = m::mock(Subscription::class);
        $subscription->shouldReceive('canResumeDelivery')
            ->once()
            ->andReturn(false);

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
        $this->expectExceptionMessage('This subscription is not paused');

        $this->service->resumeDelivery(1);
    }

    public function test_resume_delivery_restores_paused_deliveries(): void
    {
        $subscription = m::mock(Subscription::class);
        $subscription->shouldReceive('canResumeDelivery')->once()->andReturn(true);

        $delivery1 = m::mock(IssueDelivery::class)->makePartial();
        $delivery1->id = 1;
        $delivery1->estimated_delivery_date = new \DateTime('+10 days');
        $delivery1->metadata = [
            'paused' => true,
            'original_date' => (new \DateTime('+5 days'))->format('Y-m-d'),
            'pause_days' => 10
        ];

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')->with(1)->once()->andReturn($subscription);
        $this->subscriptionRepository->shouldReceive('update')->once()->andReturn($subscription);

        $this->issueDeliveryRepository->shouldReceive('getUpcomingDeliveries')
            ->with(1)
            ->once()
            ->andReturn(collect([$delivery1]));

        $this->issueDeliveryRepository->shouldReceive('update')
            ->with(1, m::on(function ($data) {
                return !isset($data['metadata']['paused'])
                    && !isset($data['metadata']['original_date'])
                    && !isset($data['metadata']['pause_days']);
            }))
            ->once();

        $result = $this->service->resumeDelivery(1);
        $this->assertTrue($result['success']);
    }

    public function test_resume_delivery_reverts_adjusted_deliveries(): void
    {
        $subscription = m::mock(Subscription::class);
        $subscription->shouldReceive('canResumeDelivery')->once()->andReturn(true);

        $delivery1 = m::mock(IssueDelivery::class)->makePartial();
        $delivery1->id = 1;
        $delivery1->estimated_delivery_date = new \DateTime('+20 days');
        $delivery1->metadata = [
            'adjusted_for_pause' => true,
            'pause_days' => 10
        ];

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')->with(1)->once()->andReturn($subscription);
        $this->subscriptionRepository->shouldReceive('update')->once()->andReturn($subscription);

        $this->issueDeliveryRepository->shouldReceive('getUpcomingDeliveries')
            ->with(1)
            ->once()
            ->andReturn(collect([$delivery1]));

        $this->issueDeliveryRepository->shouldReceive('update')
            ->with(1, m::on(function ($data) {
                return !isset($data['metadata']['adjusted_for_pause'])
                    && !isset($data['metadata']['pause_days']);
            }))
            ->once();

        $result = $this->service->resumeDelivery(1);
        $this->assertTrue($result['success']);
    }

    public function test_get_pause_status_returns_error_when_subscription_not_found(): void
    {
        $this->subscriptionRepository->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $result = $this->service->getPauseStatus(999);

        $this->assertFalse($result['success']);
        $this->assertEquals('Subscription not found', $result['message']);
    }

    public function test_pause_delivery_only_affects_specific_subscription(): void
    {
        // This test ensures we're not accidentally pausing deliveries for other subscriptions
        $subscription = m::mock(Subscription::class);
        $subscription->shouldReceive('canPauseDelivery')->once()->andReturn(true);
        //$subscription->shouldReceive('isDeliveryPaused')->once()->andReturn(false);

        $pauseStart = new \DateTime('+1 day');
        $pauseEnd = new \DateTime('+7 days');

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')->with(1)->once()->andReturn($subscription);
        $this->subscriptionRepository->shouldReceive('update')->once()->andReturn($subscription);

        // CRITICAL: Verify we're only fetching deliveries for subscription ID 1
        $this->issueDeliveryRepository->shouldReceive('getUpcomingDeliveries')
            ->with(1) // Must be called with specific subscription ID
            ->once()
            ->andReturn(collect([]));

        $result = $this->service->pauseDelivery(1, $pauseStart, $pauseEnd);
        $this->assertTrue($result['success']);
    }

    public function test_resume_delivery_successfully(): void
    {
        $subscription = m::mock(Subscription::class);
        $subscription->shouldReceive('canResumeDelivery')
            ->once()
            ->andReturn(true);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($subscription);

        $this->issueDeliveryRepository->shouldReceive('getUpcomingDeliveries')
            ->with(1)
            ->once()
            ->andReturn(collect([]));

        $this->subscriptionRepository->shouldReceive('update')
            ->with(1, m::on(function ($data) {
                return $data['delivery_paused'] === false
                    && $data['delivery_pause_start'] === null
                    && $data['delivery_pause_end'] === null
                    && $data['delivery_pause_reason'] === null;
            }))
            ->once()
            ->andReturn($subscription);

        $result = $this->service->resumeDelivery(1);

        $this->assertTrue($result['success']);
        $this->assertEquals('Delivery resumed successfully', $result['message']);
    }

    public function test_get_pause_status_returns_correct_data(): void
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('isDeliveryPaused')
            ->once()
            ->andReturn(true);
        $subscription->shouldReceive('canPauseDelivery')
            ->once()
            ->andReturn(false);
        $subscription->shouldReceive('canResumeDelivery')
            ->once()
            ->andReturn(true);
        $subscription->shouldReceive('getDaysUntilPauseEnds')
            ->once()
            ->andReturn(5);
        $pauseStart = new \DateTime('+1 day');
        $pauseEnd = new \DateTime('+6 days');

        $subscription->delivery_pause_start = $pauseStart;
        $subscription->delivery_pause_end = $pauseEnd;
        $subscription->delivery_pause_reason = 'Holiday';

        $this->subscriptionRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($subscription);

        $result = $this->service->getPauseStatus(1);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['is_paused']);
        $this->assertFalse($result['can_pause']);
        $this->assertTrue($result['can_resume']);
        $this->assertEquals(5, $result['days_until_resume']);
        $this->assertEquals('Holiday', $result['reason']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = m::mock(SubscriptionRepository::class);
        $this->databaseMock = m::mock(Database::class);
        $this->issueDeliveryRepository = m::mock(IssueDeliveryRepository::class);

        $this->service = new SubscriptionDeliveryService(
            $this->subscriptionRepository,
            $this->issueDeliveryRepository,
            $this->databaseMock
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}