<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Audit record for any subscription change (edition change, publication change, etc.).
 *
 * @property int         $id
 * @property int         $subscription_id
 * @property string      $change_type                 e.g. 'edition_change', 'publication_change'
 * @property int|null    $old_edition_id
 * @property int|null    $new_edition_id
 * @property int|null    $old_publication_id
 * @property int|null    $new_publication_id
 * @property int|null    $remaining_issues_transferred
 * @property string|null $reason
 * @property int         $created_by
 * @property string      $created_at
 * @property string      $updated_at
 */
class SubscriptionChange extends Model
{
    protected $table = 'subscription_changes';

    protected $fillable = [
        'subscription_id',
        'change_type',
        'old_edition_id',
        'new_edition_id',
        'old_publication_id',
        'new_publication_id',
        'remaining_issues_transferred',
        'reason',
        'created_by',
        'created_at',
        'updated_at',
    ];
}