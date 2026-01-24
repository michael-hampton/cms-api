<?php

namespace App\Tests\Unit\Search;

use App\Search\Configurations\AuthorSearchConfiguration;
use App\Search\Configurations\BrandSearchConfiguration;
use App\Search\Configurations\CategorySearchConfiguration;
use App\Search\Configurations\ImageSearchConfiguration;
use App\Search\Configurations\OrderSearchConfiguration;
use App\Search\Configurations\PageGridSearchConfiguration;
use App\Search\Configurations\PageSearchConfiguration;
use App\Search\Configurations\ProductSearchConfiguration;
use App\Search\Configurations\RegionSetSearchConfiguration;
use App\Search\Configurations\TagSearchConfiguration;
use App\Search\Configurations\TerritorySearchConfiguration;
use App\Search\Configurations\UserSearchConfiguration;
use App\Search\Configurations\VoucherSearchConfiguration;
use App\Search\SearchConfigurationFactory;
use PHPUnit\Framework\TestCase;

class SearchConfigurationFactoryTest extends TestCase
{
    public function testFactoryCreatesProductConfiguration()
    {
        $config = SearchConfigurationFactory::create('product');
        $this->assertInstanceOf(ProductSearchConfiguration::class, $config);
    }

    public function testFactoryCreatesPageConfiguration()
    {
        $config = SearchConfigurationFactory::create('page');
        $this->assertInstanceOf(PageSearchConfiguration::class, $config);
    }

    public function testFactoryCreatesOrderConfiguration()
    {
        $config = SearchConfigurationFactory::create('order');
        $this->assertInstanceOf(OrderSearchConfiguration::class, $config);
    }

    public function testFactoryCreatesVoucherConfiguration()
    {
        $config = SearchConfigurationFactory::create('voucher');
        $this->assertInstanceOf(VoucherSearchConfiguration::class, $config);
    }

    public function testFactoryCreatesCategoryConfiguration()
    {
        $config = SearchConfigurationFactory::create('category');
        $this->assertInstanceOf(CategorySearchConfiguration::class, $config);
    }

    public function testFactoryCreatesTagConfiguration()
    {
        $config = SearchConfigurationFactory::create('tag');
        $this->assertInstanceOf(TagSearchConfiguration::class, $config);
    }

    public function testFactoryCreatesAuthorConfiguration()
    {
        $config = SearchConfigurationFactory::create('author');
        $this->assertInstanceOf(AuthorSearchConfiguration::class, $config);
    }

    public function testFactoryCreatesImageConfiguration()
    {
        $config = SearchConfigurationFactory::create('image');
        $this->assertInstanceOf(ImageSearchConfiguration::class, $config);
    }

    public function testFactoryCreatesUserConfiguration()
    {
        $config = SearchConfigurationFactory::create('user');
        $this->assertInstanceOf(UserSearchConfiguration::class, $config);
    }

    public function testFactoryCreatesBrandConfiguration()
    {
        $config = SearchConfigurationFactory::create('brand');
        $this->assertInstanceOf(BrandSearchConfiguration::class, $config);
    }

    public function testFactoryCreatesRegionSetConfiguration()
    {
        $config = SearchConfigurationFactory::create('region_set');
        $this->assertInstanceOf(RegionSetSearchConfiguration::class, $config);
    }

    public function testFactoryCreatesTerritoryConfiguration()
    {
        $config = SearchConfigurationFactory::create('territory');
        $this->assertInstanceOf(TerritorySearchConfiguration::class, $config);
    }

    public function testFactoryCreatesPageGridConfiguration()
    {
        $config = SearchConfigurationFactory::create('page_grid');
        $this->assertInstanceOf(PageGridSearchConfiguration::class, $config);
    }

    public function testFactoryThrowsExceptionForUnknownType()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown configuration type: invalid_type');

        SearchConfigurationFactory::create('invalid_type');
    }

    public function testFactoryThrowsExceptionForEmptyType()
    {
        $this->expectException(\InvalidArgumentException::class);

        SearchConfigurationFactory::create('');
    }

    public function testFactoryCreatesNewInstanceEachTime()
    {
        $config1 = SearchConfigurationFactory::create('product');
        $config2 = SearchConfigurationFactory::create('product');

        $this->assertNotSame($config1, $config2);
    }

    public function testAllRegisteredTypesAreValid()
    {
        $types = [
            'product',
            'page',
            'order',
            'voucher',
            'category',
            'tag',
            'author',
            'image',
            'user',
            'brand',
            'region_set',
            'territory',
            'page_grid',
        ];

        foreach ($types as $type) {
            $config = SearchConfigurationFactory::create($type);
            $this->assertNotNull($config, "Failed to create configuration for type: {$type}");
        }
    }
}