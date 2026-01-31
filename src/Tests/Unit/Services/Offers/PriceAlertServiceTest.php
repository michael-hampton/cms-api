<?php

namespace App\Tests\Unit\Services\Offers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Repositories\Offers\PriceAlertRepository;
use App\Repositories\Product\ProductRepository;
use App\Services\Offers\PriceAlertService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery as m;

class PriceAlertServiceTest extends FunctionalTestCase
{
    private PriceAlertService $service;
    private $mockRepository;
    private $productRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockRepository = m::mock(PriceAlertRepository::class);
        $this->productRepository = m::mock(ProductRepository::class);
        $this->service = new PriceAlertService($this->mockRepository, $this->productRepository);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function test_create_alert_fails_when_product_not_found(): void
    {
        $data = [
            'product_id' => 999,
            'email' => 'test@example.com',
            'target_price' => 50.00
        ];

        $this->productRepository->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        $result = $this->service->createAlert($data);

        $this->assertFalse($result['success']);
        $this->assertEquals('Product not found', $result['message']);
    }

    public function test_create_alert_fails_with_invalid_email(): void
    {
        $product = m::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->price = 100.00;
        $product->sale_price = 90.00;

        $this->productRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($product);

        $data = [
            'product_id' => 1,
            'email' => 'invalid-email',
            'target_price' => 50.00
        ];

        $result = $this->service->createAlert($data);

        $this->assertFalse($result['success']);
        $this->assertEquals('Valid email is required', $result['message']);
    }

    public function test_create_alert_fails_with_empty_email(): void
    {
        $product = m::mock(Product::class)->makePartial();
        $product->id = 1;

        $this->productRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($product);

        $data = [
            'product_id' => 1,
            'email' => '',
            'target_price' => 50.00
        ];

        $result = $this->service->createAlert($data);

        $this->assertFalse($result['success']);
        $this->assertEquals('Valid email is required', $result['message']);
    }

    public function test_create_alert_fails_with_missing_email(): void
    {
        $product = m::mock(Product::class)->makePartial();
        $product->id = 1;

        $this->productRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($product);

        $data = [
            'product_id' => 1,
            'target_price' => 50.00
        ];

        $result = $this->service->createAlert($data);

        $this->assertFalse($result['success']);
        $this->assertEquals('Valid email is required', $result['message']);
    }

    public function test_create_alert_fails_with_zero_target_price(): void
    {
        $product = m::mock(Product::class)->makePartial();
        $product->id = 1;

        $this->productRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($product);

        $data = [
            'product_id' => 1,
            'email' => 'test@example.com',
            'target_price' => 0
        ];

        $result = $this->service->createAlert($data);

        $this->assertFalse($result['success']);
        $this->assertEquals('Valid target price is required', $result['message']);
    }

    public function test_create_alert_fails_with_negative_target_price(): void
    {
        $product = m::mock(Product::class)->makePartial();
        $product->id = 1;

        $this->productRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($product);

        $data = [
            'product_id' => 1,
            'email' => 'test@example.com',
            'target_price' => -10
        ];

        $result = $this->service->createAlert($data);

        $this->assertFalse($result['success']);
        $this->assertEquals('Valid target price is required', $result['message']);
    }

    public function test_create_alert_fails_with_missing_target_price(): void
    {
        $product = m::mock(Product::class)->makePartial();
        $product->id = 1;

        $this->productRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($product);

        $data = [
            'product_id' => 1,
            'email' => 'test@example.com'
        ];

        $result = $this->service->createAlert($data);

        $this->assertFalse($result['success']);
        $this->assertEquals('Valid target price is required', $result['message']);
    }

    public function test_create_alert_fails_when_target_price_above_current_price(): void
    {
        $product = m::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->price = 100.00;
        $product->sale_price = 80.00;
        $product->variants = null;
        $product->merchants = null;

        $this->productRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($product);

        $data = [
            'product_id' => 1,
            'email' => 'test@example.com',
            'target_price' => 120.00
        ];

        $result = $this->service->createAlert($data);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Target price must be lower than current price', $result['message']);
    }

    public function test_create_alert_fails_when_target_price_equals_current_price(): void
    {
        $product = m::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->price = 100.00;
        $product->sale_price = 80.00;
        $product->variants = null;
        $product->merchants = null;

        $this->productRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($product);

        $data = [
            'product_id' => 1,
            'email' => 'test@example.com',
            'target_price' => 100.00
        ];

        $result = $this->service->createAlert($data);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Target price must be lower than current price', $result['message']);
    }

    public function test_create_alert_successfully(): void
    {
        $product = m::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->price = 100.00;
        $product->sale_price = 80.00;
        $product->variants = null;
        $product->merchants = null;

        $this->productRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($product);

        $this->mockRepository->shouldReceive('findActiveAlertByEmailAndProduct')
            ->once()
            ->with('test@example.com', 1)
            ->andReturn(null);

        $mockAlert = m::mock(\App\Models\PriceAlert::class)->makePartial();
        $mockAlert->id = 1;

        $this->mockRepository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($arg) {
                return $arg['email'] === 'test@example.com'
                    && $arg['product_id'] === 1
                    && $arg['target_price'] == 70
                    && $arg['current_price'] == 100
                    && $arg['is_triggered'] === false
                    && $arg['is_notified'] === false;
            }))
            ->andReturn($mockAlert);

        $data = [
            'product_id' => 1,
            'email' => 'test@example.com',
            'target_price' => 70.00
        ];

        $result = $this->service->createAlert($data);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('created successfully', $result['message']);
        $this->assertArrayHasKey('alert', $result);
    }

    public function test_create_alert_updates_existing_alert(): void
    {
        $product = m::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->price = 100.00;
        $product->sale_price = 80.00;
        $product->variants = null;
        $product->merchants = null;

        $this->productRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($product);

        $existingAlert = m::mock(\App\Models\PriceAlert::class)->makePartial();
        $existingAlert->id = 1;

        $this->mockRepository->shouldReceive('findActiveAlertByEmailAndProduct')
            ->once()
            ->with('test@example.com', 1)
            ->andReturn($existingAlert);

        $this->mockRepository->shouldReceive('update')
            ->once()
            ->with($existingAlert, m::type('array'))
            ->andReturn(true);

        $data = [
            'product_id' => 1,
            'email' => 'test@example.com',
            'target_price' => 70.00
        ];

        $result = $this->service->createAlert($data);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('updated successfully', $result['message']);
    }

    public function test_create_alert_with_user_id(): void
    {
        $product = m::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->price = 100.00;
        $product->sale_price = 80.00;
        $product->variants = null;
        $product->merchants = null;

        $this->productRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($product);

        $this->mockRepository->shouldReceive('findActiveAlertByEmailAndProduct')
            ->once()
            ->andReturn(null);

        $mockAlert = m::mock(\App\Models\PriceAlert::class)->makePartial();

        $this->mockRepository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($arg) {
                return $arg['user_id'] === 5;
            }))
            ->andReturn($mockAlert);

        $data = [
            'product_id' => 1,
            'user_id' => 5,
            'email' => 'test@example.com',
            'target_price' => 70.00
        ];

        $result = $this->service->createAlert($data);

        $this->assertTrue($result['success']);
    }

    public function test_create_alert_with_variant_id(): void
    {
        $product = m::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->price = 100.00;
        $product->sale_price = 80.00;
        $product->merchants = null;

        $variant = m::mock(ProductVariant::class)->makePartial();
        $variant->id = 1;
        $variant->price = 90.00;
        $variant->sale_price = 70.00;
        $variant->merchants = null;

        $this->productRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($product);

        $this->mockRepository->shouldReceive('findVariant')
            ->once()
            ->with(1)
            ->andReturn($variant);

        $this->mockRepository->shouldReceive('findActiveAlertByEmailAndProduct')
            ->once()
            ->andReturn(null);

        $mockAlert = m::mock(\App\Models\PriceAlert::class)->makePartial();

        $this->mockRepository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($arg) {
                return $arg['variant_id'] === 1 && $arg['current_price'] === 70.00;
            }))
            ->andReturn($mockAlert);

        $data = [
            'product_id' => 1,
            'variant_id' => 1,
            'email' => 'test@example.com',
            'target_price' => 60.00
        ];

        $result = $this->service->createAlert($data);

        $this->assertTrue($result['success']);
    }

    public function test_check_alerts_processes_untriggered_alerts(): void
    {
        $alertData = [
            'id' => 1,
            'email' => 'test@example.com',
            'product_id' => 1,
            'target_price' => 50.00,
            'current_price' => 100.00,
            'is_triggered' => false
        ];

        $this->mockRepository->shouldReceive('getUntriggeredAlerts')
            ->once()
            ->andReturn([$alertData]);

        $alert = m::mock(\App\Models\PriceAlert::class)->makePartial();
        $alert->id = 1;
        $alert->email = 'test@example.com';
        $alert->product_id = 1;
        $alert->target_price = 120.00;
        $alert->current_price = 100.00;
        $alert->variant_id = null;
        $alert->merchant_id = null;

        $this->mockRepository->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($alert);

        $product = m::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->price = 100.00;
        $product->sale_price = 45.00;
        $product->variants = null;
        $product->merchants = null;

       $this->mockRepository->shouldReceive('getProductWithVariantMerchant')
           ->once()
           ->with(1)
           ->andReturn($product);

        $this->mockRepository->shouldReceive('update')
            ->once()
            ->with($alert, m::on(function ($arg) {
                return $arg['is_triggered'] === true
                    && isset($arg['triggered_at'])
                    && $arg['current_price'] == 100;
            }))
            ->andReturn(true);

        $product = m::mock(Product::class)->makePartial();
        $product->id = 1;

        $this->productRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($product);

        $this->mockRepository->shouldReceive('update')
            ->once()
            ->with($alert, m::on(function ($arg) {
                return $arg['is_notified'] === true && isset($arg['notified_at']);
            }))
            ->andReturn(true);

        $count = $this->service->checkAlerts();

        $this->assertEquals(1, $count);
    }

//    public function test_check_alerts_updates_current_price_when_not_triggered(): void
//    {
//        $alertData = [
//            'id' => 1,
}