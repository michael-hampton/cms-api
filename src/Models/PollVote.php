<?php

namespace App\Models;

class PollVote extends Model
{
    protected $table = 'poll_votes';

    protected $fillable = [
        'poll_id', 'poll_option_id', 'member_id', 'voted_at',
    ];

    protected $dates = ['voted_at', 'created_at', 'updated_at'];

    public function poll()
    {
        return $this->belongsTo(Poll::class, 'poll_id');
    }

    public function option()
    {
        return $this->belongsTo(PollOption::class, 'poll_option_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }
}