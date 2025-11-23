<?php

namespace App\Models;

class ConsentType extends Model
{
    protected $table = 'consent_types';

    protected $fillable = [
        'code',
        'name',
        'description',
        'category',
        'required',
        'retention_days',
        'data_purposes',
        'is_active'
    ];

    protected $casts = [
        'required' => 'boolean',
        'is_active' => 'boolean',
        'retention_days' => 'integer',
        'data_purposes' => 'array'
    ];

    public function memberConsents()
    {
        return $this->hasMany(MemberConsent::class, 'consent_type_id');
    }

    public function subscriberConsents()
    {
        return $this->hasMany(SubscriberConsent::class, 'consent_type_id');
    }

    public function auditLogs()
    {
        return $this->hasMany(ConsentAuditLog::class, 'consent_type_id');
    }

    public function isRequired(): bool
    {
        return $this->required === true;
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeRequired($query)
    {
        return $query->where('required', true);
    }

    public function scopeOptional($query)
    {
        return $query->where('required', false);
    }
}