<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\EmailTemplate;

/**
 * Seeds the canonical set of email templates that were previously hardcoded
 * in the Angular EmailThemesComponent::templateLibrary array.
 *
 * Run once per site, or idempotently (existing slugs are skipped).
 *
 * Usage:
 *   php artisan db:seed --class=EmailTemplateSeeder --site=1
 *   php artisan db:seed --class=EmailTemplateSeeder  # seeds for all sites
 */
class EmailTemplateSeeder extends Seeder
{
    /**
     * Canonical template definitions — mirrors the hardcoded frontend library.
     * These are site-agnostic blueprints; site_id is injected at seed time.
     */
    private const TEMPLATES = [
        [
            'name' => 'Order Confirmation',
            'slug' => 'order-confirmation',
            'category' => 'transactional',
            'description' => 'Sent when a customer places an order. Includes order summary and CTA.',
            'is_active' => true,
            'blocks' => [
                [
                    'type' => 'text',
                    'visible' => true,
                    'data' => [
                        'content' => "Hi {{ user.first_name }},\n\nThank you for your order!",
                        'align' => 'left',
                        'size' => 'md',
                    ],
                ],
                [
                    'type' => 'order_summary',
                    'visible' => true,
                    'data' => [
                        'order_id' => '{{ order.id }}',
                        'show_line_items' => true,
                        'show_totals' => true,
                        'show_shipping' => true,
                        'title' => 'Order Summary',
                    ],
                ],
                [
                    'type' => 'button',
                    'visible' => true,
                    'data' => [
                        'label' => 'View Order',
                        'url' => '{{ order.url }}',
                        'style' => 'primary',
                        'align' => 'center',
                    ],
                ],
                [
                    'type' => 'divider',
                    'visible' => true,
                    'data' => [
                        'style' => 'solid',
                        'color' => '#e9ecef',
                        'thickness' => 1,
                    ],
                ],
                [
                    'type' => 'text',
                    'visible' => true,
                    'data' => [
                        'content' => 'If you have any questions, reply to this email.',
                        'align' => 'left',
                        'size' => 'md',
                    ],
                ],
            ],
        ],

        [
            'name' => 'Password Reset',
            'slug' => 'password-reset',
            'category' => 'transactional',
            'description' => 'Single-action reset email with expiry notice.',
            'is_active' => true,
            'blocks' => [
                [
                    'type' => 'text',
                    'visible' => true,
                    'data' => [
                        'content' => "Hi {{ user.first_name }},\n\nWe received a request to reset your password.",
                        'align' => 'left',
                        'size' => 'md',
                    ],
                ],
                [
                    'type' => 'button',
                    'visible' => true,
                    'data' => [
                        'label' => 'Reset Password',
                        'url' => '{{ reset_url }}',
                        'style' => 'primary',
                        'align' => 'center',
                    ],
                ],
                [
                    'type' => 'text',
                    'visible' => true,
                    'data' => [
                        'content' => 'This link expires in 24 hours. If you did not request this, ignore this email.',
                        'align' => 'left',
                        'size' => 'md',
                    ],
                ],
            ],
        ],

        [
            'name' => 'Welcome Email',
            'slug' => 'welcome-email',
            'category' => 'transactional',
            'description' => 'Onboarding email for new users with next steps.',
            'is_active' => true,
            'blocks' => [
                [
                    'type' => 'text',
                    'visible' => true,
                    'data' => [
                        'content' => "Welcome, {{ user.first_name }}! 🎉\n\nWe're glad you're here.",
                        'align' => 'left',
                        'size' => 'md',
                    ],
                ],
                [
                    'type' => 'two_column',
                    'visible' => true,
                    'data' => [
                        'left' => '**Step 1** — Complete your profile',
                        'right' => '**Step 2** — Explore the platform',
                    ],
                ],
                [
                    'type' => 'button',
                    'visible' => true,
                    'data' => [
                        'label' => 'Get Started',
                        'url' => '{{ app_url }}',
                        'style' => 'primary',
                        'align' => 'center',
                    ],
                ],
            ],
        ],

        [
            'name' => 'Promotional Sale',
            'slug' => 'promotional-sale',
            'category' => 'marketing',
            'description' => 'Marketing email with hero image, offer, and product cards.',
            'is_active' => true,
            'blocks' => [
                [
                    'type' => 'image',
                    'visible' => true,
                    'data' => [
                        'url' => 'https://placehold.co/600x200/667eea/ffffff?text=Sale',
                        'alt' => 'Sale banner',
                        'width' => '100%',
                        'link' => '',
                        'align' => 'center',
                        'layout' => 'full',
                    ],
                ],
                [
                    'type' => 'text',
                    'visible' => true,
                    'data' => [
                        'content' => "## 30% off everything\n\nUse code **SAVE30** at checkout. Offer ends Sunday.",
                        'align' => 'left',
                        'size' => 'md',
                    ],
                ],
                [
                    'type' => 'ad_slot',
                    'visible' => true,
                    'data' => [
                        'placement' => 'top',
                        'fallback' => 'hide',
                    ],
                ],
                [
                    'type' => 'product_card',
                    'visible' => true,
                    'data' => [
                        'product_id' => '{{ product.id }}',
                        'name' => '{{ product.name }}',
                        'price' => '{{ product.price }}',
                        'image_url' => '{{ product.image_url }}',
                        'url' => '{{ product.url }}',
                    ],
                ],
                [
                    'type' => 'button',
                    'visible' => true,
                    'data' => [
                        'label' => 'Shop Now',
                        'url' => '{{ shop_url }}',
                        'style' => 'primary',
                        'align' => 'center',
                    ],
                ],
            ],
        ],

        [
            'name' => 'Newsletter',
            'slug' => 'newsletter-template',
            'category' => 'marketing',
            'description' => 'Regular newsletter with ad slots and rich content.',
            'is_active' => true,
            'blocks' => [
                [
                    'type' => 'text',
                    'visible' => true,
                    'data' => [
                        'content' => '## This week in {{ site.name }}',
                        'align' => 'left',
                        'size' => 'md',
                    ],
                ],
                [
                    'type' => 'ad_slot',
                    'visible' => true,
                    'data' => [
                        'placement' => 'top',
                        'fallback' => 'hide',
                    ],
                ],
                [
                    'type' => 'text',
                    'visible' => true,
                    'data' => [
                        'content' => 'Your weekly roundup goes here.',
                        'align' => 'left',
                        'size' => 'md',
                    ],
                ],
                [
                    'type' => 'divider',
                    'visible' => true,
                    'data' => [
                        'style' => 'solid',
                        'color' => '#e9ecef',
                        'thickness' => 1,
                    ],
                ],
                [
                    'type' => 'ad_slot',
                    'visible' => true,
                    'data' => [
                        'placement' => 'bottom',
                        'fallback' => 'hide',
                    ],
                ],
            ],
        ],

        [
            'name' => 'System Alert',
            'slug' => 'system-alert',
            'category' => 'system',
            'description' => 'Minimal alert email for system notifications.',
            'is_active' => true,
            'blocks' => [
                [
                    'type' => 'text',
                    'visible' => true,
                    'data' => [
                        'content' => "⚠️ **System Alert**\n\n{{ alert.message }}",
                        'align' => 'left',
                        'size' => 'md',
                    ],
                ],
                [
                    'type' => 'button',
                    'visible' => true,
                    'data' => [
                        'label' => 'View Details',
                        'url' => '{{ alert.url }}',
                        'style' => 'secondary',
                        'align' => 'center',
                    ],
                ],
            ],
        ],
    ];

    // ── Seeder entry point ────────────────────────────────────────────────────

    public function run(): void
    {
        $siteIds = $this->resolveSiteIds();

        foreach ($siteIds as $siteId) {
            $this->seedForSite($siteId);
        }
    }

    /**
     * Resolve the list of site IDs to seed.
     * Accepts an optional --site=N argument; defaults to all sites.
     */
    private function resolveSiteIds(): array
    {
        //$siteArg = $this->command?->option('site');

        //if ($siteArg !== null) {
        //return [(int) $siteArg];
        // }

        // Fall back to querying all sites when no argument given
        try {
            return \App\Models\Site::all()->pluck('id')->toArray();
        } catch (\Throwable $e) {
            echo $e->getMessage();
            die;
            // In environments where Site model may not be available
            return [];
        }
    }

    /**
     * Seed all canonical templates for a single site.
     * Existing slugs are skipped (idempotent).
     */
    private function seedForSite(int $siteId): void
    {
        foreach (self::TEMPLATES as $definition) {
            $existing = EmailTemplate::where('site_id', $siteId)
                ->where('slug', $definition['slug'])
                ->first();

            if ($existing !== null) {
                echo "  Skipping [{$definition['slug']}] — already exists for site {$siteId}.";
                continue;
            }

            EmailTemplate::create(array_merge($definition, ['site_id' => $siteId]));

            echo "  Created [{$definition['slug']}] for site {$siteId}.";
        }
    }
}