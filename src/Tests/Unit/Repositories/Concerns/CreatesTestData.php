<?php

namespace App\Tests\Unit\Repositories\Concerns;

use App\Enums\Gifts\GiftType;
use App\Framework\Tests\Factories\HasFactories;
use App\Framework\Tests\Factories\RelationshipFactory;
use App\Models\Address;
use App\Models\Author;
use App\Models\Badge;
use App\Models\Block;
use App\Models\Brand;
use App\Models\Brief;
use App\Models\BriefAttachment;
use App\Models\BriefComment;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Comment;
use App\Models\ConsentAuditLog;
use App\Models\ConsentType;
use App\Models\ConsentWithdrawalRequest;
use App\Models\CustomFieldDefinition;
use App\Models\EmailTheme;
use App\Models\GiftedArticle;
use App\Models\GiftPromotion;
use App\Models\Image;
use App\Models\IssueDelivery;
use App\Models\Member;
use App\Models\MemberActivity;
use App\Models\MemberBadge;
use App\Models\MemberConsent;
use App\Models\MemberGiftAllowance;
use App\Models\MemberPoint;
use App\Models\MemberReward;
use App\Models\Merchant;
use App\Models\MerchantContact;
use App\Models\MerchantProductFeed;
use App\Models\MerchantUrl;
use App\Models\Model;
use App\Models\Newsletter;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Page;
use App\Models\PageAuthor;
use App\Models\PageCategory;
use App\Models\PageCustomField;
use App\Models\PageGrid;
use App\Models\PageHistory;
use App\Models\PageLike;
use App\Models\PageMetadata;
use App\Models\PageProduct;
use App\Models\PageRegionSet;
use App\Models\PageSeo;
use App\Models\PageSettings;
use App\Models\PageSocial;
use App\Models\PageTag;
use App\Models\PageTerritory;
use App\Models\PageView;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductMerchant;
use App\Models\ProductOffer;
use App\Models\ProductPriceHistory;
use App\Models\ProductSpecification;
use App\Models\ProductVariant;
use App\Models\ProductView;
use App\Models\ProductVoucher;
use App\Models\RegionSet;
use App\Models\RewardDefinition;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
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

    protected function createAddress(array $overrides = []): Model
    {
        return $this->factory(Address::class)
            ->forSite($this->siteId)
            ->create($overrides);
    }

    protected function attachProductToPage(Page $page, Product $product): void
    {
        PageProduct::create([
            'page_id' => $page->id,
            'product_id' => $product->id,
            'sort_order' => 0,
            'site_id' => $this->siteId
        ]);
    }

    protected function createBadge(array $attributes = []): Badge
    {
        return Badge::create(array_merge([
            'site_id' => $this->siteId,
            'name' => 'Test Badge ' . uniqid(),
            'slug' => 'test-badge-' . uniqid(),
            'description' => 'Test badge description',
            'icon' => 'trophy',
            'points' => 100,
            'criteria' => [['type' => 'comments_count', 'operator' => '>=', 'value' => 5]],
            'is_active' => true,
            'sort_order' => 0,
            'category' => 'test'
        ], $attributes));
    }

    protected function createMemberBadge(array $attributes = []): MemberBadge
    {
        $member = $attributes['member_id'] ?? $this->createMember()->id;
        $badge = $attributes['badge_id'] ?? $this->createBadge()->id;

        return MemberBadge::create(array_merge([
            'member_id' => $member,
            'badge_id' => $badge,
            'earned_at' => now(),
            'criteria_met' => [],
            'is_visible' => true
        ], $attributes));
    }

    protected function createMemberActivity(array $attributes = []): MemberActivity
    {
        $member = $attributes['member_id'] ?? $this->createMember()->id;

        return MemberActivity::create(array_merge([
            'member_id' => $member,
            'site_id' => $this->siteId,
            'activity_type' => 'test_activity',
            'entity_type' => null,
            'entity_id' => null,
            'metadata' => [],
            'points' => 0,
            'activity_date' => now()
        ], $attributes));
    }

    protected function createMemberPoint(array $attributes = []): MemberPoint
    {
        $member = $attributes['member_id'] ?? $this->createMember()->id;

        return MemberPoint::create(array_merge([
            'member_id' => $member,
            'points' => 10,
            'reason' => 'Test points',
            'reference_type' => null,
            'reference_id' => null,
            'awarded_at' => now()
        ], $attributes));
    }

    protected function createComment(array $attributes = []): Comment
    {
        return Comment::create(array_merge([
            'member_id' => $this->createMember()->id,
            'page_id' => $this->createPage()->id,
            'content' => 'Test comment',
            'status' => 'approved',
            'created_at' => now()
        ], $attributes));
    }

    protected function createPageView(array $attributes = []): PageView
    {
        return PageView::create(array_merge([
            'member_id' => $this->createMember()->id,
            'page_id' => $this->createPage()->id,
            'viewed_at' => now(),
            'site_id' => $this->siteId,
        ], $attributes));
    }

    protected function createPageLike(array $attributes = []): PageLike
    {
        return PageLike::create(array_merge([
            'member_id' => $this->createMember()->id,
            'page_id' => $this->createPage()->id,
            'created_at' => now(),
            'site_id' => $this->siteId,
            'liked_at' => now(),
        ], $attributes));
    }

    protected function createConsentType(array $attributes = []): ConsentType
    {
        return ConsentType::create(array_merge([
            'code' => 'test_consent_' . uniqid(),
            'name' => 'Test Consent',
            'description' => 'Test description',
            'category' => 'marketing',
            'required' => false,
            'retention_days' => 365,
            'data_purposes' => ['Test purpose'],
            'is_active' => true
        ], $attributes));
    }

    protected function createMemberConsent(array $attributes = []): MemberConsent
    {
        $member = $attributes['member'] ?? $this->createMember();
        $consentType = $attributes['consent_type'] ?? $this->createConsentType();

        unset($attributes['member'], $attributes['consent_type']);

        return MemberConsent::create(array_merge([
            'member_id' => $member->id,
            'consent_type_id' => $consentType->id,
            'is_granted' => true,
            'channel' => 'web',
            'granted_at' => now_datetime()
        ], $attributes));
    }

    protected function createConsentAuditLog(array $attributes = []): ConsentAuditLog
    {
        $member = $attributes['member'] ?? $this->createMember();
        $consentType = $attributes['consent_type'] ?? $this->createConsentType();

        unset($attributes['member'], $attributes['consent_type']);

        return ConsentAuditLog::create(array_merge([
            'member_id' => $member->id,
            'consent_type_id' => $consentType->id,
            'action' => 'granted',
            'new_state' => true,
            'source' => 'web',
            'created_at' => now_datetime()
        ], $attributes));
    }

    protected function createConsentWithdrawalRequest(array $attributes = []): ConsentWithdrawalRequest
    {
        $member = $attributes['member'] ?? $this->createMember();

        unset($attributes['member']);

        return ConsentWithdrawalRequest::create(array_merge([
            'member_id' => $member->id,
            'type' => 'all_marketing',
            'status' => 'pending',
            'requested_at' => now_datetime()
        ], $attributes));
    }

    protected function createEmailTheme(array $attributes = []): EmailTheme
    {
        return EmailTheme::create(array_merge([
            'name' => 'Test Theme ' . uniqid(),
            'slug' => 'test-theme-' . uniqid(),
            'description' => 'Test theme description',
            'is_active' => true,
            'is_default' => false,
            'site_id' => $this->siteId ?? 1
        ], $attributes));
    }

    protected function createNewsletter(array $attributes = []): Newsletter
    {
        return Newsletter::create(array_merge([
            'title' => 'Test Newsletter',
            'content' => 'Test content',
            'interval' => 'weekly',
            'active' => true,
            'site_id' => $this->siteId,
            'last_sent_at' => null
        ], $attributes));
    }

    protected function createSubscription(array $attributes = [])
    {
        return Subscription::create(array_merge([
            'site_id' => $this->siteId,
            'plan_name' => 'Test Plan',
            'member_id' => $this->createMember()->id,
            'start_date' => now(),
        ], $attributes));
    }

    protected function createIssueDelivery(array $attributes = [])
    {
        $plan = $attributes['subscription_plan'] ?? $this->createSubscriptionPlan();
        return IssueDelivery::create(array_merge([
            'subscription_plan_id' => $plan->id,
            'on_sale_date' => now_datetime()->addDays(7),
        ], $attributes));
    }

    protected function createSubscriptionPlan(array $attributes = []): Model
    {
        return SubscriptionPlan::create(array_merge([
            'site_id' => $this->siteId,
            'name' => 'Test Plan ' . uniqid(),
            'slug' => 'test-plan-' . uniqid(),
            'description' => 'A test subscription plan',
            'price' => 29.99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'features' => ['Feature 1', 'Feature 2'],
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ], $attributes));
    }

    protected function createSubscriber(array $attributes = []): Subscriber
    {
        $defaults = [
            'email' => 'subscriber' . uniqid() . '@example.com',
            'confirmed' => true,
            'confirmation_token' => bin2hex(random_bytes(16)),
            'unsubscribe_token' => bin2hex(random_bytes(16)),
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ];

        return Subscriber::create(array_merge($defaults, $attributes));
    }

    protected function createCampaign(array $attributes = []): Model
    {
        return Campaign::create(array_merge([
            'name' => 'Test Campaign ' . uniqid(),
            'slug' => 'test-campaign-' . uniqid(),
            'description' => 'Test campaign description',
            'is_active' => true,
            'is_default' => false,
            'site_id' => $this->siteId ?? 1,
            'start_date' => now(),
            'end_date' => now()
        ]));

    }

    protected function createGiftedArticle(array $attributes = []): Model
    {
        return GiftedArticle::create(array_merge([
            'page_id' => $this->createPage()->id,
            'gifted_by_member_id' => $this->createMember()->id,
            'site_id' => $this->siteId,
            'recipient_email' => 'recipient@example.com',
            'gift_token' => bin2hex(random_bytes(32)),
            'gifted_at' => now_datetime()->format('Y-m-d H:i:s'),
            'status' => 'pending'
        ], $attributes));
    }

    protected function createMemberReward(array $attributes = []): Model
    {
        return MemberReward::create(array_merge([
            'member_id' => $this->createMember()->id,
            'reward_definition_id' => $this->createRewardDefinition()->id,
            'site_id' => $this->siteId,
            'status' => 'pending',
            'earned_at' => now_datetime()->format('Y-m-d H:i:s'),
        ], $attributes));
    }

    protected function createMemberGiftAllowance(array $attributes = []): Model
    {
        return MemberGiftAllowance::create(array_merge([
            'member_id' => $this->createMember()->id,
            'site_id' => $this->siteId,
            'annual_gift_limit' => 10,
            'gifts_used_this_year' => 0,
            'year_start_date' => now_datetime()->format('Y-m-d')
        ], $attributes));
    }

    protected function createRewardDefinition(array $attributes = []): Model
    {
        return RewardDefinition::create(array_merge([
            'site_id' => $this->siteId,
            'name' => 'Test Reward',
            'description' => 'Test reward description',
            'reward_type' => 'points',
            'reward_config' => ['points' => 100],
            'criteria_type' => 'manual',
            'criteria_config' => [],
            'max_claims_per_member' => 1,
            'status' => 'active',
            'slug' => 'test-reward',
            'criteria' => []
        ], $attributes));
    }

    protected function createBrief(array $attributes = []): Model
    {
        $defaults = [
            'title' => 'Test Brief',
            'description' => 'Test description',
            'status' => 'ready',
            'site_id' => $this->siteId ?? 1,
            'owner_id' => $this->createUser()->id,
        ];

        return Brief::create(array_merge($defaults, $attributes));
    }

    protected function createBriefAttachment(int $briefId, array $attributes = []): Model
    {
        $defaults = [
            'brief_id' => $briefId,
            'type' => 'image',
            'file_url' => 'http://example.com/image.jpg',
            'file_name' => 'image.jpg',
            'sort_order' => 0
        ];

        return BriefAttachment::create(array_merge($defaults, $attributes));
    }

    protected function createBriefComment(int $briefId, int $userId, array $attributes = []): Model
    {
        $defaults = [
            'brief_id' => $briefId,
            'user_id' => $userId,
            'content' => 'Test comment'
        ];

        return BriefComment::create(array_merge($defaults, $attributes));
    }

    protected function createImage(array $attributes = []): Model
    {
        $defaults = [
            'filename' => 'test.jpg',
            'original_name' => 'test.jpg',
            'url' => '/images/test.jpg',
            'file_path' => '/images/test.jpg',
            'file_size' => 1024,
            'mime_type' => 'image/jpeg',
            'site_id' => $this->siteId ?? 1
        ];

        return Image::create(array_merge($defaults, $attributes));
    }

    protected function createMerchantFeed(array $attributes = []): Model
    {
        if (empty($attributes['merchant_id'])) {
            $attributes['merchant_id'] = $this->createMerchant()->id;
        }

        return MerchantProductFeed::create(array_merge([
            'feed_url' => 'test',
            'feed_type' => 'test',
        ], $attributes));
    }

    protected function createMerchantUrl(array $attributes = []): Model
    {
        if (empty($attributes['merchant_id'])) {
            $attributes['merchant_id'] = $this->createMerchant()->id;
        }

        return MerchantUrl::create(array_merge([
            'url' => 'test',

        ], $attributes));

    }

    protected function createMerchantContact(array $attributes = []): Model
    {
        if (empty($attributes['merchant_id'])) {
            $attributes['merchant_id'] = $this->createMerchant()->id;
        }

        return MerchantContact::create(array_merge([
            'merchant_id' => $attributes['merchant_id'],
            'name' => 'Test Merchant',
            'email' => 'test.merchant@example.com',
            'phone' => '1234567890',
            'role' => 'merchant'
        ], $attributes));

    }

    protected function createProductOffer(int $productId, array $attributes = []): Model
    {
        return ProductOffer::create(array_merge([
            'product_id' => $productId,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
            'original_price' => 0
        ], $attributes));
    }

    protected function createProductView(array $attributes = []): Model
    {
        return ProductView::create(array_merge([
            'product_id' => $this->createProduct()->id,
            'site_id' => $this->siteId,
            'user_id' => $this->createMember()->id,
            'session_id' => 'test',
            'ip_address' => '127.0.0.1',
            'viewed_at' => date('Y-m-d H:i:s'),
        ], $attributes));
    }

    public function createIssueSchedule()
    {
        return IssueDelivery::create([
            'site_id' => $this->siteId,
            'issue_number' => 1,
            'issue_title' => 'Test Issue',
            'on_sale_date' => now(),
            'estimated_delivery_date' => now(),
            'status' => 'active'
        ]);
    }

    protected function createMemberWithSubscription(array $memberData = [], array $planData = [], array $subscriptionData = []): Member
    {
        $member = $this->createMember($memberData);

        $plan = \App\Models\SubscriptionPlan::create(array_merge([
            'name' => 'Test Plan',
            'type' => 'paid',
            'tier' => 'gold',
            'price' => 9.99,
            'site_id' => $this->siteId,
            'slug' => 'test-plan'
        ], $planData));

        $subscription = \App\Models\Subscription::create(array_merge([
            'member_id' => $member->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'site_id' => $this->siteId,
            'plan_name' => 'Test Plan',
            'start_date' => now(),
        ], $subscriptionData));

        return $member->fresh();
    }

    private int $creationScheduleCounter = 0;
    private int $sendScheduleCounter = 0;

    protected function createNewsletterCreationSchedule(array $overrides = []): \App\Models\NewsletterCreationSchedule
    {
        $this->creationScheduleCounter++;

        $defaults = [
            'newsletter_id' => $overrides['newsletter_id'] ?? $this->createNewsletter()->id,
            'site_id' => $this->siteId,
            'frequency' => 'weekly',
            'day_of_week' => 1,
            'day_of_month' => null,
            'time' => '12:00',
            'status' => 'active',
            'next_run_at' => (new \DateTime('+7 days'))->format('Y-m-d H:i:s'),
            'last_run_at' => null,
        ];

        return \App\Models\NewsletterCreationSchedule::create(array_merge($defaults, $overrides));
    }

    protected function createNewsletterSendSchedule(array $overrides = []): \App\Models\NewsletterSendSchedule
    {
        $this->sendScheduleCounter++;

        $defaults = [
            'newsletter_id' => $overrides['newsletter_id'] ?? $this->createNewsletter()->id,
            'site_id' => $this->siteId,
            'creation_schedule_id' => null,
            'frequency' => 'weekly',
            'day_of_week' => 1,
            'day_of_month' => null,
            'time' => '14:30',
            'status' => 'active',
            'next_run_at' => (new \DateTime('+7 days'))->format('Y-m-d H:i:s'),
            'last_run_at' => null,
        ];

        return \App\Models\NewsletterSendSchedule::create(array_merge($defaults, $overrides));
    }

    protected function createPromotion(array $overrides = [])
    {
        $product = $overrides['product_id'] ?? $this->createProduct()->id;
        return GiftPromotion::create([
            'gift_type' => GiftType::PRODUCT->value,
            'active' => true,
            'merchant_id' => null,
            'gift_product_id' => $product,
            'gift_subscription_plan_id' => null
        ]);
    }
}