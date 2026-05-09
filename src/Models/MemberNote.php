<?php

namespace App\Models;

/**
 * @property int $id
 * @property int $member_id
 * @property int $site_id
 * @property int|null $author_id
 * @property string|null $author_name
 * @property string $body
 * @property int|null $parent_id
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 * @property \DateTime|null $deleted_at
 */
class MemberNote extends Model
{
    protected $table = 'member_notes';

    protected $fillable = [
        'member_id',
        'site_id',
        'author_id',
        'author_name',
        'body',
        'parent_id',
        'deleted_at'
    ];

    protected bool $useSoftDeletes = true;

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    /** Direct replies to this note (one level deep). */
    public function replies()
    {
        return $this->hasMany(MemberNote::class, 'parent_id')
            ->orderBy('created_at', 'asc');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }
}