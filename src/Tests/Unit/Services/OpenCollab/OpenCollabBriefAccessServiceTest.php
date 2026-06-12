<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Models\Brief;
use App\Models\BriefAttachment;
use App\Models\Collaborator;
use App\Repositories\OpenCollab\ContributorBriefRepository;
use App\Services\OpenCollab\OpenCollabBriefAccessService;
use Mockery;
use PHPUnit\Framework\TestCase;

class OpenCollabBriefAccessServiceTest extends TestCase
{
    private ContributorBriefRepository $briefs;
    private OpenCollabBriefAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->briefs = Mockery::mock(ContributorBriefRepository::class);
        $this->service = new OpenCollabBriefAccessService($this->briefs);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_assert_can_access_brief_returns_repository_brief(): void
    {
        $brief = $this->mockBrief();

        $this->briefs->shouldReceive('findAssignedBrief')
            ->once()
            ->with(10, 20, 30)
            ->andReturn($brief);

        $this->assertSame($brief, $this->service->assertCanAccessBrief(10, 20, 30));
    }

    public function test_assert_can_access_brief_throws_when_repository_returns_null(): void
    {
        $this->briefs->shouldReceive('findAssignedBrief')
            ->once()
            ->with(10, 20, 30)
            ->andReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Forbidden');

        $this->service->assertCanAccessBrief(10, 20, 30);
    }

    public function test_assignment_methods_delegate_to_repository(): void
    {
        $assignment = $this->mockAssignment();

        $this->briefs->shouldReceive('assignmentForBrief')
            ->once()
            ->with(10, 20, 30)
            ->andReturn($assignment);

        $this->assertSame($assignment, $this->service->assignmentForBrief(10, 20, 30));
    }

    public function test_assert_can_access_attachment_requires_brief_access_and_attachment(): void
    {
        $brief = $this->mockBrief();
        $attachment = Mockery::mock(BriefAttachment::class);

        $this->briefs->shouldReceive('findAssignedBrief')
            ->once()
            ->with(10, 20, 30)
            ->andReturn($brief);
        $this->briefs->shouldReceive('findAttachmentForBrief')
            ->once()
            ->with(10, 99)
            ->andReturn($attachment);

        $this->service->assertCanAccessAttachment(10, 99, 20, 30);

        $this->addToAssertionCount(1);
    }

    private function mockBrief(): Brief
    {
        return Mockery::mock(Brief::class);
    }

    private function mockAssignment(): Collaborator
    {
        return Mockery::mock(Collaborator::class);
    }
}
