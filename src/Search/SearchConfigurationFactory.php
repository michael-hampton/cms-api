<?php

namespace App\Search;

use App\Search\Configurations\AuthorSearchConfiguration;
use App\Search\Configurations\BrandSearchConfiguration;
use App\Search\Configurations\BriefSearchConfiguration;
use App\Search\Configurations\CampaignSearchConfiguration;
use App\Search\Configurations\CategorySearchConfiguration;
use App\Search\Configurations\EmailTemplateSearchConfiguration;
use App\Search\Configurations\EmailThemeSearchConfiguration;
use App\Search\Configurations\GiftPromotionSearchConfiguration;
use App\Search\Configurations\ImageSearchConfiguration;
use App\Search\Configurations\IssueDeliverySearchConfiguration;
use App\Search\Configurations\MerchantContactSearchConfiguration;
use App\Search\Configurations\MerchantProductFeedSearchConfiguration;
use App\Search\Configurations\MerchantSearchConfiguration;
use App\Search\Configurations\NewsletterIssueSearchConfiguration;
use App\Search\Configurations\NewsletterLayoutSearchConfiguration;
use App\Search\Configurations\NewsletterSearchConfiguration;
use App\Search\Configurations\OrderSearchConfiguration;
use App\Search\Configurations\PageGridSearchConfiguration;
use App\Search\Configurations\PageSearchConfiguration;
use App\Search\Configurations\PipelineSearchConfiguration;
use App\Search\Configurations\PrintFulfilmentSearchConfiguration;
use App\Search\Configurations\ProductOfferBundleSearchConfiguration;
use App\Search\Configurations\ProductOfferSearchConfiguration;
use App\Search\Configurations\ProductSearchConfiguration;
use App\Search\Configurations\RegionSetSearchConfiguration;
use App\Search\Configurations\RewardDefinitionSearchConfiguration;
use App\Search\Configurations\RewardSearchConfiguration;
use App\Search\Configurations\SubscriptionPaymentSearchConfiguration;
use App\Search\Configurations\SubscriptionPlanPricingSearchConfiguration;
use App\Search\Configurations\SubscriptionPlanSearchConfiguration;
use App\Search\Configurations\SubscriptionVoucherSearchConfiguration;
use App\Search\Configurations\TagSearchConfiguration;
use App\Search\Configurations\TerritorySearchConfiguration;
use App\Search\Configurations\UserSearchConfiguration;
use App\Search\Configurations\VariantSearchConfiguration;
use App\Search\Configurations\VoucherSearchConfiguration;
use App\Search\Configurations\WorkflowRunSearchConfiguration;

class SearchConfigurationFactory
{
    private static array $configurations = [
        'campaign' => CampaignSearchConfiguration::class,
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
        'email-template' => EmailTemplateSearchConfiguration::class,
        'pipeline' => PipelineSearchConfiguration::class,
        'brief' => BriefSearchConfiguration::class,
        'merchant' => MerchantSearchConfiguration::class,
        'merchant_contact' => MerchantContactSearchConfiguration::class,
        'merchant_product_feed' => MerchantProductFeedSearchConfiguration::class,
        'variant' => VariantSearchConfiguration::class,
        'reward_definition' => RewardDefinitionSearchConfiguration::class,
        'reward' => RewardSearchConfiguration::class,
        'product_offer' => ProductOfferSearchConfiguration::class,
        'newsletters' => NewsletterSearchConfiguration::class,
        'newsletter_issue' => NewsletterIssueSearchConfiguration::class,
        'product_offer_bundle' => ProductOfferBundleSearchConfiguration::class,
        'gift_promotion' => GiftPromotionSearchConfiguration::class,
        'issue_delivery' => IssueDeliverySearchConfiguration::class,
        'subscription_plan' => SubscriptionPlanSearchConfiguration::class,
        'subscription_plan_pricing' => SubscriptionPlanPricingSearchConfiguration::class,
        'payment' => SubscriptionPaymentSearchConfiguration::class,
        'newsletter_layout' => NewsletterLayoutSearchConfiguration::class,
        'print-fulfilment' => PrintFulfilmentSearchConfiguration::class,
        'workflow-run' => WorkflowRunSearchConfiguration::class,
        'subscription_voucher' => SubscriptionVoucherSearchConfiguration::class,
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
