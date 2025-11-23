<?php

namespace App\Tests\Unit\Models;

use App\Models\Member;
use App\Models\MemberActivity;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberActivityModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testCreateMemberActivity()
    {
        $member = $this->createMember();

        $activity = MemberActivity::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'activity_type' => 'comment',
            'entity_type' => 'page',
            'entity_id' => 1,
            'points' => 10,
            'activity_date' => now()
        ]);

        $this->assertInstanceOf(MemberActivity::class, $activity);
        $this->assertEquals('comment', $activity->activity_type);
        $this->assertEquals(10, $activity->points);
    }

    public function testActivityBelongsToMember()
    {
        $member = $this->createMember();

        $activity = MemberActivity::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'activity_type' => 'like',
            'activity_date' => now()
        ]);

        $this->assertInstanceOf(Member::class, $activity->member());
        $this->assertEquals($member->id, $activity->member()->id);
    }

    public function testMetadataCast()
    {
        $member = $this->createMember();
        $metadata = ['comment_id' => 123, 'page_title' => 'Test Page'];

        $activity = MemberActivity::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'activity_type' => 'comment',
            'metadata' => $metadata,
            'activity_date' => now()
        ]);

        $this->assertIsArray($activity->metadata);
        $this->assertEquals($metadata, $activity->metadata);
    }

    public function testPointsCast()
    {
        $member = $this->createMember();

        $activity = MemberActivity::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'activity_type' => 'read',
            'points' => '5',
            'activity_date' => now()
        ]);

        $this->assertIsInt($activity->points);
        $this->assertEquals(5, $activity->points);
    }

    public function testActivityDateCast()
    {
        $member = $this->createMember();

        $activity = MemberActivity::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'activity_type' => 'like',
            'activity_date' => now()
        ]);

        $this->assertInstanceOf(\DateTime::class, $activity->activity_date);
    }

    public function testActivityWithNoPoints()
    {
        $member = $this->createMember();

        $activity = MemberActivity::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'activity_type' => 'view',
            'points' => 0,
            'activity_date' => now()
        ]);

        $this->assertEquals(0, $activity->points);
    }

    public function testTimestamps()
    {
        $member = $this->createMember();

        $activity = MemberActivity::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'activity_type' => 'comment',
            'activity_date' => now()
        ]);

        $this->assertNotNull($activity->created_at);
        $this->assertNotNull($activity->updated_at);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }
}