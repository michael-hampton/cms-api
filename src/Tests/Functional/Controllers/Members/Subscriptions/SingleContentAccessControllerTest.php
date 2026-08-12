<?php

namespace App\Tests\Functional\Controllers\Members\Subscriptions;

use App\Models\Member;
use App\Models\Newsletter;
use App\Models\SingleContentAccess;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class SingleContentAccessControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Member $testMember;

    public function testShowRequiresAuthentication(): void
    {
        $this->unauthenticateMember();

        $page = $this->createPage();

        $response = $this->getForSiteUnauthenticated("/member/single-access/show?type=page&id={$page->id}");

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/member/login', (string)$response->getHeader('Location'));
    }

    public function testShowReturnsPurchasePageForValidContent(): void
    {
        $page = $this->createPage(['title' => 'Premium Article']);

        $this->actingAsMember($this->testMember);

        $response = $this->getForSite("/member/single-access/show?type=page&id={$page->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringContainsString('Premium Article', $content);
    }

    public function testShowReturns400ForMissingParameters(): void
    {
        $this->actingAsMember($this->testMember);

        $response = $this->getForSite('/member/single-access/show');

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testShowReturns404ForNonExistentContent(): void
    {
        $this->actingAsMember($this->testMember);

        $response = $this->getForSite('/member/single-access/show?type=page&id=99999');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testShowRedirectsWhenMemberAlreadyHasAccess(): void
    {
        $page = $this->createPage();

        SingleContentAccess::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'content_type' => 'page',
            'content_id' => $page->id,
            'access_token' => SingleContentAccess::generateToken(),
            'price' => 9.99,
            'currency' => 'USD',
            'purchased_at' => now_datetime()->format('Y-m-d H:i:s'),
            'expires_at' => now_datetime()->modify('+7 days')->format('Y-m-d H:i:s'),
            'is_active' => true
        ]);

        $this->actingAsMember($this->testMember);

        $response = $this->getForSite("/member/single-access/show?type=page&id={$page->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('already', strtolower($response->getContent()));
    }

    public function testPurchaseRequiresAuthentication(): void
    {
        $page = $this->createPage();

        $response = $this->postForSiteUnauthenticated('/member/single-access/purchase', [
            'content_type' => 'page',
            'content_id' => $page->id
        ]);

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function testPurchaseReturns400ForInvalidContent(): void
    {
        $this->actingAsMember($this->testMember);

        $response = $this->postForSiteUnauthenticated('/member/single-access/purchase', [
            'content_type' => 'invalid',
            'content_id' => 1
        ]);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testPurchaseReturns400ForMissingParameters(): void
    {
        $this->actingAsMember($this->testMember);

        $response = $this->postForSiteUnauthenticated('/member/single-access/purchase', [
            'content_type' => 'page'
        ]);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testCompleteRequiresAuthentication(): void
    {
        $this->unauthenticateMember();

        $response = $this->postForSiteUnauthenticated('/member/single-access/complete', [
            'payment_intent_id' => 'pi_test123',
        ]);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testCompleteReturns400ForMissingPaymentIntent(): void
    {
        $this->actingAsMember($this->testMember);

        $response = $this->postForSite('/member/single-access/complete', []);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        //$this->assertStringContainsString('payment', strtolower($data['message']));
    }

    public function testIndexRequiresAuthentication(): void
    {
        $response = $this->getForSite('/member/single-access');

        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testIndexReturnsEmptyListForNewMember(): void
    {
        $this->actingAsMember($this->testMember);

        $response = $this->getForSite('/member/single-access');

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringNotContainsString('Premium Article', $content);
    }

    public function testIndexReturnsActiveAccessList(): void
    {
        $page1 = $this->createPage(['title' => 'Premium Article 1']);
        $page2 = $this->createPage(['title' => 'Premium Article 2']);

        SingleContentAccess::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'content_type' => 'page',
            'content_id' => $page1->id,
            'access_token' => SingleContentAccess::generateToken(),
            'price' => 9.99,
            'currency' => 'USD',
            'purchased_at' => now_datetime()->format('Y-m-d H:i:s'),
            'expires_at' => now_datetime()->modify('+7 days')->format('Y-m-d H:i:s'),
            'is_active' => true
        ]);

        SingleContentAccess::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'content_type' => 'page',
            'content_id' => $page2->id,
            'access_token' => SingleContentAccess::generateToken(),
            'price' => 4.99,
            'currency' => 'USD',
            'purchased_at' => now_datetime()->format('Y-m-d H:i:s'),
            'expires_at' => now_datetime()->modify('+30 days')->format('Y-m-d H:i:s'),
            'is_active' => true
        ]);

        $this->actingAsMember($this->testMember);

        $response = $this->getForSiteUnauthenticated('/member/single-access');

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringContainsString('Premium Article 1', $content);
        $this->assertStringContainsString('Premium Article 2', $content); //todo needs views
    }

    public function testIndexDoesNotShowExpiredAccess(): void
    {
        $page = $this->createPage(['title' => 'Expired Article']);

        SingleContentAccess::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'content_type' => 'page',
            'content_id' => $page->id,
            'access_token' => SingleContentAccess::generateToken(),
            'price' => 9.99,
            'currency' => 'USD',
            'purchased_at' => now_datetime()->modify('-14 days')->format('Y-m-d H:i:s'),
            'expires_at' => now_datetime()->modify('-7 days')->format('Y-m-d H:i:s'),
            'is_active' => true
        ]);

        $this->actingAsMember($this->testMember);

        $response = $this->getForSite('/member/single-access');

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringNotContainsString('Expired Article', $content);
    }

    public function testShowSupportsNewsletterContent(): void
    {
        $newsletter = Newsletter::create([
            'site_id' => $this->siteId,
            'title' => 'Premium Newsletter',
            'slug' => 'premium-newsletter',
            'content' => 'Newsletter content here',
            'status' => 'published',
            'interval' => 'weekly'
        ]);

        $this->actingAsMember($this->testMember);

        $response = $this->getForSite("/member/single-access/show?type=newsletter&id={$newsletter->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringContainsString('Premium Newsletter', $content); //todo needs view
    }

    public function testShowSupportsPageContent(): void
    {
        $page = $this->createPage(['title' => 'Premium Page']);

        $this->actingAsMember($this->testMember);

        $response = $this->getForSite("/member/single-access/show?type=page&id={$page->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringContainsString('Premium Page', $content); //todo needs view
    }

    public function testIndexShowsCorrectExpiryDates(): void
    {
        $page = $this->createPage(['title' => 'Expiring Soon']);

        $expiresAt = now_datetime()->modify('+3 days');

        SingleContentAccess::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'content_type' => 'page',
            'content_id' => $page->id,
            'access_token' => SingleContentAccess::generateToken(),
            'price' => 9.99,
            'currency' => 'USD',
            'purchased_at' => now_datetime()->format('Y-m-d H:i:s'),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'is_active' => true
        ]);

        $this->actingAsMember($this->testMember);

        $response = $this->getForSite('/member/single-access');

        $this->assertEquals(200, $response->getStatusCode());
        // Should show expiry information
        $content = $response->getContent();
        $this->assertStringContainsString('Expiring Soon', $content); //todo needs view
    }

//    public function testShowSupportsReportContent(): void
//    {
//        $report = $this->createPage(['title' => 'Premium Report', 'type' => 'report']);
//
//        $this->loginAsMember($this->testMember);
//
//        $response = $this->getForSiteUnauthenticated('/member/single-access/purchase?type=report&id=' . $report->id);
//
//        echo '<pre>';
//        print_r($response->getContent());
//
//        $this->assertEquals(200, $response->getStatusCode());
//        $content = $response->getContent();
//        $this->assertStringContainsString('Premium Report', $content);
//    }

    public function testShowDisplaysPricingInformation(): void
    {
        $page = $this->createPage(['title' => 'Premium Content']);

        $this->actingAsMember($this->testMember);

        $response = $this->getForSite("/member/single-access/show?type=page&id={$page->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();

        // Should show pricing details
        $this->assertMatchesRegularExpression('/\$?\d+\.\d{2}/', $content); //todo needs view
    }

    public function testPurchaseHandlesAlreadyPurchasedGracefully(): void
    {
        $page = $this->createPage();

        SingleContentAccess::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'content_type' => 'page',
            'content_id' => $page->id,
            'access_token' => SingleContentAccess::generateToken(),
            'price' => 9.99,
            'currency' => 'USD',
            'purchased_at' => now_datetime()->format('Y-m-d H:i:s'),
            'expires_at' => now_datetime()->modify('+7 days')->format('Y-m-d H:i:s'),
            'is_active' => true
        ]);

        $this->actingAsMember($this->testMember);

        $response = $this->postForSite('/member/single-access/purchase', [
            'content_type' => 'page',
            'content_id' => $page->id
        ]);


        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        //$this->assertStringContainsString('already', strtolower($data['message'])); //todo needs view
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->testMember = Member::create([
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'site_id' => $this->siteId,
            'email_verified_at' => now_datetime()->format('Y-m-d H:i:s'),
            'is_active' => true
        ]);
    }
}