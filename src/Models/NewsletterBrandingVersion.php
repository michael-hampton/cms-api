<?php

namespace App\Models;

/**
 * @property int $id
 * @property int $branding_config_id
 * @property int $version_number
 * @property array $branding_json_snapshot
 * @property string $created_at
 */
class NewsletterBrandingVersion extends Model
{
    protected $table = 'newsletter_branding_versions';

    public $timestamps = false;

    protected $fillable = [
        'branding_config_id',
        'version_number',
        'branding_json_snapshot',
        'created_at',
    ];

    protected $casts = [
        'branding_json_snapshot' => 'array',
        'version_number' => 'integer',
    ];

    public function config(): ?Model
    {
        return NewsletterBrandingConfiguration::find($this->branding_config_id);
    }
}