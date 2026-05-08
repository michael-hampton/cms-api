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
    ];

    protected bool $useSoftDeletes = true;

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

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'member_id' => $this->member_id,
            'author_id' => $this->author_id,
            'author_name' => $this->author_name,
            'body' => $this->body,
            'parent_id' => $this->parent_id,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}