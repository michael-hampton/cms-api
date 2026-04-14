<?php

namespace App\Database\Seeders;

use App\Models\Newsletter;
use App\Models\NewsletterLayout;

/**
 * Seeds a demo newsletter using the curates-masthead layout.
 *
 * Run after CuratesMastheadNewsletterLayoutSeeder.
 */
class CuratesMastheadDemoSeeder
{
    public function run(int $siteId = 7): void
    {
        $layout = NewsletterLayout::where('slug', 'curates-masthead')
            ->whereNull('site_id')
            ->first();

        Newsletter::updateOrCreate(
            [
                'slug' => 'curates-masthead-demo-' . $siteId,
                'site_id' => $siteId,
            ],
            [
                'title' => 'WWW Curates — Weekly Edit',
                'template' => 'curates',
                'content_type' => Newsletter::CONTENT_TYPE_CUSTOM_BLOCKS,
                'interval' => Newsletter::INTERVAL_WEEKLY,
                'active' => true,
                'is_default' => false,
                'layout_id' => $layout->id,
                'content_blocks' => $this->contentBlocks(),
            ]
        );
    }

    private function contentBlocks(): array
    {
        return [

            // ── Editorial intro ───────────────────────────────────────────────

            [
                'type' => 'quote',
                'data' => [
                    'text' => 'Style is a way to say who you are without having to speak.',
                    'attribution' => 'Rachel Zoe',
                ],
            ],

            [
                'type' => 'image',
                'data' => [
                    'src' => 'https://images.unsplash.com/photo-1483985988355-763728e1935b?w=600&h=380&fit=crop',
                    'alt' => "This season's key looks",
                    'caption' => 'The edit: pieces chosen for longevity, not trend cycles.',
                    'layout' => 'full',
                    'alignment' => 'fullscreen',
                ],
            ],

            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        "The case for investment dressing has never been stronger. This season's edit focuses on pieces with longevity — fabrics that improve with age, cuts that transcend trend cycles, and colours that sit effortlessly in an already-considered wardrobe.",
                        "We've pulled the best of what's arrived this week, from the shirt dress that works three ways to the earrings every fashion editor has already ordered.",
                    ],
                ],
            ],

            [
                'type' => 'quote',
                'data' => [
                    'text' => 'Fashion is the armour to survive the reality of everyday life.',
                    'attribution' => 'Bill Cunningham',
                ],
            ],

            // ── Sponsor banner ────────────────────────────────────────────────

            [
                'type' => 'banner',
                'data' => [
                    'bannerType' => 'promo-header',
                    'title' => 'SPONSOR CONTENT',
                    'subtitle' => 'Shop Celebrity Street Style Fashion Trends',
                    'ctaText' => 'SHOP NOW',
                    'ctaUrl' => 'https://example.com/sponsor',
                    'backgroundColor' => '#1a1a1a',
                    'textColor' => '#ffffff',
                    'image' => ['src' => 'https://images.unsplash.com/photo-1558769132-cb1aea458c5e?w=600&h=180&fit=crop'],
                    'providers' => [],
                    'rating' => 0,
                    'reviewCount' => 0,
                    'showDismiss' => false,
                    'dismissible' => false,
                ],
            ],

            // ── Shop the collection ───────────────────────────────────────────

            [
                'type' => 'heading',
                'data' => ['text' => 'SHOP THE COLLECTION', 'level' => 2],
            ],

            [
                'type' => 'card-group',
                'data' => [
                    'itemsPerRow' => 3,
                    'gap' => 'medium',
                    'cards' => [
                        [
                            'title' => 'Silk Slip Dress',
                            'description' => '£285.00',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1469334031218-e382a71b716b?w=180&h=220&fit=crop'],
                            'linkUrl' => 'https://example.com/shop/silk-slip',
                            'buttonText' => 'SHOP NOW',
                            'buttonType' => 'primary',
                            'layout' => 'full',
                            'alignment' => 'center',
                            'itemsPerRow' => 1,
                        ],
                        [
                            'title' => 'Gold Hoop Earrings',
                            'description' => '£85.00',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?w=180&h=220&fit=crop'],
                            'linkUrl' => 'https://example.com/shop/hoops',
                            'buttonText' => 'SHOP NOW',
                            'buttonType' => 'primary',
                            'layout' => 'full',
                            'alignment' => 'center',
                            'itemsPerRow' => 1,
                        ],
                        [
                            'title' => 'Linen Blazer',
                            'description' => '£320.00',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1591369822096-ffd140ec948f?w=180&h=220&fit=crop'],
                            'linkUrl' => 'https://example.com/shop/blazer',
                            'buttonText' => 'SHOP NOW',
                            'buttonType' => 'primary',
                            'layout' => 'full',
                            'alignment' => 'center',
                            'itemsPerRow' => 1,
                        ],
                    ],
                ],
            ],

            // ── Feature editorial (two-column) ────────────────────────────────

            [
                'type' => 'card-group',
                'data' => [
                    'itemsPerRow' => 2,
                    'gap' => 'medium',
                    'cards' => [
                        [
                            'title' => 'The Shirt Dress',
                            'description' => 'Wear it belted for the office, open over trousers at the weekend, or cinched as an evening cover-up. Linen-cotton blend. £195.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?w=280&h=340&fit=crop'],
                            'linkUrl' => 'https://example.com/shop/shirt-dress',
                            'buttonText' => 'SHOP NOW',
                            'buttonType' => 'primary',
                            'layout' => 'full',
                            'alignment' => 'left',
                            'itemsPerRow' => 1,
                        ],
                        [
                            'title' => 'The Statement Earring',
                            'description' => 'Gold vermeil hoops with a twist — every fashion editor has already ordered a pair. £85.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?w=280&h=340&fit=crop'],
                            'linkUrl' => 'https://example.com/shop/earrings',
                            'buttonText' => 'SHOP NOW',
                            'buttonType' => 'primary',
                            'layout' => 'full',
                            'alignment' => 'left',
                            'itemsPerRow' => 1,
                        ],
                    ],
                ],
            ],

            // ── More picks (four-column) ──────────────────────────────────────

            [
                'type' => 'heading',
                'data' => ['text' => 'MORE PICKS', 'level' => 2],
            ],

            [
                'type' => 'card-group',
                'data' => [
                    'itemsPerRow' => 4,
                    'gap' => 'small',
                    'cards' => [
                        [
                            'title' => 'Leather Tote',
                            'description' => '£450.00',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=135&h=165&fit=crop'],
                            'linkUrl' => 'https://example.com/shop/tote',
                            'buttonText' => 'SHOP NOW',
                            'buttonType' => 'primary',
                            'layout' => 'full',
                            'alignment' => 'center',
                            'itemsPerRow' => 1,
                        ],
                        [
                            'title' => 'Cashmere Knit',
                            'description' => '£195.00',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1434389677669-e08b4cac3105?w=135&h=165&fit=crop'],
                            'linkUrl' => 'https://example.com/shop/cashmere',
                            'buttonText' => 'SHOP NOW',
                            'buttonType' => 'primary',
                            'layout' => 'full',
                            'alignment' => 'center',
                            'itemsPerRow' => 1,
                        ],
                        [
                            'title' => 'Strappy Sandals',
                            'description' => '£165.00',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=135&h=165&fit=crop'],
                            'linkUrl' => 'https://example.com/shop/sandals',
                            'buttonText' => 'SHOP NOW',
                            'buttonType' => 'primary',
                            'layout' => 'full',
                            'alignment' => 'center',
                            'itemsPerRow' => 1,
                        ],
                        [
                            'title' => 'Silk Scarf',
                            'description' => '£95.00',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1601924994987-69e26d50dc26?w=135&h=165&fit=crop'],
                            'linkUrl' => 'https://example.com/shop/scarf',
                            'buttonText' => 'SHOP NOW',
                            'buttonType' => 'primary',
                            'layout' => 'full',
                            'alignment' => 'center',
                            'itemsPerRow' => 1,
                        ],
                    ],
                ],
            ],

            // ── Sign off ──────────────────────────────────────────────────────

            [
                'type' => 'person',
                'data' => [
                    'name' => 'Sophie Ashworth',
                    'role' => 'Fashion & Style Director',
                    'bio' => "This week's edit has been a joy to put together. The quiet luxury conversation isn't going away, and honestly, I'm here for it.",
                    'image' => ['src' => 'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=80&h=80&fit=crop&crop=face'],
                ],
            ],

            [
                'type' => 'note',
                'data' => [
                    'title' => 'WWW Curates',
                    'paragraphs' => [
                        'The full edit lives online — more picks, more looks, updated daily.',
                    ],
                    'linkUrl' => 'https://example.com/curates',
                    'linkText' => 'VIEW ALL',
                    'sponsored' => false,
                ],
            ],

        ];
    }
}