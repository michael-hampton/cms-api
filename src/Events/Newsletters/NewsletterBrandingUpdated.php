<?php

namespace App\Events\Newsletters;

use App\Models\Newsletter;
use App\Models\NewsletterBrandingConfiguration;

/**
 * Fired when branding configuration is saved/updated for a newsletter.
 * Listeners: invalidate preview caches, notify editors, etc.
 */
class NewsletterBrandingUpdated
{
    public function __construct(
        public readonly Newsletter                      $newsletter,
        public readonly NewsletterBrandingConfiguration $branding,
    )
    {
    }
}