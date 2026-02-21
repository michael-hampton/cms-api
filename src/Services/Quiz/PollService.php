<?php

namespace App\Services\Quiz;

use App\Exceptions\PollAlreadyVotedException;
use App\Exceptions\PollNotAvailableException;
use App\Exceptions\PollOptionInvalidException;
use App\Framework\Database\Database;
use App\Models\Member;
use App\Repositories\Quiz\PollRepository;

class PollService
{
    public function __construct(
        private readonly PollRepository $pollRepository,
        private readonly Database       $database
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

    /**
     * @throws PollAlreadyVotedException
     * @throws PollNotAvailableException
     * @throws PollOptionInvalidException
     */
    public function castVote(int $pollId, int $optionId, Member $member): array
    {
        if ($this->pollRepository->getMemberVote($pollId, $member->id)) {
            throw new PollAlreadyVotedException("Member {$member->id} has already voted on poll {$pollId}.");
        }

        $poll = $this->pollRepository->find($pollId);

        if (!$poll || !$poll->isActive()) {
            throw new PollNotAvailableException("Poll {$pollId} is not available.");
        }

        if (!$poll->options->contains('id', $optionId)) {
            throw new PollOptionInvalidException("Option {$optionId} does not belong to poll {$pollId}.");
        }

        return $this->database->transaction(function () use ($pollId, $optionId, $member) {
            $this->pollRepository->castVote($pollId, $optionId, $member->id);

            return [
                'results' => $this->pollRepository->getResults($pollId),
                'total_votes' => $this->pollRepository->getTotalVotes($pollId),
            ];
        });
    }
}