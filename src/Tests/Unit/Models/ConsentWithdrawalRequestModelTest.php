<?php

namespace App\Tests\Unit\Models;

use App\Models\ConsentWithdrawalRequest;
use App\Models\Member;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ConsentWithdrawalRequestModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testCreateWithdrawalRequest()
    {
        $member = $this->createMember();

        $request = ConsentWithdrawalRequest::create([
            'member_id' => $member->id,
            'type' => 'all_marketing',
            'status' => 'pending',
            'requested_at' => now()
        ]);

        $this->assertInstanceOf(ConsentWithdrawalRequest::class, $request);
        $this->assertEquals('all_marketing', $request->type);
        $this->assertEquals('pending', $request->status);
    }

    public function testIsPending()
    {
        $member = $this->createMember();

        $pending = ConsentWithdrawalRequest::create([
            'member_id' => $member->id,
            'type' => 'all_marketing',
            'status' => 'pending',
            'requested_at' => now()
        ]);

        $completed = ConsentWithdrawalRequest::create([
            'member_id' => $member->id,
            'type' => 'specific_consent',
            'status' => 'completed',
            'requested_at' => now()
        ]);

        $this->assertTrue($pending->isPending());
        $this->assertFalse($completed->isPending());
    }

    public function testIsInProgress()
    {
        $member = $this->createMember();

        $inProgress = ConsentWithdrawalRequest::create([
            'member_id' => $member->id,
            'type' => 'all_marketing',
            'status' => 'in_progress',
            'requested_at' => now()
        ]);

        $this->assertTrue($inProgress->isInProgress());
    }

    public function testIsCompleted()
    {
        $member = $this->createMember();

        $completed = ConsentWithdrawalRequest::create([
            'member_id' => $member->id,
            'type' => 'all_marketing',
            'status' => 'completed',
            'requested_at' => now(),
            'completed_at' => now()
        ]);

        $this->assertTrue($completed->isCompleted());
    }

    public function testIsCancelled()
    {
        $member = $this->createMember();

        $cancelled = ConsentWithdrawalRequest::create([
            'member_id' => $member->id,
            'type' => 'all_marketing',
            'status' => 'cancelled',
            'requested_at' => now()
        ]);

        $this->assertTrue($cancelled->isCancelled());
    }

    public function testScopePending()
    {
        $member = $this->createMember();

        ConsentWithdrawalRequest::create([
            'member_id' => $member->id,
            'type' => 'all_marketing',
            'status' => 'pending',
            'requested_at' => now()
        ]);

        ConsentWithdrawalRequest::create([
            'member_id' => $member->id,
            'type' => 'specific_consent',
            'status' => 'completed',
            'requested_at' => now()
        ]);

        $pending = ConsentWithdrawalRequest::pending()->get();
        $this->assertCount(1, $pending);
    }

    public function testScopeByMember()
    {
        $member1 = $this->createMember();
        $member2 = $this->createMember();

        ConsentWithdrawalRequest::create([
            'member_id' => $member1->id,
            'type' => 'all_marketing',
            'status' => 'pending',
            'requested_at' => now()
        ]);

        ConsentWithdrawalRequest::create([
            'member_id' => $member2->id,
            'type' => 'specific_consent',
            'status' => 'pending',
            'requested_at' => now()
        ]);

        $requests = ConsentWithdrawalRequest::byMember($member1->id)->get();
        $this->assertCount(1, $requests);
    }

    public function testScopeByType()
    {
        $member = $this->createMember();

        ConsentWithdrawalRequest::create([
            'member_id' => $member->id,
            'type' => 'all_marketing',
            'status' => 'pending',
            'requested_at' => now()
        ]);

        ConsentWithdrawalRequest::create([
            'member_id' => $member->id,
            'type' => 'specific_consent',
            'status' => 'pending',
            'requested_at' => now()
        ]);

        $marketing = ConsentWithdrawalRequest::byType('all_marketing')->get();
        $this->assertCount(1, $marketing);
    }

    public function testConsentTypesCast()
    {
        $member = $this->createMember();

        $request = ConsentWithdrawalRequest::create([
            'member_id' => $member->id,
            'type' => 'specific_consent',
            'consent_types' => ['marketing_email', 'analytics'],
            'status' => 'pending',
            'requested_at' => now()
        ]);

        $this->assertIsArray($request->consent_types);
        $this->assertCount(2, $request->consent_types);
    }

    public function testRelationships()
    {
        $member = $this->createMember();

        $request = ConsentWithdrawalRequest::create([
            'member_id' => $member->id,
            'type' => 'all_marketing',
            'status' => 'pending',
            'requested_at' => now()
        ]);

        $this->assertInstanceOf(Member::class, $request->member());
        $this->assertEquals($member->id, $request->member()->id);
    }

    public function testTimestamps()
    {
        $member = $this->createMember();

        $request = ConsentWithdrawalRequest::create([
            'member_id' => $member->id,
            'type' => 'all_marketing',
            'status' => 'pending',
            'requested_at' => now()
        ]);

        $this->assertNotNull($request->created_at);
        $this->assertNotNull($request->updated_at);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }
}