<?php

namespace App\Tests\Unit\Services\Rewards;

use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Support\Collection;
use App\Models\MemberReward;
use App\Models\Order;
use App\Models\OrderItem;
use App\Repositories\Rewards\ProductRewardRepository;
use App\Repositories\Rewards\RewardAuditLogRepository;
use App\Repositories\Rewards\RewardDefinitionRepository;
use App\Services\Rewards\OrderRewardProcessor;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for OrderRewardProcessor.
 *
 * Rules:
 *   - Real class instances via makePartial() for models.
 *   - Repositories, Database, EventDispatcher mocked with Mockery.
 *   - No framework internals tested (no DB assertions — that belongs in repo tests).
 *   - Each test targets a single behaviour and is isolated.
 *
 * Bugs documented inline (marked @bug). Tests are written against the correct
 * expected behaviour, not the current broken behaviour, so they drive fixes.
 */
class OrderRewardProcessorTest extends TestCase
{
    private MockInterface|RewardDefinitionRepository $rewardDefinitionRepository;
    private MockInterface|ProductRewardRepository $productRewardRepository;
    private MockInterface|EventDispatcher $dispatcher;
    private MockInterface|Database $database;
    private MockInterface|RewardAuditLogRepository $auditLogRepository;

    private OrderRewardProcessor $processor;

    /**
     * @bug processCompletedOrder guards on `user_id` being empty but captures
     *      `member_id` into $memberId and then passes `user_id` to the
     *      repository. This test documents the guard behaviour: when user_id
     *      is null the method must return early without touching the repository.
     */
    public function test_returns_early_when_order_has_no_user_id(): void
    {
        $order = $this->makeOrder(['user_id' => null]);

        $this->productRewardRepository->shouldNotReceive('findPendingRewardsForProducts');
        $this->database->shouldNotReceive('transaction');

        $this->processor->processCompletedOrder($order);
        $this->assertTrue(true);
    }

    /**
     * Build a partial Order mock with the minimum attributes needed to pass
     * the processor's guard clauses.
     */
    private function makeOrder(array $attributes = []): Order
    {
        /** @var Order|MockInterface $order */
        $order = Mockery::mock(Order::class)->makePartial();

        $defaults = [
            'id' => 1,
            'user_id' => 42,
            'member_id' => 42,
            'order_number' => 'ORD-001',
            'items' => [],
        ];

        foreach (array_merge($defaults, $attributes) as $key => $value) {
            $order->$key = $value;
        }

        return $order;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function test_returns_early_when_order_has_no_items(): void
    {
        $order = $this->makeOrder(['items' => []]);

        $this->productRewardRepository->shouldNotReceive('findPendingRewardsForProducts');
        $this->database->shouldNotReceive('transaction');

        $this->processor->processCompletedOrder($order);
        $this->assertTrue(true);
    }

    public function test_returns_early_when_items_have_no_product_ids(): void
    {
        $item = Mockery::mock(OrderItem::class)->makePartial();
        $item->product_id = null;

        $order = $this->makeOrder(['items' => [$item]]);

        $this->productRewardRepository->shouldNotReceive('findPendingRewardsForProducts');
        $this->database->shouldNotReceive('transaction');

        $this->processor->processCompletedOrder($order);
        $this->assertTrue(true);
    }

    public function test_returns_early_when_no_pending_rewards_found(): void
    {
        $item = $this->makeOrderItem(5);
        $order = $this->makeOrder(['items' => [$item]]);

        $emptyCollection = new \App\Framework\Support\Collection([]);

        $this->productRewardRepository
            ->shouldReceive('findPendingRewardsForProducts')
            ->once()
            ->with($order->user_id, [5])
            ->andReturn($emptyCollection);

        $this->database->shouldNotReceive('transaction');

        $this->processor->processCompletedOrder($order);
        $this->assertTrue(true);
    }

    /**
     * Build a partial OrderItem mock carrying a product_id.
     */
    private function makeOrderItem(int $productId): OrderItem
    {
        /** @var OrderItem|MockInterface $item */
        $item = Mockery::mock(OrderItem::class)->makePartial();
        $item->product_id = $productId;

        return $item;
    }

    public function test_approves_single_pending_reward_inside_a_transaction(): void
    {
        $item = $this->makeOrderItem(7);
        $order = $this->orderWithItems([$item], ['user_id' => 99, 'order_number' => 'ORD-001']);
        $reward = $this->makeMemberReward(55, 10);

        $this->productRewardRepository
            ->shouldReceive('findPendingRewardsForProducts')
            ->once()
            ->with(99, [7])
            ->andReturn(new Collection([$reward]));

        $this->allowTransaction();

        $this->productRewardRepository
            ->shouldReceive('approve')
            ->once()
            ->with(55, Mockery::type('string'))
            ->andReturn(true);

        $this->auditLogRepository
            ->shouldReceive('logAction')
            ->once()
            ->with(55, 'approved', null, 'pending', 'approved', null, null, Mockery::type('string'), 10);

        $this->processor->processCompletedOrder($order);

        $this->assertTrue(true, 'Approval and audit log called exactly once inside transaction.');
    }

    // -------------------------------------------------------------------------
    // Guard clauses — processCompletedOrder
    // -------------------------------------------------------------------------

    /**
     * Order with the items relationship pre-populated.
     */
    private function orderWithItems(array $items, array $orderAttributes = []): Order
    {
        $order = $this->makeOrder($orderAttributes);
        $order->items = $items;

        return $order;
    }

    /**
     * Build a partial MemberReward mock.
     *
     * @bug The processor's approvePendingRewardsForProduct() type-hints
     *      RewardDefinition but it actually receives MemberReward instances
     *      from findPendingRewardsForProducts(). Tests use MemberReward to
     *      match the real runtime behaviour.
     */
    private function makeMemberReward(int $id, int $rewardDefinitionId = 10): MemberReward
    {
        /** @var MemberReward|MockInterface $reward */
        $reward = Mockery::mock(MemberReward::class)->makePartial();
        $reward->id = $id;
        $reward->reward_definition_id = $rewardDefinitionId;

        return $reward;
    }

    /**
     * Configures the database mock so that the transaction callback is invoked
     * immediately and its return value is passed through.
     */
    private function allowTransaction(): void
    {
        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn(callable $cb) => $cb());
    }

    public function test_includes_order_number_in_approval_notes(): void
    {
        $item = $this->makeOrderItem(7);
        $order = $this->makeOrder(['items' => [$item], 'order_number' => 'ORD-XYZ']);
        $reward = $this->makeMemberReward(55);

        $this->productRewardRepository
            ->shouldReceive('findPendingRewardsForProducts')
            ->andReturn(new \App\Framework\Support\Collection([$reward]));

        $this->allowTransaction();

        $this->productRewardRepository
            ->shouldReceive('approve')
            ->once()
            ->with($reward->id, Mockery::on(fn($notes) => str_contains($notes, 'ORD-XYZ')))
            ->andReturn(true);

        $this->auditLogRepository
            ->shouldReceive('logAction')
            ->once();

        $this->processor->processCompletedOrder($order);
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Happy path — approval flow
    // -------------------------------------------------------------------------

    public function test_approves_multiple_rewards_in_single_transaction(): void
    {
        $item = $this->makeOrderItem(7);
        $order = $this->makeOrder(['items' => [$item]]);
        $reward1 = $this->makeMemberReward(10);
        $reward2 = $this->makeMemberReward(20);

        $this->productRewardRepository
            ->shouldReceive('findPendingRewardsForProducts')
            ->andReturn(new \App\Framework\Support\Collection([$reward1, $reward2]));

        $this->allowTransaction();

        $this->productRewardRepository->shouldReceive('approve')->twice()->andReturn(true);
        $this->auditLogRepository->shouldReceive('logAction')->twice();

        $this->processor->processCompletedOrder($order);
        $this->assertTrue(true);
    }

    public function test_deduplicates_product_ids_from_order_items(): void
    {
        // Two items with the same product_id — repository must receive it once.
        $item1 = $this->makeOrderItem(7);
        $item2 = $this->makeOrderItem(7);
        $order = $this->makeOrder(['items' => [$item1, $item2]]);

        $this->productRewardRepository
            ->shouldReceive('findPendingRewardsForProducts')
            ->once()
            ->with($order->user_id, [7])   // deduplicated to a single entry
            ->andReturn(new \App\Framework\Support\Collection([]));

        $this->database->shouldNotReceive('transaction');

        $this->processor->processCompletedOrder($order);
        $this->assertTrue(true);
    }

    public function test_skips_silently_when_reward_was_already_approved_concurrently(): void
    {
        $item = $this->makeOrderItem(7);
        $order = $this->makeOrder(['items' => [$item]]);
        $reward = $this->makeMemberReward(55);

        $this->productRewardRepository
            ->shouldReceive('findPendingRewardsForProducts')
            ->andReturn(new \App\Framework\Support\Collection([$reward]));

        $this->allowTransaction();

        // approve() returns false — the row was already updated by another process.
        $this->productRewardRepository
            ->shouldReceive('approve')
            ->once()
            ->andReturn(false);

        // No audit log entry must be written for a skipped approval.
        $this->auditLogRepository->shouldNotReceive('logAction');

        // Must not throw — non-critical flow.
        $this->processor->processCompletedOrder($order);
        $this->assertTrue(true);
    }

    public function test_catches_and_logs_exception_from_approve_without_propagating(): void
    {
        $item = $this->makeOrderItem(7);
        $order = $this->makeOrder(['items' => [$item]]);
        $reward = $this->makeMemberReward(55);

        $this->productRewardRepository
            ->shouldReceive('findPendingRewardsForProducts')
            ->andReturn(new \App\Framework\Support\Collection([$reward]));

        $this->allowTransaction();

        $this->productRewardRepository
            ->shouldReceive('approve')
            ->andThrow(new \RuntimeException('DB error'));

        // Exception must be swallowed — order completion must never be blocked.
        $this->processor->processCompletedOrder($order);

        // If we reached here, the exception did not propagate. Test passes.
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Concurrent approval (skip silently)
    // -------------------------------------------------------------------------

    public function test_continues_processing_remaining_rewards_after_one_approval_fails(): void
    {
        $item = $this->makeOrderItem(7);
        $order = $this->makeOrder(['items' => [$item]]);
        $reward1 = $this->makeMemberReward(10);
        $reward2 = $this->makeMemberReward(20);

        $this->productRewardRepository
            ->shouldReceive('findPendingRewardsForProducts')
            ->andReturn(new \App\Framework\Support\Collection([$reward1, $reward2]));

        $this->allowTransaction();

        $this->productRewardRepository
            ->shouldReceive('approve')
            ->with($reward1->id, Mockery::any())
            ->andThrow(new \RuntimeException('DB error for reward1'));

        $this->productRewardRepository
            ->shouldReceive('approve')
            ->with($reward2->id, Mockery::any())
            ->andReturn(true);

        $this->auditLogRepository
            ->shouldReceive('logAction')
            ->once()
            ->with(20, 'approved', null, 'pending', 'approved', null, null, Mockery::type('string'), 10);

        $this->processor->processCompletedOrder($order);
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Error handling — non-critical, must not propagate
    // -------------------------------------------------------------------------

    public function test_wraps_all_approvals_in_a_single_database_transaction(): void
    {
        $item = $this->makeOrderItem(7);
        $order = $this->makeOrder(['items' => [$item]]);
        $reward1 = $this->makeMemberReward(10);
        $reward2 = $this->makeMemberReward(20);

        $this->productRewardRepository
            ->shouldReceive('findPendingRewardsForProducts')
            ->andReturn(new \App\Framework\Support\Collection([$reward1, $reward2]));

        // Exactly ONE transaction wrapping both approvals.
        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn(callable $cb) => $cb());

        $this->productRewardRepository->shouldReceive('approve')->twice()->andReturn(true);
        $this->auditLogRepository->shouldReceive('logAction')->twice();

        $this->processor->processCompletedOrder($order);
        $this->assertTrue(true);
    }

    public function test_no_transaction_is_opened_when_there_are_no_pending_rewards(): void
    {
        $item = $this->makeOrderItem(7);
        $order = $this->makeOrder(['items' => [$item]]);

        $this->productRewardRepository
            ->shouldReceive('findPendingRewardsForProducts')
            ->andReturn(new \App\Framework\Support\Collection([]));

        $this->database->shouldNotReceive('transaction');

        $this->processor->processCompletedOrder($order);
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Transaction boundary
    // -------------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();

        $this->rewardDefinitionRepository = Mockery::mock(RewardDefinitionRepository::class);
        $this->productRewardRepository = Mockery::mock(ProductRewardRepository::class);
        $this->dispatcher = Mockery::mock(EventDispatcher::class);
        $this->auditLogRepository = Mockery::mock(RewardAuditLogRepository::class);
        $this->database = Mockery::mock(Database::class);

        $this->processor = new OrderRewardProcessor(
            $this->rewardDefinitionRepository,
            $this->productRewardRepository,
            $this->dispatcher,
            $this->database,
            $this->auditLogRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}