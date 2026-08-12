<?php

namespace App\Tests\Unit\Services\Cms;

use App\Models\Page;
use App\Services\Cms\Pages\ClonePermissionChecker;
use App\Tests\Unit\UnitTestCase;
use Mockery;

class ClonePermissionCheckerTest extends UnitTestCase
{
    private ClonePermissionChecker $checker;

    public function testCanCloneDraftPage(): void
    {
        $page = $this->makePage(['status' => 'draft', 'created_by' => 1]);
        $this->assertTrue($this->checker->canClone($page, 2));
    }

    public function testCanClonePublishedPage(): void
    {
        $page = $this->makePage(['status' => 'published', 'created_by' => 1]);
        $this->assertTrue($this->checker->canClone($page, 2));
    }

    public function testCannotClonePageOnHold(): void
    {
        $page = $this->makePage(['status' => 'on_hold', 'created_by' => 1]);
        $this->assertFalse($this->checker->canClone($page, 1));
        $this->assertFalse($this->checker->canClone($page, 2));
    }

    public function testCreatorCanClonePrivatePage(): void
    {
        $page = $this->makePage(['status' => 'private', 'created_by' => 1]);
        $this->assertTrue($this->checker->canClone($page, 1));
    }

    public function testNonCreatorCannotClonePrivatePage(): void
    {
        $page = $this->makePage(['status' => 'private', 'created_by' => 1]);
        $this->assertFalse($this->checker->canClone($page, 2));
    }

    public function testGetCloneRestrictionReasonForOnHold(): void
    {
        $page = $this->makePage(['status' => 'on_hold', 'created_by' => 1]);
        $reason = $this->checker->getCloneRestrictionReason($page, 1);
        $this->assertEquals('Pages on hold cannot be cloned', $reason);
    }

    public function testGetCloneRestrictionReasonForPrivatePage(): void
    {
        $page = $this->makePage(['status' => 'private', 'created_by' => 1]);
        $reason = $this->checker->getCloneRestrictionReason($page, 2);
        $this->assertEquals('Only the creator can clone private pages', $reason);
    }

    public function testGetCloneRestrictionReasonReturnsNullForAllowed(): void
    {
        $page = $this->makePage(['status' => 'draft', 'created_by' => 1]);
        $reason = $this->checker->getCloneRestrictionReason($page, 2);
        $this->assertNull($reason);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new ClonePermissionChecker();
    }

    /**
     * @param array{status: string, created_by: int} $attributes
     */
    private function makePage(array $attributes): Page
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->shouldIgnoreMissing();
        $page->status = $attributes['status'];
        $page->created_by = $attributes['created_by'];

        return $page;
    }
}
