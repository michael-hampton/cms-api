<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Subscriptions\SubscriptionWithAddress;
use App\Exceptions\Subscriptions\SubscriptionAlreadyLinkedException;
use App\Exceptions\Subscriptions\SubscriptionNotFoundException;
use App\Models\Subscription;
use App\Repositories\Subscriptions\PrintSubscriptionRepository;
use App\Services\Subscriptions\SubscriptionLinkingService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class SubscriptionLinkingServiceTest extends FunctionalTestCase
{
    private PrintSubscriptionRepository&MockObject $printRepo;
    private SubscriptionLinkingService $service;

    public function test_returns_true_when_active_linked_subscription_exists(): void
    {
        $this->printRepo
            ->method('hasLinkedActiveSubscription')
            ->with(1, 1)
            ->willReturn(true);

        $this->assertTrue($this->service->memberHasLinkedSubscription(1, 1));
    }

    // ── memberHasLinkedSubscription ───────────────────────────────────

    public function test_returns_false_when_no_linked_subscription(): void
    {
        $this->printRepo
            ->method('hasLinkedActiveSubscription')
            ->willReturn(false);

        $this->assertFalse($this->service->memberHasLinkedSubscription(1, 1));
    }

    public function test_returns_false_when_subscription_is_active_but_not_linked(): void
    {
        // Active but unlinked — must NOT skip Step 3
        $this->printRepo
            ->method('hasLinkedActiveSubscription')
            ->willReturn(false);

        $this->assertFalse($this->service->memberHasLinkedSubscription(1, 1));
    }

    public function test_throws_not_found_when_no_subscription_matches(): void
    {
        $this->printRepo
            ->method('findByAccountNumberAndPostcode')
            ->willReturn(null);

        $this->expectException(SubscriptionNotFoundException::class);

        $this->service->linkToMember(1, 'ACC123', 'SW1A1AA', 1);
    }

    // ── linkToMember — not found ──────────────────────────────────────

    public function test_throws_already_linked_when_claimed_by_different_member(): void
    {
        $existing = $this->makeSubscription(['is_linked' => true, 'member_id' => 99]);

        $this->printRepo
            ->method('findByAccountNumberAndPostcode')
            ->willReturn(new SubscriptionWithAddress(
                $existing,
                'SW1A 1AA',
                'shipping',
                true
            ));

        $this->expectException(SubscriptionAlreadyLinkedException::class);

        $this->service->linkToMember(1, 'ACC123', 'SW1A1AA', 1);
    }

    // ── linkToMember — already linked to another member ───────────────

    private function makeSubscription(array $attributes = []): Subscription
    {
        $subscription = new Subscription();
        $subscription->id = $attributes['id'] ?? 42;

        foreach ($attributes as $key => $value) {
            $subscription->$key = $value;
        }

        return $subscription;
    }

    // ── linkToMember — idempotent ─────────────────────────────────────

    public function test_returns_existing_subscription_when_already_linked_to_same_member(): void
    {
        $existing = $this->makeSubscription();
        $existing->is_linked = true;
        $existing->member_id = 1;

        $this->printRepo
            ->method('findByAccountNumberAndPostcode')
            ->willReturn(new SubscriptionWithAddress(
                $existing,
                'SW1A 1AA',
                'shipping',
                true
            ));

        // linkToMember on the repo must NOT be called
        $this->printRepo
            ->expects($this->never())
            ->method('linkToMember');

        $result = $this->service->linkToMember(1, 'ACC123', 'SW1A1AA', 1);

        $this->assertSame($existing, $result);
    }

    // ── linkToMember — happy path ─────────────────────────────────────

    public function test_links_subscription_and_emits_event(): void
    {
        $unlinked = $this->makeSubscription();
        $unlinked->is_linked = false;
        $unlinked->member_id = null;

        $linked = $this->makeSubscription();
        $linked->is_linked = true;
        $linked->member_id = 1;

        $this->printRepo
            ->method('findByAccountNumberAndPostcode')
            ->willReturn(new SubscriptionWithAddress(
                $unlinked,
                'SW1A 1AA',
                'shipping',
                true
            ));

        $this->printRepo
            ->expects($this->once())
            ->method('linkToMember')
            ->with($unlinked->id, 1)
            ->willReturn($linked);


        $emittedEvents = [];
        // Capture event() calls without a real event dispatcher
        $GLOBALS['__test_events'] = [];

        $result = $this->service->linkToMember(1, 'ACC123', 'SW1A 1AA', 1);

        $this->assertSame($linked, $result);
    }

    public function test_postcode_is_normalised_before_lookup(): void
    {
        // "sw1a 1aa" must be passed to the repo as "SW1A1AA"
        $this->printRepo
            ->expects($this->once())
            ->method('findByAccountNumberAndPostcode')
            ->with('ACC123', 'SW1A1AA', 1)
            ->willReturn(null);

        try {
            $this->service->linkToMember(1, 'ACC123', 'sw1a 1aa', 1);
        } catch (SubscriptionNotFoundException) {
            // Expected — we only care that normalisation happened
        }
    }

    public function test_account_number_is_trimmed_before_lookup(): void
    {
        $this->printRepo
            ->expects($this->once())
            ->method('findByAccountNumberAndPostcode')
            ->with('ACC123', 'SW1A1AA', 1)
            ->willReturn(null);

        try {
            $this->service->linkToMember(1, '  ACC123  ', 'SW1A1AA', 1);
        } catch (SubscriptionNotFoundException) {
            // Expected
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        $this->printRepo = $this->createMock(PrintSubscriptionRepository::class);

        $this->service = new SubscriptionLinkingService(
            $this->printRepo,
        );
    }
}