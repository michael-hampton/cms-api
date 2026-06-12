<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Framework\Support\Collection;
use App\Models\Brief;
use App\Models\Collaborator;
use App\Repositories\OpenCollab\ContributorBriefRepository;
use App\Services\OpenCollab\ContributorBriefInboxService;
use App\Services\OpenCollab\OpenCollabBriefStatusMapper;
use Mockery;
use PHPUnit\Framework\TestCase;

class ContributorBriefInboxServiceTest extends TestCase
{
    private ContributorBriefRepository $briefs;
    private ContributorBriefInboxService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->briefs = Mockery::mock(ContributorBriefRepository::class);
        $this->service = new ContributorBriefInboxService($this->briefs, new OpenCollabBriefStatusMapper());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_assigned_briefs_filters_by_search_and_assignment_status(): void
    {
        $matching = $this->brief('Camera roundup', 'draft', 'pending');
        $nonMatching = $this->brief('Laptop roundup', 'draft', 'writer');

        $this->briefs->shouldReceive('assignedBriefsForContributor')
            ->once()
            ->with(5, 9)
            ->andReturn(Collection::make([$matching, $nonMatching]));

        $results = $this->service->getAssignedBriefs(5, 9, [
            'filter' => 'awaiting_response',
            'search' => 'camera',
        ]);

        $this->assertCount(1, $results);
        $this->assertSame('Camera roundup', $results->first()->title);
    }

    public function test_summarize_counts_workflow_assignment_and_overdue_states(): void
    {
        $awaiting = $this->brief('Waiting', 'draft', 'pending');
        $inProgress = $this->brief('Started', 'in_progress', 'writer');
        $submitted = $this->brief('Submitted', 'in_review', 'writer');
        $returned = $this->brief('Returned', 'on_hold', 'writer');
        $overdue = $this->brief('Late', 'draft', 'writer', '2024-01-01 00:00:00');

        $summary = $this->service->summarize(Collection::make([
            $awaiting,
            $inProgress,
            $submitted,
            $returned,
            $overdue,
        ]), 5);

        $this->assertSame(1, $summary['awaiting_response']);
        $this->assertSame(1, $summary['in_progress']);
        $this->assertSame(1, $summary['submitted']);
        $this->assertSame(1, $summary['returned_for_changes']);
        $this->assertSame(1, $summary['overdue']);
    }

    public function test_normalize_filters_rejects_unknown_filter(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid brief filter.');

        $this->service->normalizeFilters(['filter' => 'made_up']);
    }

    private function brief(string $title, string $status, string $role, ?string $deadline = null): Brief
    {
        $assignment = Mockery::mock(Collaborator::class)->makePartial();
        $assignment->user_id = 5;
        $assignment->role = $role;

        $site = (object) [
            'name' => 'Creator Weekly',
            'slug' => 'creator-weekly',
        ];

        $deadlines = Collection::make($deadline ? [
            (object) ['due_date' => $deadline],
        ] : []);

        $brief = Mockery::mock(Brief::class)->makePartial();
        $brief->title = $title;
        $brief->status = $status;
        $brief->site_id = 9;
        $brief->site = $site;
        $brief->collaborators = Collection::make([$assignment]);
        $brief->deadlines = $deadlines;

        return $brief;
    }
}
