<?php

namespace App\Models;

/**
 * @property string   $code         ISO 3166-1 alpha-2 (PK). Matches Stripe.
 * @property string   $name         English display name.
 * @property bool     $has_states   Whether state/province is collected for this country.
 * @property bool     $is_active    Whether this country appears in dropdowns.
 * @property int      $sort_order   Controls dropdown ordering.
 */
class Country extends Model
{
    protected $table = 'countries';
    protected $primaryKey = 'code';
    protected $keyType    = 'string';
    public    $incrementing = false;

    protected $fillable = [
        'code',
        'name',
        'has_states',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'has_states' => 'boolean',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Returns ['GB' => 'United Kingdom', ...] ordered for a dropdown.
     * Cached per-request via the application cache.
     */
    public static function forDropdown(): array
    {
        return cache()->remember('countries.dropdown', now_datetime()->addMinutes(10), function () {
            return static::active()->ordered()->pluck('name', 'code')->all();
        });
    }
}