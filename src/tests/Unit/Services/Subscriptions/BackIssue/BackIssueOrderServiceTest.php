<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\BackIssue;

use App\Actions\Stock\FulfilSubscriptionAction;
use App\Actions\Subscriptions\Print\CreatePrintFulfillmentAction;
use App\Enums\Subscriptions\FulfilmentTypeEnum;
use App\Enums\Subscriptions\SubscriptionType;
use App\Exceptions\Stock\StockException;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\IssueDelivery;
use App\Models\Subscription;
use App\Models\SubscriptionIssueFulfilment;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\SubscriptionIssueFulfilmentRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\BackIssue\BackIssueClassifier;
use App\Services\Subscriptions\BackIssue\BackIssueOrderService;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for BackIssueOrderService.
 *
 * Verifies:
 *   - The whole operation runs inside Database::transaction().
 *   - Stock is reserved and confirmed before the fulfilment is created.
 *   - The classifier's decision is passed straight through to the repository.
 *   - Print subscriptions also get a PrintFulfillment created via
 *     CreatePrintFulfillmentAction; digital subscriptions do not.
 *   - A stock failure rolls back — no fulfilment or PrintFulfillment is
 *     created and the exception propagates (money-critical flow).
 *   - An unknown issue/subscription throws before any stock action is attempted.
 */
class BackIssueOrderServiceTest extends TestCase
{
    private IssueDeliveryRepository $issueDeliveryRepository;
    private SubscriptionRepository $subscriptionRepository;
    private SubscriptionIssueFulfilmentRepository $fulfilmentRepository;
    private BackIssueClassifier $classifier;
    private FulfilSubscriptionAction $fulfilSubscriptionAction;
    private CreatePrintFulfillmentAction $createPrintFulfillmentAction;
    private Database $database;
    private Logger $logger;
    private BackIssueOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->issueDeliveryRepository = Mockery::mock(IssueDeliveryRepository::class);
        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $this->fulfilmentRepository = Mockery::mock(SubscriptionIssueFulfilmentRepository::class);
        $this->classifier = Mockery::mock(BackIssueClassifier::class);
        $this->fulfilSubscriptionAction = Mockery::mock(FulfilSubscriptionAction::class);
        $this->createPrintFulfillmentAction = Mockery::mock(CreatePrintFulfillmentAction::class);
        $this->database = Mockery::mock(Database::class);
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $this->service = new BackIssueOrderService(
            $this->issueDeliveryRepository,
            $this->subscriptionRepository,
            $this->fulfilmentRepository,
            $this->classifier,
            $this->fulfilSubscriptionAction,
            $this->createPrintFulfillmentAction,
            $this->database,
            $this->logger,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_creates_back_issue_fulfilment_and_print_fulfilment_for_a_print_subscription(): void
    {
        $issue = $this->makeIssue(id: 55);
        $subscription = $this->makeSubscription(id: 3, deliveryType: SubscriptionType::PRINTED->value);
        $fulfilment = $this->makeFulfilment(id: 900);

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn (callable $callback) => $callback());

        $this->issueDeliveryRepository->shouldReceive('find')->once()->with(55)->andReturn($issue);
        $this->subscriptionRepository->shouldReceive('find')->once()->with(3)->andReturn($subscription);

        $this->fulfilSubscriptionAction->shouldReceive('reserve')->once()->with($issue, 1)->andReturn(777);
        $this->fulfilSubscriptionAction->shouldReceive('confirm')->once()->with(777);

        $this->classifier
            ->shouldReceive('classify')
            ->once()
            ->with($issue)
            ->andReturn(FulfilmentTypeEnum::BACK_ISSUE);

        $this->fulfilmentRepository
            ->shouldReceive('createBackIssueFulfilment')
            ->once()
            ->with(3, 55, FulfilmentTypeEnum::BACK_ISSUE)
            ->andReturn($fulfilment);

        $this->createPrintFulfillmentAction
            ->shouldReceive('execute')
            ->once()
            ->with($subscription, $issue);

        $result = $this->service->order(3, 55);

        $this->assertSame($fulfilment, $result);
    }

    public function test_does_not_create_print_fulfilment_for_a_digital_subscription(): void
    {
        $issue = $this->makeIssue(id: 55);
        $subscription = $this->makeSubscription(id: 4, deliveryType: SubscriptionType::DIGITAL->value);
        $fulfilment = $this->makeFulfilment(id: 901);

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn (callable $callback) => $callback());

        $this->issueDeliveryRepository->shouldReceive('find')->once()->with(55)->andReturn($issue);
        $this->subscriptionRepository->shouldReceive('find')->once()->with(4)->andReturn($subscription);

        $this->fulfilSubscriptionAction->shouldReceive('reserve')->once()->andReturn(778);
        $this->fulfilSubscriptionAction->shouldReceive('confirm')->once()->with(778);

        $this->classifier->shouldReceive('classify')->once()->andReturn(FulfilmentTypeEnum::BACK_ISSUE);

        $this->fulfilmentRepository
            ->shouldReceive('createBackIssueFulfilment')
            ->once()
            ->andReturn($fulfilment);

        $this->createPrintFulfillmentAction->shouldNotReceive('execute');

        $result = $this->service->order(4, 55);

        $this->assertSame($fulfilment, $result);
    }

    public function test_throws_when_issue_does_not_exist_and_never_touches_stock(): void
    {
        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn (callable $callback) => $callback());

        $this->issueDeliveryRepository->shouldReceive('find')->once()->with(999)->andReturn(null);

        $this->fulfilSubscriptionAction->shouldNotReceive('reserve');
        $this->fulfilmentRepository->shouldNotReceive('createBackIssueFulfilment');
        $this->createPrintFulfillmentAction->shouldNotReceive('execute');

        $this->expectException(\InvalidArgumentException::class);

        $this->service->order(3, 999);
    }

    public function test_throws_when_subscription_does_not_exist(): void
    {
        $issue = $this->makeIssue(id: 55);

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn (callable $callback) => $callback());

        $this->issueDeliveryRepository->shouldReceive('find')->once()->with(55)->andReturn($issue);
        $this->subscriptionRepository->shouldReceive('find')->once()->with(999)->andReturn(null);

        $this->fulfilSubscriptionAction->shouldNotReceive('reserve');
        $this->fulfilmentRepository->shouldNotReceive('createBackIssueFulfilment');

        $this->expectException(\InvalidArgumentException::class);

        $this->service->order(999, 55);
    }

    public function test_stock_exception_propagates_and_nothing_else_is_created(): void
    {
        $issue = $this->makeIssue(id: 56);
        $subscription = $this->makeSubscription(id: 3, deliveryType: SubscriptionType::PRINTED->value);

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn (callable $callback) => $callback());

        $this->issueDeliveryRepository->shouldReceive('find')->once()->with(56)->andReturn($issue);
        $this->subscriptionRepository->shouldReceive('find')->once()->with(3)->andReturn($subscription);

        $this->fulfilSubscriptionAction
            ->shouldReceive('reserve')
            ->once()
            ->andThrow(new StockException('Insufficient stock'));

        $this->fulfilSubscriptionAction->shouldNotReceive('confirm');
        $this->classifier->shouldNotReceive('classify');
        $this->fulfilmentRepository->shouldNotReceive('createBackIssueFulfilment');
        $this->createPrintFulfillmentAction->shouldNotReceive('execute');

        $this->expectException(StockException::class);

        $this->service->order(3, 56);
    }

    private function makeIssue(int $id): IssueDelivery
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id = $id;

        return $issue;
    }

    private function makeSubscription(int $id, string $deliveryType): Subscription
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = $id;
        $subscription->delivery_type = $deliveryType;

        return $subscription;
    }

    private function makeFulfilment(int $id): SubscriptionIssueFulfilment
    {
        $fulfilment = Mockery::mock(SubscriptionIssueFulfilment::class)->makePartial();
        $fulfilment->id = $id;

        return $fulfilment;
    }
}
