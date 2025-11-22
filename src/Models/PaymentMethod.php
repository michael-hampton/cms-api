<?php

namespace App\Models;

class PaymentMethod extends Model
{
    protected $table = 'payment_methods';

    protected $fillable = [
        'site_id',
        'name',
        'code',
        'provider',
        'is_active',
        'requires_processing',
        'configuration',
        'instructions',
        'sort_order',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'requires_processing' => 'boolean',
        'configuration' => 'array',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function site($relation = false)
    {
        return $this->belongsTo(Site::class, 'site_id', 'id', $relation);
    }

    public function payments($relation = false)
    {
        return $this->hasMany(Payment::class, 'payment_method', 'code', $relation);
    }

    public function getConfiguration(string $key = null)
    {
        if ($key === null) {
            return $this->configuration;
        }

        return $this->configuration[$key] ?? null;
    }

    public function setConfiguration(string $key, $value): void
    {
        $config = $this->configuration ?? [];
        $config[$key] = $value;
        $this->configuration = $config;
    }

    public function toArray(): array
    {
        $data = parent::toArray();
        $data['is_active_bool'] = $this->isActive();
        $data['requires_processing_bool'] = $this->requiresProcessing();

        // Don't expose sensitive configuration in API responses
        unset($data['configuration']);

        return $data;
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    public function requiresProcessing(): bool
    {
        return $this->requires_processing === true;
    }
}