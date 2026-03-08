<?php

namespace App\Database\Seeders;

use App\Models\Newsletter;
use App\Models\NewsletterLayout;

class CuratesTemplateAV2DemoSeeder
{
    public function run(int $siteId = 1): void
    {
        $layout = NewsletterLayout::where('slug', 'curates-template-a-v2')
            ->whereNull('site_id')
            ->first();

        Newsletter::updateOrCreate(
            [
                'slug' => 'curates-template-a-v2-demo-' . $siteId,
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
                'brand_color' => '#000000',
                'brand_secondary_color' => '#ffffff',
                'content_blocks' => $this->contentBlocks(),
            ]
        );
    }

    // -------------------------------------------------------------------------
    // Block definitions — one method per layout slot for clarity
    // -------------------------------------------------------------------------

    private function contentBlocks(): array
    {
        return array_merge(
            $this->editorialIntroBlocks(),
            $this->sponsorBannerBlocks(),
            $this->productCollectionBlocks(),
            $this->featureEditorialBlocks(),
            $this->morePicksBlocks(),
            $this->signOffBlocks(),
        );
    }

    /**
     * Slot: editorial_intro
     * Approved design: quote → full-width hero image → text paragraphs → second quote
     */
    private function editorialIntroBlocks(): array
    {
        return [
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
                        "The case for investment dressing has never been stronger. This season's edit "
                        . "focuses on pieces with longevity — fabrics that improve with age, cuts that "
                        . "transcend trend cycles, and colours that sit effortlessly in an "
                        . "already-considered wardrobe.",
                        "We've pulled the best of what's arrived this week, from the shirt dress that "
                        . "works three ways to the earrings every fashion editor has already ordered.",
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
        ];
    }

    /**
     * Slot: sponsor_banner
     * Approved design: dark full-width promo banner with background image and CTA
     */
    private function sponsorBannerBlocks(): array
    {
        return [
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
                    'image' => [
                        'src' => 'https://images.unsplash.com/photo-1558769132-cb1aea458c5e?w=600&h=180&fit=crop',
                    ],
                    'providers' => [],
                    'rating' => 0,
                    'reviewCount' => 0,
                    'showDismiss' => false,
                    'dismissible' => false,
                ],
            ],
        ];
    }

    /**
     * Slot: product_collection
     * Approved design: "SHOP THE COLLECTION" heading + 3-column product card grid
     */
    private function productCollectionBlocks(): array
    {
        return [
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'SHOP THE COLLECTION',
                    'level' => 2,
                ],
            ],

            [
                'type' => 'card-group',
                'data' => [
                    'itemsPerRow' => 3,
                    'gap' => 'medium',
                    'cards' => [
                        [
                            'title' => 'Product title goes here',
                            'description' => '£00.00',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1469334031218-e382a71b716b?w=180&h=220&fit=crop',
                            ],
                            'linkUrl' => 'https://example.com/shop/product-1',
                            'buttonText' => 'SHOP NOW',
                            'buttonType' => 'primary',
                            'layout' => 'full',
                            'alignment' => 'center',
                            'itemsPerRow' => 1,
                        ],
                        [
                            'title' => 'Product title goes here',
                            'description' => '£00.00',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?w=180&h=220&fit=crop',
                            ],
                            'linkUrl' => 'https://example.com/shop/product-2',
                            'buttonText' => 'SHOP NOW',
                            'buttonType' => 'primary',
                            'layout' => 'full',
                            'alignment' => 'center',
                            'itemsPerRow' => 1,
                        ],
                        [
                            'title' => 'Product title goes here',
                            'description' => '£00.00',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1591369822096-ffd140ec948f?w=180&h=220&fit=crop',
                            ],
                            'linkUrl' => 'https://example.com/shop/product-3',
                            'buttonText' => 'SHOP NOW',
                            'buttonType' => 'primary',
                            'layout' => 'full',
                            'alignment' => 'center',
                            'itemsPerRow' => 1,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Slot: feature_editorial
     * Approved design: 2-column card group (image + description per card)
     * Caption text in the approved design reads "Left image: ... Right image: ..."
     */
    private function featureEditorialBlocks(): array
    {
        return [
            [
                'type' => 'card-group',
                'data' => [
                    'itemsPerRow' => 2,
                    'gap' => 'medium',
                    'cards' => [
                        [
                            'title' => 'The Shirt Dress',
                            'description' => 'Wear it belted for the office, open over trousers at the '
                                . 'weekend, or cinched as an evening cover-up. Linen-cotton blend. £195.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?w=280&h=340&fit=crop',
                            ],
                            'linkUrl' => 'https://example.com/shop/shirt-dress',
                            'buttonText' => 'SHOP NOW',
                            'buttonType' => 'primary',
                            'layout' => 'full',
                            'alignment' => 'left',
                            'itemsPerRow' => 1,
                        ],
                        [
                            'title' => 'The Statement Earring',
                            'description' => 'Gold vermeil hoops with a twist — every fashion editor '
                                . 'has already ordered a pair. £85.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?w=280&h=340&fit=crop',
                            ],
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
        ];
    }

    /**
     * Slot: more_picks
     * Approved design: "MORE PICKS" heading (implied) + 4-column product grid
     * The approved design shows 4 portrait-format product cards in a tight row.
     */
    private function morePicksBlocks(): array
    {
        return [
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'MORE PICKS',
                    'level' => 2,
                ],
            ],

            [
                'type' => 'card-group',
                'data' => [
                    'itemsPerRow' => 4,
                    'gap' => 'small',
                    'cards' => [
                        [
                            'title' => 'Product title goes here',
                            'description' => '£00.00',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=135&h=165&fit=crop',
                            ],
                            'linkUrl' => 'https://example.com/shop/pick-1',
                            'buttonText' => 'SHOP NOW',
                            'buttonType' => 'primary',
                            'layout' => 'full',
                            'alignment' => 'center',
                            'itemsPerRow' => 1,
                        ],
                        [
                            'title' => 'Product title goes here',
                            'description' => '£00.00',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1434389677669-e08b4cac3105?w=135&h=165&fit=crop',
                            ],
                            'linkUrl' => 'https://example.com/shop/pick-2',
                            'buttonText' => 'SHOP NOW',
                            'buttonType' => 'primary',
                            'layout' => 'full',
                            'alignment' => 'center',
                            'itemsPerRow' => 1,
                        ],
                        [
                            'title' => 'Product title goes here',
                            'description' => '£00.00',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=135&h=165&fit=crop',
                            ],
                            'linkUrl' => 'https://example.com/shop/pick-3',
                            'buttonText' => 'SHOP NOW',
                            'buttonType' => 'primary',
                            'layout' => 'full',
                            'alignment' => 'center',
                            'itemsPerRow' => 1,
                        ],
                        [
                            'title' => 'Product title goes here',
                            'description' => '£00.00',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1601924994987-69e26d50dc26?w=135&h=165&fit=crop',
                            ],
                            'linkUrl' => 'https://example.com/shop/pick-4',
                            'buttonText' => 'SHOP NOW',
                            'buttonType' => 'primary',
                            'layout' => 'full',
                            'alignment' => 'center',
                            'itemsPerRow' => 1,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Slot: sign_off
     * Approved design: editor sign-off paragraph → WWW Curates note block with CTA
     */
    private function signOffBlocks(): array
    {
        return [
            [
                'type' => 'person',
                'data' => [
                    'name' => 'Sophie Ashworth',
                    'role' => 'Fashion & Style Director',
                    'bio' => "Sign off goes here Lorem ipsum dolor sit amet, consectetur adipiscing "
                        . "elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.",
                    'image' => [
                        'src' => 'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=80&h=80&fit=crop&crop=face',
                    ],
                ],
            ],

            [
                'type' => 'note',
                'data' => [
                    'title' => 'WWW Curates',
                    'paragraphs' => [
                        'A title goes here, lorem ipsum, ipsum dolor sit amet, consectetur adipiscing elit.',
                    ],
                    'linkUrl' => 'https://example.com/curates',
                    'linkText' => 'VIEW ALL',
                    'sponsored' => false,
                ],
            ],
        ];
    }
}