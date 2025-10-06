<?php

namespace App\Models;

use App\Framework\Database\QueryBuilder;

class CustomFieldDefinition extends Model
{
    protected $table = 'custom_field_definitions';
    protected $fillable = [
        'name', 'key', 'type', 'description', 'options', 'validation_rules',
        'default_value', 'is_required', 'is_searchable', 'group_name',
        'sort_order', 'is_active', 'created_at', 'updated_at', 'placeholder', 'site_id'
    ];

    public function pageCustomFields(): array
    {
        return $this->hasMany(PageCustomField::class, 'custom_field_definition_id', 'id');
    }

    public function getOptionsAttribute()
    {
        $rawData = $this->attributes['options'] ?? null;
        return $rawData ? json_decode($rawData, true) : null;
    }

    public function getValidationRulesAttribute()
    {
        $rawData = $this->attributes['validation_rules'] ?? null;
        return $rawData ? json_decode($rawData, true) : null;
    }

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

    public function isSelectType(): bool
    {
        return in_array($this->type, ['select', 'multi_select']);
    }

    public function validateValue($value): bool
    {
        if ($this->is_required && empty($value)) {
            return false;
        }

        switch ($this->type) {
            case 'email':
                return empty($value) || filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
            case 'url':
                return empty($value) || filter_var($value, FILTER_VALIDATE_URL) !== false;
            case 'number':
                return empty($value) || is_numeric($value);
            case 'boolean':
                return is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false'], true);
            case 'date':
                return empty($value) || strtotime($value) !== false;
            case 'select':
                if (empty($value)) return !$this->is_required;
                $options = $this->getOptionsAttribute();
                return $options && in_array($value, array_column($options, 'value'));
            case 'multi_select':
                if (empty($value)) return !$this->is_required;
                if (!is_array($value)) return false;
                $options = $this->getOptionsAttribute();
                $allowedValues = $options ? array_column($options, 'value') : [];
                foreach ($value as $val) {
                    if (!in_array($val, $allowedValues)) return false;
                }
                return true;
            default:
                return true;
        }
    }

    public function getFormattedValue($value)
    {
        if ($value === null) return null;

        switch ($this->type) {
            case 'json':
                return json_decode($value, true);
            case 'number':
                return is_numeric($value) ? (float) $value : 0;
            case 'boolean':
                return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
            case 'date':
                return $value ? date('Y-m-d', strtotime($value)) : null;
            case 'multi_select':
                return is_string($value) ? json_decode($value, true) : $value;
            default:
                return $value;
        }
    }

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

    public function setOptionsAttribute($value): void
    {
        $this->attributes['options'] = is_array($value) ? json_encode($value) : $value;
    }

    public function setValidationRulesAttribute($value): void
    {
        $this->attributes['validation_rules'] = is_array($value) ? json_encode($value) : $value;
    }
}