<?php

namespace App\Tests\Unit\Services;

use App\Services\ShippingService;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class ShippingServiceTest extends FunctionalTestCase
{
    private ShippingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ShippingService();
    }

    public function testCalculateShippingReturnsFreeShippingOverThreshold()
    {
        $data = ['country' => 'US'];

        $shipping = $this->service->calculateShipping(100.00, $data);

        $this->assertEquals(0.00, $shipping);
    }

    public function testCalculateShippingReturnsDefaultRateUnderThreshold()
    {
        $data = ['country' => 'US'];

        $shipping = $this->service->calculateShipping(50.00, $data);

        $this->assertEquals(10.00, $shipping);
    }

    public function testCalculateShippingReturnsCountrySpecificRate()
    {
        $data = ['country' => 'CA'];

        $shipping = $this->service->calculateShipping(50.00, $data);

        $this->assertEquals(15.00, $shipping);
    }

    public function testCalculateShippingForUK()
    {
        $data = ['country' => 'GB'];

        $shipping = $this->service->calculateShipping(50.00, $data);

        $this->assertEquals(12.00, $shipping);
    }

    public function testCalculateShippingForAustralia()
    {
        $data = ['country' => 'AU'];

        $shipping = $this->service->calculateShipping(50.00, $data);

        $this->assertEquals(20.00, $shipping);
    }

    public function testCalculateShippingForUnknownCountryUsesDefault()
    {
        $data = ['country' => 'XX'];

        $shipping = $this->service->calculateShipping(50.00, $data);

        $this->assertEquals(10.00, $shipping);
    }

    public function testCalculateShippingWhenCountryNotProvided()
    {
        $data = [];

        $shipping = $this->service->calculateShipping(50.00, $data);

        $this->assertEquals(10.00, $shipping);
    }

    public function testCalculateShippingExactlyAtThreshold()
    {
        $data = ['country' => 'US'];

        $shipping = $this->service->calculateShipping(100.00, $data);

        $this->assertEquals(0.00, $shipping);
    }

    public function testCalculateShippingJustBelowThreshold()
    {
        $data = ['country' => 'US'];

        $shipping = $this->service->calculateShipping(99.99, $data);

        $this->assertEquals(10.00, $shipping);
    }

    public function testCalculateShippingJustAboveThreshold()
    {
        $data = ['country' => 'US'];

        $shipping = $this->service->calculateShipping(100.01, $data);

        $this->assertEquals(0.00, $shipping);
    }

    public function testGetFreeShippingThreshold()
    {
        $threshold = $this->service->getFreeShippingThreshold();

        $this->assertEquals(100.00, $threshold);
    }

    public function testGetShippingRateForUS()
    {
        $rate = $this->service->getShippingRate('US');

        $this->assertEquals(10.00, $rate);
    }

    public function testGetShippingRateForCanada()
    {
        $rate = $this->service->getShippingRate('CA');

        $this->assertEquals(15.00, $rate);
    }

    public function testGetShippingRateForUK()
    {
        $rate = $this->service->getShippingRate('GB');

        $this->assertEquals(12.00, $rate);
    }

    public function testGetShippingRateForAustralia()
    {
        $rate = $this->service->getShippingRate('AU');

        $this->assertEquals(20.00, $rate);
    }

    public function testGetShippingRateForUnknownCountry()
    {
        $rate = $this->service->getShippingRate('XX');

        $this->assertEquals(10.00, $rate);
    }

    public function testCalculateShippingWithZeroSubtotal()
    {
        $data = ['country' => 'US'];

        $shipping = $this->service->calculateShipping(0.00, $data);

        $this->assertEquals(10.00, $shipping);
    }

    public function testCalculateShippingWithNegativeSubtotal()
    {
        $data = ['country' => 'US'];

        // Edge case - should still apply threshold logic
        $shipping = $this->service->calculateShipping(-10.00, $data);

        $this->assertEquals(10.00, $shipping);
    }

    public function testCalculateShippingWithVeryLargeSubtotal()
    {
        $data = ['country' => 'US'];

        $shipping = $this->service->calculateShipping(999999.99, $data);

        $this->assertEquals(0.00, $shipping);
    }
}