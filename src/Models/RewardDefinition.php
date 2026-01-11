<?php

namespace App\Models;

class RewardDefinition extends Model
{
    protected $table = 'reward_definitions';

    protected $fillable = [
        'site_id',
        'name',
        'slug',
        'description',
        'reward_type',
        'criteria',
        'reward_config',
        'max_claims_per_member',
        'is_active',
        'sort_order'
    ];

    protected $casts = [
        'criteria' => 'array',
        'reward_config' => 'array',
        'is_active' => 'boolean',
        'max_claims_per_member' => 'integer',
        'sort_order' => 'integer'
    ];

    public function checkCriteria(Member $member): bool
    {

        if (!$this->criteria || !is_array($this->criteria)) {
            return false;
        }

        foreach ($this->criteria as $criterion) {
            if (!$this->checkSingleCriterion($member, $criterion)) {
                return false;
            }
        }

        return true;
    }

    private function checkSingleCriterion(Member $member, array $criterion): bool
    {
        $type = $criterion['type'] ?? '';
        $operator = $criterion['operator'] ?? '>=';
        $value = $criterion['value'] ?? 0;

        $actualValue = $this->getCurrentValue($member, $type);

        return $this->compareValues($actualValue, $operator, $value);
    }

    private function getCurrentValue(Member $member, string $type)
    {
        switch ($type) {
            case 'signup':
                return 1; // Always true for signups

            case 'badges_earned':
                return $member->badges()->count();

            case 'points_earned':
                return $member->points()->sum('points');

            case 'comments_count':
                return $member->comments()->count();

            case 'subscriptions_count':
                return \App\Models\Subscription::where('member_id', $member->id)
                    ->where('status', 'active')
                    ->count();

            case 'orders_completed':
                return \App\Models\Order::where('user_id', $member->id)
                    ->where('status', 'completed')
                    ->count();

            case 'member_days':
                return now_datetime()->diffInDays($member->created_at);

            case 'specific_badge':
                $badgeSlug = $type['badge_slug'] ?? '';
                return $member->badges()->where('slug', $badgeSlug)->exists() ? 1 : 0;

            default:
                return 0;
        }
    }

    private function compareValues($actual, string $operator, $expected): bool
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

    public function memberRewards($relation = false)
    {
        return $this->hasMany(MemberReward::class, 'reward_definition_id', 'id', $relation);
    }

    public function availableVouchers($relation = false)
    {
        return $this->hasMany(RewardVoucherCode::class, 'reward_definition_id', 'id', $relation)
            ->where('is_used', false)
            ->whereNull('assigned_to_member_id');
    }
}