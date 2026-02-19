<?php

namespace App\Models;

class MerchantAutoBoostSetting extends Model
{
    protected $table = 'merchant_auto_boost_settings';

    protected $fillable = [
        'merchant_id',
        'monthly_budget',
        'goal',
        'contexts_allowed',
        'is_enabled',
        'budget_used_this_month',
        'budget_period_month',
    ];

    protected $casts = [
        'monthly_budget' => 'float',
        'contexts_allowed' => 'array',
        'is_enabled' => 'boolean',
        'budget_used_this_month' => 'float',
    ];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function remainingBudget(): float
    {
        return max(0, $this->monthly_budget - $this->budget_used_this_month);
    }

    public function isInCurrentPeriod(): bool
    {
        return $this->budget_period_month === date('Y-m');
    }

    /**
     * Resets the used budget counter when we enter a new calendar month.
     */
    public function resetIfNewPeriod(): void
    {
        $currentPeriod = date('Y-m');

        if ($this->budget_period_month !== $currentPeriod) {
            $this->budget_used_this_month = 0;
            $this->budget_period_month = $currentPeriod;
            $this->save();
        }
    }
}