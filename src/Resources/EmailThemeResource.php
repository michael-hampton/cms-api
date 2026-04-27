<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;
use App\Models\NewsletterBrandingConfiguration;

/**
 * Transforms a NewsletterBrandingConfiguration (email_template type) into the
 * response shape the frontend expects from email-theme endpoints.
 *
 * The shape is intentionally identical to the previous EmailTheme resource so
 * no frontend changes are required.
 */
class EmailThemeResource extends JsonResource
{
    /**
     * @param NewsletterBrandingConfiguration $model
     */
    public function toArray(): array
    {
        /** @var NewsletterBrandingConfiguration $this */

        return [
            'id' => $this->getAttribute('id'),
            'name' => $this->getAttribute('name'),
            'slug' => $this->getAttribute('slug'),
            'description' => $this->getAttribute('description'),
            'is_active' => (bool)$this->getAttribute('is_active'),
            'is_default' => (bool)$this->getAttribute('is_default'),
            'site_id' => $this->getAttribute('site_id'),
            'type' => $this->getAttribute('type'),

            // Structured theme data from theme_json
            'colors' => $this->getAttribute('theme_json.colors') ?? [],
            'fonts' => $this->getAttribute('theme_json.fonts') ?? [],
            'assets' => $this->getAttribute('theme_json.assets') ?? [],
            'settings' => $this->getAttribute('theme_json.settings') ?? [],

            // Convenience columns (also present inside assets.logo.url)
            'logo_url' => $this->getAttribute('logo_url'),

            // Branding fields (may be null for pure email-template themes)
            'header_text' => $this->getAttribute('header_text'),
            'footer_text' => $this->getAttribute('footer_text'),
            'custom_css' => $this->getAttribute('custom_css'),

            'clone_history' => $this->getAttribute('clone_history') ?? [],

            'created_at' => $this->getAttribute('created_at'),
            'updated_at' => $this->getAttribute('updated_at'),
        ];
    }
}