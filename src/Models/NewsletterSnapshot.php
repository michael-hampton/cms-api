<?php

namespace App\Models;

/**
 * @property int $id
 * @property int $newsletter_id
 * @property int|null $layout_version_id
 * @property int|null $branding_version_id
 * @property string $layout_html_snapshot
 * @property array|null $branding_snapshot_json
 * @property string|null $view_token
 * @property string|null $view_token_expires_at
 * @property string $created_at
 */
class NewsletterSnapshot extends Model
{
    protected $table = 'newsletter_snapshots';

    public $timestamps = false;

    protected $fillable = [
        'newsletter_id',
        'layout_version_id',
        'branding_version_id',
        'layout_html_snapshot',
        'branding_snapshot_json',
        'view_token',
        'view_token_expires_at',
        'created_at',
    ];

    protected $casts = [
        'branding_snapshot_json' => 'array',
    ];

    public function isViewTokenValid(): bool
    {
        if (!$this->view_token) {
            return false;
        }

        if ($this->view_token_expires_at === null) {
            return true;
        }

        return strtotime($this->view_token_expires_at) > time();
    }

    public static function findByToken(string $token): ?self
    {
        return static::where('view_token', $token)->first();
    }
}