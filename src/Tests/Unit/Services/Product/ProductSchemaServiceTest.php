<?php

namespace App\Tests\Unit\Services\Product;

use App\Models\Product;
use App\Services\Product\ProductSchemaService;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class ProductSchemaServiceTest extends FunctionalTestCase
{
    private ProductSchemaService $service;

    public function testGenerateStructuredData()
    {
        $product = new Product([
            'name' => 'Test Product',
            'description' => 'Test Description',
            'price' => 99.99,
            'sale_price' => 79.99,
            'slug' => 'test-product',
            'in_stock' => true
        ]);

        $result = $this->service->generateStructuredData($product);

        $this->assertEquals('https://schema.org/', $result['@context']);
        $this->assertEquals('Product', $result['@type']);
        $this->assertEquals('Test Product', $result['name']);
        $this->assertEquals(79.99, $result['offers']['price']);
        $this->assertEquals('InStock', $result['offers']['availability']);
    }

    public function testGenerateStructuredDataOutOfStock()
    {
        $product = new Product([
            'name' => 'Test Product',
            'price' => 99.99,
            'slug' => 'test-product',
            'in_stock' => false
        ]);

        $result = $this->service->generateStructuredData($product);

        $this->assertEquals('OutOfStock', $result['offers']['availability']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProductSchemaService();
    }
}