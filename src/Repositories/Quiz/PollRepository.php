<?php

namespace App\Repositories\Quiz;

use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\Poll;
use App\Models\PollVote;
use App\Repositories\Repository;

class PollRepository extends Repository
{
    public function getActiveForSite(int $siteId, int $limit = 5): Collection
    {
        return Poll::where('site_id', $siteId)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('closes_at')
                    ->orWhere('closes_at', '>', now_datetime()->format('Y-m-d H:i:s'));
            })
            ->with(['options'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getMemberVote(int $pollId, int $memberId): ?PollVote
    {
        return PollVote::where('poll_id', $pollId)
            ->where('member_id', $memberId)
            ->first();
    }

    public function castVote(int $pollId, int $optionId, int $memberId): Model
    {
        return PollVote::create([
            'poll_id' => $pollId,
            'poll_option_id' => $optionId,
            'member_id' => $memberId,
            'voted_at' => now_datetime(),
        ]);
    }

    public function getResults(int $pollId): array
    {
        $poll = Poll::with(['options.votes'])->find($pollId);
        if (!$poll) return [];

        $total = $poll->totalVotes();

        return $poll->options->map(function ($option) use ($total) {
            $count = $option->voteCount();
            return [
                'id' => $option->id,
                'label' => $option->label,
                'votes' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100) : 0,
            ];
        })->toArray();
    }

    protected function getModelClass(): string
    {
        return Poll::class;
    }
}