<?php

namespace App\Tests\Unit\Services\Quiz;

use App\Exceptions\PollAlreadyVotedException;
use App\Exceptions\PollNotAvailableException;
use App\Exceptions\PollOptionInvalidException;
use App\Framework\Database\Database;
use App\Framework\databaseMock\databaseMock;
use App\Framework\Support\Collection;
use App\Models\Member;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Repositories\Quiz\PollRepository;
use App\Services\Quiz\PollService;
use App\Tests\Unit\UnitTestCase;
use Mockery;
use Mockery\MockInterface;

class PollServiceTest extends UnitTestCase
{
    private PollRepository|MockInterface $pollRepository;
    private Database|MockInterface $databaseMock;
    private PollService $service;

    public function test_get_active_polls_returns_empty_array_when_no_polls(): void
    {
        $this->pollRepository
            ->expects('getActiveForSite')
            ->with(1)
            ->andReturn(new Collection([]));

        $result = $this->service->getActivePollsForSite(1);

        $this->assertSame([], $result);
    }

    public function test_get_active_polls_does_not_check_vote_when_no_member(): void
    {
        $poll = $this->makePoll(id: 10, question: 'Favourite colour?', totalVotes: 0);

        $this->pollRepository
            ->expects('getActiveForSite')
            ->with(1)
            ->andReturn(new Collection([$poll]));

        $this->pollRepository->shouldNotReceive('getMemberVote');

        $result = $this->service->getActivePollsForSite(1, member: null);

        $this->assertCount(1, $result);
        $this->assertFalse($result[0]['has_voted']);
        $this->assertNull($result[0]['voted_option_id']);
        $this->assertSame([], $result[0]['results']);
    }

    // -------------------------------------------------------------------------
    // getActivePollsForSite
    // -------------------------------------------------------------------------

    /**
     * @param PollOption[] $options
     */
    private function makePoll(
        int        $id,
        string     $question = 'A question?',
        int        $totalVotes = 0,
        bool       $isActive = true,
        array      $options = [],
        ?\DateTime $closesAt = null
    ): Poll
    {
        $poll = Mockery::mock(Poll::class)->makePartial();
        $poll->id = $id;
        $poll->question = $question;
        $poll->closes_at = $closesAt;
        $poll->options = new Collection($options);

        $poll->allows('totalVotes')->andReturn($totalVotes);
        $poll->allows('isActive')->andReturn($isActive);

        return $poll;
    }

    public function test_get_active_polls_marks_member_as_not_voted_when_no_vote_record(): void
    {
        $member = $this->makeMember(id: 5);
        $poll = $this->makePoll(id: 10, question: 'Cats or dogs?', totalVotes: 3);

        $this->pollRepository
            ->expects('getActiveForSite')
            ->with(1)
            ->andReturn(new Collection([$poll]));

        $this->pollRepository
            ->expects('getMemberVote')
            ->with(10, 5)
            ->andReturn(null);

        $result = $this->service->getActivePollsForSite(1, $member);

        $this->assertFalse($result[0]['has_voted']);
        $this->assertNull($result[0]['voted_option_id']);
        $this->assertSame([], $result[0]['results']);
    }

    private function makeMember(int $id): Member
    {
        $member = new Member();
        $member->id = $id;
        return $member;
    }

    public function test_get_active_polls_returns_results_when_member_has_voted(): void
    {
        $member = $this->makeMember(id: 5);
        $poll = $this->makePoll(id: 10, question: 'Cats or dogs?', totalVotes: 4);

        $vote = new PollVote();
        $vote->poll_option_id = 99;

        $expectedResults = [['id' => 99, 'label' => 'Cats', 'votes' => 3, 'percentage' => 75]];

        $this->pollRepository
            ->expects('getActiveForSite')
            ->with(1)
            ->andReturn(new Collection([$poll]));

        $this->pollRepository
            ->expects('getMemberVote')
            ->with(10, 5)
            ->andReturn($vote);

        $this->pollRepository
            ->expects('getResults')
            ->with(10)
            ->andReturn($expectedResults);

        $result = $this->service->getActivePollsForSite(1, $member);

        $this->assertTrue($result[0]['has_voted']);
        $this->assertSame(99, $result[0]['voted_option_id']);
        $this->assertSame($expectedResults, $result[0]['results']);
    }

    public function test_get_active_polls_formats_closes_at(): void
    {
        $member = $this->makeMember(id: 5);
        $closesAt = new \DateTime('2025-12-25');
        $poll = $this->makePoll(id: 10, question: 'Holiday poll?', totalVotes: 0, closesAt: $closesAt);

        $this->pollRepository
            ->expects('getActiveForSite')
            ->andReturn(new Collection([$poll]));

        $this->pollRepository
            ->expects('getMemberVote')
            ->andReturn(null);

        $result = $this->service->getActivePollsForSite(1, $member);

        $this->assertSame('Dec 25', $result[0]['closes_at']);
    }

    public function test_get_active_polls_closes_at_is_null_when_poll_has_no_closing_date(): void
    {
        $poll = $this->makePoll(id: 10, question: 'Open ended?', totalVotes: 0, closesAt: null);

        $this->pollRepository
            ->expects('getActiveForSite')
            ->andReturn(new Collection([$poll]));

        $result = $this->service->getActivePollsForSite(1, member: null);

        $this->assertNull($result[0]['closes_at']);
    }

    public function test_get_active_polls_includes_option_list(): void
    {
        $option1 = $this->makePollOption(id: 1, label: 'Yes');
        $option2 = $this->makePollOption(id: 2, label: 'No');
        $poll = $this->makePoll(id: 10, question: 'Yes or no?', totalVotes: 0, options: [$option1, $option2]);

        $this->pollRepository
            ->expects('getActiveForSite')
            ->andReturn(new Collection([$poll]));

        $result = $this->service->getActivePollsForSite(1, member: null);

        $this->assertSame(
            [['id' => 1, 'label' => 'Yes'], ['id' => 2, 'label' => 'No']],
            $result[0]['options']
        );
    }

    // -------------------------------------------------------------------------
    // castVote — guard: already voted
    // -------------------------------------------------------------------------

    private function makePollOption(int $id, string $label = 'Option'): PollOption
    {
        $option = new PollOption();
        $option->id = $id;
        $option->label = $label;
        return $option;
    }

    public function test_cast_vote_throws_when_member_already_voted(): void
    {
        $member = $this->makeMember(id: 5);

        $this->pollRepository
            ->expects('getMemberVote')
            ->with(10, 5)
            ->andReturn(new PollVote());

        $this->expectException(PollAlreadyVotedException::class);

        $this->service->castVote(pollId: 10, optionId: 1, member: $member);
    }

    // -------------------------------------------------------------------------
    // castVote — guard: poll not available
    // -------------------------------------------------------------------------

    public function test_cast_vote_does_not_write_when_member_already_voted(): void
    {
        $member = $this->makeMember(id: 5);

        $this->pollRepository
            ->expects('getMemberVote')
            ->andReturn(new PollVote());

        $this->pollRepository->shouldNotReceive('castVote');

        try {
            $this->service->castVote(10, 1, $member);
        } catch (PollAlreadyVotedException) {
        }

        $this->assertTrue(true);
    }

    public function test_cast_vote_throws_when_poll_not_found(): void
    {
        $member = $this->makeMember(id: 5);

        $this->pollRepository->expects('getMemberVote')->andReturn(null);
        $this->pollRepository->expects('find')->with(10)->andReturn(null);

        $this->expectException(PollNotAvailableException::class);

        $this->service->castVote(10, 1, $member);
    }

    // -------------------------------------------------------------------------
    // castVote — guard: invalid option
    // -------------------------------------------------------------------------

    public function test_cast_vote_throws_when_poll_is_not_active(): void
    {
        $member = $this->makeMember(id: 5);
        $poll = $this->makePoll(id: 10, isActive: false);

        $this->pollRepository->expects('getMemberVote')->andReturn(null);
        $this->pollRepository->expects('find')->with(10)->andReturn($poll);

        $this->expectException(PollNotAvailableException::class);

        $this->service->castVote(10, 1, $member);
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // castVote — happy path
    // -------------------------------------------------------------------------

    public function test_cast_vote_throws_when_option_does_not_belong_to_poll(): void
    {
        $member = $this->makeMember(id: 5);
        $poll = $this->makePoll(id: 10, options: [$this->makePollOption(id: 2)]);

        $this->pollRepository->expects('getMemberVote')->andReturn(null);
        $this->pollRepository->expects('find')->with(10)->andReturn($poll);

        $this->expectException(PollOptionInvalidException::class);

        $this->service->castVote(pollId: 10, optionId: 999, member: $member);
        $this->assertTrue(true);
    }

    public function test_cast_vote_persists_vote_inside_transaction(): void
    {
        $member = $this->makeMember(id: 5);
        $option = $this->makePollOption(id: 1);
        $poll = $this->makePoll(id: 10, options: [$option]);
        $results = [['id' => 1, 'label' => 'Yes', 'votes' => 1, 'percentage' => 100]];

        $this->pollRepository->expects('getMemberVote')->with(10, 5)->andReturn(null);
        $this->pollRepository->expects('find')->with(10)->andReturn($poll);
        $this->pollRepository->expects('castVote')->with(10, 1, 5)->once();
        $this->pollRepository->expects('getResults')->with(10)->andReturn($results);
        $this->pollRepository->expects('getTotalVotes')->with(10)->andReturn(1);

        $transactionCalled = false;
        $this->databaseMock
            ->expects('transaction')
            ->once()
            ->andReturnUsing(function (callable $cb) use (&$transactionCalled) {
                $transactionCalled = true;
                return $cb();
            });

        $result = $this->service->castVote(10, 1, $member);

        $this->assertTrue($transactionCalled, 'Database::transaction was not called');
        $this->assertSame($results, $result['results']);
        $this->assertSame(1, $result['total_votes']);
    }

    public function test_cast_vote_returns_fresh_total_votes_from_repository(): void
    {
        $member = $this->makeMember(id: 5);
        $option = $this->makePollOption(id: 1);
        $poll = $this->makePoll(id: 10, options: [$option]);

        $this->pollRepository->expects('getMemberVote')->andReturn(null);
        $this->pollRepository->expects('find')->andReturn($poll);
        $this->pollRepository->expects('castVote');
        $this->pollRepository->expects('getResults')->andReturn([]);
        $this->pollRepository->expects('getTotalVotes')->with(10)->andReturn(42);

        $this->databaseMock
            ->expects('transaction')
            ->andReturnUsing(fn(callable $cb) => $cb());

        $result = $this->service->castVote(10, 1, $member);

        $this->assertSame(42, $result['total_votes']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function test_cast_vote_rollback_on_repository_failure(): void
    {
        $member = $this->makeMember(id: 5);
        $option = $this->makePollOption(id: 1);
        $poll = $this->makePoll(id: 10, options: [$option]);

        $this->pollRepository->expects('getMemberVote')->andReturn(null);
        $this->pollRepository->expects('find')->andReturn($poll);
        $this->pollRepository->expects('castVote')->andThrow(new \RuntimeException('DB failure'));

        // Transaction should propagate the exception (triggering rollback in real DB)
        $this->databaseMock
            ->expects('transaction')
            ->andReturnUsing(function (callable $cb) {
                return $cb();
            });

        $this->expectException(\RuntimeException::class);

        $this->service->castVote(10, 1, $member);
    }

    protected function setUp(): void
    {

        $this->pollRepository = Mockery::mock(PollRepository::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->service = new PollService($this->pollRepository, $this->databaseMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}