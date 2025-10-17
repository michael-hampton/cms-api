<?php

namespace App\Models;

class MemberRole extends Model
{
    protected $fillable = [
        'site_id',
        'name',
        'slug',
        'description',
        'permissions'
    ];

    protected $casts = [
        'permissions' => 'json'
    ];

    public $table = 'member_roles';

    public function members($relation = false)
    {
        return $this->belongsToMany(
            Member::class,
            'member_role_assignments',
            'role_id',
            'member_id',
            $relation
        );
    }

    public static function findBySlug(string $slug, int $siteId): ?self
    {
        return self::where('slug', $slug)
            ->where('site_id', $siteId)
            ->first();
    }
}