<?php

namespace App\Tests\Unit\Services\Reviews;

use App\Services\Reviews\VerifiedPurchaseResolver;
use PHPUnit\Framework\TestCase;

class VerifiedPurchaseResolverTest extends TestCase
{
    private VerifiedPurchaseResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new VerifiedPurchaseResolver();
    }

    public function testIsVerifiedAlwaysReturnsFalse()
    {
        // TODO implementation pending order system
        $result = $this->resolver->isVerified(123, 1);
        $this->assertFalse($result);
    }

    public function testIsVerifiedWithDifferentParameters()
    {
        $this->assertFalse($this->resolver->isVerified(1, 1));
        $this->assertFalse($this->resolver->isVerified(999, 999));
    }
}