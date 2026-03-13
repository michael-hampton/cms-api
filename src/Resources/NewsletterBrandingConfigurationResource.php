<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class NewsletterBrandingConfigurationResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'newsletter_id' => $this->getAttribute('newsletter_id'),
            'logo_url' => $this->getAttribute('logo_url'),
            'header_text' => $this->getAttribute('header_text'),
            'footer_text' => $this->getAttribute('footer_text'),
            'theme_json' => $this->getAttribute('theme_json'),
            'custom_css' => $this->getAttribute('custom_css'),
            'created_at' => $this->getAttribute('created_at'),
            'updated_at' => $this->getAttribute('updated_at'),
        ];
    }
}