<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mail\Alerts;

use App\Enums\Alerts\ExpiryAlertThreshold;
use App\Mail\Alerts\GiftPromotionExpiringSoonMemberMail;
use App\Models\GiftPromotion;
use App\Models\Member;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class GiftPromotionExpiringSoonMemberMailTest extends FunctionalTestCase
{
    public function testSubjectContainsHoursRemaining(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();

        $this->assertStringContainsString('24 hours', $mailable->subject);
    }

    public function testSubjectReflects48HourThreshold(): void
    {
        $mailable = $this->makeMail(threshold: ExpiryAlertThreshold::FortyEightHours);
        $mailable->build();

        $this->assertStringContainsString('48 hours', $mailable->subject);
    }

    public function testRecipientIsSetToMemberEmail(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();

        $this->assertNotEmpty($mailable->to);
        $this->assertSame('member@example.com', $mailable->to[0]['address']);
    }

    public function testUsesMarkdownTemplate(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();

        $this->assertSame('emails.alerts.gift-promotion-expiring-soon-member', $mailable->markdown);
    }

    public function testViewDataContainsHoursRemaining(): void
    {
        $mailable = $this->makeMail(threshold: ExpiryAlertThreshold::FortyEightHours);
        $mailable->build();

        $this->assertSame(48, $mailable->viewData['hoursRemaining']);
    }

    public function testViewDataContainsMember(): void
    {
        $member = $this->makeMember();
        $mailable = $this->makeMail(member: $member);
        $mailable->build();

        $this->assertSame($member, $mailable->viewData['member']);
    }

    public function testViewDataContainsPromotion(): void
    {
        $promotion = $this->makePromotion();
        $mailable = $this->makeMail(promotion: $promotion);
        $mailable->build();

        $this->assertSame($promotion, $mailable->viewData['promotion']);
    }

    public function testViewDataContainsGiftTypeFormatted(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();

        // gift_type = 'subscription' → ucfirst → 'Subscription'
        $this->assertSame('Subscription', $mailable->viewData['giftType']);
    }

    public function testViewDataContainsExpiresAt(): void
    {
        $expiresAt = new \DateTime('+20 hours');
        $promotion = $this->makePromotion(endsAt: $expiresAt);
        $mailable = $this->makeMail(promotion: $promotion);
        $mailable->build();

        $this->assertSame($expiresAt->format('Y-m-d'), $mailable->viewData['expiresAt']->format('Y-m-d'));
    }

    public function testViewDataContainsShopUrl(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();

        $this->assertStringContainsString('/shop', $mailable->viewData['shopUrl']);
    }

    public function testViewDataUsesPromotionNameWhenSet(): void
    {
        $promotion = $this->makePromotion();
        $mailable = $this->makeMail(promotion: $promotion);
        $mailable->build();

        $this->assertSame('Buy One Get One', $mailable->viewData['promotionName']);
    }

    public function testViewDataFallsBackWhenNoPromotionName(): void
    {
        $promotion = $this->makePromotion();
        $promotion->name = null;

        $mailable = $this->makeMail(promotion: $promotion);
        $mailable->build();

        $this->assertSame('a special promotion', $mailable->viewData['promotionName']);
    }

    public function testRendersWithMemberFirstName(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();
        $html = $mailable->render();

        $this->assertStringContainsString('Alice', $html);
    }

    public function testRendersWithGiftType(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();
        $html = $mailable->render();

        $this->assertStringContainsString('subscription', $html);
    }

    public function testDoesNotExposeInternalTriggerConditionsInRenderedHtml(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();
        $html = $mailable->render();

        // Members must not see internal trigger logic
        $this->assertStringNotContainsString('trigger', strtolower($html));
        $this->assertStringNotContainsString('quantity_rule', $html);
    }

    // -------------------------------------------------------------------------
    // Factories
    // -------------------------------------------------------------------------

    private function makeMail(
        ?Member              $member = null,
        ?GiftPromotion       $promotion = null,
        ExpiryAlertThreshold $threshold = ExpiryAlertThreshold::TwentyFourHours,
    ): GiftPromotionExpiringSoonMemberMail
    {
        return new GiftPromotionExpiringSoonMemberMail(
            member: $member ?? $this->makeMember(),
            promotion: $promotion ?? $this->makePromotion(),
            threshold: $threshold,
        );
    }

    private function makeMember(): Member
    {
        $member = new Member();
        $member->email = 'member@example.com';
        $member->first_name = 'Alice';
        return $member;
    }

    private function makePromotion(?\DateTimeInterface $endsAt = null): GiftPromotion
    {
        $promotion = new GiftPromotion();
        $promotion->name = 'Buy One Get One';
        $promotion->gift_type = 'subscription';
        $promotion->ends_at = $endsAt ?? new \DateTime('+20 hours');
        return $promotion;
    }
}