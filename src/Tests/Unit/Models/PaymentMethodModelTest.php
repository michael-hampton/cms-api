<?php

namespace App\Tests\Unit\Models;

use App\Models\PaymentMethod;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class PaymentMethodModelTest extends FunctionalTestCase
{
    public function testPaymentMethodHasCorrectTable()
    {
        $paymentMethod = new PaymentMethod();
        $this->assertEquals('payment_methods', $paymentMethod->getTable());
    }

    public function testIsActiveReturnsTrueWhenActive()
    {
        $paymentMethod = new PaymentMethod(['is_active' => true]);
        $this->assertTrue($paymentMethod->isActive());
    }

    public function testIsActiveReturnsFalseWhenInactive()
    {
        $paymentMethod = new PaymentMethod(['is_active' => false]);
        $this->assertFalse($paymentMethod->isActive());
    }

    public function testRequiresProcessingReturnsTrueWhenSet()
    {
        $paymentMethod = new PaymentMethod(['requires_processing' => true]);
        $this->assertTrue($paymentMethod->requiresProcessing());
    }

    public function testRequiresProcessingReturnsFalseWhenNotSet()
    {
        $paymentMethod = new PaymentMethod(['requires_processing' => false]);
        $this->assertFalse($paymentMethod->requiresProcessing());
    }

    public function testGetConfigurationReturnsAllConfig()
    {
        $config = ['api_key' => 'test123', 'secret' => 'secret123'];
        $paymentMethod = new PaymentMethod(['configuration' => $config]);

        $this->assertEquals($config, $paymentMethod->getConfiguration());
    }

    public function testGetConfigurationReturnsSpecificKey()
    {
        $config = ['api_key' => 'test123', 'secret' => 'secret123'];
        $paymentMethod = new PaymentMethod(['configuration' => $config]);

        $this->assertEquals('test123', $paymentMethod->getConfiguration('api_key'));
    }

    public function testGetConfigurationReturnsNullForMissingKey()
    {
        $paymentMethod = new PaymentMethod(['configuration' => []]);

        $this->assertNull($paymentMethod->getConfiguration('missing_key'));
    }

    public function testSetConfigurationUpdatesConfig()
    {
        $paymentMethod = new PaymentMethod(['configuration' => []]);

        $paymentMethod->setConfiguration('api_key', 'new_value');

        $this->assertEquals('new_value', $paymentMethod->getConfiguration('api_key'));
    }

    public function testToArrayExcludesConfiguration()
    {
        $config = ['api_key' => 'test123', 'secret' => 'secret123'];
        $paymentMethod = new PaymentMethod([
            'name' => 'Stripe',
            'code' => 'stripe',
            'is_active' => true,
            'configuration' => $config
        ]);

        $array = $paymentMethod->toArray();

        $this->assertArrayNotHasKey('configuration', $array);
        $this->assertArrayHasKey('is_active_bool', $array);
        $this->assertArrayHasKey('requires_processing_bool', $array);
    }

    public function testPaymentMethodCanBeCreated()
    {
        $paymentMethod = PaymentMethod::create([
            'site_id' => $this->siteId,
            'name' => 'Test Payment',
            'code' => 'test_payment_' . uniqid(),
            'provider' => 'test',
            'is_active' => true,
            'requires_processing' => false,
            'sort_order' => 0
        ]);

        $this->assertNotNull($paymentMethod->id);
        $this->assertEquals('Test Payment', $paymentMethod->name);
    }

    protected function setUp(): void
    {
        parent::setUp();
    }
}