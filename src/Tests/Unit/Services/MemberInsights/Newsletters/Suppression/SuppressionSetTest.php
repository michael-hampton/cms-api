<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\MemberInsights\Newsletters\Suppression;

use App\Services\MemberInsights\Newsletters\Suppression\SuppressionSet;
use PHPUnit\Framework\TestCase;

class SuppressionSetTest extends TestCase
{
    public function test_from_builds_set_with_given_ids(): void
    {
        $set = SuppressionSet::from([1, 2, 3]);

        $this->assertSame([1, 2, 3], $set->ids());
        $this->assertSame(3, $set->count());
        $this->assertFalse($set->isEmpty());
    }

    public function test_empty_returns_empty_set(): void
    {
        $set = SuppressionSet::empty();

        $this->assertSame([], $set->ids());
        $this->assertTrue($set->isEmpty());
        $this->assertSame(0, $set->count());
    }

    public function test_contains_returns_true_for_present_id(): void
    {
        $set = SuppressionSet::from([10, 20]);

        $this->assertTrue($set->contains(10));
        $this->assertTrue($set->contains(20));
    }

    public function test_contains_returns_false_for_absent_id(): void
    {
        $set = SuppressionSet::from([10, 20]);

        $this->assertFalse($set->contains(99));
    }

    public function test_from_deduplicates_input(): void
    {
        $set = SuppressionSet::from([5, 5, 10, 10]);

        $this->assertSame(2, $set->count());
        $this->assertSame([5, 10], $set->ids());
    }

    public function test_from_reindexes_output_array(): void
    {
        // Ensures ids() always returns a 0-indexed list regardless of input keys.
        $set = SuppressionSet::from([2 => 10, 5 => 20]);

        $this->assertSame([10, 20], $set->ids());
    }
}