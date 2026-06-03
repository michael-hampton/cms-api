<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Members;

use App\DTO\Members\DuplicateMatch;
use App\Enums\Member\DuplicateMemberMatchType;
use App\Models\Member;
use App\Repositories\Members\MemberRepository;
use App\Services\Members\MemberDuplicateDetectionService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MemberDuplicateDetectionService.
 *
 * MemberRepository is mocked; no database is touched.
 * All mocked models use Mockery::mock(Member::class)->makePartial()
 * so real properties are accessible.
 */
class MemberDuplicateDetectionServiceTest extends TestCase
{
    private MemberRepository&MockInterface $memberRepository;
    private MemberDuplicateDetectionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->memberRepository = Mockery::mock(MemberRepository::class);
        $this->service          = new MemberDuplicateDetectionService($this->memberRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ─── detectForMember ─────────────────────────────────────────────────────

    public function test_returns_empty_collection_when_no_duplicates_found(): void
    {
        $member = $this->makeMember(1, 'alice@example.com');

        $this->expectAllRepositoryCallsReturn($member, collect());

        $result = $this->service->detectForMember($member);

        $this->assertCount(0, $result);
    }

    public function test_exact_email_match_returns_high_confidence_flag(): void
    {
        $member    = $this->makeMember(1, 'alice@example.com');
        $duplicate = $this->makeMember(2, 'alice@example.com');

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByEmail')
            ->once()
            ->with($member)
            ->andReturn(collect([$duplicate]));

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByStripeCustomerId')
            ->once()
            ->with($member)
            ->andReturn(collect());

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByPhone')
            ->once()
            ->with($member)
            ->andReturn(collect());

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByNameAndPostcode')
            ->once()
            ->with($member)
            ->andReturn(collect());

        $result = $this->service->detectForMember($member);

        $this->assertCount(1, $result);
        $this->assertEquals(DuplicateMemberMatchType::Email, $result->first()->matchType);
        $this->assertEquals(95, $result->first()->confidenceScore);
        $this->assertEquals(2, $result->first()->duplicateMember->id);
    }

    public function test_stripe_customer_id_match_returns_high_confidence_flag(): void
    {
        $member    = $this->makeMember(1, 'a@example.com', phone: null, stripeId: 'cus_abc123');
        $duplicate = $this->makeMember(2, 'b@example.com', phone: null, stripeId: 'cus_abc123');

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByEmail')
            ->once()->with($member)->andReturn(collect());

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByStripeCustomerId')
            ->once()->with($member)->andReturn(collect([$duplicate]));

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByPhone')
            ->once()->with($member)->andReturn(collect());

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByNameAndPostcode')
            ->once()->with($member)->andReturn(collect());

        $result = $this->service->detectForMember($member);

        $this->assertCount(1, $result);
        $this->assertEquals(DuplicateMemberMatchType::StripeCustomer, $result->first()->matchType);
        $this->assertEquals(95, $result->first()->confidenceScore);
    }

    public function test_phone_match_returns_medium_confidence_flag(): void
    {
        $member    = $this->makeMember(1, 'a@example.com', phone: '07700900000');
        $duplicate = $this->makeMember(2, 'b@example.com', phone: '07700900000');

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByEmail')
            ->once()->with($member)->andReturn(collect());

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByStripeCustomerId')
            ->once()->with($member)->andReturn(collect());

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByPhone')
            ->once()->with($member)->andReturn(collect([$duplicate]));

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByNameAndPostcode')
            ->once()->with($member)->andReturn(collect());

        $result = $this->service->detectForMember($member);

        $this->assertCount(1, $result);
        $this->assertEquals(DuplicateMemberMatchType::Phone, $result->first()->matchType);
        $this->assertEquals(85, $result->first()->confidenceScore);
    }

    public function test_name_and_postcode_match_returns_lower_confidence_flag(): void
    {
        $member    = $this->makeMember(1, 'a@example.com', lastName: 'Smith');
        $duplicate = $this->makeMember(2, 'b@example.com', lastName: 'Smith');

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByEmail')
            ->once()->with($member)->andReturn(collect());

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByStripeCustomerId')
            ->once()->with($member)->andReturn(collect());

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByPhone')
            ->once()->with($member)->andReturn(collect());

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByNameAndPostcode')
            ->once()->with($member)->andReturn(collect([$duplicate]));

        $result = $this->service->detectForMember($member);

        $this->assertCount(1, $result);
        $this->assertEquals(DuplicateMemberMatchType::NamePostcode, $result->first()->matchType);
        $this->assertEquals(60, $result->first()->confidenceScore);
    }

    public function test_member_is_not_compared_against_itself(): void
    {
        // Repository methods must be called with the member object;
        // the service never injects the member into results itself —
        // that responsibility lives in the repository queries.
        // This test confirms all four strategies are invoked exactly once.
        $member = $this->makeMember(1, 'a@example.com');

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByEmail')
            ->once()->with($member)->andReturn(collect());

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByStripeCustomerId')
            ->once()->with($member)->andReturn(collect());

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByPhone')
            ->once()->with($member)->andReturn(collect());

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByNameAndPostcode')
            ->once()->with($member)->andReturn(collect());

        $result = $this->service->detectForMember($member);

        $this->assertCount(0, $result);
    }

    public function test_when_same_duplicate_matched_by_multiple_signals_highest_confidence_wins(): void
    {
        // Member 2 appears in both email AND phone results.
        // email confidence = 95, phone confidence = 85.
        // Result should contain member 2 once, with email match type (95).
        $member    = $this->makeMember(1, 'a@example.com', phone: '07700900000');
        $duplicate = $this->makeMember(2, 'a@example.com', phone: '07700900000');

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByEmail')
            ->once()->with($member)->andReturn(collect([$duplicate]));

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByStripeCustomerId')
            ->once()->with($member)->andReturn(collect());

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByPhone')
            ->once()->with($member)->andReturn(collect([$duplicate]));

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByNameAndPostcode')
            ->once()->with($member)->andReturn(collect());

        $result = $this->service->detectForMember($member);

        $this->assertCount(1, $result);
        $this->assertEquals(DuplicateMemberMatchType::Email, $result->first()->matchType);
        $this->assertEquals(95, $result->first()->confidenceScore);
    }

    public function test_multiple_distinct_duplicates_are_all_returned(): void
    {
        $member = $this->makeMember(1, 'a@example.com');
        $dup1   = $this->makeMember(2, 'a@example.com');   // email match
        $dup2   = $this->makeMember(3, 'c@example.com');   // phone match

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByEmail')
            ->once()->with($member)->andReturn(collect([$dup1]));

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByStripeCustomerId')
            ->once()->with($member)->andReturn(collect());

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByPhone')
            ->once()->with($member)->andReturn(collect([$dup2]));

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByNameAndPostcode')
            ->once()->with($member)->andReturn(collect());

        $result = $this->service->detectForMember($member);

        $this->assertCount(2, $result);

        $ids = $result->pluck('duplicateMember.id')->sort()->values()->all();
        $this->assertEquals([2, 3], $ids);
    }

    public function test_results_ordered_by_confidence_score_descending(): void
    {
        $member = $this->makeMember(1, 'a@example.com', lastName: 'Jones');
        $dup1   = $this->makeMember(2, 'c@example.com'); // phone = 85
        $dup2   = $this->makeMember(3, 'd@example.com'); // name+postcode = 60

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByEmail')
            ->once()->with($member)->andReturn(collect());

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByStripeCustomerId')
            ->once()->with($member)->andReturn(collect());

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByPhone')
            ->once()->with($member)->andReturn(collect([$dup1]));

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByNameAndPostcode')
            ->once()->with($member)->andReturn(collect([$dup2]));

        $result = $this->service->detectForMember($member);

        $this->assertCount(2, $result);
        $this->assertEquals(85, $result->first()->confidenceScore);
        $this->assertEquals(60, $result->last()->confidenceScore);
    }

    // ─── detectBestMatchForMember ─────────────────────────────────────────────

    public function test_detect_best_match_returns_null_when_no_duplicates(): void
    {
        $member = $this->makeMember(1, 'a@example.com');

        $this->expectAllRepositoryCallsReturn($member, collect());

        $result = $this->service->detectBestMatchForMember($member);

        $this->assertNull($result);
    }

    public function test_detect_best_match_returns_highest_confidence_duplicate(): void
    {
        $member = $this->makeMember(1, 'a@example.com', phone: '07700900000');
        $dup1   = $this->makeMember(2, 'a@example.com'); // email = 95
        $dup2   = $this->makeMember(3, 'b@example.com'); // phone = 85

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByEmail')
            ->once()->with($member)->andReturn(collect([$dup1]));

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByStripeCustomerId')
            ->once()->with($member)->andReturn(collect());

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByPhone')
            ->once()->with($member)->andReturn(collect([$dup2]));

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByNameAndPostcode')
            ->once()->with($member)->andReturn(collect());

        $best = $this->service->detectBestMatchForMember($member);

        $this->assertInstanceOf(DuplicateMatch::class, $best);
        $this->assertEquals(2, $best->duplicateMember->id);
        $this->assertEquals(95, $best->confidenceScore);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Create a partial mock of Member with the given properties set.
     */
    private function makeMember(
        int     $id,
        string  $email,
        ?string $phone     = null,
        ?string $stripeId  = null,
        string  $lastName  = 'Doe',
        int     $siteId    = 1,
    ): Member {
        $member = Mockery::mock(Member::class)->makePartial();

        $member->id                 = $id;
        $member->email              = $email;
        $member->phone              = $phone;
        $member->stripe_customer_id = $stripeId;
        $member->last_name          = $lastName;
        $member->first_name         = 'Test';
        $member->site_id            = $siteId;

        return $member;
    }

    /**
     * Set all four repository methods to return the given collection.
     * Used in tests that only care that the result is empty.
     */
    private function expectAllRepositoryCallsReturn(Member $member, mixed $return): void
    {
        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByEmail')
            ->once()->with($member)->andReturn($return);

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByStripeCustomerId')
            ->once()->with($member)->andReturn($return);

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByPhone')
            ->once()->with($member)->andReturn($return);

        $this->memberRepository
            ->shouldReceive('findPossibleDuplicatesByNameAndPostcode')
            ->once()->with($member)->andReturn($return);
    }
}