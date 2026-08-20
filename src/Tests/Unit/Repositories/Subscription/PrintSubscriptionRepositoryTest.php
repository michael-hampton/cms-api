<?php

namespace App\Tests\Unit\Repositories\Subscription;

use App\DTO\Subscriptions\SubscriptionWithAddress;
use App\Models\Address;
use App\Models\Member;
use App\Models\Model;
use App\Models\Subscription;
use App\Repositories\Subscriptions\PrintSubscriptionRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class PrintSubscriptionRepositoryTest extends FunctionalTestCase
{
    use CreatesTestData;

    private PrintSubscriptionRepository $repository;

    public function test_returns_null_when_no_rows_returned(): void
    {
        $result = $this->repository->findByAccountNumberAndPostcode('ACC123', 'SW1A1AA', $this->siteId);

        $this->assertNull($result);
    }

    // ── findByAccountNumberAndPostcode ────────────────────────────────

    public function test_returns_hydrated_subscription_on_match(): void
    {
        $row = $this->subscriptionRow(['id' => 7, 'account_number' => 'ACC123']);
        $sub = $this->seedValidSubscription($row);

        $dto = $this->repository->findByAccountNumberAndPostcode('ACC123', 'SW1A1AA', $this->siteId);

        $this->assertInstanceOf(SubscriptionWithAddress::class, $dto);
        $this->assertInstanceOf(Subscription::class, $dto->subscription);
        $this->assertSame($sub->id, $dto->subscription->id);

        $this->assertSame('SW1A 1AA', $dto->postcode);
        $this->assertSame('shipping', $dto->type);
        $this->assertTrue($dto->isDefault);
    }

    private function subscriptionRow(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'member_id' => null,
            'site_id' => 1,
            'account_number' => 'ACC123',
            'status' => 'active',
            'is_linked' => false,
            'plan_name' => 'Print Monthly',
            'price' => 9.99,
            'currency' => 'GBP',
        ], $overrides);
    }

    private function seedValidSubscription(array $row = []): Model
    {
        // Create the member
        $member = Member::create([
            'site_id' => $this->siteId,
            'email' => 'test@example.com',
            'first_name' => 'Joe',
            'last_name' => 'Bloggs'
        ]);

        // Create the subscription linked to that member
        $subscription = Subscription::create([
            'id' => $row['id'],
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'account_number' => $row['account_number'],
            'status' => $row['status'] ?? 'active',
            'is_linked' => $row['is_linked'] ?? false,
            'plan_name' => $row['plan_name'] ?? 'Print Monthly',
            'price' => $row['price'] ?? 9.99,
            'currency' => $row['currency'] ?? 'GBP',
            'start_date' => now_datetime(),
        ]);

        // Create the default shipping address
        Address::create([
            'member_id' => $member->id,
            'postcode' => $row['postcode'] ?? 'SW1A 1AA',
            'type' => 'shipping',
            'is_default' => true,
            'address_line_1' => 'Test',
            'city' => 'Test',
            'country' => 'UK'
        ]);

        return $subscription;
    }

    public function test_query_receives_correct_account_number_site_and_postcode(): void
    {
        $row = $this->subscriptionRow([
            'account_number' => 'ACC999',
            'site_id' => 3,
            'postcode' => 'EC1A 1BB'
        ]);
        $this->seedValidSubscription($row);

        $dto = $this->repository->findByAccountNumberAndPostcode('ACC999', 'EC1A1BB', 3);

        $this->assertSame('ACC999', $dto->subscription->account_number);
        $this->assertSame('EC1A 1BB', $dto->postcode);
    }

    // ── linkToMember ──────────────────────────────────────────────────

    public function test_postcode_passed_verbatim_to_query(): void
    {
        $row = $this->subscriptionRow(['postcode' => 'SW1A 1AA', 'account_number' => 'ACC1']);

        $this->seedValidSubscription($row);

        $dto = $this->repository->findByAccountNumberAndPostcode('ACC1', 'SW1A1AA', $this->siteId);

        $this->assertSame('SW1A 1AA', $dto->postcode);
    }

    public function test_link_calls_update_with_member_id_and_is_linked_flag(): void
    {
        $row = $this->subscriptionRow();
        $sub = $this->seedValidSubscription($row);

        $dto = $this->repository->linkToMember($sub->id, $sub->member_id);

        $this->assertInstanceOf(Subscription::class, $dto);
        $this->assertSame($sub->member_id, $dto->member_id);
        $this->assertTrue($dto->is_linked);
    }

    public function test_link_returns_refreshed_subscription_from_find(): void
    {
        $row = $this->subscriptionRow();
        $sub = $this->seedValidSubscription($row);

        $dto = $this->repository->linkToMember($sub->id, $sub->member_id);

        $this->assertSame($sub->member_id, $dto->member_id);
        $this->assertTrue($dto->is_linked);
    }

    public function test_returns_true_when_valid_active_linked_subscription_exists(): void
    {
        $member = $this->createMember();

        Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'is_linked' => true,
            'status' => 'active',
            'start_date' => now_datetime()->subDay(),
            'end_date' => now_datetime()->addDay(),
            'plan_name' => 'Test'
        ]);

        $this->assertTrue(
            $this->repository->hasLinkedActiveSubscription($member->id, $this->siteId)
        );
    }

    public function test_returns_false_when_subscription_is_not_linked(): void
    {
        $member = $this->createMember();

        Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'is_linked' => false,
            'status' => 'active',
            'plan_name' => 'Test',
            'start_date' => now_datetime()
        ]);

        $this->assertFalse(
            $this->repository->hasLinkedActiveSubscription($member->id, $this->siteId)
        );
    }

    public function test_returns_false_when_subscription_is_expired(): void
    {
        $member = $this->createMember();

        Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'is_linked' => true,
            'status' => 'active',
            'start_date' => now_datetime()->subDays(10),
            'end_date' => now_datetime()->subDay(),
            'plan_name' => 'test'
        ]);

        $this->assertFalse(
            $this->repository->hasLinkedActiveSubscription($member->id, $this->siteId)
        );
    }


    // ── Helpers ───────────────────────────────────────────────────────

    public function test_returns_false_when_subscription_has_not_started(): void
    {
        $member = $this->createMember();

        Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'is_linked' => true,
            'status' => 'active',
            'start_date' => now_datetime()->addDay(),
            'end_date' => null,
            'plan_name' => 'test'
        ]);

        $this->assertFalse(
            $this->repository->hasLinkedActiveSubscription($member->id, $this->siteId)
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PrintSubscriptionRepository();
    }
}