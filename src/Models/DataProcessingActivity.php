<?php

namespace App\Models;

class DataProcessingActivity extends Model
{
    protected $table = 'data_processing_activities';

    protected $fillable = [
        'name',
        'purpose',
        'data_categories',
        'data_subjects',
        'recipients',
        'transfers',
        'retention_period_days',
        'security_measures',
        'related_consent_types'
    ];

    protected $casts = [
        'data_categories' => 'array',
        'data_subjects' => 'array',
        'recipients' => 'array',
        'transfers' => 'array',
        'retention_period_days' => 'integer',
        'security_measures' => 'array',
        'related_consent_types' => 'array'
    ];

    public function hasInternationalTransfers(): bool
    {
        return is_array($this->transfers) && count($this->transfers) > 0;
    }

    public function getRetentionPeriodYears(): float
    {
        return round($this->retention_period_days / 365, 1);
    }

    public function involvesConsentType(string $consentCode): bool
    {
        return in_array($consentCode, $this->related_consent_types ?? []);
    }

    public function scopeByConsentType($query, string $consentCode)
    {
        return $query->whereJsonContains('related_consent_types', $consentCode);
    }

    public function scopeWithTransfers($query)
    {
        return $query->whereNotNull('transfers');
    }

    public function scopeByRecipient($query, string $recipient)
    {
        return $query->whereJsonContains('recipients', $recipient);
    }
}