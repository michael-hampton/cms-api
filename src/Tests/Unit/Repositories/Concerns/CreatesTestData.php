<?php

namespace App\Tests\Unit\Repositories\Concerns;

use App\Controllers\CustomFieldDefinitionController;
use App\Factories\VoucherFactory;
use App\Framework\Tests\Factories\HasFactories;
use App\Framework\Tests\Factories\RelationshipFactory;
use App\Models\Author;
use App\Models\Block;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CustomFieldDefinition;
use App\Models\Member;
use App\Models\Merchant;
use App\Models\Model;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Page;
use App\Models\PageAuthor;
use App\Models\PageCategory;
use App\Models\PageCustomField;
use App\Models\PageGrid;
use App\Models\PageHistory;
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
use App\Models\ProductPriceHistory;
use App\Models\ProductSpecification;
use App\Models\ProductVariant;
use App\Models\ProductVoucher;
use App\Models\RegionSet;
use App\Models\Tag;
use App\Models\Territory;
use App\Models\User;
use App\Models\Voucher;

trait CreatesTestData
{
    use HasFactories;

    /**
     * Create a test page
     */
    protected function createPage(array $overrides = []): Page
    {
        return $this->factory(Page::class)
            ->forSite($this->siteId)
            ->create($overrides);
    }

    protected function createRegionSet(array $overrides = []): RegionSet
    {
        return $this->factory(RegionSet::class)
            ->forSite($this->siteId)
            ->create($overrides);
    }

    /**
     * Create multiple test pages
     */
    protected function createPages(int $count, array $overrides = []): array
    {
        return $this->factory(Page::class)
            ->forSite($this->siteId)
            ->count($count)
            ->create($overrides);
    }

    /**
     * Create a test block
     */
    protected function createBlock(int $pageId, array $overrides = []): Block
    {
        return $this->factory(Block::class)
            ->forPage($pageId)
            ->create($overrides);
    }

    /**
     * Create multiple blocks for a page
     */
    protected function createBlocks(int $pageId, int $count, array $overrides = []): array
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
        return $this->factory(Category::class)
            ->forSite($this->siteId)
            ->create($overrides);
    }

    protected function createTerritory(array $overrides = []): Model
    {
        return $this->factory(Territory::class)
            ->forSite($this->siteId)
            ->create($overrides);
    }

    /**
     * Create a test tag
     */
    protected function createTag(array $overrides = []): Tag
    {
        return $this->factory(Tag::class)
            ->forSite($this->siteId)
            ->create($overrides);
    }

    /**
     * Attach category to page
     */
    protected function attachCategoryToPage(Page $page, Category $category): Model
    {
        return RelationshipFactory::attach($page, $category, PageCategory::class);
    }

    protected function attachCustomFieldToPage(Page $page, CustomFieldDefinition $customFieldDefinition, array $additionalData = []): Model
    {
        return RelationshipFactory::attach($page, $customFieldDefinition, PageCustomField::class, $additionalData);
    }

    protected function createVoucher(array $overrides = []): Model
    {
        return $this->factory(Voucher::class)
            ->forSite($this->siteId)
            ->create($overrides);
    }

    protected function createPageGrid(array $overrides = []): Model
    {
        return $this->factory(PageGrid::class)
            ->forSite($this->siteId)
            ->create($overrides);
    }

    protected function createPageGrids(int $times, array $overrides = []): array
    {
        return $this->factory(PageGrid::class)
            ->forSite($this->siteId)
            ->count($times)
            ->create($overrides);
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
    protected function attachTagToPage(Page $page, Tag $tag): Model
    {
        return RelationshipFactory::attach($page, $tag, PageTag::class);
    }

    protected function attachTerritoryToPage(Page $page, Territory $territory): Model
    {
        return RelationshipFactory::attach($page, $territory, PageTerritory::class, ['site_id' => $this->siteId]);
    }

    protected function attachRegionSetToPage(Page $page, RegionSet $regionSet): Model
    {
        return RelationshipFactory::attach($page, $regionSet, PageRegionSet::class, ['site_id' => $this->siteId]);
    }

    /**
     * Create page metadata
     */
    protected function createPageMetadata(int $pageId, array $overrides = []): PageMetadata
    {
        return $this->factory(PageMetadata::class)
            ->forPage($pageId)
            ->create($overrides);
    }

    /**
     * Create page SEO data
     */
    protected function createPageSeo(int $pageId, array $overrides = []): PageSeo
    {
        return $this->factory(PageSeo::class)
            ->forPage($pageId)
            ->create($overrides);
    }

    /**
     * Create page settings
     */
    protected function createPageSettings(int $pageId, array $overrides = []): PageSettings
    {
        return $this->factory(PageSettings::class)
            ->forPage($pageId)
            ->create($overrides);
    }

    /**
     * Create page social data
     */
    protected function createPageSocial(int $pageId, array $overrides = []): PageSocial
    {
        return $this->factory(PageSocial::class)
            ->forPage($pageId)
            ->create($overrides);
    }

    /**
     * Create an author
     */
    protected function createAuthor(array $overrides = []): Author
    {
        return $this->factory(Author::class)
            ->forSite($this->siteId)
            ->create($overrides);
    }

    /**
     * Attach author to page
     */
    protected function attachAuthorToPage(Page $page, Author $author, array $overrides = []): PageAuthor
    {
        return RelationshipFactory::attach($page, $author, PageAuthor::class, $overrides);
    }

    /**
     * Create custom field definition
     */
    protected function createCustomFieldDefinition(array $overrides = []): CustomFieldDefinition
    {
        return $this->factory(CustomFieldDefinition::class)
            ->forSite($this->siteId)
            ->create($overrides);
    }

    /**
     * Create page custom field
     */
    protected function createPageCustomField(int $pageId, CustomFieldDefinition $definition, array $overrides = []): PageCustomField
    {
        return $this->factory(PageCustomField::class)
            ->forDefinition($definition->id)
            ->forPage($pageId)
            ->create($overrides);
    }

    // Product-related factories

    /**
     * Create a test product
     */
    protected function createProduct(array $overrides = []): Product
    {
        return $this->factory(Product::class)
            ->forSite($this->siteId)
            ->create($overrides);
    }

    /**
     * Create product image
     */
    protected function createProductImage(int $productId, array $overrides = []): ProductImage
    {
        return $this->factory(ProductImage::class)
            ->forProduct($productId)
            ->create($overrides);
    }

    /**
     * Create product merchant
     */
    protected function createProductMerchant(int $productId, array $overrides = []): ProductMerchant
    {
        return $this->factory(ProductMerchant::class)
            ->forProduct($productId)
            ->create($overrides);
    }

    /**
     * Create product variant
     */
    protected function createProductVariant(int $productId, array $overrides = []): ProductVariant
    {
        return $this->factory(ProductVariant::class)
            ->forProduct($productId)
            ->create($overrides);
    }

    /**
     * Create product specification
     */
    protected function createProductSpecification(int $productId, array $overrides = []): ProductSpecification
    {
        return $this->factory(ProductSpecification::class)
            ->forProduct($productId)
            ->create($overrides);
    }

    protected function createBrand(array $overrides = [])
    {
        return $this->factory(Brand::class)
            ->forSite($this->siteId)
            ->create($overrides);
    }

    protected function createPageHistory(int $pageId, ?int $userId = null, array $overrides = []): PageHistory
    {
        $userId = $userId ?? $this->createUser()->id;
        return $this->factory(PageHistory::class)
            ->forSite($this->siteId)
            ->forPage($pageId)
            ->forUser($userId)
            ->create($overrides);
    }

    protected function createUser(array $overrides = []): User
            {
        return $this->factory(User::class)
            ->forSite($this->siteId)
            ->create($overrides);
    }

    protected function createProductPriceHistory(array $overrides = []): Model
    {
        return $this->factory(ProductPriceHistory::class)
            ->create($overrides);
    }

    protected function createOrder(array $overrides = []): Order
    {
        return $this->factory(Order::class)
            ->forSite($this->siteId)
            ->create($overrides);
    }

    protected function createOrderItem(int $orderId, array $overrides = []): OrderItem
    {
        return $this->factory(OrderItem::class)
            ->forOrder($orderId)
            ->create($overrides);
    }

    protected function createMember(array $overrides = []): Member
    {
        return $this->factory(Member::class)
            ->forSite($this->siteId)
            ->create($overrides);
    }

    /**
     * Create multiple test members
     */
    protected function createMembers(int $count, array $overrides = []): array
    {
        return $this->factory(Member::class)
            ->forSite($this->siteId)
            ->count($count)
            ->create($overrides);
    }

    protected function createMerchant(array $overrides = []): Model
    {
        return $this->factory(Merchant::class)
            ->create($overrides);
    }
}