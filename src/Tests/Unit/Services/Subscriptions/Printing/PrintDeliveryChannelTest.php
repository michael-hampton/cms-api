<?php

namespace App\Tests\Unit\Services\Subscriptions\Printing;

use App\DTO\Subscriptions\FulfilmentDecisionContext;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\IssueDelivery;
use App\Models\SubscriptionIssueFulfilment;
use App\Models\PrintBatch;
use App\Models\PrintFulfillment;
use App\Models\Subscription;
use App\Models\Territory;
use App\Repositories\Subscriptions\SubscriptionIssueFulfilmentRepository;
use App\Repositories\Subscriptions\PrintBatchRepository;
use App\Repositories\Subscriptions\PrintFulfillmentRepository;
use App\Services\Subscriptions\DeliveryChannels\PrintDeliveryChannel;
use App\Services\Subscriptions\Printing\PrintAddressResolver;
use App\Tests\Unit\UnitTestCase;
use Mockery;
use Mockery\MockInterface;

class PrintDeliveryChannelTest extends UnitTestCase
{
    private PrintBatchRepository|MockInterface $batchRepository;
    private PrintFulfillmentRepository|MockInterface $fulfillmentRepository;
    private SubscriptionIssueFulfilmentRepository|MockInterface $subscriptionIssueFulfilmentRepository;
    private PrintAddressResolver|MockInterface $addressResolver;
    private Database|MockInterface $databaseMock;
    private Logger|MockInterface $logger;
    private PrintDeliveryChannel $channel;

    // =========================================================================
    // Happy path — no context (legacy path)
    // =========================================================================

    public function test_creates_fulfillment_and_registers_post_commit_export_job(): void
    {
        [$subscription, $issueDelivery, $batch, $fulfillment, $subscriptionIssueFulfilment] = $this->makeValidScenario();

        $this->addressResolver
            ->shouldReceive('resolve')
            ->once()
            ->with($subscription)
            ->andReturn($this->makeResolvedAddress());

        $this->batchRepository
            ->shouldReceive('createForIssueDelivery')
            ->once()
            ->with($issueDelivery->id)
            ->andReturn($batch);

        $this->subscriptionIssueFulfilmentRepository
            ->shouldReceive('findBySubscriptionAndDelivery')
            ->once()
            ->with($subscription->id, $issueDelivery->id)
            ->andReturn($subscriptionIssueFulfilment);

        $this->fulfillmentRepository
            ->shouldReceive('existsForSubscriptionDeliveryAndTerritory')
            ->once()
            ->andReturn(false);

        $this->fulfillmentRepository
            ->shouldReceive('createFullfilment')
            ->once()
            ->andReturn($fulfillment);

        $afterCommitCalled = false;
        $this->databaseMock
            ->shouldReceive('afterCommit')
            ->once()
            ->withArgs(function (callable $callback) use (&$afterCommitCalled) {
                $afterCommitCalled = true;
                return true;
            });

        $this->channel->send($subscription, $issueDelivery);

        $this->assertTrue($afterCommitCalled, 'afterCommit was not called');
    }

    // =========================================================================
    // Happy path — with FulfilmentDecisionContext (territory-aware path)
    // =========================================================================

    public function test_creates_fulfillment_with_territory_when_context_provided(): void
    {
        [$subscription, $issueDelivery, $batch, $fulfillment, $subscriptionIssueFulfilment] = $this->makeValidScenario();

        $territory = Mockery::mock(Territory::class)->makePartial();
        $territory->id = 7;

        $addressSnapshot = $this->makeResolvedAddress()['snapshot'];

        $context = new FulfilmentDecisionContext(
            territory: $territory,
            addressSnapshot: $addressSnapshot,
            fullName: $addressSnapshot['first_name'] . ' ' . $addressSnapshot['last_name'],
            channelMetadata: ['subscription_id' => $subscription->id]
        );

        // Address resolver must NOT be called when a context with a complete snapshot is provided
        $this->addressResolver->shouldNotReceive('resolve');

        $this->batchRepository
            ->shouldReceive('createForIssueDelivery')
            ->once()
            ->with($issueDelivery->id)
            ->andReturn($batch);

        $this->subscriptionIssueFulfilmentRepository
            ->shouldReceive('findBySubscriptionAndDelivery')
            ->once()
            ->andReturn($subscriptionIssueFulfilment);

        $this->fulfillmentRepository
            ->shouldReceive('existsForSubscriptionDeliveryAndTerritory')
            ->once()
            ->andReturn(false);

        $this->fulfillmentRepository
            ->shouldReceive('createFullfilment')
            ->once()
            ->andReturn($fulfillment);

        $this->databaseMock
            ->shouldReceive('afterCommit')
            ->once();

        $this->channel->send($subscription, $issueDelivery, $context);

        $this->assertTrue(true);
    }

    public function test_creates_fulfillment_with_null_territory_when_context_has_no_territory(): void
    {
        [$subscription, $issueDelivery, $batch, $fulfillment, $subscriptionIssueFulfilment] = $this->makeValidScenario();

        $addressSnapshot = $this->makeResolvedAddress()['snapshot'];

        $context = new FulfilmentDecisionContext(
            territory: null,
            addressSnapshot: $this->makeResolvedAddress()['snapshot'],
            fullName: $addressSnapshot['first_name'] . ' ' . $addressSnapshot['last_name']
        );

        $this->addressResolver->shouldNotReceive('resolve');

        $this->batchRepository
            ->shouldReceive('createForIssueDelivery')
            ->once()
            ->with($issueDelivery->id)
            ->andReturn($batch);

        $this->subscriptionIssueFulfilmentRepository
            ->shouldReceive('findBySubscriptionAndDelivery')
            ->andReturn($subscriptionIssueFulfilment);

        $this->fulfillmentRepository
            ->shouldReceive('existsForSubscriptionDeliveryAndTerritory')
            ->once()
            ->andReturn(false);

        $this->fulfillmentRepository
            ->shouldReceive('createFullfilment')
            ->once()
            ->andReturn($fulfillment);

        $this->databaseMock->shouldReceive('afterCommit')->once();

        $this->channel->send($subscription, $issueDelivery, $context);

        $this->assertTrue(true);
    }

    // =========================================================================
    // Address failures
    // =========================================================================

    public function test_throws_when_address_resolver_finds_no_valid_address(): void
    {
        [$subscription, $issueDelivery] = $this->makeValidScenario();

        $this->addressResolver
            ->shouldReceive('resolve')
            ->once()
            ->andThrow(new \RuntimeException('no valid delivery address found'));

        $this->batchRepository->shouldNotReceive('createForIssueDelivery');
        $this->fulfillmentRepository->shouldNotReceive('createFullfilment');
        $this->databaseMock->shouldNotReceive('afterCommit');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/no valid delivery address found/');

        $this->channel->send($subscription, $issueDelivery);
    }

    // =========================================================================
    // Missing subscription guard
    // =========================================================================

    public function test_throws_when_subscription_has_no_id(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = null;

        $issueDelivery = $this->makeIssueDelivery();

        $this->addressResolver->shouldNotReceive('resolve');
        $this->batchRepository->shouldNotReceive('createForIssueDelivery');
        $this->databaseMock->shouldNotReceive('afterCommit');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/subscription has no ID/');

        $this->channel->send($subscription, $issueDelivery);
    }

    // =========================================================================
    // Missing SubscriptionIssueFulfilment guard
    // =========================================================================

    public function test_throws_when_subscription_issue_fulfilments_record_not_found(): void
    {
        [$subscription, $issueDelivery] = $this->makeValidScenario();

        $this->addressResolver
            ->shouldReceive('resolve')
            ->once()
            ->andReturn($this->makeResolvedAddress());

        // Regression: batch creation now happens after this guard, so a
        // missing SubscriptionIssueFulfilment record must not leave an
        // orphan PrintBatch row behind either.
        $this->batchRepository->shouldNotReceive('createForIssueDelivery');

        $this->subscriptionIssueFulfilmentRepository
            ->shouldReceive('findBySubscriptionAndDelivery')
            ->once()
            ->andReturn(null);

        $this->fulfillmentRepository->shouldNotReceive('createFullfilment');
        $this->databaseMock->shouldNotReceive('afterCommit');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/SubscriptionIssueFulfilment record not found/');

        $this->channel->send($subscription, $issueDelivery);
    }

    // =========================================================================
    // Idempotency guard
    // =========================================================================

    public function test_skips_fulfillment_creation_when_record_already_exists(): void
    {
        [$subscription, $issueDelivery, , , $subscriptionIssueFulfilment] = $this->makeValidScenario();

        $this->addressResolver
            ->shouldReceive('resolve')
            ->once()
            ->andReturn($this->makeResolvedAddress());

        // Regression: previously the PrintBatch row was created before the
        // idempotency check ran, so a retry that hit this guard still left
        // behind an orphan batch with no fulfilments attached to it.
        $this->batchRepository->shouldNotReceive('createForIssueDelivery');

        $this->subscriptionIssueFulfilmentRepository
            ->shouldReceive('findBySubscriptionAndDelivery')
            ->once()
            ->andReturn($subscriptionIssueFulfilment);

        // Fulfilment already exists — guard fires
        $this->fulfillmentRepository
            ->shouldReceive('existsForSubscriptionDeliveryAndTerritory')
            ->once()
            ->andReturn(true);

        // Must not create another fulfilment or register another export job
        $this->fulfillmentRepository->shouldNotReceive('createFullfilment');
        $this->databaseMock->shouldNotReceive('afterCommit');

        $this->channel->send($subscription, $issueDelivery);

        $this->assertTrue(true);
    }

    // =========================================================================
    // No afterCommit registered when fulfillment persistence fails
    // =========================================================================

    public function test_does_not_register_export_when_fulfillment_creation_fails(): void
    {
        [$subscription, $issueDelivery, $batch, , $subscriptionIssueFulfilment] = $this->makeValidScenario();

        $this->addressResolver
            ->shouldReceive('resolve')
            ->andReturn($this->makeResolvedAddress());

        $this->batchRepository
            ->shouldReceive('createForIssueDelivery')
            ->andReturn($batch);

        $this->subscriptionIssueFulfilmentRepository
            ->shouldReceive('findBySubscriptionAndDelivery')
            ->andReturn($subscriptionIssueFulfilment);

        $this->fulfillmentRepository
            ->shouldReceive('existsForSubscriptionDeliveryAndTerritory')
            ->once()
            ->andReturn(false);

        $this->fulfillmentRepository
            ->shouldReceive('createFullfilment')
            ->andThrow(new \RuntimeException('DB write failed'));

        $this->databaseMock->shouldNotReceive('afterCommit');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/DB write failed/');

        $this->channel->send($subscription, $issueDelivery);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeValidScenario(): array
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 10;

        $issueDelivery = $this->makeIssueDelivery();

        $batch = Mockery::mock(PrintBatch::class)->makePartial();
        $batch->id = 42;

        $fulfillment = Mockery::mock(PrintFulfillment::class)->makePartial();
        $fulfillment->id = 1;

        $subscriptionIssueFulfilment = Mockery::mock(SubscriptionIssueFulfilment::class)->makePartial();
        $subscriptionIssueFulfilment->id = 99;

        return [$subscription, $issueDelivery, $batch, $fulfillment, $subscriptionIssueFulfilment];
    }

    private function makeIssueDelivery(): IssueDelivery
    {
        $delivery = Mockery::mock(IssueDelivery::class)->makePartial();
        $delivery->id = 7;
        $delivery->issue_title = 'Spring Issue';
        return $delivery;
    }

    private function makeResolvedAddress(): array
    {
        return [
            'full_name' => 'Jane Doe',
            'address_line_1' => '10 Downing St',
            'address_line_2' => null,
            'city' => 'London',
            'postcode' => 'SW1A 2AA',
            'country' => 'GB',
            'snapshot' => [
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'address_line_1' => '10 Downing St',
                'city' => 'London',
                'postcode' => 'SW1A 2AA',
                'country' => 'GB',
            ],
        ];
    }

    protected function setUp(): void
    {

        $this->batchRepository = Mockery::mock(PrintBatchRepository::class);
        $this->fulfillmentRepository = Mockery::mock(PrintFulfillmentRepository::class);
        $this->subscriptionIssueFulfilmentRepository = Mockery::mock(SubscriptionIssueFulfilmentRepository::class);
        $this->addressResolver = Mockery::mock(PrintAddressResolver::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $this->channel = new PrintDeliveryChannel(
            $this->batchRepository,
            $this->fulfillmentRepository,
            $this->subscriptionIssueFulfilmentRepository,
            $this->addressResolver,
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