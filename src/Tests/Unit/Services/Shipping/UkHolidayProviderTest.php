<?php

namespace App\Tests\Unit\Services\Shipping;

use App\Services\Shipping\UkHolidayProvider;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class UkHolidayProviderTest extends FunctionalTestCase
{
    private UkHolidayProvider $provider;

    public function testRecognisesKnownUkHoliday(): void
    {
        // Christmas Day 2025
        $date = new \DateTimeImmutable('2025-12-25');

        $result = $this->provider->isHoliday($date);

        $this->assertTrue($result);
    }

    public function testDoesNotFalselyDetectNormalDay(): void
    {
        // Regular Wednesday
        $date = new \DateTimeImmutable('2026-02-18');

        $result = $this->provider->isHoliday($date);

        $this->assertFalse($result);
    }

    public function testRecognisesNewYearsDay(): void
    {
        $date = new \DateTimeImmutable('2026-01-01');

        $result = $this->provider->isHoliday($date);

        $this->assertTrue($result);
    }

    public function testRecognisesGoodFriday(): void
    {
        $date = new \DateTimeImmutable('2025-04-18');

        $result = $this->provider->isHoliday($date);

        $this->assertTrue($result);
    }

    public function testDoesNotRecogniseNonHoliday(): void
    {
        // Random day in March
        $date = new \DateTimeImmutable('2026-03-15');

        $result = $this->provider->isHoliday($date);

        $this->assertFalse($result);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new UkHolidayProvider();
    }
}