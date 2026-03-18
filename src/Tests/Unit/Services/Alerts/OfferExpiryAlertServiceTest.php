<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Alerts;

use App\Enums\Alerts\AlertableEntityType;
use App\Enums\Alerts\ExpiryAlertThreshold;
use App\Events\Alerts\OfferExpiryAlertDispatched;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Mail\MailManager;
use App\Framework\Mail\PendingMail;
use App\Mail\Alerts\BundleExpiringSoonMemberMail;
use App\Mail\Alerts\BundleExpiringSoonMerchantMail;
use App\Mail\Alerts\GiftPromotionExpiringSoonMemberMail;
use App\Mail\Alerts\GiftPromotionExpiringSoonMerchantMail;
use App\Mail\Alerts\OfferExpiryAlertMerchantMail;
use App\Mail\Alerts\OfferExpiringSoonMerchantMail;
use App\Mail\Offers\OfferEndingSoon;
use App\Models\GiftPromotion;
use App\Models\Member;
use App\Models\Merchant;
use App\Models\MerchantContact;
use App\Models\ProductOffer;
use App\Models\ProductOfferBundle;
use App\Repositories\Alerts\OfferExpiryAlertRepository;
use App\Services\Alerts\OfferExpiryAlertService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * All mail sending uses $this->mailer->to($email)->send(...) — there are no
 * direct $this->mailer->send() calls in the service. Every merchant and member
 * send goes through PendingMail.
 *
 * All model lookups (Merchant, Member, MerchantContact, bundle merchant) are
 * delegated to the repository — the service has no static model calls.
 */
class OfferExpiryAlertServiceTest extends FunctionalTestCase
{
    private OfferExpiryAlertRepository&MockObject $alertRepository;
    private MailManager&MockObject $mailer;
    private Database&MockObject $databaseMock;
    private EventDispatcher&MockObject $events;
    private OfferExpiryAlertService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alertRepository = $this->createMock(OfferExpiryAlertRepository::class);
        $this->mailer = $this->createMock(MailManager::class);
        $this->events = $this->createMock(EventDispatcher::class);

        $this->databaseMock = $this->createMock(Database::class);
        $this->databaseMock
            ->method('transaction')
            ->willReturnCallback(fn(callable $cb) => $cb());

        $this->service = new OfferExpiryAlertService(
            alertRepository: $this->alertRepository,
            mailer: $this->mailer,
            database: $this->databaseMock,
            events: $this->events,
        );
    }

    // =========================================================================
    // processOffers
    // =========================================================================

    public function testProcessOffersSendsOfferExpiringSoonMerchantMailToContact(): void
    {
        $offer = $this->makeOffer();
        $contact = $this->makeContact('merchant@acme.com');
        $pendingMail = $this->makePendingMail();

        $this->alertRepository->method('findDueProductOffers')->willReturn(collect([$offer]));
        $this->alertRepository->method('primaryContactForMerchant')->with($offer->merchant->id)->willReturn($contact);
        $this->alertRepository->method('memberIdsWhoWishlistedOffer')->willReturn([]);
        $this->alertRepository->method('findMembersByIds')->willReturn(collect([]));

        $this->mailer->expects($this->once())->method('to')->with('merchant@acme.com')->willReturn($pendingMail);
        $pendingMail->expects($this->once())->method('send')
            ->with($this->isInstanceOf(OfferExpiringSoonMerchantMail::class));

        $stats = $this->service->processOffers(ExpiryAlertThreshold::TwentyFourHours);

        $this->assertSame(1, $stats['processed']);
        $this->assertSame(0, $stats['skipped']);
    }

    public function testProcessOffersSkipsOfferWithNoMerchant(): void
    {
        $offer = $this->makeOffer();
        $offer->merchant = null;

        $this->alertRepository->method('findDueProductOffers')->willReturn(collect([$offer]));

        $this->mailer->expects($this->never())->method('to');
        $this->alertRepository->expects($this->never())->method('record');

        $stats = $this->service->processOffers(ExpiryAlertThreshold::TwentyFourHours);

        $this->assertSame(0, $stats['processed']);
        $this->assertSame(1, $stats['skipped']);
    }

    public function testProcessOffersSkipsOfferWhenNoContactFound(): void
    {
        $offer = $this->makeOffer();

        $this->alertRepository->method('findDueProductOffers')->willReturn(collect([$offer]));
        $this->alertRepository->method('primaryContactForMerchant')->willReturn(null);

        $this->mailer->expects($this->never())->method('to');
        $this->alertRepository->expects($this->never())->method('record');

        $stats = $this->service->processOffers(ExpiryAlertThreshold::TwentyFourHours);

        $this->assertSame(0, $stats['processed']);
        $this->assertSame(1, $stats['skipped']);
    }

    public function testProcessOffersSendsMemberAlertViaOfferEndingSoon(): void
    {
        $offer = $this->makeOffer();
        $contact = $this->makeContact('merchant@acme.com');
        $member = $this->makeMember(42);
        $merchantPending = $this->makePendingMail();
        $memberPending = $this->makePendingMail();

        $this->alertRepository->method('findDueProductOffers')->willReturn(collect([$offer]));
        $this->alertRepository->method('primaryContactForMerchant')->willReturn($contact);
        $this->alertRepository->method('memberIdsWhoWishlistedOffer')->willReturn([$member->id]);
        $this->alertRepository->method('findMembersByIds')->willReturn(collect([$member]));

        $this->mailer
            ->expects($this->exactly(2))
            ->method('to')
            ->willReturnCallback(function (string $email) use ($contact, $member, $merchantPending, $memberPending) {
                return $email === $contact->email ? $merchantPending : $memberPending;
            });

        $merchantPending->method('send');
        $memberPending->expects($this->once())->method('send')
            ->with($this->isInstanceOf(OfferEndingSoon::class));

        $this->service->processOffers(ExpiryAlertThreshold::TwentyFourHours);
    }

    public function testProcessOffersRecordsAlertInTransaction(): void
    {
        $threshold = ExpiryAlertThreshold::TwentyFourHours;
        $offer = $this->makeOffer();
        $contact = $this->makeContact('merchant@acme.com');
        $pending = $this->makePendingMail();

        $this->alertRepository->method('findDueProductOffers')->willReturn(collect([$offer]));
        $this->alertRepository->method('primaryContactForMerchant')->willReturn($contact);
        $this->alertRepository->method('memberIdsWhoWishlistedOffer')->willReturn([]);
        $this->alertRepository->method('findMembersByIds')->willReturn(collect([]));
        $this->mailer->method('to')->willReturn($pending);
        $pending->method('send');

        $this->databaseMock->expects($this->once())->method('transaction');
        $this->alertRepository->expects($this->once())->method('record')
            ->with(AlertableEntityType::ProductOffer, $offer->id, $threshold);

        $this->service->processOffers($threshold);
    }

    public function testProcessOffersFiresEventWithCorrectCounts(): void
    {
        $threshold = ExpiryAlertThreshold::FortyEightHours;
        $offer = $this->makeOffer();
        $contact = $this->makeContact('merchant@acme.com');
        $member = $this->makeMember(5);
        $pending = $this->makePendingMail();

        $this->alertRepository->method('findDueProductOffers')->willReturn(collect([$offer]));
        $this->alertRepository->method('primaryContactForMerchant')->willReturn($contact);
        $this->alertRepository->method('memberIdsWhoWishlistedOffer')->willReturn([$member->id]);
        $this->alertRepository->method('findMembersByIds')->willReturn(collect([$member]));
        $this->mailer->method('to')->willReturn($pending);
        $pending->method('send');

        $this->events->expects($this->once())->method('dispatch')
            ->with($this->callback(function (OfferExpiryAlertDispatched $e) use ($offer, $threshold) {
                return $e->entityType === AlertableEntityType::ProductOffer
                    && $e->entityId === $offer->id
                    && $e->threshold === $threshold
                    && $e->merchantAlertsSent === 1
                    && $e->memberAlertsSent === 1;
            }));

        $this->service->processOffers($threshold);
    }

    public function testProcessOffersReturnsZeroWhenNoneDue(): void
    {
        $this->alertRepository->method('findDueProductOffers')->willReturn(collect([]));

        $this->mailer->expects($this->never())->method('to');
        $this->alertRepository->expects($this->never())->method('record');

        $stats = $this->service->processOffers(ExpiryAlertThreshold::TwentyFourHours);

        $this->assertSame(0, $stats['processed']);
        $this->assertSame(0, $stats['skipped']);
    }

    // =========================================================================
    // processBundles
    // =========================================================================

    public function testProcessBundlesProcessesEntityWhenNoMerchantResolvable(): void
    {
        $bundle = $this->makeBundle();

        $this->alertRepository->method('findDueProductOfferBundles')->willReturn(collect([$bundle]));
        $this->alertRepository->method('resolveBundleMerchant')->willReturn(null);
        $this->alertRepository->method('memberIdsWhoWishlistedBundle')->willReturn([]);
        $this->alertRepository->method('findMembersByIds')->willReturn(collect([]));

        $this->mailer->expects($this->never())->method('to');

        $stats = $this->service->processBundles(ExpiryAlertThreshold::TwentyFourHours);

        $this->assertSame(1, $stats['processed']);
        $this->assertSame(0, $stats['skipped']);
    }

    public function testProcessBundlesSendsMerchantMailWhenContactResolvable(): void
    {
        $bundle = $this->makeBundle();
        $merchant = $this->makeMerchant();
        $contact = $this->makeContact('merchant@acme.com');
        $pending = $this->makePendingMail();

        $this->alertRepository->method('findDueProductOfferBundles')->willReturn(collect([$bundle]));
        $this->alertRepository->method('resolveBundleMerchant')->willReturn($merchant);
        $this->alertRepository->method('primaryContactForMerchant')->willReturn($contact);
        $this->alertRepository->method('memberIdsWhoWishlistedBundle')->willReturn([]);
        $this->alertRepository->method('findMembersByIds')->willReturn(collect([]));

        $this->mailer->expects($this->once())->method('to')->with('merchant@acme.com')->willReturn($pending);
        $pending->expects($this->once())->method('send')
            ->with($this->isInstanceOf(BundleExpiringSoonMerchantMail::class));

        $stats = $this->service->processBundles(ExpiryAlertThreshold::TwentyFourHours);

        $this->assertSame(1, $stats['processed']);
    }

    public function testProcessBundlesSendsMemberAlertViaBundleExpiringSoonMemberMail(): void
    {
        $bundle = $this->makeBundle();
        $member = $this->makeMember(10);
        $pending = $this->makePendingMail();

        $this->alertRepository->method('findDueProductOfferBundles')->willReturn(collect([$bundle]));
        $this->alertRepository->method('resolveBundleMerchant')->willReturn(null);
        $this->alertRepository->method('memberIdsWhoWishlistedBundle')->willReturn([$member->id]);
        $this->alertRepository->method('findMembersByIds')->willReturn(collect([$member]));

        $this->mailer->expects($this->once())->method('to')->with($member->email)->willReturn($pending);
        $pending->expects($this->once())->method('send')
            ->with($this->isInstanceOf(BundleExpiringSoonMemberMail::class));

        $this->service->processBundles(ExpiryAlertThreshold::TwentyFourHours);
    }

    public function testProcessBundlesRecordsAlert(): void
    {
        $threshold = ExpiryAlertThreshold::TwentyFourHours;
        $bundle = $this->makeBundle();

        $this->alertRepository->method('findDueProductOfferBundles')->willReturn(collect([$bundle]));
        $this->alertRepository->method('resolveBundleMerchant')->willReturn(null);
        $this->alertRepository->method('memberIdsWhoWishlistedBundle')->willReturn([]);
        $this->alertRepository->method('findMembersByIds')->willReturn(collect([]));

        $this->alertRepository->expects($this->once())->method('record')
            ->with(AlertableEntityType::ProductOfferBundle, $bundle->id, $threshold);

        $this->service->processBundles($threshold);
    }

    public function testProcessBundlesFiresEvent(): void
    {
        $threshold = ExpiryAlertThreshold::FortyEightHours;
        $bundle = $this->makeBundle();

        $this->alertRepository->method('findDueProductOfferBundles')->willReturn(collect([$bundle]));
        $this->alertRepository->method('resolveBundleMerchant')->willReturn(null);
        $this->alertRepository->method('memberIdsWhoWishlistedBundle')->willReturn([]);
        $this->alertRepository->method('findMembersByIds')->willReturn(collect([]));

        $this->events->expects($this->once())->method('dispatch')
            ->with($this->callback(fn(OfferExpiryAlertDispatched $e) => $e->entityType === AlertableEntityType::ProductOfferBundle
                && $e->entityId === $bundle->id
                && $e->threshold === $threshold
            ));

        $this->service->processBundles($threshold);
    }

    // =========================================================================
    // processPromotions
    // =========================================================================

    public function testProcessPromotionsSendsGiftPromotionExpiringSoonMerchantMailToContact(): void
    {
        $promotion = $this->makePromotion(merchantId: 7);
        $merchant = $this->makeMerchant();
        $contact = $this->makeContact('merchant@acme.com');
        $pending = $this->makePendingMail();

        $this->alertRepository->method('findDueGiftPromotions')->willReturn(collect([$promotion]));
        $this->alertRepository->method('findMerchant')->with(7)->willReturn($merchant);
        $this->alertRepository->method('primaryContactForMerchant')->willReturn($contact);
        $this->alertRepository->method('memberIdsForMerchant')->willReturn([]);
        $this->alertRepository->method('findMembersByIds')->willReturn(collect([]));

        $this->mailer->expects($this->once())->method('to')->with('merchant@acme.com')->willReturn($pending);
        $pending->expects($this->once())->method('send')
            ->with($this->isInstanceOf(GiftPromotionExpiringSoonMerchantMail::class));

        $stats = $this->service->processPromotions(ExpiryAlertThreshold::TwentyFourHours);

        $this->assertSame(1, $stats['processed']);
        $this->assertSame(0, $stats['skipped']);
    }

    public function testProcessPromotionsSendsMemberAlertViaGiftPromotionExpiringSoonMemberMail(): void
    {
        $promotion = $this->makePromotion(merchantId: 7);
        $merchant = $this->makeMerchant();
        $contact = $this->makeContact('merchant@acme.com');
        $member = $this->makeMember(20);
        $merchantPending = $this->makePendingMail();
        $memberPending = $this->makePendingMail();

        $this->alertRepository->method('findDueGiftPromotions')->willReturn(collect([$promotion]));
        $this->alertRepository->method('findMerchant')->willReturn($merchant);
        $this->alertRepository->method('primaryContactForMerchant')->willReturn($contact);
        $this->alertRepository->method('memberIdsForMerchant')->with(7)->willReturn([$member->id]);
        $this->alertRepository->method('findMembersByIds')->willReturn(collect([$member]));

        $this->mailer
            ->expects($this->exactly(2))
            ->method('to')
            ->willReturnCallback(function (string $email) use ($contact, $member, $merchantPending, $memberPending) {
                return $email === $contact->email ? $merchantPending : $memberPending;
            });

        $merchantPending->method('send');
        $memberPending->expects($this->once())->method('send')
            ->with($this->isInstanceOf(GiftPromotionExpiringSoonMemberMail::class));

        $this->service->processPromotions(ExpiryAlertThreshold::TwentyFourHours);
    }

    public function testProcessPromotionsSkipsNullMerchantId(): void
    {
        $promotion = $this->makePromotion(merchantId: null);

        $this->alertRepository->method('findDueGiftPromotions')->willReturn(collect([$promotion]));

        $this->mailer->expects($this->never())->method('to');
        $this->alertRepository->expects($this->never())->method('record');

        $stats = $this->service->processPromotions(ExpiryAlertThreshold::TwentyFourHours);

        $this->assertSame(0, $stats['processed']);
        $this->assertSame(1, $stats['skipped']);
    }

    public function testProcessPromotionsSkipsWhenMerchantNotFound(): void
    {
        $promotion = $this->makePromotion(merchantId: 99);

        $this->alertRepository->method('findDueGiftPromotions')->willReturn(collect([$promotion]));
        $this->alertRepository->method('findMerchant')->willReturn(null);

        $this->mailer->expects($this->never())->method('to');
        $this->alertRepository->expects($this->never())->method('record');

        $stats = $this->service->processPromotions(ExpiryAlertThreshold::TwentyFourHours);

        $this->assertSame(0, $stats['processed']);
        $this->assertSame(1, $stats['skipped']);
    }

    public function testProcessPromotionsSkipsWhenNoContactFound(): void
    {
        $promotion = $this->makePromotion(merchantId: 7);
        $merchant = $this->makeMerchant();

        $this->alertRepository->method('findDueGiftPromotions')->willReturn(collect([$promotion]));
        $this->alertRepository->method('findMerchant')->willReturn($merchant);
        $this->alertRepository->method('primaryContactForMerchant')->willReturn(null);

        $this->mailer->expects($this->never())->method('to');
        $this->alertRepository->expects($this->never())->method('record');

        $stats = $this->service->processPromotions(ExpiryAlertThreshold::TwentyFourHours);

        $this->assertSame(0, $stats['processed']);
        $this->assertSame(1, $stats['skipped']);
    }

    public function testProcessPromotionsQueriesMembersByMerchantId(): void
    {
        $promotion = $this->makePromotion(merchantId: 99);
        $merchant = $this->makeMerchant();
        $contact = $this->makeContact('merchant@acme.com');
        $pending = $this->makePendingMail();

        $this->alertRepository->method('findDueGiftPromotions')->willReturn(collect([$promotion]));
        $this->alertRepository->method('findMerchant')->willReturn($merchant);
        $this->alertRepository->method('primaryContactForMerchant')->willReturn($contact);
        $this->mailer->method('to')->willReturn($pending);
        $pending->method('send');

        $this->alertRepository->expects($this->once())->method('memberIdsForMerchant')->with(99)->willReturn([]);
        $this->alertRepository->method('findMembersByIds')->willReturn(collect([]));

        $this->service->processPromotions(ExpiryAlertThreshold::TwentyFourHours);
    }

    public function testProcessPromotionsRecordsAlertInTransaction(): void
    {
        $threshold = ExpiryAlertThreshold::TwentyFourHours;
        $promotion = $this->makePromotion(merchantId: 3);
        $merchant = $this->makeMerchant();
        $contact = $this->makeContact('merchant@acme.com');
        $pending = $this->makePendingMail();

        $this->alertRepository->method('findDueGiftPromotions')->willReturn(collect([$promotion]));
        $this->alertRepository->method('findMerchant')->willReturn($merchant);
        $this->alertRepository->method('primaryContactForMerchant')->willReturn($contact);
        $this->alertRepository->method('memberIdsForMerchant')->willReturn([]);
        $this->alertRepository->method('findMembersByIds')->willReturn(collect([]));
        $this->mailer->method('to')->willReturn($pending);
        $pending->method('send');

        $this->databaseMock->expects($this->once())->method('transaction');
        $this->alertRepository->expects($this->once())->method('record')
            ->with(AlertableEntityType::GiftPromotion, $promotion->id, $threshold);

        $this->service->processPromotions($threshold);
    }

    public function testProcessPromotionsFiresEventWithCorrectCounts(): void
    {
        $threshold = ExpiryAlertThreshold::FortyEightHours;
        $promotion = $this->makePromotion(merchantId: 5);
        $merchant = $this->makeMerchant();
        $contact = $this->makeContact('merchant@acme.com');
        $member = $this->makeMember(30);
        $pending = $this->makePendingMail();

        $this->alertRepository->method('findDueGiftPromotions')->willReturn(collect([$promotion]));
        $this->alertRepository->method('findMerchant')->willReturn($merchant);
        $this->alertRepository->method('primaryContactForMerchant')->willReturn($contact);
        $this->alertRepository->method('memberIdsForMerchant')->willReturn([$member->id]);
        $this->alertRepository->method('findMembersByIds')->willReturn(collect([$member]));
        $this->mailer->method('to')->willReturn($pending);
        $pending->method('send');

        $this->events->expects($this->once())->method('dispatch')
            ->with($this->callback(function (OfferExpiryAlertDispatched $e) use ($promotion, $threshold) {
                return $e->entityType === AlertableEntityType::GiftPromotion
                    && $e->entityId === $promotion->id
                    && $e->threshold === $threshold
                    && $e->merchantAlertsSent === 1
                    && $e->memberAlertsSent === 1;
            }));

        $this->service->processPromotions($threshold);
    }

    public function testProcessPromotionsFiresEventWithZeroMemberCountWhenNoMembers(): void
    {
        $promotion = $this->makePromotion(merchantId: 5);
        $merchant = $this->makeMerchant();
        $contact = $this->makeContact('merchant@acme.com');
        $pending = $this->makePendingMail();

        $this->alertRepository->method('findDueGiftPromotions')->willReturn(collect([$promotion]));
        $this->alertRepository->method('findMerchant')->willReturn($merchant);
        $this->alertRepository->method('primaryContactForMerchant')->willReturn($contact);
        $this->alertRepository->method('memberIdsForMerchant')->willReturn([]);
        $this->alertRepository->method('findMembersByIds')->willReturn(collect([]));
        $this->mailer->method('to')->willReturn($pending);
        $pending->method('send');

        $this->events->expects($this->once())->method('dispatch')
            ->with($this->callback(fn(OfferExpiryAlertDispatched $e) => $e->memberAlertsSent === 0));

        $this->service->processPromotions(ExpiryAlertThreshold::TwentyFourHours);
    }

    public function testProcessPromotionsReturnsZeroWhenNoneDue(): void
    {
        $this->alertRepository->method('findDueGiftPromotions')->willReturn(collect([]));

        $this->mailer->expects($this->never())->method('to');
        $this->alertRepository->expects($this->never())->method('record');

        $stats = $this->service->processPromotions(ExpiryAlertThreshold::TwentyFourHours);

        $this->assertSame(0, $stats['processed']);
        $this->assertSame(0, $stats['skipped']);
    }

    // =========================================================================
    // Factories
    // =========================================================================

    private function makeOffer(): ProductOffer
    {
        $offer = $this->createPartialMock(ProductOffer::class, []);
        $offer->id = 10;
        $offer->end_date = new \DateTime('+20 hours');
        $offer->sale_price = 49.99;
        $offer->original_price = 79.99;
        $offer->discount_percentage = 37;
        $offer->merchant = $this->makeMerchant();
        $offer->product = (object)['name' => 'Widget Pro'];
        return $offer;
    }

    private function makeBundle(): ProductOfferBundle
    {
        $bundle = $this->createPartialMock(ProductOfferBundle::class, []);
        $bundle->id = 20;
        $bundle->name = 'Summer Bundle';
        $bundle->slug = 'summer-bundle';
        $bundle->end_date = new \DateTime('+20 hours');
        return $bundle;
    }

    private function makePromotion(?int $merchantId): GiftPromotion
    {
        $promotion = $this->createPartialMock(GiftPromotion::class, ['triggers']);
        $promotion->id = 30;
        $promotion->name = 'Buy One Get One';
        $promotion->gift_type = 'product';
        $promotion->quantity_rule = 'one_per_qualifying';
        $promotion->merchant_id = $merchantId;
        $promotion->ends_at = new \DateTime('+20 hours');
        $promotion->method('triggers')->willReturn(collect([]));
        return $promotion;
    }

    private function makeMerchant(): Merchant
    {
        $merchant = new Merchant();
        $merchant->id = 1;
        $merchant->name = 'Acme Corp';
        return $merchant;
    }

    private function makeContact(string $email): MerchantContact
    {
        $contact = new MerchantContact();
        $contact->email = $email;
        return $contact;
    }

    private function makeMember(int $id): Member
    {
        $member = new Member();
        $member->id = $id;
        $member->email = "member{$id}@example.com";
        $member->first_name = 'Test';
        return $member;
    }

    private function makePendingMail(): PendingMail&MockObject
    {
        return $this->createMock(PendingMail::class);
    }
}