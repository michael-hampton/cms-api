<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\BusinessDecisions;

use App\Services\Subscriptions\BusinessDecisions\CancellationRefundCapCalculator;
use PHPUnit\Framework\TestCase;

class CancellationRefundCapCalculatorTest extends TestCase
{
    private CancellationRefundCapCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new CancellationRefundCapCalculator();
    }

    public function testReturnsZeroWhenPercentIsZero(): void
    {
        $this->assertSame(0.0, $this->calculator->maxRefundableAmount(40.0, 0));
    }

    public function testReturnsZeroWhenPaymentIsZero(): void
    {
        $this->assertSame(0.0, $this->calculator->maxRefundableAmount(0.0, 50));
    }

    public function testCalculatesPercentOfPayment(): void
    {
        $this->assertSame(20.0, $this->calculator->maxRefundableAmount(40.0, 50));
        $this->assertSame(40.0, $this->calculator->maxRefundableAmount(40.0, 100));
        $this->assertSame(10.0, $this->calculator->maxRefundableAmount(40.0, 25));
    }
}
