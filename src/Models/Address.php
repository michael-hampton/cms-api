<?php

namespace App\Models;

use App\Framework\Database\QueryBuilder;

class Address extends Model
{
    protected $table = 'addresses';

    protected $fillable = [
        'member_id',
        'type',
        'is_default',
        'label',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postcode',
        'country'
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function member($relation = false)
    {
        return $this->belongsTo(Member::class, 'member_id', 'id', $relation);
    }

    public function scopeForMember(QueryBuilder $query, int $memberId): QueryBuilder
    {
        return $query->where('member_id', $memberId);
    }

    public function scopeDefault(QueryBuilder $query): QueryBuilder
    {
        return $query->where('is_default', true);
    }

    public function scopeShipping(QueryBuilder $query): QueryBuilder
    {
        return $query->whereIn('type', ['shipping', 'both']);
    }

    public function scopeBilling(QueryBuilder $query): QueryBuilder
    {
        return $query->whereIn('type', ['billing', 'both']);
    }

    public function getFormattedAttribute(): string
    {
        $parts = array_filter([
            $this->address_line_1,
            $this->address_line_2,
            $this->city,
            $this->state,
            $this->postcode,
            $this->country
        ]);

        return implode(', ', $parts);
    }

    public function toArray(): array
    {
        $data = parent::toArray();
        $data['formatted'] = $this->getFormattedAttribute();
        return $data;
    }
}