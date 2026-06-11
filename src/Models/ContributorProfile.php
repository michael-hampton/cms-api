<?php

namespace App\Models;

class ContributorProfile extends Model
{
    protected $table = 'oc_contributor_profiles';

    protected $fillable = [
        'user_id',
        'site_id',
        'author_id',
        'display_name',
        'bio',
        'avatar',
        'expertise',
        'sample_links',
        'timezone',
        'portfolio_url',
        'linkedin_url',
        'twitter_url',
        'instagram_url',
        'tiktok_url',
        'payment_method_type',
        'payment_details',
        'stripe_customer_id',
        'tax_country',
        'account_status',
        'closure_reason',
        'closure_requested_at',
        'date_of_birth',
        'age_verified_at',
        'age_verification_method',
        'minimum_age_confirmed',
        'tax_classification',
        'vat_number',
        'author_sync_status',
        'author_last_synced_at',
        'author_last_synced_by',
    ];

    protected $hidden = [
        'payment_details', // encrypted; never expose raw
    ];

    protected $casts = [
        'sample_links' => 'array',
        'author_last_synced_at' => 'datetime',
    ];

    /**
     * Returns expertise topics as an array.
     * Stored as CSV in the DB column; exposed as a list everywhere in code.
     */
    public function getExpertiseArrayAttribute(): array
    {
        if (empty($this->expertise)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $this->expertise))));
    }

    /**
     * Accept either a CSV string or an array when setting expertise.
     */
    public function setExpertiseAttribute(mixed $value): void
    {
        if (is_array($value)) {
            $this->attributes['expertise'] = implode(',', array_filter(array_map('trim', $value)));
        } else {
            $this->attributes['expertise'] = $value;
        }
    }
}
