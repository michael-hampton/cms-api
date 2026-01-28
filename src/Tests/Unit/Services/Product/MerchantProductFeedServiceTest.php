<?php

namespace App\Tests\Unit\Services\Product;

use App\Framework\HttpClient\HttpClient;
use App\Framework\HttpClient\HttpClientResponse;
use App\Framework\Support\Collection;
use App\Models\Brand;
use App\Models\Category;
use App\Models\MerchantProductFeed;
use App\Models\Product;
use App\Models\ProductMerchant;
use App\Repositories\Product\MerchantProductFeedRepository;
use App\Repositories\Product\ProductRepository;
use App\Services\Product\MerchantProductFeedService;
use App\Services\Product\ProductService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Exception;
use Mockery;

class MerchantProductFeedServiceTest extends FunctionalTestCase
{
    private $repository;
    private MerchantProductFeedService $service;
    private ProductRepository $productRepository;
    private ProductService $productService;
    private HttpClient $httpClient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = Mockery::mock(MerchantProductFeedRepository::class);
        $this->productRepository = Mockery::mock(ProductRepository::class);
        $this->productService = Mockery::mock(ProductService::class);
        $this->httpClient = Mockery::mock(HttpClient::class);

        $this->service = new MerchantProductFeedService(
            $this->repository,
            $this->productRepository,
            $this->productService,
            $this->httpClient
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testCreateFeedSetsDefaults()
    {
        $data = [
            'merchant_id' => 1,
            'feed_url' => 'https://example.com/feed.xml',
            'feed_type' => 'xml',
            'fetch_frequency' => 'daily'
        ];

        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return isset($arg['status']) &&
                    isset($arg['is_active']) &&
                    isset($arg['next_fetch_at']);
            }))
            ->andReturn(new MerchantProductFeed($data));

        $result = $this->service->createFeed($data);

        $this->assertNotNull($result);
    }

    public function testCreateFeedWithManualFrequencyDoesNotSetNextFetch()
    {
        $data = [
            'merchant_id' => 1,
            'feed_url' => 'https://example.com/feed.xml',
            'feed_type' => 'xml',
            'fetch_frequency' => 'manual'
        ];

        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return !isset($arg['next_fetch_at']);
            }))
            ->andReturn(new MerchantProductFeed($data));

        $result = $this->service->createFeed($data);

        $this->assertNotNull($result);
    }

    public function testUpdateFeedReturnsNullWhenFeedNotFound()
    {
        $this->repository->shouldReceive('find')
            ->with(999)
            ->andReturn(null);

        $result = $this->service->updateFeed(999, ['fetch_frequency' => 'hourly']);

        $this->assertNull($result);
    }

    public function testDeleteFeedReturnsTrue()
    {
        $feed = new MerchantProductFeed(['id' => 1]);

        $this->repository->shouldReceive('find')
            ->with(1)
            ->andReturn($feed);

        $this->repository->shouldReceive('delete')
            ->with(1)
            ->andReturn(true);

        $result = $this->service->deleteFeed(1);

        $this->assertTrue($result);
    }

    public function testDeleteFeedReturnsFalseWhenNotFound()
    {
        $this->repository->shouldReceive('find')
            ->with(999)
            ->andReturn(null);

        $result = $this->service->deleteFeed(999);

        $this->assertFalse($result);
    }



    public function testUpdateFeedRecalculatesNextFetchTime()
    {
        $feed = new MerchantProductFeed([
            'id' => 1,
            'fetch_frequency' => 'daily'
        ]);

        $this->repository->shouldReceive('find')
            ->with(1)
            ->andReturn($feed);

        $this->repository->shouldReceive('update')
            ->once()
            ->with(1, Mockery::on(function ($arg) {
                return isset($arg['next_fetch_at']);
            }))
            ->andReturn($feed);

        $result = $this->service->updateFeed(1, ['fetch_frequency' => 'hourly']);

        $this->assertNotNull($result);
    }

    public function testFetchFeedUpdatesStatus()
    {
        $feed = new MerchantProductFeed([
            'id' => 1,
            'merchant_id' => 1,
            'feed_url' => 'https://example.com/feed.xml',
            'fetch_frequency' => 'daily',
            'status' => 'pending',
            'feed_type' => 'csv'
        ]);

        $this->repository->shouldReceive('find')
            ->with(1)
            ->andReturn($feed);

        $this->repository->shouldReceive('update')
            ->with(1, Mockery::on(function ($arg) {
                return $arg['status'] === 'processing';
            }))
            ->once();

        $this->repository->shouldReceive('update')
            ->with(1, Mockery::on(function ($arg) {
                return $arg['status'] === 'success' &&
                    isset($arg['last_fetched_at']) &&
                    isset($arg['next_fetch_at']);
            }))
            ->once()
            ->andReturn($feed);

        $this->httpClient->shouldReceive('get')
            ->once()
            ->andReturn(new HttpClientResponse());

        $result = $this->service->fetchFeed(1);

        $this->assertNotNull($result);
    }

    public function testDownloadFeedDataReturnsCorrectFormat()
    {
        $xmlFeed = new MerchantProductFeed([
            'id' => 1,
            'feed_type' => 'xml',
            'merchant_id' => 1
        ]);

        $this->repository->shouldReceive('find')
            ->with(1)
            ->andReturn($xmlFeed);

        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;

        $this->productRepository->shouldReceive('getProductsByMerchant')
            ->with(1)
            ->andReturn(new Collection([$product]));

        $result = $this->service->downloadFeedData(1);

        $this->assertStringContainsString('<?xml', $result);
    }

    public function testDownloadFeedDataThrowsExceptionWhenFeedNotFound()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Feed not found');

        $this->repository->shouldReceive('find')
            ->with(999)
            ->andReturn(null);

        $this->service->downloadFeedData(999);
    }

    public function testDownloadFeedDataThrowsExceptionWhenNoProducts()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No products found for this merchant');

        $feed = new MerchantProductFeed([
            'id' => 1,
            'merchant_id' => 1,
            'feed_type' => 'xml'
        ]);

        $this->repository->shouldReceive('find')
            ->with(1)
            ->andReturn($feed);

        $this->productRepository->shouldReceive('getProductsByMerchant')
            ->with(1)
            ->andReturn(new Collection([]));

        $this->service->downloadFeedData(1);
    }

    public function testDownloadFeedDataGeneratesValidXml()
    {
        $feed = new MerchantProductFeed([
            'id' => 1,
            'merchant_id' => 1,
            'feed_type' => 'xml'
        ]);

        $product = $this->createMockProduct();
        $products = new Collection([$product]);

        $this->repository->shouldReceive('find')->with(1)->andReturn($feed);
        $this->productRepository->shouldReceive('getProductsByMerchant')
            ->with(1)
            ->andReturn($products);

        $result = $this->service->downloadFeedData(1);

        $this->assertStringContainsString('<?xml version="1.0"', $result);
        $this->assertStringContainsString('<products>', $result);
        $this->assertStringContainsString('<product>', $result);
        $this->assertStringContainsString('<name>Test Product</name>', $result);
        $this->assertStringContainsString('<price>99.99</price>', $result);
        $this->assertStringContainsString('</products>', $result);
    }

    public function testDownloadFeedDataGeneratesValidCsv()
    {
        $feed = new MerchantProductFeed([
            'id' => 1,
            'merchant_id' => 1,
            'feed_type' => 'csv'
        ]);

        $product = $this->createMockProduct();
        $products = new Collection([$product]);

        $this->repository->shouldReceive('find')->with(1)->andReturn($feed);
        $this->productRepository->shouldReceive('getProductsByMerchant')
            ->with(1)
            ->andReturn($products);

        $result = $this->service->downloadFeedData(1);

        // Check CSV structure
        $lines = explode("\n", trim($result));
        $this->assertGreaterThan(1, count($lines)); // Header + at least one data row

        // Check header
        $header = str_getcsv($lines[0]);
        $this->assertContains('id', $header);
        $this->assertContains('name', $header);
        $this->assertContains('price', $header);

        // Check data row
        $dataRow = str_getcsv($lines[1]);
        $this->assertEquals('1', $dataRow[0]); // id
        $this->assertStringContainsString('Test Product', $dataRow[1]); // name
    }

    public function testDownloadFeedDataGeneratesValidJson()
    {
        $feed = new MerchantProductFeed([
            'id' => 1,
            'merchant_id' => 1,
            'feed_type' => 'json'
        ]);

        $product = $this->createMockProduct();
        $products = new Collection([$product]);

        $this->repository->shouldReceive('find')->with(1)->andReturn($feed);
        $this->productRepository->shouldReceive('getProductsByMerchant')
            ->with(1)
            ->andReturn($products);

        $result = $this->service->downloadFeedData(1);

        $json = json_decode($result, true);

        $this->assertIsArray($json);
        $this->assertArrayHasKey('products', $json);
        $this->assertArrayHasKey('total', $json);
        $this->assertArrayHasKey('generated_at', $json);
        $this->assertCount(1, $json['products']);

        $firstProduct = $json['products'][0];
        $this->assertEquals(1, $firstProduct['id']);
        $this->assertEquals('Test Product', $firstProduct['name']);
        $this->assertEquals(99.99, $firstProduct['price']);
    }

    public function testDownloadFeedDataThrowsExceptionForUnsupportedType()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unsupported feed type: invalid');

        $feed = new MerchantProductFeed([
            'id' => 1,
            'merchant_id' => 1,
            'feed_type' => 'invalid'
        ]);

        $product = $this->createMockProduct();
        $products = new Collection([$product]);

        $this->repository->shouldReceive('find')->with(1)->andReturn($feed);
        $this->productRepository->shouldReceive('getProductsByMerchant')
            ->with(1)
            ->andReturn($products);

        $this->service->downloadFeedData(1);
    }

    public function testDownloadFeedDataIncludesMerchantSpecificData()
    {
        $feed = new MerchantProductFeed([
            'id' => 1,
            'merchant_id' => 1,
            'feed_type' => 'json'
        ]);

        $merchant = Mockery::mock(ProductMerchant::class)->makePartial();
        $merchant->effective_sale_price = 22.99;
        $merchant->price = 99.99;
        $merchant->is_availiable = true;
        $merchant->url = 'https://merchant.com/product';

        $merchant->shouldReceive('getEffectiveSalePriceAttribute')
            ->andReturn(22.99);

        $merchant->shouldReceive('getEffectivePriceAttribute')
            ->andReturn(89.99);

        $product = $this->createMockProduct(true);
        $product->merchant_data = $merchant;
        $products = new Collection([$product]);

        $this->repository->shouldReceive('find')->with(1)->andReturn($feed);
        $this->productRepository->shouldReceive('getProductsByMerchant')
            ->with(1)
            ->andReturn($products);

        $result = $this->service->downloadFeedData(1);
        $json = json_decode($result, true);

        $this->assertArrayHasKey('merchant', $json['products'][0]);
        $this->assertEquals('https://merchant.com/product', $json['products'][0]['merchant']['url']);
        $this->assertEquals(89.99, $json['products'][0]['merchant']['price']);
    }

    public function testFetchFeedReturnsNullWhenFeedNotFound()
    {
        $this->repository->shouldReceive('find')
            ->with(999)
            ->andReturn(null);

        $result = $this->service->fetchFeed(999);

        $this->assertNull($result);
    }

    public function testGetActiveFeedsForMerchant()
    {
        $feeds = new Collection([new MerchantProductFeed(['id' => 1])]);

        $this->repository->shouldReceive('getActiveFeedsByMerchant')
            ->with(1)
            ->andReturn($feeds);

        $result = $this->service->getActiveFeedsForMerchant(1);

        $this->assertCount(1, $result);
    }

    public function testGetFeedsDueForFetch()
    {
        $feeds = new Collection([
            new MerchantProductFeed(['id' => 1]),
            new MerchantProductFeed(['id' => 2])
        ]);

        $this->repository->shouldReceive('getDueForFetch')
            ->andReturn($feeds);

        $result = $this->service->getFeedsDueForFetch();

        $this->assertCount(2, $result);
    }

    protected function createMockProduct(bool $withMerchantData = false): Product
    {
        $brand = Mockery::mock(Brand::class)->makePartial();
        $brand->name = 'Test Brand';

        $category = Mockery::mock(Category::class)->makePartial();
        $category->name = 'Test Category';

        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->name = 'Test Product';
        $product->description = 'Test Description';
        $product->sku = 'TEST123';
        $product->price = 99.99;
        $product->sale_price = 79.99;
        $product->slug = 'test-product';
        $product->stock_quantity = 10;
        $product->main_image_url = 'https://example.com/image.jpg';
        $product->brand = $brand;
        $product->category = $category;

        if ($withMerchantData) {
            $merchantData = Mockery::mock(ProductMerchant::class)->makePartial();
            $merchantData->url = 'https://merchant.com/product';
            $merchantData->effective_price = 89.99;
            $merchantData->effective_sale_price = 69.99;
            $merchantData->is_available = true;
            $product->merchant_data = $merchantData;
        }

        return $product;
    }


}