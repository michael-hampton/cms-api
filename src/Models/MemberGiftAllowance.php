<?php

namespace App\Models;

class MemberGiftAllowance extends Model
{
    protected $table = 'member_gift_allowances';

    protected $fillable = [
        'member_id',
        'site_id',
        'annual_gift_limit',
        'gifts_used_this_year',
        'year_start_date'
    ];

    protected $casts = [
        'year_start_date' => 'date',
        'annual_gift_limit' => 'integer',
        'gifts_used_this_year' => 'integer'
    ];

    public function member($relation = false)
    {
        return $this->belongsTo(Member::class, 'member_id', 'id', $relation);
    }

    public function incrementUsage(): bool
    {
        if (!$this->canGift()) {
            return false;
        }

        return $this->update([
            'gifts_used_this_year' => $this->gifts_used_this_year + 1
        ]);
    }

    public function canGift(): bool
    {
        return $this->getRemainingGifts() > 0;
    }

    public function getRemainingGifts(): int
    {
        return max(0, $this->annual_gift_limit - $this->gifts_used_this_year);
    }

    public function resetIfNewYear(): void
    {
        $yearStart = $this->year_start_date;
        $oneYearLater = $yearStart->modify('+1 year');

        if (now_datetime() >= $oneYearLater) {
            $this->update([
                'gifts_used_this_year' => 0,
                'year_start_date' => now_datetime()->format('Y-m-d')
            ]);
        }
    }
}