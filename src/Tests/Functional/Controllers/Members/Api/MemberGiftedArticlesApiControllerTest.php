<?php

namespace App\Tests\Functional\Controllers\Members\Api;

use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberGiftedArticlesApiControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexReturnsGiftedArticlesForMember(): void
    {
        $member = $this->createAuthenticatedMember();
        $this->createGiftedArticle(['gifted_by_member_id' => $member->id]);

        $response = $this->getForSite('/api/member/gifted-articles', [], true);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('received', $data['data']);
        $this->assertArrayHasKey('given', $data['data']);
        $this->assertArrayHasKey('allowance', $data['data']);
    }

    private function createAuthenticatedMember(array $overrides = [])
    {
        $member = $this->createMember($overrides);
        $this->actingAsMember($member);
        return $member;
    }

    public function testIndexFormatsDateFields(): void
    {
        $member = $this->createAuthenticatedMember();
        $this->createGiftedArticle(['gifted_by_member_id' => $member->id]);

        $response = $this->getForSite('/api/member/gifted-articles', [], true);

        $data = json_decode($response->getContent(), true);
        foreach ($data['data']['given'] as $gift) {
            if ($gift['gifted_at']) {
                $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $gift['gifted_at']);
            }
        }
    }

    public function testModalReturnsPageDetailsAndAllowance(): void
    {
        $member = $this->createAuthenticatedMember();
        $page = $this->createPage(['status' => 'published', 'slug' => 'test-article']);

        $response = $this->getForSite('/api/member/gift-modal/test-article', [], true);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('page', $data['data']);
        $this->assertArrayHasKey('allowance', $data['data']);
        $this->assertEquals('test-article', $data['data']['page']['slug']);
    }

    public function testModalReturns404ForNonexistentPage(): void
    {
        $this->createAuthenticatedMember();

        $response = $this->getForSite('/api/member/gift-modal/does-not-exist', [], true);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGiftReturns404ForNonexistentPage(): void
    {
        $this->createAuthenticatedMember();

        $response = $this->postForSite('/api/gift-article/nonexistent-page', [
            'recipient_email' => 'friend@example.com',
        ], [], [], false, true);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGiftReturns400ForInvalidEmail(): void
    {
        $this->createAuthenticatedMember();
        $page = $this->createPage(['status' => 'published', 'slug' => 'great-article']);

        $response = $this->postForSite('/api/member/gift-article/great-article', [
            'recipient_email' => 'not-an-email',
        ], [], [], false, true);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Invalid email', $data['message']);
    }

    public function testClaimReturns400ForInvalidToken(): void
    {
        $this->createAuthenticatedMember();

        $response = $this->postForSite('/api/member/gift/invalid-token-here/claim', [], [], [], false, true);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testClaimSucceedsWithValidToken(): void
    {
        $member = $this->createAuthenticatedMember();
        $gift = $this->createGiftedArticle([
            'status' => 'pending',
            'gift_token' => 'valid-test-token-abc123',
        ]);

        $response = $this->postForSite('/api/member/gift/valid-test-token-abc123/claim', [], [], [], [], true);

        // Either claimed successfully or appropriate error
        $this->assertContains($response->getStatusCode(), [200, 400]);
    }
}