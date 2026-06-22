<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enums;

use App\Enums\Subscriptions\ReplacementResolution;
use PHPUnit\Framework\TestCase;

class ReplacementResolutionTest extends TestCase
{
    public function test_from_request_accepts_replace(): void
    {
        $this->assertSame(ReplacementResolution::REPLACE, ReplacementResolution::fromRequest(' replace '));
    }

    public function test_from_request_accepts_extend(): void
    {
        $this->assertSame(ReplacementResolution::EXTEND, ReplacementResolution::fromRequest('EXTEND'));
    }

    public function test_from_request_rejects_invalid_value(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("decision must be 'replace' or 'extend'.");

        ReplacementResolution::fromRequest('refund');
    }
}
