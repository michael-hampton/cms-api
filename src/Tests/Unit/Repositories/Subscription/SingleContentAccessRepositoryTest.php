<?php

namespace App\Tests\Unit\Repositories\Subscription;

use App\Models\Member;
use App\Models\SingleContentAccess;
use App\Repositories\Subscriptions\SingleContentAccessRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class SingleContentAccessRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private SingleContentAccessRepository $repository;
    private Member $testMember;

    public function testHasActiveAccessReturnsTrueWhenAccessValid(): void
    {
        SingleContentAccess::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'content_type' => 'page',
            'content_id' => 1,
            'access_token' => SingleContentAccess::generateToken(),
            'price' => 9.99,
            'currency' => 'USD',
            'purchased_at' => now_datetime()->format('Y-m-d H:i:s'),
            'expires_at' => now_datetime()->modify('+7 days')->format('Y-m-d H:i:s'),
            'is_active' => true
        ]);

        $hasAccess = $this->repository->hasActiveAccess(
            $this->testMember->id,
            'page',
            1,
            $this->siteId
        );

        $this->assertTrue($hasAccess);
    }

    public function testHasActiveAccessReturnsFalseWhenAccessExpired(): void
    {
        SingleContentAccess::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'content_type' => 'page',
            'content_id' => 1,
            'access_token' => SingleContentAccess::generateToken(),
            'price' => 9.99,
            'currency' => 'USD',
            'purchased_at' => now_datetime()->modify('-14 days')->format('Y-m-d H:i:s'),
            'expires_at' => now_datetime()->modify('-7 days')->format('Y-m-d H:i:s'),
            'is_active' => true
        ]);

        $hasAccess = $this->repository->hasActiveAccess(
            $this->testMember->id,
            'page',
            1,
            $this->siteId
        );

        $this->assertFalse($hasAccess);
    }

    public function testHasActiveAccessReturnsFalseWhenAccessInactive(): void
    {
        SingleContentAccess::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'content_type' => 'page',
            'content_id' => 1,
            'access_token' => SingleContentAccess::generateToken(),
            'price' => 9.99,
            'currency' => 'USD',
            'purchased_at' => now_datetime()->format('Y-m-d H:i:s'),
            'expires_at' => now_datetime()->modify('+7 days')->format('Y-m-d H:i:s'),
            'is_active' => false
        ]);

        $hasAccess = $this->repository->hasActiveAccess(
            $this->testMember->id,
            'page',
            1,
            $this->siteId
        );

        $this->assertFalse($hasAccess);
    }

    public function testGetActiveAccessReturnsValidAccess(): void
    {
        $access = SingleContentAccess::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'content_type' => 'newsletter',
            'content_id' => 5,
            'access_token' => SingleContentAccess::generateToken(),
            'price' => 4.99,
            'currency' => 'USD',
            'purchased_at' => now_datetime()->format('Y-m-d H:i:s'),
            'expires_at' => now_datetime()->modify('+30 days')->format('Y-m-d H:i:s'),
            'is_active' => true
        ]);

        $result = $this->repository->getActiveAccess(
            $this->testMember->id,
            'newsletter',
            5,
            $this->siteId
        );

        $this->assertNotNull($result);
        $this->assertEquals($access->id, $result->id);
    }

    public function testCreateAccessCreatesNewRecord(): void
    {
        $accessData = [
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'content_type' => 'report',
            'content_id' => 10,
            'access_token' => SingleContentAccess::generateToken(),
            'price' => 19.99,
            'currency' => 'GBP',
            'purchased_at' => now_datetime()->format('Y-m-d H:i:s'),
            'expires_at' => now_datetime()->modify('+14 days')->format('Y-m-d H:i:s'),
            'is_active' => true
        ];

        $access = $this->repository->createAccess($accessData);

        $this->assertNotNull($access);
        $this->assertEquals($this->testMember->id, $access->member_id);
        $this->assertEquals('report', $access->content_type);
        $this->assertEquals(10, $access->content_id);
        $this->assertEquals(19.99, $access->price);
    }

    public function testGetMemberActiveAccessReturnsAllActive(): void
    {
        // Create multiple access records
        SingleContentAccess::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'content_type' => 'page',
            'content_id' => 1,
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
            'content_type' => 'newsletter',
            'content_id' => 2,
            'access_token' => SingleContentAccess::generateToken(),
            'price' => 4.99,
            'currency' => 'USD',
            'purchased_at' => now_datetime()->format('Y-m-d H:i:s'),
            'expires_at' => now_datetime()->modify('+7 days')->format('Y-m-d H:i:s'),
            'is_active' => true
        ]);

        // Create expired access
        SingleContentAccess::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'content_type' => 'page',
            'content_id' => 3,
            'access_token' => SingleContentAccess::generateToken(),
            'price' => 9.99,
            'currency' => 'USD',
            'purchased_at' => now_datetime()->modify('-14 days')->format('Y-m-d H:i:s'),
            'expires_at' => now_datetime()->modify('-7 days')->format('Y-m-d H:i:s'),
            'is_active' => true
        ]);

        $activeAccess = $this->repository->getMemberActiveAccess(
            $this->testMember->id,
            $this->siteId
        );

        $this->assertCount(2, $activeAccess);
    }

    public function testGetExpiredAccessReturnsOnlyExpired(): void
    {
        // Create active access
        SingleContentAccess::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'content_type' => 'page',
            'content_id' => 1,
            'access_token' => SingleContentAccess::generateToken(),
            'price' => 9.99,
            'currency' => 'USD',
            'purchased_at' => now_datetime()->format('Y-m-d H:i:s'),
            'expires_at' => now_datetime()->modify('+7 days')->format('Y-m-d H:i:s'),
            'is_active' => true
        ]);

        // Create expired access
        SingleContentAccess::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'content_type' => 'newsletter',
            'content_id' => 2,
            'access_token' => SingleContentAccess::generateToken(),
            'price' => 4.99,
            'currency' => 'USD',
            'purchased_at' => now_datetime()->modify('-14 days')->format('Y-m-d H:i:s'),
            'expires_at' => now_datetime()->modify('-7 days')->format('Y-m-d H:i:s'),
            'is_active' => true
        ]);

        $expiredAccess = $this->repository->getExpiredAccess($this->siteId);

        $this->assertCount(1, $expiredAccess);
        $this->assertEquals(2, $expiredAccess->first()->content_id);
    }

    public function testCleanupExpiredDeactivatesExpiredRecords(): void
    {
        // Create expired access that's still active
        SingleContentAccess::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'content_type' => 'page',
            'content_id' => 1,
            'access_token' => SingleContentAccess::generateToken(),
            'price' => 9.99,
            'currency' => 'USD',
            'purchased_at' => now_datetime()->modify('-14 days')->format('Y-m-d H:i:s'),
            'expires_at' => now_datetime()->modify('-7 days')->format('Y-m-d H:i:s'),
            'is_active' => true
        ]);

        $count = $this->repository->cleanupExpired($this->siteId);

        $this->assertEquals(1, $count);

        // Verify it was deactivated
        $access = SingleContentAccess::where('content_id', 1)->first();
        $this->assertFalse($access->is_active);
    }

    public function testAccessTokenIsUnique(): void
    {
        $token1 = SingleContentAccess::generateToken();
        $token2 = SingleContentAccess::generateToken();

        $this->assertNotEquals($token1, $token2);
        $this->assertEquals(64, strlen($token1)); // 32 bytes = 64 hex chars
    }

    public function testFindByTokenReturnsCorrectAccess(): void
    {
        $token = SingleContentAccess::generateToken();

        $access = SingleContentAccess::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'content_type' => 'page',
            'content_id' => 1,
            'access_token' => $token,
            'price' => 9.99,
            'currency' => 'USD',
            'purchased_at' => now_datetime()->format('Y-m-d H:i:s'),
            'expires_at' => now_datetime()->modify('+7 days')->format('Y-m-d H:i:s'),
            'is_active' => true
        ]);

        $found = SingleContentAccess::findByToken($token);

        $this->assertNotNull($found);
        $this->assertEquals($access->id, $found->id);
    }

    public function testIsValidMethodReturnsTrueForActiveNonExpiredAccess(): void
    {
        $access = SingleContentAccess::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'content_type' => 'page',
            'content_id' => 1,
            'access_token' => SingleContentAccess::generateToken(),
            'price' => 9.99,
            'currency' => 'USD',
            'purchased_at' => now_datetime()->format('Y-m-d H:i:s'),
            'expires_at' => now_datetime()->modify('+7 days')->format('Y-m-d H:i:s'),
            'is_active' => true
        ]);

        $this->assertTrue($access->isValid());
    }

    public function testIsValidMethodReturnsFalseForInactiveAccess(): void
    {
        $access = SingleContentAccess::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'content_type' => 'page',
            'content_id' => 1,
            'access_token' => SingleContentAccess::generateToken(),
            'price' => 9.99,
            'currency' => 'USD',
            'purchased_at' => now_datetime()->format('Y-m-d H:i:s'),
            'expires_at' => now_datetime()->modify('+7 days')->format('Y-m-d H:i:s'),
            'is_active' => false
        ]);

        $this->assertFalse($access->isValid());
    }

    public function testIsExpiredMethodReturnsTrueForExpiredAccess(): void
    {
        $access = SingleContentAccess::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'content_type' => 'page',
            'content_id' => 1,
            'access_token' => SingleContentAccess::generateToken(),
            'price' => 9.99,
            'currency' => 'USD',
            'purchased_at' => now_datetime()->modify('-14 days')->format('Y-m-d H:i:s'),
            'expires_at' => now_datetime()->modify('-7 days')->format('Y-m-d H:i:s'),
            'is_active' => true
        ]);

        $this->assertTrue($access->isExpired());
    }

    public function testRevokeDeactivatesAccess(): void
    {
        $access = SingleContentAccess::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'content_type' => 'page',
            'content_id' => 1,
            'access_token' => SingleContentAccess::generateToken(),
            'price' => 9.99,
            'currency' => 'USD',
            'purchased_at' => now_datetime()->format('Y-m-d H:i:s'),
            'expires_at' => now_datetime()->modify('+7 days')->format('Y-m-d H:i:s'),
            'is_active' => true
        ]);

        $access->revoke();

        $this->assertFalse($access->is_active);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new SingleContentAccessRepository();
        $this->testMember = $this->createMember();
    }
}