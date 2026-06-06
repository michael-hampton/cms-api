<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Subscriptions\SubscriptionWithAddress;
use App\Events\Subscriptions\SubscriptionLinked;
use App\Exceptions\Subscriptions\SubscriptionAlreadyLinkedException;
use App\Exceptions\Subscriptions\SubscriptionNotFoundException;
use App\Framework\Container;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Models\Member;
use App\Models\Subscription;
use App\Repositories\Subscriptions\PrintSubscriptionRepository;
use App\Services\Subscriptions\SubscriptionLinkingService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Support\CapturingEventDispatcher;
use Mockery;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;

class SubscriptionLinkingServiceTest extends FunctionalTestCase
{
    private PrintSubscriptionRepository $printRepo;
    private SubscriptionLinkingService $service;
    private Database $databaseMock;
    private CapturingEventDispatcher $eventDispatcher;

    // ── memberHasLinkedSubscription ───────────────────────────────────

    public function test_returns_true_when_active_linked_subscription_exists(): void
    {
        $this->printRepo
            ->shouldReceive('hasLinkedActiveSubscription')
            ->once()
            ->with(1, 1)
            ->andReturn(true);

        $this->assertTrue(
            $this->service->memberHasLinkedSubscription(
                1,
                1
            )
        );
    }

    public function test_returns_false_when_no_linked_subscription(): void
    {
        $this->printRepo
            ->shouldReceive('hasLinkedActiveSubscription')
            ->once()
            ->andReturn(false);

        $this->assertFalse(
            $this->service->memberHasLinkedSubscription(
                1,
                1
            )
        );
    }

    public function test_returns_false_when_subscription_is_active_but_not_linked(): void
    {
        $this->printRepo
            ->shouldReceive(
                'hasLinkedActiveSubscription'
            )
            ->once()
            ->with(1, 1)
            ->andReturn(false);

        $this->assertFalse(
            $this->service->memberHasLinkedSubscription(
                1,
                1
            )
        );
    }

    // ── linkToMember — not found ──────────────────────────────────────

    public function test_throws_not_found_when_no_subscription_matches(): void
    {
        $this->printRepo
            ->shouldReceive(
                'findByAccountNumberAndPostcode'
            )
            ->once()
            ->andReturn(null);

        $this->expectException(
            SubscriptionNotFoundException::class
        );

        $this->service->linkToMember(
            1,
            'ACC123',
            'SW1A1AA',
            1
        );
    }

    // ── linkToMember — already linked to another member ───────────────

    public function test_throws_already_linked_when_claimed_by_different_member(): void
    {
        $existing = $this->makeSubscription([
            'is_linked' => true,
            'member_id' => 99,
        ]);

        $this->printRepo
            ->shouldReceive(
                'findByAccountNumberAndPostcode'
            )
            ->once()
            ->andReturn(
                new SubscriptionWithAddress(
                    $existing,
                    'SW1A1AA',
                    'shipping',
                    true
                )
            );

        $this->printRepo
            ->shouldNotReceive(
                'linkToMember'
            );

        $this->expectException(
            SubscriptionAlreadyLinkedException::class
        );

        $this->service->linkToMember(
            1,
            'ACC123',
            'SW1A1AA',
            1
        );
    }

    // ── linkToMember — idempotent ─────────────────────────────────────

    public function test_returns_existing_subscription_when_already_linked_to_same_member(): void
    {
        $existing = $this->makeSubscription([
            'is_linked' => true,
            'member_id' => 1,
        ]);

        $this->printRepo
            ->shouldReceive(
                'findByAccountNumberAndPostcode'
            )
            ->once()
            ->andReturn(
                new SubscriptionWithAddress(
                    $existing,
                    'SW1A1AA',
                    'shipping',
                    true
                )
            );

        $this->printRepo
            ->shouldNotReceive(
                'linkToMember'
            );

        $result = $this->service->linkToMember(
            1,
            'ACC123',
            'SW1A1AA',
            1
        );

        $this->assertSame(
            $existing,
            $result
        );
    }

    // ── linkToMember — happy path ─────────────────────────────────────

    public function test_links_subscription_and_emits_event(): void
    {
        $unlinked = $this->makeSubscription([
            'id' => 42,
            'is_linked' => false,
        ]);

        $linked = $this->makeSubscription([
            'id' => 42,
            'is_linked' => true,
            'member_id' => 1,
        ]);

        $this->printRepo
            ->shouldReceive(
                'findByAccountNumberAndPostcode'
            )
            ->once()
            ->with(
                '1ACC123',
                'SW1A1AA',
                1
            )
            ->andReturn(
                new SubscriptionWithAddress(
                    $unlinked,
                    'SW1A1AA',
                    'shipping',
                    true
                )
            );

        $this->databaseMock
            ->shouldReceive(
                'transaction'
            )
            ->once()
            ->andReturnUsing(
                fn ($callback) => $callback()
            );

        $this->printRepo
            ->shouldReceive(
                'linkToMember'
            )
            ->once()
            ->with(
                42,
                1
            )
            ->andReturn(
                $linked
            );

        $result = $this->service->linkToMember(
            1,
            'ACC123',
            'SW1A1AA',
            1
        );

        $this->assertSame(
            $linked,
            $result
        );

        $this->assertSubscriptionLinkedDispatched($linked, 1, 1);
    }

    public function test_event_is_not_emitted_on_idempotent_link(): void
    {
        $existing = $this->makeSubscription([
            'is_linked' => true,
            'member_id' => 1,
        ]);

        $this->printRepo
            ->shouldReceive(
                'findByAccountNumberAndPostcode'
            )
            ->once()
            ->andReturn(
                new SubscriptionWithAddress(
                    $existing,
                    'SW1A1AA',
                    'shipping',
                    true
                )
            );

        $this->printRepo
            ->shouldNotReceive(
                'linkToMember'
            );

        $result = $this->service->linkToMember(
            1,
            'ACC123',
            'SW1A1AA',
            1
        );

        $this->assertSame(
            $existing,
            $result
        );

        $this->assertEventNotDispatched(SubscriptionLinked::class);
    }

    // ── linkToMember — transaction ────────────────────────────────────

    public function test_link_write_is_wrapped_in_transaction(): void
    {
        $transactionExecuted = false;

        $unlinked = $this->makeSubscription();

        $linked = $this->makeSubscription([
            'is_linked' => true,
            'member_id' => 1,
        ]);

        $this->printRepo
            ->shouldReceive(
                'findByAccountNumberAndPostcode'
            )
            ->once()
            ->andReturn(
                new SubscriptionWithAddress(
                    $unlinked,
                    'SW1A1AA',
                    'shipping',
                    true
                )
            );

        $this->databaseMock
            ->shouldReceive(
                'transaction'
            )
            ->once()
            ->andReturnUsing(
                function ($callback) use (
                    &$transactionExecuted
                ) {
                    $transactionExecuted = true;

                    return $callback();
                }
            );

        $this->printRepo
            ->shouldReceive(
                'linkToMember'
            )
            ->once()
            ->andReturn(
                $linked
            );

        $this->service->linkToMember(
            1,
            'ACC123',
            'SW1A1AA',
            1
        );

        $this->assertTrue(
            $transactionExecuted
        );
    }

    public function test_no_write_occurs_outside_transaction_on_happy_path(): void
    {
        $callLog = [];

        $unlinked = $this->makeSubscription([
            'is_linked' => false,
            'member_id' => null,
        ]);

        $linked = $this->makeSubscription([
            'is_linked' => true,
            'member_id' => 1,
        ]);

        $this->printRepo
            ->shouldReceive(
                'findByAccountNumberAndPostcode'
            )
            ->once()
            ->andReturn(
                new SubscriptionWithAddress(
                    $unlinked,
                    'SW1A1AA',
                    'shipping',
                    true
                )
            );

        $this->databaseMock
            ->shouldReceive(
                'transaction'
            )
            ->once()
            ->andReturnUsing(
                function ($callback) use (&$callLog) {
                    $callLog[] = 'transaction_open';

                    $result = $callback();

                    $callLog[] = 'transaction_close';

                    return $result;
                }
            );

        $this->printRepo
            ->shouldReceive(
                'linkToMember'
            )
            ->once()
            ->andReturnUsing(
                function () use (
                    &$callLog,
                    $linked
                ) {
                    $callLog[] = 'repo_write';

                    return $linked;
                }
            );

        $this->service->linkToMember(
            1,
            'ACC123',
            'SW1A1AA',
            1
        );

        $this->assertSame(
            [
                'transaction_open',
                'repo_write',
                'transaction_close',
            ],
            $callLog
        );
    }

    public function test_account_number_is_prefixed_with_site_id_before_lookup(): void
    {
        $this->printRepo
            ->shouldReceive(
                'findByAccountNumberAndPostcode'
            )
            ->once()
            ->with(
                '71234567890',
                'SW1A1AA',
                7
            )
            ->andReturn(null);

        $this->expectException(
            SubscriptionNotFoundException::class
        );

        $this->service->linkToMember(
            1,
            '1234567890',
            'SW1A1AA',
            7
        );
    }

    public function test_site_id_prefix_varies_by_site(): void
    {
        $this->printRepo
            ->shouldReceive(
                'findByAccountNumberAndPostcode'
            )
            ->once()
            ->with(
                '421234567890',
                'SW1A1AA',
                42
            )
            ->andReturn(null);

        $this->expectException(
            SubscriptionNotFoundException::class
        );

        $this->service->linkToMember(
            1,
            '1234567890',
            'SW1A1AA',
            42
        );
    }

    // ── linkToMember — input normalisation ───────────────────────────

    public function test_postcode_is_normalised_before_lookup(): void
    {
        $this->printRepo
            ->shouldReceive(
                'findByAccountNumberAndPostcode'
            )
            ->once()
            ->with(
                '1ACC123',
                'SW1A1AA',
                1
            )
            ->andReturn(null);

        $this->expectException(
            SubscriptionNotFoundException::class
        );

        $this->service->linkToMember(
            1,
            'ACC123',
            'sw1a 1aa',
            1
        );
    }

    public function test_account_number_is_trimmed_before_lookup(): void
    {
        $this->printRepo
            ->shouldReceive(
                'findByAccountNumberAndPostcode'
            )
            ->once()
            ->with(
                '1ACC123',
                'SW1A1AA',
                1
            )
            ->andReturn(null);

        $this->expectException(
            SubscriptionNotFoundException::class
        );

        $this->service->linkToMember(
            1,
            '  ACC123  ',
            'SW1A1AA',
            1
        );
    }

    public function test_already_linked_exception_carries_owner_email(): void
    {
        $member = new Member();
        $member->email = 'owner@example.com';

        $existing = $this->makeSubscription([
            'is_linked' => true,
            'member_id' => 99,
            'member' => $member,
        ]);

        $this->printRepo
            ->shouldReceive(
                'findByAccountNumberAndPostcode'
            )
            ->once()
            ->andReturn(
                new SubscriptionWithAddress(
                    $existing,
                    'SW1A1AA',
                    'shipping',
                    true
                )
            );

        try {
            $this->service->linkToMember(
                1,
                'ACC123',
                'SW1A1AA',
                1
            );

            $this->fail();
        } catch (
        SubscriptionAlreadyLinkedException $e
        ) {
            $this->assertSame(
                'owner@example.com',
                $e->getLinkedEmail()
            );
        }
    }

    public function test_no_event_is_emitted_when_subscription_not_found(): void
    {
        $this->printRepo
            ->shouldReceive(
                'findByAccountNumberAndPostcode'
            )
            ->once()
            ->andReturn(null);

        try {
            $this->service->linkToMember(
                1,
                'ACC123',
                'SW1A1AA',
                1
            );
        } catch (
        SubscriptionNotFoundException
        ) {
        }

        $this->assertEventNotDispatched(SubscriptionLinked::class);
    }

    public function test_no_event_is_emitted_when_subscription_already_linked_to_another(): void
    {
        $existing = $this->makeSubscription([
            'is_linked' => true,
            'member_id' => 99,
        ]);

        $this->printRepo
            ->shouldReceive(
                'findByAccountNumberAndPostcode'
            )
            ->once()
            ->andReturn(
                new SubscriptionWithAddress(
                    $existing,
                    'SW1A1AA',
                    'shipping',
                    true
                )
            );

        try {
            $this->service->linkToMember(
                1,
                'ACC123',
                'SW1A1AA',
                1
            );
        } catch (
        SubscriptionAlreadyLinkedException
        ) {
        }

        $this->assertEventNotDispatched(SubscriptionLinked::class);
    }

    public function test_already_linked_subscription_never_writes(): void
    {
        $existing = $this->makeSubscription([
            'is_linked' => true,
            'member_id' => 99,
        ]);

        $this->printRepo
            ->shouldReceive(
                'findByAccountNumberAndPostcode'
            )
            ->once()
            ->andReturn(
                new SubscriptionWithAddress(
                    $existing,
                    'SW1A1AA',
                    'shipping',
                    true
                )
            );

        $this->printRepo
            ->shouldNotReceive(
                'linkToMember'
            );

        $this->expectException(
            SubscriptionAlreadyLinkedException::class
        );

        $this->service->linkToMember(
            1,
            'ACC123',
            'SW1A1AA',
            1
        );
    }

    public function test_transaction_exception_is_not_swallowed(): void
    {
        $unlinked = $this->makeSubscription();

        $this->printRepo
            ->shouldReceive(
                'findByAccountNumberAndPostcode'
            )
            ->once()
            ->andReturn(
                new SubscriptionWithAddress(
                    $unlinked,
                    'SW1A1AA',
                    'shipping',
                    true
                )
            );

        $this->databaseMock
            ->shouldReceive(
                'transaction'
            )
            ->once()
            ->andThrow(
                new RuntimeException(
                    'db failed'
                )
            );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'db failed'
        );

        $this->service->linkToMember(
            1,
            'ACC123',
            'SW1A1AA',
            1
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function makeSubscription(array $attributes = []): Subscription
    {
        $subscription = new Subscription();

        $subscription->id = $attributes['id'] ?? 42;
        $subscription->is_linked = $attributes['is_linked'] ?? false;
        $subscription->member_id = $attributes['member_id'] ?? null;
        $subscription->member = $attributes['member'] ?? null;

        foreach ($attributes as $key => $value) {
            $subscription->$key = $value;
        }

        return $subscription;
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->printRepo = Mockery::mock(
            PrintSubscriptionRepository::class
        );

        $this->databaseMock = Mockery::mock(
            Database::class
        );
        $this->eventDispatcher = new CapturingEventDispatcher();

        Container::getInstance()->instance(EventDispatcher::class, $this->eventDispatcher);

        $this->service = new SubscriptionLinkingService(
            $this->printRepo,
            $this->databaseMock,
        );
    }

    private function assertSubscriptionLinkedDispatched(Subscription $subscription, int $memberId, int $siteId): void
    {
        $matches = array_values(array_filter(
            $this->eventDispatcher->dispatched,
            fn(object $event): bool => $event instanceof SubscriptionLinked
        ));

        $this->assertNotEmpty($matches, sprintf('Expected event [%s] to be dispatched.', SubscriptionLinked::class));
        $this->assertSame($subscription, $matches[0]->subscription);
        $this->assertSame($memberId, $matches[0]->memberId);
        $this->assertSame($siteId, $matches[0]->siteId);
    }

    private function assertEventNotDispatched(string $eventClass): void
    {
        $matches = array_filter(
            $this->eventDispatcher->dispatched,
            fn(object $event): bool => $event instanceof $eventClass
        );

        $this->assertEmpty($matches, sprintf('Expected event [%s] not to be dispatched.', $eventClass));
    }
}
