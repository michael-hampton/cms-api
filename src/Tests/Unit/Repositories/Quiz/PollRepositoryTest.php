<?php

namespace App\Tests\Unit\Repositories\Quiz;

use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\Site;
use App\Repositories\Quiz\PollRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class PollRepositoryTest extends FunctionalTestCase
{
    use CreatesTestData;

    private PollRepository $repository;

    public function test_get_active_for_site_returns_active_polls(): void
    {
        $this->createPoll(['status' => 'active', 'site_id' => $this->siteId]);
        $this->createPoll(['status' => 'active', 'site_id' => $this->siteId]);
        $this->createPoll(['status' => 'closed', 'site_id' => $this->siteId]);

        $polls = $this->repository->getActiveForSite($this->siteId);

        $this->assertCount(2, $polls);
        foreach ($polls as $poll) {
            $this->assertSame('active', $poll->status);
        }
    }

    // -------------------------------------------------------------------------
    // getActiveForSite
    // -------------------------------------------------------------------------

    private function createPoll(array $attributes = []): Poll
    {
        return Poll::create(array_merge([
            'site_id' => $this->siteId,
            'question' => 'Default question?',
            'status' => 'active',
        ], $attributes));
    }

    public function test_get_active_for_site_excludes_other_sites(): void
    {
        $otherSite = Site::create(['name' => 'test', 'slug' => 'test']);
        $this->createPoll(['status' => 'active', 'site_id' => $this->siteId]);
        $this->createPoll(['status' => 'active', 'site_id' => $otherSite->id]);

        $polls = $this->repository->getActiveForSite($this->siteId);

        $this->assertCount(1, $polls);
        $this->assertSame($this->siteId, $polls->first()->site_id);
    }

    public function test_get_active_for_site_excludes_closed_polls(): void
    {
        $this->createPoll([
            'status' => 'active',
            'site_id' => $this->siteId,
            'closes_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        ]);
        $this->createPoll([
            'status' => 'active',
            'site_id' => $this->siteId,
            'closes_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
        ]);

        $polls = $this->repository->getActiveForSite($this->siteId);

        $this->assertCount(1, $polls);
    }

    public function test_get_active_for_site_includes_polls_with_no_closing_date(): void
    {
        $this->createPoll(['status' => 'active', 'site_id' => $this->siteId, 'closes_at' => null]);

        $polls = $this->repository->getActiveForSite($this->siteId);

        $this->assertCount(1, $polls);
    }

    public function test_get_active_for_site_eager_loads_options(): void
    {
        $poll = $this->createPoll(['status' => 'active', 'site_id' => $this->siteId]);
        $this->createPollOption(['poll_id' => $poll->id, 'label' => 'Yes']);
        $this->createPollOption(['poll_id' => $poll->id, 'label' => 'No']);

        $polls = $this->repository->getActiveForSite($this->siteId);

        $this->assertTrue($polls->first()->relationLoaded('options'));
        $this->assertCount(2, $polls->first()->options);
    }

    private function createPollOption(array $attributes = []): PollOption
    {
        return PollOption::create(array_merge([
            'label' => 'Option',
        ], $attributes));
    }

    public function test_get_active_for_site_respects_limit(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->createPoll(['status' => 'active', 'site_id' => $this->siteId]);
        }

        $polls = $this->repository->getActiveForSite($this->siteId, limit: 5);

        $this->assertCount(5, $polls);
    }

    // -------------------------------------------------------------------------
    // getMemberVote
    // -------------------------------------------------------------------------

    public function test_get_active_for_site_orders_by_newest_first(): void
    {
        $older = $this->createPoll([
            'status' => 'active',
            'site_id' => $this->siteId,
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
        ]);
        $newer = $this->createPoll([
            'status' => 'active',
            'site_id' => $this->siteId,
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);

        $polls = $this->repository->getActiveForSite($this->siteId);

        $this->assertSame($newer->id, $polls->last()->id);
    }

    public function test_get_member_vote_returns_vote_when_exists(): void
    {
        $member = $this->createMember();
        $poll = $this->createPoll(['site_id' => $this->siteId]);
        $option = $this->createPollOption(['poll_id' => $poll->id]);
        $this->createPollVote(['poll_id' => $poll->id, 'poll_option_id' => $option->id, 'member_id' => $member->id]);

        $vote = $this->repository->getMemberVote($poll->id, $member->id);

        $this->assertNotNull($vote);
        $this->assertSame($member->id, $vote->member_id);
        $this->assertSame($poll->id, $vote->poll_id);
    }

    private function createPollVote(array $attributes = []): PollVote
    {
        return PollVote::create(array_merge([
            'voted_at' => now(),
        ], $attributes));
    }

    // -------------------------------------------------------------------------
    // castVote
    // -------------------------------------------------------------------------

    public function test_get_member_vote_returns_null_when_not_voted(): void
    {
        $member = $this->createMember();
        $poll = $this->createPoll(['site_id' => $this->siteId]);

        $vote = $this->repository->getMemberVote($poll->id, $member->id);

        $this->assertNull($vote);
    }

    public function test_get_member_vote_is_scoped_to_poll(): void
    {
        $member = $this->createMember();
        $poll1 = $this->createPoll(['site_id' => $this->siteId]);
        $poll2 = $this->createPoll(['site_id' => $this->siteId]);
        $option = $this->createPollOption(['poll_id' => $poll1->id]);
        $this->createPollVote(['poll_id' => $poll1->id, 'poll_option_id' => $option->id, 'member_id' => $member->id]);

        $vote = $this->repository->getMemberVote($poll2->id, $member->id);

        $this->assertNull($vote);
    }

    // -------------------------------------------------------------------------
    // getResults
    // -------------------------------------------------------------------------

    public function test_cast_vote_creates_vote_record(): void
    {
        $member = $this->createMember();
        $poll = $this->createPoll(['site_id' => $this->siteId]);
        $option = $this->createPollOption(['poll_id' => $poll->id]);

        $vote = $this->repository->castVote($poll->id, $option->id, $member->id);

        $this->assertInstanceOf(PollVote::class, $vote);
        $this->assertDatabaseHas('poll_votes', [
            'poll_id' => $poll->id,
            'poll_option_id' => $option->id,
            'member_id' => $member->id,
        ]);
    }

    public function test_cast_vote_sets_voted_at_timestamp(): void
    {
        $member = $this->createMember();
        $poll = $this->createPoll(['site_id' => $this->siteId]);
        $option = $this->createPollOption(['poll_id' => $poll->id]);

        $vote = $this->repository->castVote($poll->id, $option->id, $member->id);

        $this->assertNotNull($vote->voted_at);
    }

    public function test_get_results_returns_vote_counts_and_percentages(): void
    {
        $poll = $this->createPoll(['site_id' => $this->siteId]);
        $optYes = $this->createPollOption(['poll_id' => $poll->id, 'label' => 'Yes']);
        $optNo = $this->createPollOption(['poll_id' => $poll->id, 'label' => 'No']);

        $this->createPollVote(['poll_id' => $poll->id, 'poll_option_id' => $optYes->id, 'member_id' => $this->createMember()->id]);
        $this->createPollVote(['poll_id' => $poll->id, 'poll_option_id' => $optYes->id, 'member_id' => $this->createMember()->id]);
        $this->createPollVote(['poll_id' => $poll->id, 'poll_option_id' => $optNo->id, 'member_id' => $this->createMember()->id]);

        $results = $this->repository->getResults($poll->id);

        $yesResult = collect($results)->firstWhere('id', $optYes->id);
        $noResult = collect($results)->firstWhere('id', $optNo->id);

        $this->assertSame(2, $yesResult['votes']);
        $this->assertSame(1, $noResult['votes']);
        $this->assertSame(67.0, $yesResult['percentage']);
        $this->assertSame(33.0, $noResult['percentage']);
    }

    public function test_get_results_returns_zero_percentage_when_no_votes(): void
    {
        $poll = $this->createPoll(['site_id' => $this->siteId]);
        $option = $this->createPollOption(['poll_id' => $poll->id, 'label' => 'Maybe']);

        $results = $this->repository->getResults($poll->id);

        $this->assertSame(0, $results[0]['percentage']);
        $this->assertSame(0, $results[0]['votes']);
    }

    // -------------------------------------------------------------------------
    // Fixtures
    // -------------------------------------------------------------------------

    public function test_get_results_returns_empty_array_when_poll_not_found(): void
    {
        $results = $this->repository->getResults(99999);

        $this->assertSame([], $results);
    }

    public function test_get_results_includes_label_and_id(): void
    {
        $poll = $this->createPoll(['site_id' => $this->siteId]);
        $option = $this->createPollOption(['poll_id' => $poll->id, 'label' => 'Absolutely']);

        $results = $this->repository->getResults($poll->id);

        $this->assertSame($option->id, $results[0]['id']);
        $this->assertSame('Absolutely', $results[0]['label']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PollRepository();
    }
}