<?php

namespace App\Tests\Unit\Services\Product;

use App\Models\Product;
use App\Repositories\Product\ProductRepository;
use App\Services\Product\ProductComparisonService;
use Mockery;
use PHPUnit\Framework\TestCase;

class ProductComparisonServiceTest extends TestCase
{
    private ProductRepository $mockRepo;
    private ProductComparisonService $service;

    public function test_rejects_less_than_two_products(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Must compare between 2 and 4 products');

        $this->service->compareProducts([1]);
    }

    public function test_rejects_more_than_four_products(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Must compare between 2 and 4 products');

        $this->service->compareProducts([1, 2, 3, 4, 5]);
    }

    public function test_throws_exception_when_product_not_found(): void
    {
        $this->mockRepo->shouldReceive('find')
            ->with(999, Mockery::any())
            ->once()
            ->andReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Product 999 not found');

        $this->service->compareProducts([999, 1]);
    }

    public function test_returns_not_comparable_when_no_shared_specs(): void
    {
        $product1 = $this->createMockProduct(1, [
            $this->createMockSpec(1, 'group1', 'Spec A', 'Value 1')
        ]);

        $product2 = $this->createMockProduct(2, [
            $this->createMockSpec(2, 'group2', 'Spec B', 'Value 2')
        ]);

        $this->mockRepo->shouldReceive('find')
            ->with(1, Mockery::any())
            ->once()
            ->andReturn($product1);

        $this->mockRepo->shouldReceive('find')
            ->with(2, Mockery::any())
            ->once()
            ->andReturn($product2);

        $result = $this->service->compareProducts([1, 2]);

        $this->assertFalse($result['comparable']);
        $this->assertEquals('At least 2 products must share specification groups', $result['reason']);
    }

    private function createMockProduct(int $id, array $specs)
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = $id;
        $product->name = "Product {$id}";
        $product->slug = "product-{$id}";
        $product->price = 100.00;
        $product->sale_price = null;
        $product->main_image_url = "/image-{$id}.jpg";
        $product->image = "/image-{$id}.jpg";
        $product->brand = $this->createMockBrand();
        $product->category = $this->createMockCategory();
        $product->specifications = $specs;

        return $product;
    }

    private function createMockBrand()
    {
        $brand = Mockery::mock();
        $brand->name = 'Test Brand';

        return $brand;
    }

    private function createMockCategory()
    {
        $category = Mockery::mock();
        $category->name = 'Test Category';

        return $category;
    }

    private function createMockSpec(int $id, $group, string $key, string $value)
    {
        $spec = Mockery::mock();
        $spec->id = $id;
        $spec->key = $key;
        $spec->value = $value;
        $spec->comparison_value = null; // Can be set for testing normalization
        $spec->specification_group_id = is_object($group) ? $group->id : $group;
        $spec->specificationGroup = is_object($group) ? $group : null;

        return $spec;
    }

    public function test_compares_two_products_with_shared_specs_successfully(): void
    {
        $specGroup = $this->createMockSpecGroup(1, 'Dimensions');

        $product1 = $this->createMockProduct(1, [
            $this->createMockSpec(100, $specGroup, 'Width', '100cm'),
            $this->createMockSpec(101, $specGroup, 'Height', '200cm')
        ]);

        $product2 = $this->createMockProduct(2, [
            $this->createMockSpec(100, $specGroup, 'Width', '120cm'),
            $this->createMockSpec(101, $specGroup, 'Height', '200cm')
        ]);

        $this->mockRepo->shouldReceive('find')
            ->with(1, Mockery::any())
            ->once()
            ->andReturn($product1);

        $this->mockRepo->shouldReceive('find')
            ->with(2, Mockery::any())
            ->once()
            ->andReturn($product2);

        $result = $this->service->compareProducts([1, 2]);

        $this->assertTrue($result['comparable']);
        $this->assertCount(2, $result['products']);
        $this->assertCount(1, $result['specification_groups']);

        // Should have 2 shared specs
        $specs = $result['specification_groups'][0]['specifications'];
        $this->assertCount(2, $specs);
        $this->assertArrayHasKey('Width', $specs);
        $this->assertArrayHasKey('Height', $specs);
    }

    private function createMockSpecGroup(int $id, string $name)
    {
        $group = Mockery::mock();
        $group->id = $id;
        $group->name = $name;

        return $group;
    }

    public function test_only_shows_shared_specifications(): void
    {
        $specGroup = $this->createMockSpecGroup(1, 'Test Group');

        $product1 = $this->createMockProduct(1, [
            $this->createMockSpec(100, $specGroup, 'Shared Spec', 'Value 1'),
            $this->createMockSpec(101, $specGroup, 'Unique to Product 1', 'Value')
        ]);

        $product2 = $this->createMockProduct(2, [
            $this->createMockSpec(100, $specGroup, 'Shared Spec', 'Value 2')
        ]);

        $this->mockRepo->shouldReceive('find')
            ->with(1, Mockery::any())
            ->once()
            ->andReturn($product1);

        $this->mockRepo->shouldReceive('find')
            ->with(2, Mockery::any())
            ->once()
            ->andReturn($product2);

        $result = $this->service->compareProducts([1, 2]);

        // Should only have the "Shared Spec", not "Unique to Product 1"
        $specs = $result['specification_groups'][0]['specifications'];
        $this->assertCount(1, $specs);
        $this->assertArrayHasKey('Shared Spec', $specs);
        $this->assertArrayNotHasKey('Unique to Product 1', $specs);
    }

    public function test_identifies_differences_correctly(): void
    {
        $specGroup = $this->createMockSpecGroup(1, 'Test Group');

        $product1 = $this->createMockProduct(1, [
            $this->createMockSpec(100, $specGroup, 'Same Spec', 'Same Value'),
            $this->createMockSpec(101, $specGroup, 'Different Spec', 'Value 1')
        ]);

        $product2 = $this->createMockProduct(2, [
            $this->createMockSpec(100, $specGroup, 'Same Spec', 'Same Value'),
            $this->createMockSpec(101, $specGroup, 'Different Spec', 'Value 2')
        ]);

        $this->mockRepo->shouldReceive('find')
            ->with(1, Mockery::any())
            ->once()
            ->andReturn($product1);

        $this->mockRepo->shouldReceive('find')
            ->with(2, Mockery::any())
            ->once()
            ->andReturn($product2);

        $result = $this->service->compareProducts([1, 2]);

        // Should only identify "Different Spec" as a difference
        $this->assertCount(1, $result['differences']);
        $this->assertEquals('Different Spec', $result['differences'][0]['key']);
        $this->assertEquals(['Value 1', 'Value 2'], $result['differences'][0]['values']);
    }

    // Helper methods to create mock objects

    public function test_generates_ai_summary_when_multiple_differences_exist(): void
    {
        $specGroup = $this->createMockSpecGroup(1, 'Test');

        $product1 = $this->createMockProduct(1, [
            $this->createMockSpec(100, $specGroup, 'Spec 1', 'A'),
            $this->createMockSpec(101, $specGroup, 'Spec 2', 'B')
        ]);

        $product2 = $this->createMockProduct(2, [
            $this->createMockSpec(100, $specGroup, 'Spec 1', 'C'),
            $this->createMockSpec(101, $specGroup, 'Spec 2', 'D')
        ]);

        $this->mockRepo->shouldReceive('find')
            ->with(1, Mockery::any())
            ->once()
            ->andReturn($product1);

        $this->mockRepo->shouldReceive('find')
            ->with(2, Mockery::any())
            ->once()
            ->andReturn($product2);

        $result = $this->service->compareProducts([1, 2]);

        $this->assertNotNull($result['ai_summary']);
        $this->assertStringContainsString('Key differences', $result['ai_summary']);
        $this->assertStringContainsString('Spec 1', $result['ai_summary']);
        $this->assertStringContainsString('Spec 2', $result['ai_summary']);
    }

    public function test_no_ai_summary_when_only_one_difference(): void
    {
        $specGroup = $this->createMockSpecGroup(1, 'Test');

        $product1 = $this->createMockProduct(1, [
            $this->createMockSpec(100, $specGroup, 'Same', 'Value'),
            $this->createMockSpec(101, $specGroup, 'Different', 'A')
        ]);

        $product2 = $this->createMockProduct(2, [
            $this->createMockSpec(100, $specGroup, 'Same', 'Value'),
            $this->createMockSpec(101, $specGroup, 'Different', 'B')
        ]);

        $this->mockRepo->shouldReceive('find')
            ->with(1, Mockery::any())
            ->once()
            ->andReturn($product1);

        $this->mockRepo->shouldReceive('find')
            ->with(2, Mockery::any())
            ->once()
            ->andReturn($product2);

        $result = $this->service->compareProducts([1, 2]);

        $this->assertNull($result['ai_summary']);
    }

    public function test_compares_four_products_successfully(): void
    {
        $specGroup = $this->createMockSpecGroup(1, 'Dimensions');

        $products = [];
        for ($i = 1; $i <= 4; $i++) {
            $products[$i] = $this->createMockProduct($i, [
                $this->createMockSpec(100, $specGroup, 'Width', "{$i}00cm")
            ]);

            $this->mockRepo->shouldReceive('find')
                ->with($i, Mockery::any())
                ->once()
                ->andReturn($products[$i]);
        }

        $result = $this->service->compareProducts([1, 2, 3, 4]);

        $this->assertTrue($result['comparable']);
        $this->assertCount(4, $result['products']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRepo = Mockery::mock(ProductRepository::class);
        $this->service = new ProductComparisonService($this->mockRepo);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}