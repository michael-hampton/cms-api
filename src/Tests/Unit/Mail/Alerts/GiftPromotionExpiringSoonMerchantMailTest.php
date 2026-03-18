<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mail\Alerts;

use App\Enums\Alerts\ExpiryAlertThreshold;
use App\Mail\Alerts\GiftPromotionExpiringSoonMerchantMail;
use App\Models\GiftPromotion;
use App\Models\Merchant;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class GiftPromotionExpiringSoonMerchantMailTest extends FunctionalTestCase
{
    public function testSubjectContainsHoursRemainingAndPromotionName(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();

        $this->assertStringContainsString('24 hours', $mailable->subject);
        $this->assertStringContainsString('Buy One Get One', $mailable->subject);
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

        $this->assertSame('emails.alerts.gift-promotion-expiring-soon-merchant', $mailable->markdown);
    }

    public function testViewDataContainsHoursRemaining(): void
    {
        $mailable = $this->makeMail(threshold: ExpiryAlertThreshold::FortyEightHours);
        $mailable->build();

        $this->assertSame(48, $mailable->viewData['hoursRemaining']);
    }

    public function testViewDataContainsPromotionName(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();

        $this->assertSame('Buy One Get One', $mailable->viewData['promotionName']);
    }

    public function testViewDataContainsGiftTypeFormatted(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();

        // gift_type = 'product' → ucfirst → 'Product'
        $this->assertSame('Product', $mailable->viewData['giftType']);
    }

    public function testViewDataContainsQuantityRuleFormatted(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();

        // quantity_rule = 'one_per_qualifying' → 'One per qualifying'
        $this->assertSame('One per qualifying', $mailable->viewData['quantityRule']);
    }

    public function testViewDataContainsExpiresAt(): void
    {
        $expiresAt = new \DateTime('+20 hours');
        $promotion = $this->makePromotion(endsAt: $expiresAt);
        $mailable = $this->makeMail(promotion: $promotion);
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

    public function testViewDataContainsPromotion(): void
    {
        $promotion = $this->makePromotion();
        $mailable = $this->makeMail(promotion: $promotion);
        $mailable->build();

        $this->assertSame($promotion, $mailable->viewData['promotion']);
    }

    public function testManageUrlContainsPromotionId(): void
    {
        $promotion = $this->makePromotion(id: 77);
        $mailable = $this->makeMail(promotion: $promotion);
        $mailable->build();

        $this->assertStringContainsString('/merchant/promotions/77', $mailable->viewData['manageUrl']);
    }

    public function testFallsBackToIdWhenNoName(): void
    {
        $promotion = $this->makePromotion(id: 55);
        $promotion->name = null;

        $mailable = $this->makeMail(promotion: $promotion);
        $mailable->build();

        $this->assertSame('Promotion #55', $mailable->viewData['promotionName']);
    }

    public function testRendersWithMerchantName(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();
        $html = $mailable->render();

        $this->assertStringContainsString('Acme Corp', $html);
    }

    public function testRendersWithPromotionName(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();
        $html = $mailable->render();

        $this->assertStringContainsString('Buy One Get One', $html);
    }

    // -------------------------------------------------------------------------
    // Factories
    // -------------------------------------------------------------------------

    private function makeMail(
        ?Merchant            $merchant = null,
        ?GiftPromotion       $promotion = null,
        ExpiryAlertThreshold $threshold = ExpiryAlertThreshold::TwentyFourHours,
    ): GiftPromotionExpiringSoonMerchantMail
    {
        return new GiftPromotionExpiringSoonMerchantMail(
            merchant: $merchant ?? $this->makeMerchant(),
            promotion: $promotion ?? $this->makePromotion(),
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

    private function makePromotion(int $id = 30, ?\DateTimeInterface $endsAt = null): GiftPromotion
    {
        $promotion = $this->createPartialMock(GiftPromotion::class, ['triggers']);
        $promotion->id = $id;
        $promotion->name = 'Buy One Get One';
        $promotion->gift_type = 'product';
        $promotion->quantity_rule = 'one_per_qualifying';
        $promotion->ends_at = $endsAt ?? new \DateTime('+20 hours');

        // triggers(true) is called inside build() to get count
        $promotion->method('triggers')
            ->willReturn(collect([new \stdClass(), new \stdClass()])); // 2 triggers

        return $promotion;
    }
}