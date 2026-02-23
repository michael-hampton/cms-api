<?php

namespace App\Models;

/**
 * @property int $id
 * @property int $newsletter_id
 * @property string|null $logo_url
 * @property string|null $header_text
 * @property string|null $footer_text
 * @property array|null $theme_json
 * @property string|null $custom_css
 * @property string $created_at
 * @property string $updated_at
 */
class NewsletterBrandingConfiguration extends Model
{
    protected $table = 'newsletter_branding_configurations';

    protected $fillable = [
        'newsletter_id',
        'logo_url',
        'header_text',
        'footer_text',
        'theme_json',
        'custom_css',
    ];

    protected $casts = [
        'theme_json' => 'array',
    ];

    public function toSnapshot(): array
    {
        return [
            'logo_url' => $this->logo_url,
            'header_text' => $this->header_text,
            'footer_text' => $this->footer_text,
            'theme_json' => $this->theme_json,
            'custom_css' => $this->custom_css,
        ];
    }

    public function latestVersion(): ?NewsletterBrandingVersion
    {
        return NewsletterBrandingVersion::where('branding_config_id', $this->id)
            ->orderBy('version_number', 'desc')
            ->first();
    }
}