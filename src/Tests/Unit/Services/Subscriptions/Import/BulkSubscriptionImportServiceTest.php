<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\Import;

use App\DTO\Subscriptions\BulkSubscriptionImportRow;
use App\Models\Member;
use App\Repositories\Members\MemberRepository;
use App\Services\Subscriptions\CrmSubscriptionCreationService;
use App\Services\Subscriptions\Import\BulkSubscriptionImportService;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BulkSubscriptionImportServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_reuses_existing_member_and_forwards_pricing_id(): void
    {
        $members = Mockery::mock(MemberRepository::class);
        $subscriptions = Mockery::mock(CrmSubscriptionCreationService::class);
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 12;

        $members->expects('findByEmail')->with('jane@example.com', 7)->andReturn($member);
        $members->shouldNotReceive('create');
        $subscriptions->expects('createSubscription')
            ->withArgs(fn(...$args) => $args[0] === 12
                && $args[1] === 5
                && $args[2] === 'pm_import'
                && $args[3] === 7
                && $args[6] === 9)
            ->andReturn(['success' => true]);

        $result = (new BulkSubscriptionImportService($members, $subscriptions))->import([
            ['line' => 2, 'row' => $this->row()],
        ], 7);

        self::assertSame(['processed' => 1, 'succeeded' => 1, 'failed' => 0, 'errors' => []], $result);
    }

    public function test_creates_missing_member_before_importing_subscription(): void
    {
        $members = Mockery::mock(MemberRepository::class);
        $subscriptions = Mockery::mock(CrmSubscriptionCreationService::class);
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 99;

        $members->expects('findByEmail')->andReturnNull();
        $members->expects('create')->withArgs(fn(array $data) => $data['email'] === 'jane@example.com'
            && $data['site_id'] === 7
            && $data['anonymous'] === false)->andReturn($member);
        $subscriptions->expects('createSubscription')->andReturn(['success' => true]);

        $result = (new BulkSubscriptionImportService($members, $subscriptions))->import([
            ['line' => 2, 'row' => $this->row()],
        ], 7);

        self::assertSame(1, $result['succeeded']);
    }

    public function test_records_row_failure_and_continues(): void
    {
        $members = Mockery::mock(MemberRepository::class);
        $subscriptions = Mockery::mock(CrmSubscriptionCreationService::class);
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 12;

        $members->expects('findByEmail')->twice()->andReturn($member);
        $subscriptions->expects('createSubscription')->twice()->andReturnUsing(function () {
            static $call = 0;
            if (++$call === 1) {
                throw new RuntimeException('Stripe rejected payment method');
            }
            return ['success' => true];
        });

        $result = (new BulkSubscriptionImportService($members, $subscriptions))->import([
            ['line' => 2, 'row' => $this->row()],
            ['line' => 3, 'row' => $this->row()],
        ], 7);

        self::assertSame(2, $result['processed']);
        self::assertSame(1, $result['succeeded']);
        self::assertSame(1, $result['failed']);
        self::assertSame(2, $result['errors'][0]['line']);
    }

    private function row(): BulkSubscriptionImportRow
    {
        return new BulkSubscriptionImportRow(
            email: 'jane@example.com',
            firstName: 'Jane',
            lastName: 'Doe',
            planId: 5,
            paymentMethodId: 'pm_import',
            address: ['address_line_1' => '1 High Street', 'city' => 'London', 'postcode' => 'SW1A 1AA', 'country_code' => 'GB'],
            pricingId: 9,
        );
    }
}
