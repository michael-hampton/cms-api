<?php

namespace App\Search;

use App\Search\Configurations\AuthorSearchConfiguration;
use App\Search\Configurations\BrandSearchConfiguration;
use App\Search\Configurations\BriefSearchConfiguration;
use App\Search\Configurations\CategorySearchConfiguration;
use App\Search\Configurations\EmailThemeSearchConfiguration;
use App\Search\Configurations\ImageSearchConfiguration;
use App\Search\Configurations\MerchantSearchConfiguration;
use App\Search\Configurations\OrderSearchConfiguration;
use App\Search\Configurations\PageGridSearchConfiguration;
use App\Search\Configurations\PageSearchConfiguration;
use App\Search\Configurations\PipelineSearchConfiguration;
use App\Search\Configurations\ProductSearchConfiguration;
use App\Search\Configurations\RegionSetSearchConfiguration;
use App\Search\Configurations\TagSearchConfiguration;
use App\Search\Configurations\TerritorySearchConfiguration;
use App\Search\Configurations\UserSearchConfiguration;
use App\Search\Configurations\VariantSearchConfiguration;
use App\Search\Configurations\VoucherSearchConfiguration;

class SearchConfigurationFactory
{
    private static array $configurations = [
        'product' => ProductSearchConfiguration::class,
        'page' => PageSearchConfiguration::class,
        'order' => OrderSearchConfiguration::class,
        'voucher' => VoucherSearchConfiguration::class,
        'category' => CategorySearchConfiguration::class,
        'tag' => TagSearchConfiguration::class,
        'author' => AuthorSearchConfiguration::class,
        'image' => ImageSearchConfiguration::class,
        'user' => UserSearchConfiguration::class,
        'brand' => BrandSearchConfiguration::class,
        'region_set' => RegionSetSearchConfiguration::class,
        'territory' => TerritorySearchConfiguration::class,
        'page_grid' => PageGridSearchConfiguration::class,
        'refund' => EmailThemeSearchConfiguration::class,
        'email-theme' => EmailThemeSearchConfiguration::class,
        'pipeline' => PipelineSearchConfiguration::class,
        'brief' => BriefSearchConfiguration::class,
        'merchant' => MerchantSearchConfiguration::class,
        'variant' => VariantSearchConfiguration::class,
    ];

    public static function create(string $type): SearchConfiguration
    {
        if (!isset(self::$configurations[$type])) {
            throw new \InvalidArgumentException("Unknown configuration type: {$type}");
        }

        $class = self::$configurations[$type];
        return new $class();
    }
}