<?php

namespace App\Tests\Unit\Repositories\Concerns;

use App\Models\Author;
use App\Models\Block;
use App\Models\Category;
use App\Models\CustomFieldDefinition;
use App\Models\Model;
use App\Models\Page;
use App\Models\PageAuthor;
use App\Models\PageCategory;
use App\Models\PageCustomField;
use App\Models\PageMetadata;
use App\Models\PageRegionSet;
use App\Models\PageSeo;
use App\Models\PageSettings;
use App\Models\PageSocial;
use App\Models\PageTag;
use App\Models\PageTerritory;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductMerchant;
use App\Models\ProductSpecification;
use App\Models\ProductVariant;
use App\Models\ProductVoucher;
use App\Models\RegionSet;
use App\Models\Tag;
use App\Models\Territory;
use App\Models\Voucher;

trait CreatesTestData
{
    /**
     * Create a test page
     */
    protected function createPage(array $overrides = []): Page
    {
        return Page::create(array_merge([
            'site_id' => $this->siteId,
            'slug' => 'test-page-' . uniqid(),
            'title' => 'Test Page',
            'subtitle' => 'Test content',
            'status' => 'published',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    protected function createRegionSet(array $overrides = []): RegionSet
    {
        return RegionSet::create(array_merge([
            'site_id' => $this->siteId,
            'name' => 'Test Region Set',
            'slug' => 'test-region-set-' . uniqid(),
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    /**
     * Create multiple test pages
     */
    protected function createPages(int $count, array $overrides = []): array
    {
        $pages = [];
        for ($i = 0; $i < $count; $i++) {
            $pages[] = $this->createPage(array_merge($overrides, [
                'title' => 'Test Page ' . ($i + 1),
                'slug' => 'test-page-' . $i . '-' . uniqid(),
            ]));
        }
        return $pages;
    }

    /**
     * Create a test block
     */
    protected function createBlock(int $pageId, array $overrides = []): Block
    {
        return Block::create(array_merge([
            'page_id' => $pageId,
            'type' => 'text',
            'data' => json_encode(['content' => 'Test content']),
            'order' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    /**
     * Create multiple blocks for a page
     */
    protected function createBlocks(int $pageId, int $count, array $overrides = []): arraycreatete
    {
        $blocks = [];
        for ($i = 0; $i < $count; $i++) {
            $blocks[] = $this->createBlock($pageId, array_merge($overrides, [
                'type' => $overrides['type'] ?? 'text',
                'order' => $i,
            ]));
        }
        return $blocks;
    }

    /**
     * Create a test category
     */
    protected function createCategory(array $overrides = []): Category
    {
        return Category::create(array_merge([
            'site_id' => $this->siteId,
            'slug' => 'test-category-' . uniqid(),
            'name' => 'Test Category',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    protected function createTerritory(array $overrides = []): Model
    {
        return Territory::create(array_merge([
            'site_id' => $this->siteId,
            'code' => 'TEST-' . uniqid(),
            'region_set_id' => null,
            'name' => 'Test Territory',
            'slug' => 'test-territory',
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    /**
     * Create a test tag
     */
    protected function createTag(array $overrides = []): Tag
    {
        return Tag::create(array_merge([
            'site_id' => $this->siteId,
            'slug' => 'test-tag-' . uniqid(),
            'name' => 'Test Tag',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    /**
     * Attach category to page
     */
    protected function attachCategoryToPage(Page $page, Category $category): void
    {
        PageCategory::create([
            'page_id' => $page->id,
            'category_id' => $category->id,
            'site_id' => $this->siteId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    protected function createVoucher(array $overrides = []): Model
    {
        return Voucher::create(array_merge([
            'site_id' => $this->siteId,
            'value' => 99.99,
            'code' => 'TEST-' . uniqid(),
            'name' => 'Test Voucher',
            'usage_count' => 0,
            'description' => 'Test description',
            'minimum_order_value' => 100,
            'type' => 'fixed',
            'discount' => 0,
            'is_active' => true,
        ], $overrides));
    }

    protected function attachVoucherToProduct(Voucher $voucher, Product $product): void
    {
        ProductVoucher::create([
            'product_id' => $product->id,
            'voucher_id' => $voucher->id,
        ]);
    }

    /**
     * Attach tag to page
     */
    protected function attachTagToPage(Page $page, Tag $tag): void
    {
        PageTag::create([
            'page_id' => $page->id,
            'tag_id' => $tag->id,
            'site_id' => $this->siteId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    protected function attachTerritoryToPage(Page $page, Territory $territory): Model
    {
        return PageTerritory::create([
            'page_id' => $page->id,
            'territory_id' => $territory->id,
            'site_id' => $this->siteId
        ]);
    }

    protected function attachRegionSetToPage(Page $page, RegionSet $regionSet): Model
    {
        return PageRegionSet::create([
            'page_id' => $page->id,
            'region_set_id' => $regionSet->id ?? null,
            'site_id' => $this->siteId
        ]);
    }

    /**
     * Create page metadata
     */
    protected function createPageMetadata(int $pageId, array $overrides = []): PageMetadata
    {
        return PageMetadata::create(array_merge([
            'page_id' => $pageId,
            'featured' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    /**
     * Create page SEO data
     */
    protected function createPageSeo(int $pageId, array $overrides = []): PageSeo
    {
        return PageSeo::create(array_merge([
            'page_id' => $pageId,
            'meta_title' => 'Test SEO Title',
            'meta_description' => 'Test SEO Description',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    /**
     * Create page settings
     */
    protected function createPageSettings(int $pageId, array $overrides = []): PageSettings
    {
        return PageSettings::create(array_merge([
            'page_id' => $pageId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    /**
     * Create page social data
     */
    protected function createPageSocial(int $pageId, array $overrides = []): PageSocial
    {
        return PageSocial::create(array_merge([
            'page_id' => $pageId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    /**
     * Create an author
     */
    protected function createAuthor(array $overrides = []): Author
    {
        return Author::create(array_merge([
            'site_id' => $this->siteId,
            'slug' => 'test-author-' . uniqid(),
            'name' => 'Test Author',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    /**
     * Attach author to page
     */
    protected function attachAuthorToPage(Page $page, Author $author, array $overrides = []): PageAuthor
    {
        return PageAuthor::create(array_merge([
            'page_id' => $page->id,
            'author_id' => $author->id,
            'role' => 'contributor',
            'sort_order' => 0,
            'site_id' => $this->siteId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    /**
     * Create custom field definition
     */
    protected function createCustomFieldDefinition(array $overrides = []): CustomFieldDefinition
    {
        return CustomFieldDefinition::create(array_merge([
            'site_id' => $this->siteId,
            'key' => 'test_field_' . uniqid(),
            'name' => 'Test Field',
            'type' => 'text',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    /**
     * Create page custom field
     */
    protected function createPageCustomField(int $pageId, CustomFieldDefinition $definition, array $overrides = []): PageCustomField
    {
        return PageCustomField::create(array_merge([
            'page_id' => $pageId,
            'custom_field_definition_id' => $definition->id,
            'site_id' => $this->siteId,
            'value' => 'Test value',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    // Product-related factories

    /**
     * Create a test product
     */
    protected function createProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'site_id' => $this->siteId,
            'slug' => 'test-product-' . uniqid(),
            'name' => 'Test Product',
            'description' => 'Test description',
            'price' => 99.99,
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    /**
     * Create product image
     */
    protected function createProductImage(int $productId, array $overrides = []): ProductImage
    {
        return ProductImage::create(array_merge([
            'product_id' => $productId,
            'url' => 'https://example.com/image.jpg',
            'alt' => 'Test image',
            'is_primary' => false,
            'sort_order' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    /**
     * Create product merchant
     */
    protected function createProductMerchant(int $productId, array $overrides = []): ProductMerchant
    {
        return ProductMerchant::create(array_merge([
            'product_id' => $productId,
            'name' => 'Test Merchant',
            'url' => 'https://example.com',
            'price' => 99.99,
            'is_available' => true,
            'last_price_check' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    /**
     * Create product variant
     */
    protected function createProductVariant(int $productId, array $overrides = []): ProductVariant
    {
        return ProductVariant::create(array_merge([
            'product_id' => $productId,
            'sku' => 'SKU-' . uniqid(),
            'attributes' => json_encode(['color' => 'red', 'size' => 'M']),
            'price_modifier' => 0,
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    /**
     * Create product specification
     */
    protected function createProductSpecification(int $productId, array $overrides = []): ProductSpecification
    {
        return ProductSpecification::create(array_merge([
            'product_id' => $productId,
            'category' => 'General',
            'key' => 'weight',
            'value' => '1kg',
            'sort_order' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }
}