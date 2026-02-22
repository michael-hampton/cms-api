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
        'sort_order',
        'deleted_at'
    ];

    protected $casts = [
        'criteria' => 'array',
        'reward_config' => 'array',
        'is_active' => 'boolean',
        'max_claims_per_member' => 'integer',
        'sort_order' => 'integer',
        'deleted_at' => 'datetime'
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

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

    /**
     * Products that trigger this reward definition when purchased.
     * Pivot: product_reward_definitions (product_id, reward_definition_id)
     */
    public function products($relation = false)
    {
        return $this->belongsToMany(
            Product::class,
            'product_reward_definitions',
            'reward_definition_id',
            'product_id'
        );
    }

    // -------------------------------------------------------------------------
    // Business logic
    // -------------------------------------------------------------------------

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

        return $this->compareValues($this->getCurrentValue($member, $type), $operator, $value);
    }

    private function getCurrentValue(Member $member, string $type)
    {
        return match ($type) {
            'signup' => 1,
            'badges_earned' => $member->badges()->count(),
            'points_earned' => $member->points()->sum('points'),
            'comments_count' => $member->comments()->count(),
            'subscriptions_count' => \App\Models\Subscription::where('member_id', $member->id)
                ->where('status', 'active')->count(),
            'orders_completed' => \App\Models\Order::where('user_id', $member->id)
                ->where('status', 'completed')->count(),
            'member_days' => now_datetime()->diffInDays($member->created_at),
            'specific_badge' => $member->badges()->where('slug', $type['badge_slug'] ?? '')->exists() ? 1 : 0,
            default => 0,
        };
    }

    private function compareValues($actual, string $operator, $expected): bool
    {
        return match ($operator) {
            '>=' => $actual >= $expected,
            '>' => $actual > $expected,
            '<=' => $actual <= $expected,
            '<' => $actual < $expected,
            '==' => $actual == $expected,
            '!=' => $actual != $expected,
            default => false,
        };
    }

    public function formatCriterion(array $criterion): string
    {
        $type = $criterion['type'] ?? '';
        $operator = $criterion['operator'] ?? '>=';
        $value = $criterion['value'] ?? 0;

        $operatorText = match ($operator) {
            '>=' => 'at least',
            '>' => 'more than',
            '<=' => 'at most',
            '<' => 'less than',
            '==' => 'exactly',
            default => ''
        };

        $s = fn(int $n) => $n !== 1 ? 's' : '';

        return match ($type) {
            'badges_earned' => "Earn {$operatorText} {$value} badge{$s($value)}",
            'points_earned' => "Earn {$operatorText} {$value} points",
            'comments_count' => "Post {$operatorText} {$value} comment{$s($value)}",
            'orders_completed' => "Complete {$operatorText} {$value} order{$s($value)}",
            'member_days' => "Be a member for {$operatorText} {$value} day{$s($value)}",
            'subscriptions_count' => "Have {$operatorText} {$value} active subscription{$s($value)}",
            'signup' => 'Sign up for an account',
            default => 'Complete required action',
        };
    }
}