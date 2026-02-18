<?php

namespace App\Resources;

use App\Enums\Vouchers\VoucherType;
use App\Framework\Resource\JsonResource;

class RewardDefinitionResource extends JsonResource
{
    private ?array $statistics = null;

    public function __construct($resource, ?array $statistics = null)
    {
        parent::__construct($resource);
        $this->statistics = $statistics;
    }

    /**
     * Create resource with statistics
     */
    public static function makeWithStatistics($resource, array $statistics): self
    {
        return new self($resource, $statistics);
    }

    /**
     * Convert to array with extended information
     */
    public function toArrayExtended(): array
    {
        $data = $this->toArray();

        $data['formatted_criteria'] = $this->getFormattedCriteria();
        $data['formatted_reward_config'] = $this->getFormattedRewardConfig();
        $data['reward_type_label'] = $this->getRewardTypeLabel();

        return $data;
    }

    public function toArray(): array
    {
        $data = [
            'id' => $this->getAttribute('id'),
            'name' => $this->getAttribute('name'),
            'slug' => $this->getAttribute('slug'),
            'description' => $this->getAttribute('description'),
            'reward_type' => $this->getAttribute('reward_type'),
            'criteria' => $this->getAttribute('criteria'),
            'reward_config' => $this->getAttribute('reward_config'),
            'max_claims_per_member' => $this->getAttribute('max_claims_per_member'),
            'is_active' => $this->getAttribute('is_active'),
            'sort_order' => $this->getAttribute('sort_order'),
            'site_id' => $this->getAttribute('site_id'),
            'starts_at' => $this->getAttribute('starts_at')?->format('Y-m-d H:i:s'),
            'ends_at' => $this->getAttribute('ends_at')?->format('Y-m-d H:i:s'),
            'auto_deactivate' => $this->getAttribute('auto_deactivate'),
            'created_at' => $this->getAttribute('created_at')?->format('Y-m-d H:i:s'),
            'updated_at' => $this->getAttribute('updated_at')?->format('Y-m-d H:i:s'),

            // Computed attributes
            'is_scheduled' => $this->isScheduled(),
            'schedule_status' => $this->getScheduleStatus(),
            'criteria_count' => is_array($this->getAttribute('criteria'))
                ? count($this->getAttribute('criteria'))
                : 0,

            // Relationships
            'memberRewards' => $this->whenLoaded('memberRewards', function ($rewards) {
                return $rewards->map(fn($reward) => MemberRewardResource::make($reward)->toArray()
                )->toArray();
            }),
        ];

        // Add statistics if provided
        if ($this->statistics !== null) {
            $data['statistics'] = $this->statistics;
        }

        return $data;
    }

    /**
     * Check if the reward definition has a schedule
     */
    private function isScheduled(): bool
    {
        return $this->getAttribute('starts_at') !== null
            || $this->getAttribute('ends_at') !== null;
    }

    /**
     * Get the current schedule status
     */
    private function getScheduleStatus(): ?string
    {
        $startsAt = $this->getAttribute('starts_at');
        $endsAt = $this->getAttribute('ends_at');

        if ($startsAt === null && $endsAt === null) {
            return null; // No schedule
        }

        $now = now_datetime();

        if ($startsAt && $startsAt > $now) {
            return 'upcoming'; // Scheduled for future
        }

        if ($endsAt && $endsAt < $now) {
            return 'ended'; // Schedule has ended
        }

        if (($startsAt === null || $startsAt <= $now) &&
            ($endsAt === null || $endsAt >= $now)) {
            return 'active'; // Currently within schedule
        }

        return 'unknown';
    }

    /**
     * Format criteria for display
     */
    public function getFormattedCriteria(): array
    {
        $criteria = $this->getAttribute('criteria');

        if (!is_array($criteria)) {
            return [];
        }

        return array_map(function ($criterion) {
            return [
                'type' => $criterion['type'] ?? 'unknown',
                'type_label' => $this->getCriteriaTypeLabel($criterion['type'] ?? ''),
                'operator' => $criterion['operator'] ?? '>=',
                'operator_label' => $this->getOperatorLabel($criterion['operator'] ?? '>='),
                'value' => $criterion['value'] ?? 0,
                'badge_slug' => $criterion['badge_slug'] ?? null,
                'display' => $this->formatCriterionDisplay($criterion)
            ];
        }, $criteria);
    }

    /**
     * Get criteria type label
     */
    private function getCriteriaTypeLabel(string $type): string
    {
        $labels = [
            'signup' => 'Signup',
            'badges_earned' => 'Badges Earned',
            'points_earned' => 'Points Earned',
            'comments_count' => 'Comments Count',
            'subscriptions_count' => 'Active Subscriptions',
            'orders_completed' => 'Orders Completed',
            'member_days' => 'Member Days',
            'specific_badge' => 'Specific Badge',
        ];

        return $labels[$type] ?? $type;
    }

    /**
     * Get operator label
     */
    private function getOperatorLabel(string $operator): string
    {
        $labels = [
            '>=' => 'Greater than or equal to',
            '>' => 'Greater than',
            '<=' => 'Less than or equal to',
            '<' => 'Less than',
            '==' => 'Equal to',
            '!=' => 'Not equal to',
        ];

        return $labels[$operator] ?? $operator;
    }

    /**
     * Format criterion for display
     */
    private function formatCriterionDisplay(array $criterion): string
    {
        $type = $criterion['type'] ?? '';
        $operator = $criterion['operator'] ?? '>=';
        $value = $criterion['value'] ?? 0;
        $badgeSlug = $criterion['badge_slug'] ?? null;

        $typeLabel = $this->getCriteriaTypeLabel($type);
        $operatorLabel = $this->getOperatorLabel($operator);

        if ($type === 'signup') {
            return 'User must sign up';
        }

        if ($type === 'specific_badge' && $badgeSlug) {
            return "User must earn badge: {$badgeSlug}";
        }

        return "{$typeLabel} {$operatorLabel} {$value}";
    }

    /**
     * Get reward configuration display
     */
    public function getFormattedRewardConfig(): array
    {
        $config = $this->getAttribute('reward_config');
        $type = $this->getAttribute('reward_type');

        if (!is_array($config)) {
            return [];
        }

        $formatted = [
            'type' => $type,
            'type_label' => $this->getRewardTypeLabel(),
        ];

        switch ($type) {
            case 'points':
                $formatted['points'] = $config['points'] ?? 0;
                $formatted['display'] = ($config['points'] ?? 0) . ' points';
                break;

            case 'discount':
                $formatted['discount_type'] = $config['discount_type'] ?? VoucherType::Percentage->value;
                $formatted['discount_value'] = $config['discount_value'] ?? 0;
                $formatted['expiry_days'] = $config['expiry_days'] ?? 30;

                if ($config['discount_type'] === VoucherType::Percentage->value) {
                    $formatted['display'] = ($config['discount_value'] ?? 0) . '% discount';
                } else {
                    $formatted['display'] = '$' . ($config['discount_value'] ?? 0) . ' discount';
                }
                break;

            case 'voucher':
                $formatted['expiry_days'] = $config['expiry_days'] ?? 30;
                $formatted['display'] = 'Voucher code (expires in ' . ($config['expiry_days'] ?? 30) . ' days)';
                break;
        }

        return $formatted;
    }

    /**
     * Get reward type label
     */
    public function getRewardTypeLabel(): string
    {
        $labels = [
            'voucher' => 'Voucher',
            'discount' => 'Discount',
            'points' => 'Points',
        ];

        return $labels[$this->getAttribute('reward_type')]
            ?? $this->getAttribute('reward_type');
    }
}