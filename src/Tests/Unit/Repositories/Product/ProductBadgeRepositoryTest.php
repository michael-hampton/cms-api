<?php

namespace App\Tests\Unit\Repositories\Product;

use App\Models\ProductBadge;
use App\Repositories\Product\ProductBadgeRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class ProductBadgeRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private ProductBadgeRepository $repository;

    public function testGetActiveProductBadges()
    {
        $product = $this->createProduct();

        ProductBadge::create([
            'product_id' => $product->id,
            'badge_type' => 'bestseller',
            'label' => 'Bestseller',
            'color' => '#ff0000',
            'is_active' => true
        ]);

        ProductBadge::create([
            'product_id' => $product->id,
            'badge_type' => 'new',
            'label' => 'New',
            'color' => '#00ff00',
            'is_active' => true
        ]);

        ProductBadge::create([
            'product_id' => $product->id,
            'badge_type' => 'inactive',
            'label' => 'Inactive',
            'color' => '#0000ff',
            'is_active' => false
        ]);

        $badges = $this->repository->getActiveProductBadges($product->id);

        $this->assertCount(2, $badges);
        foreach ($badges as $badge) {
            $this->assertTrue($badge->is_active);
        }
    }

    public function testGetActiveProductBadgesRespectsDates()
    {
        $product = $this->createProduct();

        ProductBadge::create([
            'product_id' => $product->id,
            'badge_type' => 'current',
            'label' => 'Current',
            'color' => '#ff0000',
            'valid_from' => now_datetime()->subDays(5),
            'valid_until' => now_datetime()->addDays(5),
            'is_active' => true
        ]);

        ProductBadge::create([
            'product_id' => $product->id,
            'badge_type' => 'expired',
            'label' => 'Expired',
            'color' => '#00ff00',
            'valid_until' => now_datetime()->subDays(5),
            'is_active' => true
        ]);

        ProductBadge::create([
            'product_id' => $product->id,
            'badge_type' => 'future',
            'label' => 'Future',
            'color' => '#0000ff',
            'valid_from' => now_datetime()->addDays(5),
            'is_active' => true
        ]);

        $badges = $this->repository->getActiveProductBadges($product->id);

        $this->assertCount(1, $badges);
        $this->assertEquals('current', $badges->first()->badge_type);
    }

    public function testGetActiveProductBadgesOrderedBySortOrder()
    {
        $product = $this->createProduct();

        ProductBadge::create([
            'product_id' => $product->id,
            'badge_type' => 'third',
            'label' => 'Third',
            'color' => '#ff0000',
            'sort_order' => 3,
            'is_active' => true
        ]);

        ProductBadge::create([
            'product_id' => $product->id,
            'badge_type' => 'first',
            'label' => 'First',
            'color' => '#00ff00',
            'sort_order' => 1,
            'is_active' => true
        ]);

        ProductBadge::create([
            'product_id' => $product->id,
            'badge_type' => 'second',
            'label' => 'Second',
            'color' => '#0000ff',
            'sort_order' => 2,
            'is_active' => true
        ]);

        $badges = $this->repository->getActiveProductBadges($product->id);

        $this->assertEquals('first', $badges->first()->badge_type);
        $this->assertEquals('second', $badges->get(1)->badge_type);
        $this->assertEquals('third', $badges->get(2)->badge_type);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ProductBadgeRepository();
    }
}