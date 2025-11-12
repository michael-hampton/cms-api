<?php

namespace App\Tests\Unit\Models;

use App\Models\Page;
use App\Models\PageProduct;
use App\Models\Product;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class PageProductTest extends RepositoryTestCase
{
    use CreatesTestData;

    public function test_page_product_belongs_to_page(): void
    {
        $page = $this->createPage();
        $product = $this->createProduct();

        $pageProduct = PageProduct::create([
            'page_id' => $page->id,
            'product_id' => $product->id,
            'sort_order' => 0,
            'site_id' => $this->siteId
        ]);

        $this->assertInstanceOf(Page::class, $pageProduct->page);
        $this->assertEquals($page->id, $pageProduct->page->id);
    }

    public function test_page_product_belongs_to_product(): void
    {
        $page = $this->createPage();
        $product = $this->createProduct();

        $pageProduct = PageProduct::create([
            'page_id' => $page->id,
            'product_id' => $product->id,
            'sort_order' => 0,
            'site_id' => $this->siteId
        ]);

        $this->assertInstanceOf(Product::class, $pageProduct->product);
        $this->assertEquals($product->id, $pageProduct->product->id);
    }

    public function test_page_product_uses_correct_table(): void
    {
        $pageProduct = new PageProduct();
        $this->assertEquals('page_products', $pageProduct->getTable());
    }
}