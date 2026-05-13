<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Exceptions\OpenCollab\ContributorUnderageException;
use App\Services\OpenCollab\ContributorAgeValidationService;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

class ContributorAgeValidationServiceTest extends TestCase
{
    private ContributorAgeValidationService $service;

    private DateTimeZone $utc;

    protected function setUp(): void
    {
        $this->service = new ContributorAgeValidationService();
        $this->utc = new DateTimeZone('UTC');
    }

    private function today(): DateTimeImmutable
    {
        return new DateTimeImmutable('today', $this->utc);
    }

    // ── calculateAge ──────────────────────────────────────────────────────────

    public function test_it_calculates_age_correctly_for_a_birthday_in_the_past(): void
    {
        // Exactly 25 years ago today
        $dob = $this->today()->modify('-25 years');

        $age = $this->service->calculateAge($dob);

        $this->assertSame(25, $age);
    }

    public function test_it_calculates_age_correctly_the_day_before_birthday(): void
    {
        // Born 18 years ago tomorrow — still 17 today
        $dob = new DateTimeImmutable('-18 years +1 day', new DateTimeZone('UTC'));
        $age = $this->service->calculateAge($dob);

        $this->assertSame(17, $age);
    }

    public function test_it_calculates_age_as_eighteen_on_the_birthday(): void
    {
        $dob = $this->today()->modify('-18 years');

        $age = $this->service->calculateAge($dob);

        $this->assertSame(18, $age);
    }

    public function test_it_handles_leap_year_birthday_on_march_1_in_non_leap_year(): void
    {
        // Feb 29, 2000 — a leap year DOB
        $dob = DateTimeImmutable::createFromFormat('Y-m-d', '2000-02-29', new DateTimeZone('UTC'));

        // Age calculation should not throw; DateInterval handles this correctly.
        $age = $this->service->calculateAge($dob);

        $this->assertIsInt($age);
        $this->assertGreaterThan(0, $age);
    }

    public function test_it_throws_when_dob_is_in_the_future(): void
    {
        $futureDob = new DateTimeImmutable('+1 day', new DateTimeZone('UTC'));

        $this->expectException(\InvalidArgumentException::class);

        $this->service->calculateAge($futureDob);
    }

    // ── meetsMinimumAge ───────────────────────────────────────────────────────

    public function test_it_returns_true_when_contributor_meets_minimum_age(): void
    {
        $dob = new DateTimeImmutable('-20 years', new DateTimeZone('UTC'));
        $result = $this->service->meetsMinimumAge($dob, 18);

        $this->assertTrue($result);
    }

    public function test_it_returns_true_on_exact_minimum_age_boundary(): void
    {
        $dob = $this->today()->modify('-18 years');

        $result = $this->service->meetsMinimumAge($dob, 18);

        $this->assertTrue($result);
    }

    public function test_it_returns_false_when_contributor_is_below_minimum_age(): void
    {
        $dob = new DateTimeImmutable('-17 years', new DateTimeZone('UTC'));
        $result = $this->service->meetsMinimumAge($dob, 18);

        $this->assertFalse($result);
    }

    // ── assertEligible ────────────────────────────────────────────────────────

    public function test_it_does_not_throw_when_contributor_is_eligible(): void
    {
        $dob = new DateTimeImmutable('-25 years', new DateTimeZone('UTC'));

        // Should not throw
        $this->service->assertEligible($dob, 18);
        $this->addToAssertionCount(1); // explicit assertion: no exception = pass
    }

    public function test_it_throws_underage_exception_when_contributor_is_too_young(): void
    {
        $dob = new DateTimeImmutable('-16 years', new DateTimeZone('UTC'));

        $this->expectException(ContributorUnderageException::class);

        $this->service->assertEligible($dob, 18);
    }

    public function test_underage_exception_carries_correct_ages(): void
    {
        $dob = $this->today()->modify('-16 years');

        try {
            $this->service->assertEligible($dob, 18);

            $this->fail('Expected ContributorUnderageException was not thrown.');
        } catch (ContributorUnderageException $e) {
            $this->assertSame(16, $e->contributorAge);
            $this->assertSame(18, $e->minimumAge);
        }
    }

    // ── parseDob ──────────────────────────────────────────────────────────────

    public function test_it_parses_a_valid_dob_string(): void
    {
        $dob = $this->service->parseDob('1995-06-15');

        $this->assertInstanceOf(DateTimeImmutable::class, $dob);
        $this->assertSame('1995-06-15', $dob->format('Y-m-d'));
    }

    public function test_it_returns_null_for_an_empty_string(): void
    {
        $this->assertNull($this->service->parseDob(''));
        $this->assertNull($this->service->parseDob(null));
    }

    public function test_it_returns_null_for_an_invalid_date_string(): void
    {
        $this->assertNull($this->service->parseDob('not-a-date'));
        $this->assertNull($this->service->parseDob('2000-13-01')); // month 13
        $this->assertNull($this->service->parseDob('2000-00-15')); // month 0
    }

    public function test_it_returns_null_for_wrong_format(): void
    {
        // ISO-like but wrong format
        $this->assertNull($this->service->parseDob('15/06/1995'));
        $this->assertNull($this->service->parseDob('1995/06/15'));
    }
}