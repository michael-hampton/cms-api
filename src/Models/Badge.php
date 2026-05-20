<?php

namespace App\Models;

class Badge extends Model
{
    protected $table = 'badges';

    protected $fillable = [
        'site_id', 'name', 'slug', 'description', 'icon', 'tier',
        'category', 'criteria', 'points', 'is_active', 'sort_order'
    ];

    protected $casts = [
        'criteria' => 'array',
        'is_active' => 'boolean',
        'points' => 'integer'
    ];

    public function members($relation = false)
    {
        return $this->belongsToMany(
            Member::class,
            'member_badges',
            'badge_id',
            'member_id',
            true
        )->withPivot(['earned_at', 'criteria_met', 'is_visible'])
        ->get();
    }

    public function checkCriteria(Member $member): bool
    {
        $criteria = $this->criteria;

        foreach ($criteria as $rule) {
            if (!$this->evaluateRule($rule, $member)) {
                return false;
            }
        }

        return true;
    }

    private function evaluateRule(array $rule, Member $member): bool
    {
        $type = $rule['type'] ?? '';
        $operator = $rule['operator'] ?? '>=';
        $value = $rule['value'] ?? 0;

        switch ($type) {
            case 'comments_count':
                $count = $member->comments()->count();
                return $this->compareValues($count, $operator, $value);

            case 'pages_read':
                $count = $member->pageViews()
                    ->unique('page_id')
                    ->count();
                return $this->compareValues($count, $operator, $value);

            case 'likes_given':
                $count = $member->pageLikes()->count();
                return $this->compareValues($count, $operator, $value);

            case 'member_days':
                $days = now_datetime()->diffInDays($member->created_at);
                return $this->compareValues($days, $operator, $value);

            case 'orders_count':
                $count = Order::where('user_id', $member->id)
                    ->where('status', 'completed')
                    ->count();
                return $this->compareValues($count, $operator, $value);

            case 'total_spent':
                $total = Order::where('user_id', $member->id)
                    ->where('status', 'completed')
                    ->sum('total');
                return $this->compareValues($total, $operator, $value);

            default:
                return false;
        }
    }

    private function compareValues($actual, $operator, $expected): bool
    {
        switch ($operator) {
            case '>=':
                return $actual >= $expected;
            case '>':
                return $actual > $expected;
            case '<=':
                return $actual <= $expected;
            case '<':
                return $actual < $expected;
            case '==':
                return $actual == $expected;
            case '!=':
                return $actual != $expected;
            default:
                return false;
        }
    }
}
