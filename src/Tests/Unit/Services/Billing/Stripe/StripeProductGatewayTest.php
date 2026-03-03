<?php

namespace App\Tests\Unit\Services\Billing\Stripe;

use App\Services\Billing\Stripe\StripeProductGateway;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Stripe\Service\ProductService;
use Stripe\StripeClient;

class StripeProductGatewayTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private ProductService $products;
    private StripeProductGateway $gateway;

    public function testCreateProductCallsStripeWithName(): void
    {
        $product = (object)['id' => 'prod_abc123'];

        $this->products
            ->shouldReceive('create')->once()
            ->with(['name' => 'Monthly Magazine'])
            ->andReturn($product);

        $this->gateway->createProduct('Monthly Magazine');
    }

    // ---------------------------------------------------------------------------
    // createProduct
    // ---------------------------------------------------------------------------

    public function testCreateProductReturnsStripeProductId(): void
    {
        $this->products
            ->shouldReceive('create')
            ->andReturn((object)['id' => 'prod_xyz999']);

        $this->assertEquals('prod_xyz999', $this->gateway->createProduct('Annual Plan'));
    }

    public function testCreateProductPropagatesStripeException(): void
    {
        $this->products
            ->shouldReceive('create')
            ->andThrow(new \Stripe\Exception\ApiConnectionException('Network error'));

        $this->expectException(\Stripe\Exception\ApiConnectionException::class);

        $this->gateway->createProduct('Failing Plan');
    }

    public function testDeleteProductCallsStripeDeleteWithId(): void
    {
        $this->products
            ->shouldReceive('delete')->once()
            ->with('prod_to_delete');

        $this->gateway->deleteProduct('prod_to_delete');
    }

    // ---------------------------------------------------------------------------
    // deleteProduct
    // ---------------------------------------------------------------------------

    public function testDeleteProductSwallowsNotFoundError(): void
    {
        // InvalidRequestException is what Stripe throws for 404s.
        // deleteProduct is used for compensation — it must always be safe to call
        // even if the product was never created or was already deleted.
        $this->products
            ->shouldReceive('delete')
            ->andThrow(new \Stripe\Exception\InvalidRequestException(
                'No such product',
                null,
                null,
            ));

        $this->gateway->deleteProduct('prod_already_gone'); // must not throw

        $this->assertTrue(true);
    }

    public function testDeleteProductDoesNotSwallowOtherStripeExceptions(): void
    {
        $this->products
            ->shouldReceive('delete')
            ->andThrow(new \Stripe\Exception\ApiConnectionException('Network failure'));

        $this->expectException(\Stripe\Exception\ApiConnectionException::class);

        $this->gateway->deleteProduct('prod_network_fail');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->products = Mockery::mock(ProductService::class);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->products = $this->products;

        $this->gateway = new StripeProductGateway($stripe);
    }
}