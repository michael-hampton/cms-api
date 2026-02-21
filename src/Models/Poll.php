<?php

namespace App\Models;

class Poll extends Model
{
    protected $table = 'polls';

    protected $fillable = [
        'site_id', 'question', 'status', 'closes_at',
    ];

    protected $dates = ['closes_at', 'created_at', 'updated_at'];

    public function options()
    {
        return $this->hasMany(PollOption::class, 'poll_id')
            ->orderBy('sort_order');
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') return false;
        if ($this->closes_at && $this->closes_at < now_datetime()) return false;
        return true;
    }

    public function totalVotes(): int
    {
        return $this->votes()->count();
    }

    public function votes()
    {
        return $this->hasMany(PollVote::class, 'poll_id');
    }
}