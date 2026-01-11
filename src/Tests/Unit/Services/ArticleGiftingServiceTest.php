<?php

namespace App\Tests\Unit\Services;

use App\Framework\Support\Collection;
use App\Models\GiftedArticle;
use App\Models\Member;
use App\Models\MemberGiftAllowance;
use App\Models\Page;
use App\Repositories\Members\GiftedArticleRepository;
use App\Services\Members\ArticleGiftingService;
use Mockery;
use PHPUnit\Framework\TestCase;

class ArticleGiftingServiceTest extends TestCase
{
    private $repository;
    private $service;

    public function testCanMemberGiftReturnsCorrectInfo(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $siteId = 1;

        $allowance = Mockery::mock(MemberGiftAllowance::class)->makePartial();
        $allowance->annual_gift_limit = 10;
        $allowance->gifts_used_this_year = 3;
        $allowance->shouldReceive('canGift')->once()->andReturn(true);
        $allowance->shouldReceive('getRemainingGifts')->once()->andReturn(7);

        $this->repository
            ->shouldReceive('getOrCreateAllowance')
            ->once()
            ->with($member->id, $siteId)
            ->andReturn($allowance);

        $result = $this->service->canMemberGift($member, $siteId);

        $this->assertTrue($result['can_gift']);
        $this->assertEquals(7, $result['remaining_gifts']);
        $this->assertEquals(10, $result['annual_limit']);
        $this->assertEquals(3, $result['used_this_year']);
    }

    public function testCanMemberGiftReturnsFalseWhenLimitReached(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $siteId = 1;

        $allowance = Mockery::mock(MemberGiftAllowance::class)->makePartial();
        $allowance->annual_gift_limit = 10;
        $allowance->gifts_used_this_year = 10;
        $allowance->shouldReceive('canGift')->once()->andReturn(false);
        $allowance->shouldReceive('getRemainingGifts')->once()->andReturn(0);

        $this->repository
            ->shouldReceive('getOrCreateAllowance')
            ->once()
            ->with($member->id, $siteId)
            ->andReturn($allowance);

        $result = $this->service->canMemberGift($member, $siteId);

        $this->assertFalse($result['can_gift']);
        $this->assertEquals(0, $result['remaining_gifts']);
    }

    public function testGiftArticleSuccess(): void
    {
        $gifter = Mockery::mock(Member::class)->makePartial();
        $gifter->id = 1;
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;
        $siteId = 1;
        $recipientEmail = 'recipient@example.com';
        $personalMessage = 'Enjoy this article!';

        $allowance = Mockery::mock(MemberGiftAllowance::class);
        $allowance->shouldReceive('canGift')->once()->andReturn(true);
        $allowance->shouldReceive('incrementUsage')->once()->andReturn(true);

        $gift = Mockery::mock(GiftedArticle::class);

        $this->repository
            ->shouldReceive('getOrCreateAllowance')
            ->once()
            ->with($gifter->id, $siteId)
            ->andReturn($allowance);

        $this->repository
            ->shouldReceive('findExistingGift')
            ->once()
            ->with($page->id, $gifter->id, $recipientEmail)
            ->andReturn(null);

        $this->repository
            ->shouldReceive('createGift')
            ->once()
            ->with(Mockery::on(function ($arg) use ($page, $gifter, $siteId, $recipientEmail, $personalMessage) {
                return $arg['page_id'] === $page->id
                    && $arg['gifted_by_member_id'] === $gifter->id
                    && $arg['site_id'] === $siteId
                    && $arg['recipient_email'] === $recipientEmail
                    && $arg['personal_message'] === $personalMessage;
            }))
            ->andReturn($gift);

        $result = $this->service->giftArticle(
            $gifter,
            $page,
            $recipientEmail,
            $siteId,
            $personalMessage
        );

        $this->assertTrue($result['success']);
        $this->assertSame($gift, $result['gift']);
        $this->assertEquals('Article gifted successfully', $result['message']);
    }

    public function testGiftArticleFailsWhenLimitReached(): void
    {
        $gifter = Mockery::mock(Member::class)->makePartial();
        $gifter->id = 1;
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;
        $siteId = 1;

        $allowance = Mockery::mock(MemberGiftAllowance::class);
        $allowance->shouldReceive('canGift')->once()->andReturn(false);

        $this->repository
            ->shouldReceive('getOrCreateAllowance')
            ->once()
            ->with($gifter->id, $siteId)
            ->andReturn($allowance);

        $result = $this->service->giftArticle(
            $gifter,
            $page,
            'recipient@example.com',
            $siteId
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('limit', $result['message']);
    }

    public function testGiftArticleFailsWhenAlreadyGiftedToSameEmail(): void
    {
        $gifter = Mockery::mock(Member::class)->makePartial();
        $gifter->id = 1;
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;
        $siteId = 1;
        $recipientEmail = 'recipient@example.com';

        $allowance = Mockery::mock(MemberGiftAllowance::class);
        $allowance->shouldReceive('canGift')->once()->andReturn(true);

        $existingGift = Mockery::mock(GiftedArticle::class);

        $this->repository
            ->shouldReceive('getOrCreateAllowance')
            ->once()
            ->with($gifter->id, $siteId)
            ->andReturn($allowance);

        $this->repository
            ->shouldReceive('findExistingGift')
            ->once()
            ->with($page->id, $gifter->id, $recipientEmail)
            ->andReturn($existingGift);

        $result = $this->service->giftArticle(
            $gifter,
            $page,
            $recipientEmail,
            $siteId
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already gifted', $result['message']);
    }

    public function testGiftArticleTrimsAndLowercasesEmail(): void
    {
        $gifter = Mockery::mock(Member::class)->makePartial();
        $gifter->id = 1;
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;
        $siteId = 1;
        $recipientEmail = 'recipient@example.com';

        $allowance = Mockery::mock(MemberGiftAllowance::class);
        $allowance->shouldReceive('canGift')->once()->andReturn(true);
        $allowance->shouldReceive('incrementUsage')->once()->andReturn(true);

        $gift = Mockery::mock(GiftedArticle::class);

        $this->repository
            ->shouldReceive('getOrCreateAllowance')
            ->once()
            ->andReturn($allowance);

        $this->repository
            ->shouldReceive('findExistingGift')
            ->once()
            ->with($page->id, $gifter->id, 'recipient@example.com')
            ->andReturn(null);

        $this->repository
            ->shouldReceive('createGift')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return $arg['recipient_email'] === 'recipient@example.com';
            }))
            ->andReturn($gift);

        $result = $this->service->giftArticle(
            $gifter,
            $page,
            $recipientEmail,
            $siteId
        );

        $this->assertTrue($result['success']);
    }

    public function testGenerateShareLinkReturnsCorrectUrl(): void
    {
        $gift = Mockery::mock(GiftedArticle::class)->makePartial();;
        $gift->gift_token = 'abc123xyz';

        $result = $this->service->generateShareLink($gift);

        $this->assertStringContainsString('/gift/abc123xyz', $result);
    }

    public function testClaimGiftSuccessfully(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $member->email = 'recipient@example.com';
        $token = 'test-token-123';

        $gift = Mockery::mock(GiftedArticle::class)->makePartial();
        $gift->recipient_email = 'recipient@example.com';
        $gift->shouldReceive('isExpired')->once()->andReturn(false);
        $gift->shouldReceive('isClaimed')->once()->andReturn(false);
        $gift->shouldReceive('claim')->once()->with($member->id)->andReturn(true);

        $this->repository
            ->shouldReceive('findByToken')
            ->once()
            ->with($token)
            ->andReturn($gift);

        $result = $this->service->claimGift($token, $member);

        $this->assertTrue($result['success']);
        $this->assertSame($gift, $result['gift']);
        $this->assertEquals('Gift claimed successfully! You now have access to this article.', $result['message']);
    }

    public function testClaimGiftFailsForInvalidToken(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $token = 'invalid-token';

        $this->repository
            ->shouldReceive('findByToken')
            ->once()
            ->with($token)
            ->andReturn(null);

        $result = $this->service->claimGift($token, $member);

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid gift link', $result['message']);
    }

    public function testClaimGiftFailsForExpiredGift(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $token = 'test-token-123';

        $gift = Mockery::mock(GiftedArticle::class);
        $gift->shouldReceive('isExpired')->once()->andReturn(true);

        $this->repository
            ->shouldReceive('findByToken')
            ->once()
            ->with($token)
            ->andReturn($gift);

        $result = $this->service->claimGift($token, $member);

        $this->assertFalse($result['success']);
        $this->assertEquals('This gift has expired', $result['message']);
    }

    public function testClaimGiftHandlesAlreadyClaimedBySameMember(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $token = 'test-token-123';

        $gift = Mockery::mock(GiftedArticle::class)->makePartial();
        $gift->recipient_member_id = 1;
        $gift->shouldReceive('isExpired')->once()->andReturn(false);
        $gift->shouldReceive('isClaimed')->once()->andReturn(true);

        $this->repository
            ->shouldReceive('findByToken')
            ->once()
            ->with($token)
            ->andReturn($gift);

        $result = $this->service->claimGift($token, $member);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['already_claimed']);
        $this->assertEquals('You already have access to this article', $result['message']);
    }

    public function testClaimGiftFailsWhenClaimedByDifferentMember(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $token = 'test-token-123';

        $gift = Mockery::mock(GiftedArticle::class)->makePartial();
        $gift->recipient_member_id = 2; // Different member
        $gift->shouldReceive('isExpired')->once()->andReturn(false);
        $gift->shouldReceive('isClaimed')->once()->andReturn(true);

        $this->repository
            ->shouldReceive('findByToken')
            ->once()
            ->with($token)
            ->andReturn($gift);

        $result = $this->service->claimGift($token, $member);

        $this->assertFalse($result['success']);
        $this->assertEquals('This gift has already been claimed', $result['message']);
    }

    public function testClaimGiftFailsForWrongEmailRecipient(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $member->email = 'wrong@example.com';
        $token = 'test-token-123';

        $gift = Mockery::mock(GiftedArticle::class)->makePartial();
        $gift->recipient_email = 'correct@example.com';
        $gift->shouldReceive('isExpired')->once()->andReturn(false);
        $gift->shouldReceive('isClaimed')->once()->andReturn(false);

        $this->repository
            ->shouldReceive('findByToken')
            ->once()
            ->with($token)
            ->andReturn($gift);

        $result = $this->service->claimGift($token, $member);

        $this->assertFalse($result['success']);
        $this->assertEquals('This gift was sent to a different email address', $result['message']);
    }

    public function testClaimGiftHandlesCaseInsensitiveEmailMatch(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $member->email = 'recipient@example.com';
        $token = 'test-token-123';

        $gift = Mockery::mock(GiftedArticle::class)->makePartial();
        $gift->recipient_email = 'recipient@example.com';
        $gift->shouldReceive('isExpired')->once()->andReturn(false);
        $gift->shouldReceive('isClaimed')->once()->andReturn(false);
        $gift->shouldReceive('claim')->once()->with($member->id)->andReturn(true);

        $this->repository
            ->shouldReceive('findByToken')
            ->once()
            ->with($token)
            ->andReturn($gift);

        $result = $this->service->claimGift($token, $member);

        $this->assertTrue($result['success']);
    }

    public function testGetGiftedArticlesForMemberReturnsReceivedAndGiven(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $siteId = 1;

        $receivedGifts = new Collection([]);
        $givenGifts = new Collection([]);

        $this->repository
            ->shouldReceive('getReceivedGifts')
            ->once()
            ->with($member->id, $siteId)
            ->andReturn($receivedGifts);

        $this->repository
            ->shouldReceive('getGiftsByMember')
            ->once()
            ->with($member->id, $siteId)
            ->andReturn($givenGifts);

        $result = $this->service->getGiftedArticlesForMember($member, $siteId);

        $this->assertArrayHasKey('received', $result);
        $this->assertArrayHasKey('given', $result);
        $this->assertSame($receivedGifts, $result['received']);
        $this->assertSame($givenGifts, $result['given']);
    }

    public function testAutoClaimGiftsOnSignupReturnsClaimCount(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $member->email = 'newuser@example.com';
        $expectedCount = 3;

        $this->repository
            ->shouldReceive('claimPendingGiftsForEmail')
            ->once()
            ->with($member->email, $member->id)
            ->andReturn($expectedCount);

        $result = $this->service->autoClaimGiftsOnSignup($member);

        $this->assertEquals($expectedCount, $result);
    }

    public function testAutoClaimGiftsOnSignupReturnsZeroWhenNoPendingGifts(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $member->email = 'newuser@example.com';

        $this->repository
            ->shouldReceive('claimPendingGiftsForEmail')
            ->once()
            ->with($member->email, $member->id)
            ->andReturn(0);

        $result = $this->service->autoClaimGiftsOnSignup($member);

        $this->assertEquals(0, $result);
    }

    public function testGiftArticleFailsWhenGiftingToSelf(): void
    {
        $gifter = Mockery::mock(Member::class)->makePartial();
        $gifter->id = 1;
        $gifter->email = 'user@example.com';
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;
        $siteId = 1;

        $allowance = Mockery::mock(MemberGiftAllowance::class);
        $allowance->shouldReceive('canGift')->once()->andReturn(true);

        $this->repository
            ->shouldReceive('getOrCreateAllowance')
            ->once()
            ->with($gifter->id, $siteId)
            ->andReturn($allowance);

        $result = $this->service->giftArticle(
            $gifter,
            $page,
            'user@example.com', // Same as gifter's email
            $siteId
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('cannot gift an article to yourself', $result['message']);
    }

    public function testGiftArticleFailsWhenGiftingToSelfCaseInsensitive(): void
    {
        $gifter = Mockery::mock(Member::class)->makePartial();
        $gifter->id = 1;
        $gifter->email = 'User@Example.com';
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;
        $siteId = 1;

        $allowance = Mockery::mock(MemberGiftAllowance::class);
        $allowance->shouldReceive('canGift')->once()->andReturn(true);

        $this->repository
            ->shouldReceive('getOrCreateAllowance')
            ->once()
            ->andReturn($allowance);

        $result = $this->service->giftArticle(
            $gifter,
            $page,
            'user@example.com', // Different case
            $siteId
        );

        $this->assertFalse($result['success']);
    }

    public function testCheckAndClaimGiftForPageClaimsPendingGift(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $member->email = 'user@example.com';

        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;

        $gift = Mockery::mock(GiftedArticle::class)->makePartial();
        $gift->shouldReceive('isClaimed')->once()->andReturn(false);
        $gift->shouldReceive('claim')->once()->with($member->id)->andReturn(true);

        $this->repository
            ->shouldReceive('findPendingGiftForMemberAndPage')
            ->once()
            ->with($member->id, $member->email, $page->id)
            ->andReturn($gift);

        $result = $this->service->checkAndClaimGiftForPage($member, $page);

        $this->assertSame($gift, $result);
    }

    public function testCheckAndClaimGiftForPageReturnsNullIfNoGift(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $member->email = 'user@example.com';

        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;

        $this->repository
            ->shouldReceive('findPendingGiftForMemberAndPage')
            ->once()
            ->with($member->id, $member->email, $page->id)
            ->andReturn(null);

        $result = $this->service->checkAndClaimGiftForPage($member, $page);

        $this->assertNull($result);
    }

    public function testCheckAndClaimGiftForPageReturnsNullIfAlreadyClaimed(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $member->email = 'user@example.com';

        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;

        $gift = Mockery::mock(GiftedArticle::class)->makePartial();
        $gift->shouldReceive('isClaimed')->once()->andReturn(true);

        $this->repository
            ->shouldReceive('findPendingGiftForMemberAndPage')
            ->once()
            ->andReturn($gift);

        $result = $this->service->checkAndClaimGiftForPage($member, $page);

        $this->assertNull($result);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = Mockery::mock(GiftedArticleRepository::class);
        $this->service = new ArticleGiftingService($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}