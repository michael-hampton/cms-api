<?php

namespace App\Tests\Functional\Controllers\Members;

use App\Models\GiftedArticle;
use App\Models\Member;
use App\Models\Page;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class GiftedArticlesControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Member $member;
    private Page $page;

    public function testIndexRedirectsWhenNotAuthenticated(): void
    {
        $this->unauthenticateMember();

        $response = $this->getForSiteUnauthenticated('/member/gifted-articles');

        $this->assertResponseStatus(302, $response);
        $this->assertStringContainsString('/member/login', $response->getHeaders()['Location'] ?? '');
    }

    public function testIndexDisplaysGiftedArticles(): void
    {
        $this->actingAsMember($this->member);

        $this->createMemberGiftAllowance(['member_id' => $this->member->id]);

        $received = GiftedArticle::create([
            'page_id' => $this->page->id,
            'gifted_by_member_id' => $this->createMember(['email' => 'gifter@example.com'])->id,
            'site_id' => $this->siteId,
            'recipient_email' => $this->member->email,
            'recipient_member_id' => $this->member->id,
            'gift_token' => bin2hex(random_bytes(32)),
            'gifted_at' => now_datetime()->format('Y-m-d H:i:s'),
            'status' => 'claimed'
        ]);

        $response = $this->getForSite('/member/gifted-articles');

        $this->assertResponseOk($response);
        $content = $response->getContent();
        $this->assertStringContainsString($this->member->full_name, $content);
    }

    public function testShowGiftFormRedirectsWhenNotAuthenticated(): void
    {
        $this->unauthenticateMember();

        $response = $this->getForSiteUnauthenticated("/gift-article/{$this->page->slug}");

        $this->assertResponseStatus(302, $response);
        $this->assertStringContainsString('/member/login', $response->getHeaders()['Location'] ?? '');
    }

    public function testShowGiftFormDisplaysForm(): void
    {
        $this->actingAsMember($this->member);
        $this->createMemberGiftAllowance(['member_id' => $this->member->id]);

        $response = $this->getForSiteUnauthenticated("/gift-article/{$this->page->slug}");

        $this->assertResponseOk($response);
        $content = $response->getContent();
        $this->assertStringContainsString($this->page->slug, $content);
    }

    public function testShowGiftFormReturns404ForNonExistentArticle(): void
    {
        $this->actingAsMember($this->member);

        $response = $this->getForSite("/gift-article/non-existent-slug");

        $this->assertResponseStatus(404, $response);
    }

    public function testGiftArticleSuccess(): void
    {
        $this->actingAsMember($this->member);
        $this->createMemberGiftAllowance([
            'member_id' => $this->member->id,
            'annual_gift_limit' => 10,
            'gifts_used_this_year' => 0
        ]);

        $response = $this->postForSiteUnauthenticated(
            "/gift-article/{$this->page->slug}",
            [
                'recipient_email' => 'recipient@example.com',
                'personal_message' => 'Check this out!'
            ],
            [],
            ['Accept' => 'application/json']
        );

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['data']['success']);
        $this->assertArrayHasKey('share_link', $data['data']);
        $this->assertArrayHasKey('gift_id', $data['data']);

        $this->assertDatabaseHas('gifted_articles', [
            'page_id' => $this->page->id,
            'gifted_by_member_id' => $this->member->id,
            'recipient_email' => 'recipient@example.com',
            'status' => 'pending'
        ]);
    }

    public function testGiftArticleFailsWithInvalidEmail(): void
    {
        $this->actingAsMember($this->member);

        $response = $this->postForSiteUnauthenticated(
            "/gift-article/{$this->page->slug}",
            ['recipient_email' => 'invalid-email'],
            [],
            ['Accept' => 'application/json']
        );

        $this->assertResponseStatus(400, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['data']['success']);
    }

    public function testGiftArticleFailsWhenLimitReached(): void
    {
        $this->actingAsMember($this->member);
        $this->createMemberGiftAllowance([
            'member_id' => $this->member->id,
            'annual_gift_limit' => 10,
            'gifts_used_this_year' => 10
        ]);

        $response = $this->postForSiteUnauthenticated(
            "/gift-article/{$this->page->slug}",
            ['recipient_email' => 'recipient@example.com'],
            [],
            ['Accept' => 'application/json']
        );

        $this->assertResponseStatus(400, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['data']['success']);
        $this->assertStringContainsString('limit', $data['data']['message']);
    }

    public function testGiftArticleFailsForNonExistentArticle(): void
    {
        $this->actingAsMember($this->member);

        $response = $this->postForSiteUnauthenticated(
            "/gift-article/non-existent-slug",
            ['recipient_email' => 'recipient@example.com'],
            [],
            ['Accept' => 'application/json']
        );

        $this->assertResponseStatus(404, $response);
    }

    public function testClaimRedirectsWhenNotAuthenticated(): void
    {
        $this->unauthenticateMember();

        $gift = GiftedArticle::create([
            'page_id' => $this->page->id,
            'gifted_by_member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'recipient_email' => 'recipient@example.com',
            'gift_token' => 'test-token-123',
            'gifted_at' => now_datetime()->format('Y-m-d H:i:s'),
            'status' => 'pending'
        ]);

        $response = $this->getForSiteUnauthenticated("/gift/{$gift->gift_token}");

        $this->assertResponseStatus(302, $response);
        $this->assertStringContainsString('/member/login', $response->getHeaders()['Location'] ?? '');
    }

    public function testClaimSuccessfully(): void
    {
        $recipient = $this->createMember(['email' => 'recipient@example.com']);
        $this->actingAsMember($recipient);

        $gift = GiftedArticle::create([
            'page_id' => $this->page->id,
            'gifted_by_member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'recipient_email' => 'recipient@example.com',
            'gift_token' => bin2hex(random_bytes(32)),
            'gifted_at' => now_datetime()->format('Y-m-d H:i:s'),
            'status' => 'pending'
        ]);

        $response = $this->getForSite("/gift/{$gift->gift_token}");

        $this->assertResponseOk($response);
        $content = $response->getContent();
        $this->assertStringContainsString('claimed successfully', $content);

        $gift = $gift->fresh();
        $this->assertEquals('claimed', $gift->status);
        $this->assertEquals($recipient->id, $gift->recipient_member_id);
    }

    public function testClaimFailsForExpiredGift(): void
    {
        $recipient = $this->createMember(['email' => 'recipient@example.com']);
        $this->actingAsMember($recipient);

        $gift = GiftedArticle::create([
            'page_id' => $this->page->id,
            'gifted_by_member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'recipient_email' => 'recipient@example.com',
            'gift_token' => bin2hex(random_bytes(32)),
            'gifted_at' => now_datetime()->format('Y-m-d H:i:s'),
            'status' => 'expired'
        ]);

        $response = $this->getForSite("/gift/{$gift->gift_token}");

        $content = $response->getContent();
        $this->assertStringContainsString('expired', $content);
    }

    public function testClaimFailsForInvalidToken(): void
    {
        $this->actingAsMember($this->member);

        $response = $this->getForSite("/gift/invalid-token-xyz");

        $content = $response->getContent();
        $this->assertStringContainsString('Invalid', $content);
    }

    public function testClaimFailsForWrongEmailRecipient(): void
    {
        $wrongMember = $this->createMember(['email' => 'wrong@example.com']);
        $this->actingAsMember($wrongMember);

        $gift = GiftedArticle::create([
            'page_id' => $this->page->id,
            'gifted_by_member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'recipient_email' => 'correct@example.com',
            'gift_token' => bin2hex(random_bytes(32)),
            'gifted_at' => now_datetime()->format('Y-m-d H:i:s'),
            'status' => 'pending'
        ]);

        $response = $this->getForSite("/gift/{$gift->gift_token}");

        $content = $response->getContent();
        $this->assertStringContainsString('different email', $content);
    }

    public function testClaimAlreadyClaimedBySameUserRedirects(): void
    {
        $recipient = $this->createMember(['email' => 'recipient@example.com']);
        $this->actingAsMember($recipient);

        $gift = GiftedArticle::create([
            'page_id' => $this->page->id,
            'gifted_by_member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'recipient_email' => 'recipient@example.com',
            'recipient_member_id' => $recipient->id,
            'gift_token' => bin2hex(random_bytes(32)),
            'gifted_at' => now_datetime()->format('Y-m-d H:i:s'),
            'claimed_at' => now_datetime()->format('Y-m-d H:i:s'),
            'status' => 'claimed'
        ]);

        $response = $this->getForSite("/gift/{$gift->gift_token}");

        $this->assertResponseStatus(302, $response);
        $this->assertStringContainsString($this->page->slug, $response->getHeaders()['Location'] ?? '');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->member = $this->createMember();
        $this->page = $this->createPage(['status' => 'published']);
    }
}