<?php

declare(strict_types=1);

namespace App\Models;

/**
 * MemberMerge
 *
 * Audit record for a completed member account merge.
 *
 * @property int         $id
 * @property int         $primary_member_id
 * @property int         $merged_member_id
 * @property int         $merged_by
 * @property string      $merged_at
 * @property string|null $reason
 * @property string|null $metadata   JSON
 * @property string      $created_at
 * @property string      $updated_at
 */
class MemberMerge extends Model
{
    protected $table = 'member_merges';

    protected $fillable = [
        'primary_member_id',
        'merged_member_id',
        'merged_by',
        'merged_at',
        'reason',
        'metadata',
    ];

    protected $casts = [
        'merged_at'  => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}