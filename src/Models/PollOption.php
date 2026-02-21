<?php

namespace App\Models;

class PollOption extends Model
{
    protected $table = 'poll_options';

    protected $fillable = [
        'poll_id', 'label', 'sort_order',
    ];

    public function poll()
    {
        return $this->belongsTo(Poll::class, 'poll_id');
    }

    public function voteCount(): int
    {
        return $this->votes()->count();
    }

    public function votes()
    {
        return $this->hasMany(PollVote::class, 'poll_option_id');
    }
}