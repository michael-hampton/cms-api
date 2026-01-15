<?php

namespace App\Tests\Unit\Services;

use App\Services\Cms\ClonePermissionChecker;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ClonePermissionCheckerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private ClonePermissionChecker $checker;

    public function testCanCloneDraftPage()
    {
        $page = $this->createPage(['status' => 'draft', 'created_by' => 1]);
        $this->assertTrue($this->checker->canClone($page, 2));
    }

    public function testCanClonePublishedPage()
    {
        $page = $this->createPage(['status' => 'published', 'created_by' => 1]);
        $this->assertTrue($this->checker->canClone($page, 2));
    }

    public function testCannotClonePageOnHold()
    {
        $page = $this->createPage(['status' => 'on_hold', 'created_by' => 1]);
        $this->assertFalse($this->checker->canClone($page, 1));
        $this->assertFalse($this->checker->canClone($page, 2));
    }

    public function testCreatorCanClonePrivatePage()
    {
        $page = $this->createPage(['status' => 'private', 'created_by' => 1]);
        $this->assertTrue($this->checker->canClone($page, 1));
    }

    public function testNonCreatorCannotClonePrivatePage()
    {
        $page = $this->createPage(['status' => 'private', 'created_by' => 1]);
        $this->assertFalse($this->checker->canClone($page, 2));
    }

    public function testGetCloneRestrictionReasonForOnHold()
    {
        $page = $this->createPage(['status' => 'on_hold', 'created_by' => 1]);
        $reason = $this->checker->getCloneRestrictionReason($page, 1);
        $this->assertEquals('Pages on hold cannot be cloned', $reason);
    }

    public function testGetCloneRestrictionReasonForPrivatePage()
    {
        $page = $this->createPage(['status' => 'private', 'created_by' => 1]);
        $reason = $this->checker->getCloneRestrictionReason($page, 2);
        $this->assertEquals('Only the creator can clone private pages', $reason);
    }

    public function testGetCloneRestrictionReasonReturnsNullForAllowed()
    {
        $page = $this->createPage(['status' => 'draft', 'created_by' => 1]);
        $reason = $this->checker->getCloneRestrictionReason($page, 2);
        $this->assertNull($reason);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new ClonePermissionChecker();
    }
}