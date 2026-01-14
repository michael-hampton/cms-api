<?php

namespace App\Models;

use App\Framework\Support\Str;

class ProductSpecificationGroup extends Model
{
    protected $table = 'product_specification_groups';

    protected $fillable = [
        'name',
        'slug',
        'sort_order',
        'is_active'
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get or create a specification group by name
     */
    public static function getOrCreate(string $name): self
    {
        // Normalize name (trim and capitalize first letter)
        $normalizedName = trim($name);
        $normalizedName = ucfirst(strtolower($normalizedName));

        // Try to find existing group (case-insensitive)
        $group = self::whereRaw('LOWER(name) = ?', [strtolower($normalizedName)])->first();

        if (!$group) {
            $group = self::create([
                'name' => $normalizedName,
                'slug' => self::generateSlug($normalizedName),
                'is_active' => true
            ]);
        }

        return $group;
    }

    /**
     * Generate slug from name
     */
    public static function generateSlug(string $name): string
    {
        $slug = Str::slug($name);
        $count = 1;
        $originalSlug = $slug;

        while (self::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }

        return $slug;
    }

    public function specifications($relation = false)
    {
        return $this->hasMany(ProductSpecification::class, 'specification_group_id', null, $relation);
    }

    public function activeSpecifications($relation = false)
    {
        return ProductSpecification::whereHas('product', function ($query) {
            $query->where('is_active', true);
        });
    }
}