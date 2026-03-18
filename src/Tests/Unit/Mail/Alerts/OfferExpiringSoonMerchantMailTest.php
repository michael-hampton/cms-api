<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mail\Alerts;

use App\Enums\Alerts\ExpiryAlertThreshold;
use App\Mail\Alerts\OfferExpiringSoonMerchantMail;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class OfferExpiringSoonMerchantMailTest extends FunctionalTestCase
{
    public function testSubjectContainsHoursRemainingAndProductName(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();

        $this->assertStringContainsString('24 hours', $mailable->subject);
        $this->assertStringContainsString('Widget Pro', $mailable->subject);
    }

    public function testSubjectReflects48HourThreshold(): void
    {
        $mailable = $this->makeMail(threshold: ExpiryAlertThreshold::FortyEightHours);
        $mailable->build();

        $this->assertStringContainsString('48 hours', $mailable->subject);
    }

//    public function testRecipientIsSetToMerchantEmail(): void
//    {
//        $mailable = $this->makeMail();
//        $mailable->build();
//
//        $this->assertNotEmpty($mailable->to);
//        $this->assertSame('merchant@acme.com', $mailable->to[0]['address']);
//    }

    public function testUsesMarkdownTemplate(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();

        $this->assertSame('emails.alerts.offer-expiring-soon-merchant', $mailable->markdown);
    }

    public function testViewDataContainsHoursRemaining(): void
    {
        $mailable = $this->makeMail(threshold: ExpiryAlertThreshold::FortyEightHours);
        $mailable->build();

        $this->assertSame(48, $mailable->viewData['hoursRemaining']);
    }

    public function testViewDataContainsProductName(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();

        $this->assertSame('Widget Pro', $mailable->viewData['productName']);
    }

    public function testViewDataContainsSalePrice(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();

        $this->assertSame(49.99, $mailable->viewData['salePrice']);
    }

    public function testViewDataContainsOriginalPrice(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();

        $this->assertSame(79.99, $mailable->viewData['originalPrice']);
    }

    public function testViewDataContainsDiscountPercentage(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();

        $this->assertSame(38, $mailable->viewData['discountPercentage']);
    }

    public function testViewDataContainsExpiresAt(): void
    {
        $expiresAt = new \DateTime('+20 hours');
        $offer = $this->makeOffer(endDate: $expiresAt);
        $mailable = $this->makeMail(offer: $offer);
        $mailable->build();

        $this->assertSame($expiresAt->format('Y-m-d'), $mailable->viewData['expiresAt']->format('Y-m-d'));
    }

    public function testViewDataContainsMerchant(): void
    {
        $merchant = $this->makeMerchant();
        $mailable = $this->makeMail(merchant: $merchant);
        $mailable->build();

        $this->assertSame($merchant, $mailable->viewData['merchant']);
    }

    public function testViewDataContainsOffer(): void
    {
        $offer = $this->makeOffer();
        $mailable = $this->makeMail(offer: $offer);
        $mailable->build();

        $this->assertSame($offer, $mailable->viewData['offer']);
    }

    public function testManageUrlContainsOfferId(): void
    {
        $offer = $this->makeOffer(id: 42);
        $mailable = $this->makeMail(offer: $offer);
        $mailable->build();

        $this->assertStringContainsString('/merchant/offers/42', $mailable->viewData['manageUrl']);
    }

    public function testRendersWithMerchantName(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();
        $html = $mailable->render();

        $this->assertStringContainsString('Acme Corp', $html);
    }

    public function testRendersWithProductName(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();
        $html = $mailable->render();

        $this->assertStringContainsString('Widget Pro', $html);
    }

    public function testFallsBackToOfferIdWhenNoProduct(): void
    {
        $offer = $this->makeOffer(id: 99);
        $offer->product = null;

        $mailable = $this->makeMail(offer: $offer);
        $mailable->build();

        $this->assertStringContainsString('Offer #99', $mailable->viewData['productName']);
    }

    // -------------------------------------------------------------------------
    // Factories
    // -------------------------------------------------------------------------

    private function makeMail(
        ?Merchant            $merchant = null,
        ?ProductOffer        $offer = null,
        ExpiryAlertThreshold $threshold = ExpiryAlertThreshold::TwentyFourHours,
    ): OfferExpiringSoonMerchantMail
    {
        return new OfferExpiringSoonMerchantMail(
            merchant: $merchant ?? $this->makeMerchant(),
            offer: $offer ?? $this->makeOffer(),
            threshold: $threshold,
        );
    }

    private function makeMerchant(): Merchant
    {
        $merchant = new Merchant();
        $merchant->name = 'Acme Corp';
        $merchant->email = 'merchant@acme.com';
        return $merchant;
    }

    private function makeOffer(int $id = 10, ?\DateTimeInterface $endDate = null): ProductOffer
    {
        $product = new Product();
        $product->name = 'Widget Pro';
        $product->price = 32.99;
        $product->sale_price = 18;

        $offer = new ProductOffer();
        $offer->id = $id;
        $offer->sale_price = 49.99;
        $offer->original_price = 79.99;
        $offer->discount_percentage = 37;
        $offer->end_date = $endDate ?? new \DateTime('+20 hours');
        $offer->product = $product;

        return $offer;
    }
}