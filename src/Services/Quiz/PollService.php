<?php

namespace App\Services\Quiz;

use App\Models\Member;
use App\Repositories\Quiz\PollRepository;

class PollService
{
    public function __construct(
        private readonly PollRepository $pollRepository
    )
    {
    }

    public function getActivePollsForSite(int $siteId, ?Member $member = null): array
    {
        $polls = $this->pollRepository->getActiveForSite($siteId);

        return $polls->map(function ($poll) use ($member) {
            $memberVoteOptionId = null;
            $hasVoted = false;

            if ($member) {
                $vote = $this->pollRepository->getMemberVote($poll->id, $member->id);
                if ($vote) {
                    $hasVoted = true;
                    $memberVoteOptionId = $vote->poll_option_id;
                }
            }

            $results = $hasVoted ? $this->pollRepository->getResults($poll->id) : [];
            $total = $poll->totalVotes();

            return [
                'id' => $poll->id,
                'question' => $poll->question,
                'total_votes' => $total,
                'has_voted' => $hasVoted,
                'voted_option_id' => $memberVoteOptionId,
                'options' => $poll->options->map(fn($o) => [
                    'id' => $o->id,
                    'label' => $o->label,
                ])->toArray(),
                'results' => $results,
                'closes_at' => $poll->closes_at?->format('M j'),
            ];
        })->toArray();
    }

    public function castVote(int $pollId, int $optionId, Member $member): array
    {
        // Check already voted
        if ($this->pollRepository->getMemberVote($pollId, $member->id)) {
            return ['success' => false, 'message' => 'Already voted'];
        }

        // Validate option belongs to poll
        $poll = $this->pollRepository->find($pollId);
        if (!$poll || !$poll->isActive()) {
            return ['success' => false, 'message' => 'Poll not available'];
        }

        $validOption = $poll->options->contains('id', $optionId);
        if (!$validOption) {
            return ['success' => false, 'message' => 'Invalid option'];
        }

        $this->pollRepository->castVote($pollId, $optionId, $member->id);

        return [
            'success' => true,
            'results' => $this->pollRepository->getResults($pollId),
            'total_votes' => $poll->totalVotes() + 1,
        ];
    }
}