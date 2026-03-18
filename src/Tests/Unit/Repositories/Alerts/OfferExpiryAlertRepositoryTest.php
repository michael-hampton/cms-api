<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repositories\Alerts;

use App\Enums\Alerts\AlertableEntityType;
use App\Enums\Alerts\ExpiryAlertThreshold;
use App\Models\OfferExpiryAlert;
use App\Models\Wishlist;
use App\Repositories\Alerts\OfferExpiryAlertRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class OfferExpiryAlertRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private OfferExpiryAlertRepository $repository;

    // =========================================================================
    // findDueProductOffers
    // =========================================================================

    public function testFindDueProductOffersReturnsOffersExpiringWithinThreshold(): void
    {
        $merchant = $this->createMerchant();
        $product = $this->createProduct();

        $this->createProductOffer($product->id, [
            'merchant_id' => $merchant->id,
            'product_id' => $product->id,
            'is_active' => true,
            'end_date' => date('Y-m-d H:i:s', strtotime('+20 hours')),
        ]);

        $offers = $this->repository->findDueProductOffers(ExpiryAlertThreshold::TwentyFourHours);

        $this->assertCount(1, $offers);
    }

    public function testFindDueProductOffersExcludesExpiredOffers(): void
    {
        $merchant = $this->createMerchant();
        $product = $this->createProduct();

        $this->createProductOffer($product->id, [
            'merchant_id' => $merchant->id,
            'product_id' => $product->id,
            'is_active' => true,
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        ]);

        $offers = $this->repository->findDueProductOffers(ExpiryAlertThreshold::TwentyFourHours);

        $this->assertCount(0, $offers);
    }

    public function testFindDueProductOffersExcludesInactiveOffers(): void
    {
        $merchant = $this->createMerchant();
        $product = $this->createProduct();

        $this->createProductOffer($product->id, [
            'merchant_id' => $merchant->id,
            'product_id' => $product->id,
            'is_active' => false,
            'end_date' => date('Y-m-d H:i:s', strtotime('+20 hours')),
        ]);

        $offers = $this->repository->findDueProductOffers(ExpiryAlertThreshold::TwentyFourHours);

        $this->assertCount(0, $offers);
    }

    public function testFindDueProductOffersExcludesAlreadyAlertedOffers(): void
    {
        $merchant = $this->createMerchant();
        $product = $this->createProduct();

        $offer = $this->createProductOffer($product->id, [
            'merchant_id' => $merchant->id,
            'product_id' => $product->id,
            'is_active' => true,
            'end_date' => date('Y-m-d H:i:s', strtotime('+20 hours')),
        ]);

        OfferExpiryAlert::create([
            'entity_type' => AlertableEntityType::ProductOffer->value,
            'entity_id' => $offer->id,
            'threshold_hours' => ExpiryAlertThreshold::TwentyFourHours->value,
            'sent_at' => now(),
        ]);

        $offers = $this->repository->findDueProductOffers(ExpiryAlertThreshold::TwentyFourHours);

        $this->assertCount(0, $offers);
    }

    public function testFindDueProductOffersDoesNotExcludeOfferAlertedAtDifferentThreshold(): void
    {
        $merchant = $this->createMerchant();
        $product = $this->createProduct();

        $offer = $this->createProductOffer($product->id, [
            'merchant_id' => $merchant->id,
            'product_id' => $product->id,
            'is_active' => true,
            'end_date' => date('Y-m-d H:i:s', strtotime('+20 hours')),
        ]);

        // Alert was sent at 48h threshold, not 24h
        OfferExpiryAlert::create([
            'entity_type' => AlertableEntityType::ProductOffer->value,
            'entity_id' => $offer->id,
            'threshold_hours' => ExpiryAlertThreshold::FortyEightHours->value,
            'sent_at' => now(),
        ]);

        $offers = $this->repository->findDueProductOffers(ExpiryAlertThreshold::TwentyFourHours);

        $this->assertCount(1, $offers);
    }

    // =========================================================================
    // findDueProductOfferBundles
    // =========================================================================

    public function testFindDueProductOfferBundlesReturnsBundlesExpiringWithinThreshold(): void
    {
        $this->createProductOfferBundle([
            'is_active' => true,
            'end_date' => date('Y-m-d H:i:s', strtotime('+20 hours')),
        ]);

        $bundles = $this->repository->findDueProductOfferBundles(ExpiryAlertThreshold::TwentyFourHours);

        $this->assertCount(1, $bundles);
    }

    public function testFindDueProductOfferBundlesExcludesAlreadyAlerted(): void
    {
        $bundle = $this->createProductOfferBundle([
            'is_active' => true,
            'end_date' => date('Y-m-d H:i:s', strtotime('+20 hours')),
        ]);

        OfferExpiryAlert::create([
            'entity_type' => AlertableEntityType::ProductOfferBundle->value,
            'entity_id' => $bundle->id,
            'threshold_hours' => ExpiryAlertThreshold::TwentyFourHours->value,
            'sent_at' => now(),
        ]);

        $bundles = $this->repository->findDueProductOfferBundles(ExpiryAlertThreshold::TwentyFourHours);

        $this->assertCount(0, $bundles);
    }

    // =========================================================================
    // findDueGiftPromotions
    // =========================================================================

    public function testFindDueGiftPromotionsReturnsPromotionsExpiringWithinThreshold(): void
    {
        $merchant = $this->createMerchant();

        $this->createGiftPromotion([
            'merchant_id' => $merchant->id,
            'active' => true,
            'ends_at' => date('Y-m-d H:i:s', strtotime('+20 hours')),
        ]);

        $promotions = $this->repository->findDueGiftPromotions(ExpiryAlertThreshold::TwentyFourHours);

        $this->assertCount(1, $promotions);
    }

    public function testFindDueGiftPromotionsExcludesInactivePromotions(): void
    {
        $merchant = $this->createMerchant();

        $this->createGiftPromotion([
            'merchant_id' => $merchant->id,
            'active' => false,
            'ends_at' => date('Y-m-d H:i:s', strtotime('+20 hours')),
        ]);

        $promotions = $this->repository->findDueGiftPromotions(ExpiryAlertThreshold::TwentyFourHours);

        $this->assertCount(0, $promotions);
    }

    public function testFindDueGiftPromotionsExcludesAlreadyAlerted(): void
    {
        $merchant = $this->createMerchant();
        $promotion = $this->createGiftPromotion([
            'merchant_id' => $merchant->id,
            'active' => true,
            'ends_at' => date('Y-m-d H:i:s', strtotime('+20 hours')),
        ]);

        OfferExpiryAlert::create([
            'entity_type' => AlertableEntityType::GiftPromotion->value,
            'entity_id' => $promotion->id,
            'threshold_hours' => ExpiryAlertThreshold::TwentyFourHours->value,
            'sent_at' => now(),
        ]);

        $promotions = $this->repository->findDueGiftPromotions(ExpiryAlertThreshold::TwentyFourHours);

        $this->assertCount(0, $promotions);
    }

    // =========================================================================
    // primaryContactForMerchant
    // =========================================================================

    public function testPrimaryContactForMerchantReturnsFirstContact(): void
    {
        $merchant = $this->createMerchant();

        $first = $this->createMerchantContact(['merchant_id' => $merchant->id, 'email' => 'first@acme.com']);
        $second = $this->createMerchantContact(['merchant_id' => $merchant->id, 'email' => 'second@acme.com']);

        $contact = $this->repository->primaryContactForMerchant($merchant->id);

        $this->assertNotNull($contact);
        $this->assertEquals($first->id, $contact->id);
    }

    public function testPrimaryContactForMerchantReturnsNullWhenNoContacts(): void
    {
        $merchant = $this->createMerchant();

        $contact = $this->repository->primaryContactForMerchant($merchant->id);

        $this->assertNull($contact);
    }

    // =========================================================================
    // findMerchant
    // =========================================================================

    public function testFindMerchantReturnsMerchantById(): void
    {
        $merchant = $this->createMerchant();

        $found = $this->repository->findMerchant($merchant->id);

        $this->assertNotNull($found);
        $this->assertEquals($merchant->id, $found->id);
    }

    public function testFindMerchantReturnsNullWhenNotFound(): void
    {
        $found = $this->repository->findMerchant(999999);

        $this->assertNull($found);
    }

    // =========================================================================
    // resolveBundleMerchant
    // =========================================================================

    public function testResolveBundleMerchantReturnsMerchantFromFirstItemOffer(): void
    {
        $merchant = $this->createMerchant();
        $product = $this->createProduct();
        $bundle = $this->createProductOfferBundle(['is_active' => true]);

        $offer = $this->createProductOffer($product->id, [
            'merchant_id' => $merchant->id,
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        \App\Models\ProductOfferBundleItem::create([
            'bundle_id' => $bundle->id,
            'product_offer_id' => $offer->id,
            'quantity' => 1,
        ]);

        $resolved = $this->repository->resolveBundleMerchant($bundle->id);

        $this->assertNotNull($resolved);
        $this->assertEquals($merchant->id, $resolved->id);
    }

    public function testResolveBundleMerchantReturnsNullForBundleWithNoItems(): void
    {
        $bundle = $this->createProductOfferBundle(['is_active' => true]);

        $resolved = $this->repository->resolveBundleMerchant($bundle->id);

        $this->assertNull($resolved);
    }

    // =========================================================================
    // findMembersByIds
    // =========================================================================

    public function testFindMembersByIdsReturnsMembersForGivenIds(): void
    {
        $member1 = $this->createMember();
        $member2 = $this->createMember();
        $member3 = $this->createMember();

        $members = $this->repository->findMembersByIds([$member1->id, $member2->id]);

        $this->assertCount(2, $members);
        $ids = $members->pluck('id')->toArray();
        $this->assertContains($member1->id, $ids);
        $this->assertContains($member2->id, $ids);
        $this->assertNotContains($member3->id, $ids);
    }

    public function testFindMembersByIdsReturnsEmptyCollectionForEmptyInput(): void
    {
        $members = $this->repository->findMembersByIds([]);

        $this->assertCount(0, $members);
    }

    // =========================================================================
    // memberIdsWhoWishlistedOffer
    // =========================================================================

    public function testMemberIdsWhoWishlistedOfferReturnsMemberIds(): void
    {
        $member1 = $this->createMember();
        $member2 = $this->createMember();
        $merchant = $this->createMerchant();
        $product = $this->createProduct();
        $offer = $this->createProductOffer($product->id, ['merchant_id' => $merchant->id, 'product_id' => $product->id]);

        \App\Models\Wishlist::create(['user_id' => $member1->id, 'product_id' => $offer->id, 'wishlistable_type' => \App\Models\ProductOffer::class, 'site_id' => $this->siteId]);
        \App\Models\Wishlist::create(['user_id' => $member2->id, 'product_id' => $offer->id, 'wishlistable_type' => \App\Models\ProductOffer::class, 'site_id' => $this->siteId]);

        $ids = $this->repository->memberIdsWhoWishlistedOffer($offer->id);

        $this->assertCount(2, $ids);
        $this->assertContains($member1->id, $ids);
        $this->assertContains($member2->id, $ids);
    }

    public function testMemberIdsWhoWishlistedOfferReturnsEmptyForNoWishlists(): void
    {
        $merchant = $this->createMerchant();
        $product = $this->createProduct();
        $offer = $this->createProductOffer($product->id, ['merchant_id' => $merchant->id, 'product_id' => $product->id]);

        $ids = $this->repository->memberIdsWhoWishlistedOffer($offer->id);

        $this->assertCount(0, $ids);
    }

    // =========================================================================
    // record + hasBeenSent
    // =========================================================================

    public function testRecordCreatesAlertRow(): void
    {
        $this->repository->record(
            AlertableEntityType::ProductOffer,
            42,
            ExpiryAlertThreshold::TwentyFourHours,
        );

        $this->assertDatabaseHas('offer_expiry_alerts', [
            'entity_type' => 'product_offer',
            'entity_id' => 42,
            'threshold_hours' => 24,
        ]);
    }

    public function testHasBeenSentReturnsTrueAfterRecord(): void
    {
        $this->repository->record(
            AlertableEntityType::ProductOffer,
            42,
            ExpiryAlertThreshold::TwentyFourHours,
        );

        $this->assertTrue($this->repository->hasBeenSent(
            AlertableEntityType::ProductOffer,
            42,
            ExpiryAlertThreshold::TwentyFourHours,
        ));
    }

    public function testHasBeenSentReturnsFalseWhenNotRecorded(): void
    {
        $this->assertFalse($this->repository->hasBeenSent(
            AlertableEntityType::ProductOffer,
            42,
            ExpiryAlertThreshold::TwentyFourHours,
        ));
    }

    public function testHasBeenSentReturnsFalseForDifferentThreshold(): void
    {
        $this->repository->record(
            AlertableEntityType::ProductOffer,
            42,
            ExpiryAlertThreshold::FortyEightHours,
        );

        $this->assertFalse($this->repository->hasBeenSent(
            AlertableEntityType::ProductOffer,
            42,
            ExpiryAlertThreshold::TwentyFourHours,
        ));
    }

    public function testHasBeenSentReturnsFalseForDifferentEntityType(): void
    {
        $this->repository->record(
            AlertableEntityType::ProductOffer,
            42,
            ExpiryAlertThreshold::TwentyFourHours,
        );

        $this->assertFalse($this->repository->hasBeenSent(
            AlertableEntityType::GiftPromotion,
            42,
            ExpiryAlertThreshold::TwentyFourHours,
        ));
    }

    // =========================================================================
    // Setup
    // =========================================================================

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new OfferExpiryAlertRepository();
    }
}