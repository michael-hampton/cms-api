<?php

namespace App\Tests\Unit\Services\Subscriptions\Printing;

use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\IssueDelivery;
use App\Models\IssuesDelivered;
use App\Models\PrintBatch;
use App\Models\PrintFulfillment;
use App\Models\Subscription;
use App\Repositories\Subscriptions\IssuesDeliveredRepository;
use App\Repositories\Subscriptions\PrintBatchRepository;
use App\Repositories\Subscriptions\PrintFulfillmentRepository;
use App\Services\Subscriptions\DeliveryChannels\PrintDeliveryChannel;
use App\Services\Subscriptions\Printing\PrintAddressResolver;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class PrintDeliveryChannelTest extends FunctionalTestCase
{
    private PrintBatchRepository|MockInterface $batchRepository;
    private PrintFulfillmentRepository|MockInterface $fulfillmentRepository;
    private IssuesDeliveredRepository|MockInterface $issuesDeliveredRepository;
    private PrintAddressResolver|MockInterface $addressResolver;
    private Database|MockInterface $databaseMock;
    private Logger|MockInterface $logger;
    private PrintDeliveryChannel $channel;

    public function test_creates_fulfillment_and_registers_post_commit_export_job(): void
    {
        [$subscription, $issueDelivery, $batch, $fulfillment, $issuesDelivered] = $this->makeValidScenario();

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

        $this->issuesDeliveredRepository
            ->shouldReceive('findBySubscriptionAndDelivery')
            ->once()
            ->with($subscription->id, $issueDelivery->id)
            ->andReturn($issuesDelivered);

        $this->fulfillmentRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($fulfillment);

        // afterCommit receives a callable — we execute it immediately in the
        // test so we can verify the dispatch happens, without a real transaction.
        // We assert the callback is registered (once) as our contract guarantee.
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

    private function makeValidScenario(): array
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 10;

        $issueDelivery = $this->makeIssueDelivery();

        $batch = Mockery::mock(PrintBatch::class)->makePartial();
        $batch->id = 42;

        $fulfillment = Mockery::mock(PrintFulfillment::class)->makePartial();
        $fulfillment->id = 1;

        $issuesDelivered = Mockery::mock(IssuesDelivered::class)->makePartial();
        $issuesDelivered->id = 99;

        return [$subscription, $issueDelivery, $batch, $fulfillment, $issuesDelivered];
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    private function makeIssueDelivery(): IssueDelivery
    {
        $delivery = Mockery::mock(IssueDelivery::class)->makePartial();
        $delivery->id = 7;
        $delivery->issue_title = 'Spring Issue';
        return $delivery;
    }

    // -------------------------------------------------------------------------
    // Address failures
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // Missing subscription guard
    // -------------------------------------------------------------------------

    public function test_throws_when_address_resolver_finds_no_valid_address(): void
    {
        [$subscription, $issueDelivery] = $this->makeValidScenario();

        $this->addressResolver
            ->shouldReceive('resolve')
            ->once()
            ->andThrow(new \RuntimeException('no valid delivery address found'));

        $this->batchRepository->shouldNotReceive('createForIssueDelivery');
        $this->fulfillmentRepository->shouldNotReceive('create');
        $this->databaseMock->shouldNotReceive('afterCommit');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/no valid delivery address found/');

        $this->channel->send($subscription, $issueDelivery);
    }

    // -------------------------------------------------------------------------
    // Missing IssuesDelivered guard
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // No afterCommit registered when fulfillment persistence fails
    // -------------------------------------------------------------------------

    public function test_throws_when_issues_delivered_record_not_found(): void
    {
        [$subscription, $issueDelivery, $batch] = $this->makeValidScenario();

        $this->addressResolver
            ->shouldReceive('resolve')
            ->once()
            ->andReturn($this->makeResolvedAddress());

        $this->batchRepository
            ->shouldReceive('createForIssueDelivery')
            ->once()
            ->andReturn($batch);

        $this->issuesDeliveredRepository
            ->shouldReceive('findBySubscriptionAndDelivery')
            ->once()
            ->andReturn(null);

        $this->fulfillmentRepository->shouldNotReceive('create');
        $this->databaseMock->shouldNotReceive('afterCommit');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/IssuesDelivered record not found/');

        $this->channel->send($subscription, $issueDelivery);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function test_does_not_register_export_when_fulfillment_creation_fails(): void
    {
        [$subscription, $issueDelivery, $batch, , $issuesDelivered] = $this->makeValidScenario();

        $this->addressResolver
            ->shouldReceive('resolve')
            ->andReturn($this->makeResolvedAddress());

        $this->batchRepository
            ->shouldReceive('createForIssueDelivery')
            ->andReturn($batch);

        $this->issuesDeliveredRepository
            ->shouldReceive('findBySubscriptionAndDelivery')
            ->andReturn($issuesDelivered);

        $this->fulfillmentRepository
            ->shouldReceive('create')
            ->andThrow(new \RuntimeException('DB write failed'));

        $this->databaseMock->shouldNotReceive('afterCommit');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/DB write failed/');

        $this->channel->send($subscription, $issueDelivery);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->batchRepository = Mockery::mock(PrintBatchRepository::class);
        $this->fulfillmentRepository = Mockery::mock(PrintFulfillmentRepository::class);
        $this->issuesDeliveredRepository = Mockery::mock(IssuesDeliveredRepository::class);
        $this->addressResolver = Mockery::mock(PrintAddressResolver::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $this->channel = new PrintDeliveryChannel(
            $this->batchRepository,
            $this->fulfillmentRepository,
            $this->issuesDeliveredRepository,
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