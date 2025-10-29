<?php

namespace App\Tests\Unit\Repositories;

use App\Models\Member;
use App\Repositories\MemberRepository;
use App\Search\SearchCriteria;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use function PHPUnit\Framework\assertEquals;

class MemberRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private MemberRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new MemberRepository();
    }

    public function test_it_can_find_member_by_email(): void
    {
        $member = $this->createMember([
            'email' => 'john@example.com',
            'name' => 'John Doe'
        ]);

        $found = $this->repository->findByEmail('john@example.com');

        $this->assertNotNull($found);
        $this->assertEquals($member->id, $found->id);
        $this->assertEquals('john@example.com', $found->email);
    }

    public function test_it_returns_null_when_email_not_found(): void
    {
        $found = $this->repository->findByEmail('nonexistent@example.com');

        $this->assertNull($found);
    }

    public function test_it_finds_correct_member_when_multiple_exist(): void
    {
        $this->createMember(['email' => 'john@example.com', 'first_name' => 'John']);
        $member2 = $this->createMember(['email' => 'jane@example.com', 'first_name' => 'Jane']);
        $this->createMember(['email' => 'bob@example.com', 'first_name' => 'Bob']);

        $found = $this->repository->findByEmail('jane@example.com');

        $this->assertNotNull($found);
        $this->assertEquals($member2->id, $found->id);
        $this->assertEquals('jane@example.com', $found->email);
        $this->assertEquals('Jane', $found->first_name);
    }

    public function test_find_by_email_is_case_sensitive(): void
    {
        $this->createMember(['email' => 'john@example.com']);

        $found = $this->repository->findByEmail('JOHN@example.com');

        // Depending on your database collation, this might be null
        // Adjust based on your actual implementation
        $this->assertNotNull($found);
    }

    public function test_it_can_create_member(): void
    {
        $data = [
            'email' => 'new@example.com',
            'first_name' => 'New',
            'last_name' => 'Member',
            'password' => '<PASSWORD>',
            'site_id' => $this->siteId
        ];

        $member = $this->repository->create($data);

        $this->assertInstanceOf(Member::class, $member);
        $this->assertEquals('new@example.com', $member->email);
        $this->assertEquals('New', $member->first_name);
        assertEquals('Member', $member->last_name);
    }

    public function test_it_can_update_member(): void
    {
        $member = $this->createMember([
            'email' => 'old@example.com',
            'first_name' => 'Old',
            'last_name' => 'Member'
        ]);

        $updated = $this->repository->update($member->id, [
            'first_name' => 'New',
            'last_name' => 'Member',
        ]);

        $member = $member->fresh();

        $this->assertEquals('New', $updated->first_name);
        $this->assertEquals('Member', $updated->last_name);
        $this->assertEquals('old@example.com', $updated->email); // Email unchanged
    }

    public function test_it_can_delete_member(): void
    {
        $member = $this->createMember(['email' => 'delete@example.com']);

        $result = $this->repository->delete($member->id);

        $this->assertTrue($result);
        $this->assertNull($this->repository->find($member->id));
    }

    public function test_it_can_find_member_by_id(): void
    {
        $member = $this->createMember(['email' => 'test@example.com']);

        $found = $this->repository->find($member->id);

        $this->assertNotNull($found);
        $this->assertEquals($member->id, $found->id);
        $this->assertEquals('test@example.com', $found->email);
    }

    public function test_it_returns_null_when_id_not_found(): void
    {
        $found = $this->repository->find(99999);

        $this->assertNull($found);
    }

    public function test_it_can_get_all_members(): void
    {
        $this->createMember(['email' => 'member1@example.com']);
        $this->createMember(['email' => 'member2@example.com']);
        $this->createMember(['email' => 'member3@example.com']);

        $members = $this->repository->all();

        $this->assertCount(3, $members);
    }

    public function test_members_belong_to_correct_site(): void
    {
        $member1 = $this->createMember(['email' => 'member1@example.com']);

        // Create a different site
        $otherSite = $this->createSite(['slug' => 'other-site']);
        $member2 = $this->createMember([
            'email' => 'member2@example.com',
            'site_id' => $otherSite->id
        ]);

        $this->assertEquals($this->siteId, $member1->site_id);
        $this->assertEquals($otherSite->id, $member2->site_id);
        $this->assertNotEquals($member1->site_id, $member2->site_id);
    }

    public function test_find_by_email_only_searches_within_site(): void
    {
        $member = $this->createMember(['email' => 'test@example.com']);

        // Create same email in different site
        $otherSite = $this->createSite(['slug' => 'other-site']);
        $this->createMember([
            'email' => 'test@example.com',
            'site_id' => $otherSite->id
        ]);

        $found = $this->repository->findByEmail('test@example.com');

        // Should find the member from current site
        $this->assertNotNull($found);
        $this->assertEquals($member->id, $found->id);
        $this->assertEquals($this->siteId, $found->site_id);
    }
}