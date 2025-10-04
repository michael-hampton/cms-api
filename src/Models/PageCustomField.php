<?php

namespace App\Models;

class PageCustomField extends Model
{
    protected $table = 'page_custom_fields';
    protected $fillable = ['page_id', 'custom_field_definition_id', 'field_value', 'created_at', 'updated_at'];

    public function page(): ?Model
    {
        return $this->belongsTo(Page::class, 'page_id', 'id');
    }

    public function customFieldDefinition(): ?Model
    {
        return $this->belongsTo(CustomFieldDefinition::class, 'custom_field_definition_id');
    }

    public function getTypedValue()
    {
        $definition = $this->customFieldDefinition();
        if (!$definition) {
            return $this->field_value;
        }

        return $definition->getFormattedValue($this->field_value);
    }

    public function validateValue(): bool
    {
        $definition = $this->customFieldDefinition();
        if (!$definition) {
            return true;
        }

        return $definition->validateValue($this->field_value);
    }
}