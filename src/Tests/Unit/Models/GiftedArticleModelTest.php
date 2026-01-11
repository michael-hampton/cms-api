<?php

namespace App\Tests\Unit\Models;

use App\Models\Member;
use App\Models\Page;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class GiftedArticleModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIsClaimedReturnsTrueForClaimedGift(): void
    {
        $gift = $this->createGiftedArticle(['status' => 'claimed']);

        $this->assertTrue($gift->isClaimed());
    }

    public function testIsClaimedReturnsFalseForPendingGift(): void
    {
        $gift = $this->createGiftedArticle(['status' => 'pending']);

        $this->assertFalse($gift->isClaimed());
    }

    public function testIsExpiredReturnsTrueForExpiredStatus(): void
    {
        $gift = $this->createGiftedArticle(['status' => 'expired']);

        $this->assertTrue($gift->isExpired());
    }

    public function testIsExpiredReturnsTrueWhenExpiredAtPassed(): void
    {
        $gift = $this->createGiftedArticle([
            'status' => 'pending',
            'expires_at' => now_datetime()->modify('-1 day')->format('Y-m-d H:i:s')
        ]);

        $this->assertTrue($gift->isExpired());
    }

    public function testIsExpiredReturnsFalseForActiveGift(): void
    {
        $gift = $this->createGiftedArticle([
            'status' => 'pending',
            'expires_at' => now_datetime()->modify('+7 days')->format('Y-m-d H:i:s')
        ]);

        $this->assertFalse($gift->isExpired());
    }

    public function testIsPendingReturnsTrueForPendingGift(): void
    {
        $gift = $this->createGiftedArticle([
            'status' => 'pending',
            'expires_at' => now_datetime()->modify('+7 days')->format('Y-m-d H:i:s')
        ]);

        $this->assertTrue($gift->isPending());
    }

    public function testIsPendingReturnsFalseForClaimedGift(): void
    {
        $gift = $this->createGiftedArticle(['status' => 'claimed']);

        $this->assertFalse($gift->isPending());
    }

    public function testIsPendingReturnsFalseForExpiredGift(): void
    {
        $gift = $this->createGiftedArticle([
            'status' => 'pending',
            'expires_at' => now_datetime()->modify('-1 day')->format('Y-m-d H:i:s')
        ]);

        $this->assertFalse($gift->isPending());
    }

    public function testClaimSuccessfullyClaimsPendingGift(): void
    {
        $gift = $this->createGiftedArticle([
            'status' => 'pending',
            'expires_at' => now_datetime()->modify('+7 days')->format('Y-m-d H:i:s')
        ]);
        $member = $this->createMember();

        $result = $gift->claim($member->id);

        $this->assertTrue($result);
        $gift = $gift->fresh();
        $this->assertEquals('claimed', $gift->status);
        $this->assertEquals($member->id, $gift->recipient_member_id);
        $this->assertNotNull($gift->claimed_at);
    }

    public function testClaimFailsForAlreadyClaimedGift(): void
    {
        $gift = $this->createGiftedArticle(['status' => 'claimed']);
        $member = $this->createMember();

        $result = $gift->claim($member->id);

        $this->assertFalse($result);
    }

    public function testClaimFailsForExpiredGift(): void
    {
        $gift = $this->createGiftedArticle([
            'status' => 'pending',
            'expires_at' => now_datetime()->modify('-1 day')->format('Y-m-d H:i:s')
        ]);
        $member = $this->createMember();

        $result = $gift->claim($member->id);

        $this->assertFalse($result);
    }

    public function testPageRelationship(): void
    {
        $page = $this->createPage();
        $gift = $this->createGiftedArticle(['page_id' => $page->id]);

        $relatedPage = $gift->page;

        $this->assertInstanceOf(Page::class, $relatedPage);
        $this->assertEquals($page->id, $relatedPage->id);
    }

    public function testGiftedByRelationship(): void
    {
        $gifter = $this->createMember();
        $gift = $this->createGiftedArticle(['gifted_by_member_id' => $gifter->id]);

        $giftedBy = $gift->giftedBy;

        $this->assertInstanceOf(Member::class, $giftedBy);
        $this->assertEquals($gifter->id, $giftedBy->id);
    }

    public function testRecipientRelationship(): void
    {
        $recipient = $this->createMember();
        $gift = $this->createGiftedArticle([
            'recipient_member_id' => $recipient->id,
            'status' => 'claimed'
        ]);

        $relatedRecipient = $gift->recipient;

        $this->assertInstanceOf(Member::class, $relatedRecipient);
        $this->assertEquals($recipient->id, $relatedRecipient->id);
    }
}