<?php

namespace App\Models;

use App\Enums\Cms\CustomFieldContext;
use App\Enums\Cms\CustomFieldStorageType;
use App\Framework\Database\QueryBuilder;

class CustomFieldDefinition extends Model
{
    protected $table = 'custom_field_definitions';

    protected $fillable = [
        'name',
        'key',
        'type',
        'description',
        'options',
        'validation_rules',
        'default_value',
        'is_required',
        'is_searchable',
        'group_name',
        'sort_order',
        'is_active',
        'created_at',
        'updated_at',
        'placeholder',
        'site_id',
        // New columns (Ticket 2)
        'context',
        'storage_type',
        'profile_column',
        'is_locked',
    ];

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getOptionsAttribute(): ?array
    {
        $rawData = $this->attributes['options'] ?? null;
        return $rawData ? json_decode($rawData, true) : null;
    }

    public function getValidationRulesAttribute(): ?array
    {
        $rawData = $this->attributes['validation_rules'] ?? null;
        return $rawData ? json_decode($rawData, true) : null;
    }

    // ── Mutators ──────────────────────────────────────────────────────────────

    public function setOptionsAttribute(mixed $value): void
    {
        $this->attributes['options'] = is_array($value) ? json_encode($value) : $value;
    }

    public function setValidationRulesAttribute(mixed $value): void
    {
        $this->attributes['validation_rules'] = is_array($value) ? json_encode($value) : $value;
    }

    // ── Boolean helpers ───────────────────────────────────────────────────────

    public function isRequired(): bool
    {
        return (bool) $this->is_required;
    }

    public function isSearchable(): bool
    {
        return (bool) $this->is_searchable;
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function isLocked(): bool
    {
        return (bool) $this->is_locked;
    }

    public function isSelectType(): bool
    {
        return in_array($this->type, ['select', 'multi_select']);
    }

    // ── Profile-column helpers ────────────────────────────────────────────────

    public function isProfileColumnField(): bool
    {
        return $this->storage_type === CustomFieldStorageType::ProfileColumn->value;
    }

    public function isContributorProfileField(): bool
    {
        return $this->context === CustomFieldContext::ContributorProfile->value;
    }

    public function profileColumn(): ?string
    {
        return $this->profile_column;
    }

    // ── Validation / formatting ───────────────────────────────────────────────

    public function validateValue(mixed $value): bool
    {
        if ($this->is_required && empty($value)) {
            return false;
        }

        return match ($this->type) {
            'email'        => empty($value) || filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url'          => empty($value) || filter_var($value, FILTER_VALIDATE_URL) !== false,
            'number'       => empty($value) || is_numeric($value),
            'boolean'      => is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false'], true),
            'date'         => empty($value) || strtotime($value) !== false,
            'select'       => $this->validateSelectValue($value),
            'multi_select' => $this->validateMultiSelectValue($value),
            default        => true,
        };
    }

    public function getFormattedValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this->type) {
            'json'         => json_decode($value, true),
            'number'       => is_numeric($value) ? (float) $value : 0,
            'boolean'      => in_array(strtolower((string) $value), ['true', '1', 'yes', 'on'], true),
            'date'         => $value ? date('Y-m-d', strtotime($value)) : null,
            'multi_select' => is_string($value) ? json_decode($value, true) : $value,
            default        => $value,
        };
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive(QueryBuilder $query): QueryBuilder
    {
        return $query->where('is_active', 1);
    }

    public function scopeRequired(QueryBuilder $query): QueryBuilder
    {
        return $query->where('is_required', 1);
    }

    public function scopeSearchable(QueryBuilder $query): QueryBuilder
    {
        return $query->where('is_searchable', 1);
    }

    public function scopeByGroup(QueryBuilder $query, string $group): QueryBuilder
    {
        return $query->where('group_name', $group);
    }

    public function scopeByKey(QueryBuilder $query, string $key): QueryBuilder
    {
        return $query->where('key', $key);
    }

    public function scopeOrdered(QueryBuilder $query): QueryBuilder
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
    }

    /** Filter by context (e.g. 'page' or 'contributor_profile'). */
    public function scopeByContext(QueryBuilder $query, string $context): QueryBuilder
    {
        return $query->where('context', $context);
    }

    /** Filter by site. */
    public function scopeForSite(QueryBuilder $query, int $siteId): QueryBuilder
    {
        return $query->where('site_id', $siteId);
    }

    // ── Private validation helpers ────────────────────────────────────────────

    private function validateSelectValue(mixed $value): bool
    {
        if (empty($value)) {
            return !$this->is_required;
        }

        $options = $this->getOptionsAttribute();
        return $options && in_array($value, array_column($options, 'value'));
    }

    private function validateMultiSelectValue(mixed $value): bool
    {
        if (empty($value)) {
            return !$this->is_required;
        }

        if (!is_array($value)) {
            return false;
        }

        $options       = $this->getOptionsAttribute();
        $allowedValues = $options ? array_column($options, 'value') : [];

        foreach ($value as $val) {
            if (!in_array($val, $allowedValues)) {
                return false;
            }
        }

        return true;
    }
}