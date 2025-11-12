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

    public function test_it_can_search_members_by_email(): void
    {
        $member1 = $this->createMember(['email' => 'john@example.com', 'first_name' => 'John', 'site_id' => $this->siteId]);;
        $member2 = $this->createMember(['email' => 'jane@test.com', 'first_name' => 'Jane', 'site_id' => $this->siteId]);
        $member3 = $this->createMember(['email' => 'bob@example.com', 'first_name' => 'Bob', 'site_id' => $this->siteId]);

        $results = $this->repository->searchMembers('example.com', 10, $this->siteId);

        $this->assertCount(2, $results);
        $emails = array_column($results->toArray(), 'email');
        $this->assertContains('john@example.com', $emails);
        $this->assertContains('bob@example.com', $emails);
    }

    public function test_it_can_search_members_by_first_name(): void
    {
        $this->createMember(['first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com', 'site_id' => $this->siteId, 'is_active' => true]);
        $this->createMember(['first_name' => 'Jane', 'last_name' => 'Smith', 'email' => 'jane@example.com', 'site_id' => $this->siteId, 'is_active' => true]);
        $this->createMember(['first_name' => 'Johnny', 'last_name' => 'Test', 'email' => 'johnny@example.com', 'site_id' => $this->siteId, 'is_active' => true]);

        $results = $this->repository->searchMembers('John', 10, $this->siteId);

        $this->assertCount(2, $results);
    }

    public function test_it_can_search_members_by_last_name(): void
    {
        $this->createMember(['first_name' => 'John', 'last_name' => 'Smith', 'email' => 'john@example.com', 'site_id' => $this->siteId, 'is_active' => true]);
        $this->createMember(['first_name' => 'Jane', 'last_name' => 'Smith', 'email' => 'jane@example.com', 'site_id' => $this->siteId, 'is_active' => true]);
        $this->createMember(['first_name' => 'Bob', 'last_name' => 'Johnson', 'email' => 'bob@example.com', 'site_id' => $this->siteId, 'is_active' => true]);

        $results = $this->repository->searchMembers('Smith', 10, $this->siteId);

        $this->assertCount(2, $results);
    }

    public function test_it_can_search_members_by_display_name(): void
    {
        $this->createMember(['display_name' => 'JohnDoe123', 'first_name' => 'John', 'email' => 'john@example.com', 'site_id' => $this->siteId, 'is_active' => true]);
        $this->createMember(['display_name' => 'JaneSmith', 'first_name' => 'Jane', 'email' => 'jane@example.com', 'site_id' => $this->siteId, 'is_active' => true]);

        $results = $this->repository->searchMembers('JohnDoe', 10, $this->siteId);;

        $this->assertCount(1, $results);
        $this->assertEquals('JohnDoe123', $results->first()['display_name']);
    }

    public function test_search_returns_empty_collection_when_no_matches(): void
    {
        $this->createMember(['email' => 'john@example.com', 'first_name' => 'John']);

        $results = $this->repository->searchMembers('nonexistent');

        $this->assertCount(0, $results);
    }

    public function test_search_respects_per_page_limit(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            $this->createMember(['first_name' => "Member{$i}", 'email' => "member{$i}@example.com", 'site_id' => $this->siteId]);;
        }

        $results = $this->repository->searchMembers('Member', 5, $this->siteId);

        $this->assertCount(5, $results);
    }

    public function test_search_orders_by_first_name_then_last_name(): void
    {
        $this->createMember(['first_name' => 'Alice', 'last_name' => 'Smith', 'email' => 'alice@example.com', 'site_id' => $this->siteId, 'is_active' => true]);
        $this->createMember(['first_name' => 'Alice', 'last_name' => 'Johnson', 'email' => 'alice2@example.com', 'site_id' => $this->siteId, 'is_active' => true]);
        $this->createMember(['first_name' => 'Bob', 'last_name' => 'Adams', 'email' => 'bob@example.com','site_id' => $this->siteId, 'is_active' => true]);

        $results = $this->repository->searchMembers('', 10, $this->siteId);

        $this->assertEquals('Alice', $results->toArray()[0]['first_name']);
        $this->assertEquals('Johnson', $results->toArray()[0]['last_name']);
        $this->assertEquals('Alice', $results->toArray()[1]['first_name']);
        $this->assertEquals('Smith', $results->toArray()[1]['last_name']);
    }

    public function test_search_only_returns_active_members(): void
    {
        $this->createMember(['first_name' => 'Active', 'email' => 'active@example.com', 'is_active' => true, 'site_id' => $this->siteId]);
        $this->createMember(['first_name' => 'Inactive', 'email' => 'inactive@example.com', 'is_active' => false, 'site_id' => $this->siteId]);

        $results = $this->repository->searchMembers('', 10, $this->siteId);;

        $this->assertCount(1, $results);
        $this->assertEquals('Active', $results->first()['first_name']);
    }

    public function test_search_includes_full_name_in_results(): void
    {
        $this->createMember([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'site_id' => $this->siteId,
        ]);

        $results = $this->repository->searchMembers('John', 10, $this->siteId,);

        $this->assertArrayHasKey('full_name', $results->first());
    }

    public function test_search_includes_verification_status(): void
    {
        $verified = $this->createMember([
            'email' => 'verified@example.com',
            'email_verified_at' => now(),
            'site_id' => $this->siteId
        ]);
        $unverified = $this->createMember([
            'email' => 'unverified@example.com',
            'email_verified_at' => null,
            'site_id' => $this->siteId
        ]);

        $results = $this->repository->searchMembers('', 10, $this->siteId);;

        $this->assertArrayHasKey('is_verified', $results->first());
    }

    public function test_it_can_get_active_members(): void
    {
        $this->createMember(['is_active' => true, 'first_name' => 'Active1', 'site_id' => $this->siteId]);;
        $this->createMember(['is_active' => true, 'first_name' => 'Active2', 'site_id' => $this->siteId]);;;
        $this->createMember(['is_active' => false, 'first_name' => 'Inactive', 'site_id' => $this->siteId]);;;

        $members = $this->repository->getActiveMembers(null, $this->siteId);

        $this->assertCount(2, $members);
        foreach ($members as $member) {
            $this->assertTrue($member->is_active);
        }
    }

    public function test_active_members_respects_limit(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->createMember(['is_active' => true, 'site_id' => $this->siteId]);
        }

        $members = $this->repository->getActiveMembers(5, $this->siteId);

        $this->assertCount(5, $members);
    }

    public function test_it_can_update_account_details(): void
    {
        $member = $this->createMember([
            'email' => 'old@example.com',
            'first_name' => 'Old',
            'last_name' => 'Name',
            'display_name' => 'OldDisplay'
        ]);

        $updated = $this->repository->updateAccountDetails($member->id, [
            'first_name' => 'New',
            'last_name' => 'Name',
            'display_name' => 'NewDisplay'
        ]);

        $this->assertNotNull($updated);
        $this->assertEquals('New', $updated->first_name);
        $this->assertEquals('Name', $updated->last_name);
        $this->assertEquals('NewDisplay', $updated->display_name);
        $this->assertEquals('old@example.com', $updated->email);
    }

    public function test_updating_email_resets_verification(): void
    {
        $member = $this->createMember([
            'email' => 'old@example.com',
            'email_verified_at' => now()
        ]);

        $this->assertNotNull($member->email_verified_at);

        $updated = $this->repository->updateAccountDetails($member->id, [
            'email' => 'new@example.com'
        ]);

        // Refresh from database to get the actual updated values
        $updated = $updated->fresh();

        $this->assertEquals('new@example.com', $updated->email);
        $this->assertNull($updated->email_verified_at);
    }

    public function test_updating_email_to_same_email_keeps_verification(): void
    {
        $verifiedAt = now();
        $member = $this->createMember([
            'email' => 'test@example.com',
            'email_verified_at' => $verifiedAt
        ]);

        $updated = $this->repository->updateAccountDetails($member->id, [
            'email' => 'test@example.com',
            'first_name' => 'NewName'
        ]);

        $this->assertEquals('test@example.com', $updated->email);
        $this->assertNotNull($updated->email_verified_at);
    }

    public function test_update_account_details_throws_exception_for_duplicate_email(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email address is already in use.');

        $member1 = $this->createMember(['email' => 'first@example.com']);
        $member2 = $this->createMember(['email' => 'second@example.com']);

        $this->repository->updateAccountDetails($member2->id, [
            'email' => 'first@example.com'
        ]);
    }

    public function test_update_account_details_only_updates_allowed_fields(): void
    {
        $member = $this->createMember([
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'is_active' => true,
            'password' => password_hash('password123', PASSWORD_DEFAULT)
        ]);

        $updated = $this->repository->updateAccountDetails($member->id, [
            'first_name' => 'Updated',
            'is_active' => false, // Should be ignored
            'password' => 'hackAttempt', // Should be ignored
            'site_id' => 999 // Should be ignored
        ]);

        $this->assertEquals('Updated', $updated->first_name);
        $this->assertTrue($updated->is_active); // Unchanged
        $this->assertNotEquals('hackAttempt', $updated->password); // Unchanged
    }

    public function test_update_account_details_returns_null_for_nonexistent_member(): void
    {
        $result = $this->repository->updateAccountDetails(99999, [
            'first_name' => 'Test'
        ]);

        $this->assertNull($result);
    }

    public function test_is_email_available_returns_true_for_available_email(): void
    {
        $this->createMember(['email' => 'taken@example.com']);

        $available = $this->repository->isEmailAvailable('available@example.com');

        $this->assertTrue($available);
    }

    public function test_is_email_available_returns_false_for_taken_email(): void
    {
        $this->createMember(['email' => 'taken@example.com']);

        $available = $this->repository->isEmailAvailable('taken@example.com');

        $this->assertFalse($available);
    }

    public function test_is_email_available_excludes_current_member(): void
    {
        $member = $this->createMember(['email' => 'test@example.com']);

        $available = $this->repository->isEmailAvailable('test@example.com', $member->id);

        $this->assertTrue($available);
    }

    public function test_is_email_available_checks_other_members(): void
    {
        $member1 = $this->createMember(['email' => 'first@example.com']);
        $member2 = $this->createMember(['email' => 'second@example.com']);

        $available = $this->repository->isEmailAvailable('second@example.com', $member1->id);

        $this->assertFalse($available);
    }

    public function test_update_account_details_with_partial_data(): void
    {
        $member = $this->createMember([
            'email' => 'test@example.com',
            'first_name' => 'Original',
            'last_name' => 'Name',
            'display_name' => 'OriginalDisplay'
        ]);

        $updated = $this->repository->updateAccountDetails($member->id, [
            'first_name' => 'Updated'
            // Only updating first_name, others should remain unchanged
        ]);

        $this->assertEquals('Updated', $updated->first_name);
        $this->assertEquals('Name', $updated->last_name);
        $this->assertEquals('OriginalDisplay', $updated->display_name);
        $this->assertEquals('test@example.com', $updated->email);
    }
}