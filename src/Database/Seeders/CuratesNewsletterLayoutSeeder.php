<?php

namespace App\Database\Seeders;

use App\Enums\Newsletters\LayoutVersionState;
use App\Models\NewsletterLayout;
use App\Models\NewsletterLayoutVersion;

/**
 * Seeds the "Curates" newsletter layout — a fashion-editorial template
 * modelled on the WhoWhatWear curated digest format.
 *
 * Layout schema v2 — uses regions (top / center / bottom).
 *
 * Region map:
 *   top    → brand header bar (logo, view-in-browser, nav label)
 *   center → editorial content (driven by newsletter content_blocks)
 *   bottom → footer (social links, legal, unsubscribe)
 *
 * The center region's slots accept any block type.  The top and bottom
 * regions carry fixed structural blocks that do not change per issue.
 */
class CuratesNewsletterLayoutSeeder
{
    public function run(): void
    {
        $existing = NewsletterLayout::where('slug', 'curates-template-a')
            ->whereNull('site_id') // system layout
            ->first();

        if ($existing) {
            // Already seeded — update to latest definition
            $this->addNewVersion($existing);
            return;
        }

        $layout = NewsletterLayout::create([
            'name' => 'Curates Template A',
            'slug' => 'curates-template-a',
            'is_system_layout' => true,
            'site_id' => null,
            'created_by' => null,
            'layout_definition_json' => $this->definition(),
        ]);

        NewsletterLayoutVersion::create([
            'layout_id' => $layout->id,
            'version_number' => 1,
            'layout_definition_json' => $this->definition(),
            'state' => LayoutVersionState::Published->value,
            'created_at' => now(),
        ]);
    }

    private function addNewVersion(NewsletterLayout $layout): void
    {
        $latest = NewsletterLayoutVersion::where('layout_id', $layout->id)
            ->orderBy('version_number', 'desc')
            ->first();

        $nextVersion = $latest ? $latest->version_number + 1 : 1;

        NewsletterLayoutVersion::create([
            'layout_id' => $layout->id,
            'version_number' => $nextVersion,
            'layout_definition_json' => $this->definition(),
            'state' => LayoutVersionState::Draft->value,
            'created_at' => now(),
        ]);
    }

    private function definition(): array
    {
        return [
            'schema_version' => 2,
            'template' => 'curates',
            'meta' => [
                'name' => 'Curates Template A',
                'description' => 'Fashion-editorial curated digest newsletter. '
                    . 'Supports editorial intro, product grids, sponsor banners, quotes and sign-off.',
                'preview_url' => 'https://view.ceros.com/dennis/3-curates-template-a-approved/p/1',
            ],
            'regions' => [
                [
                    'id' => 'top',
                    'type' => 'top',
                    'order' => 1,
                    'label' => 'Brand Header',
                    'slots' => [
                        [
                            'name' => 'brand_header',
                            'label' => 'Brand Header (auto-rendered)',
                            'required' => true,
                            'allowed_block_types' => ['banner'],
                            'blocks' => [
                                [
                                    'type' => 'banner',
                                    'data' => [
                                        'bannerType' => 'curates-header',
                                        'title' => '{{newsletter.title}}',
                                        'subtitle' => 'VIEW IN BROWSER',
                                        'backgroundColor' => '{{newsletter.brand_color|default:#000000}}',
                                        'textColor' => '{{newsletter.brand_secondary_color|default:#ffffff}}',
                                        'image' => null,
                                        'providers' => [],
                                        'rating' => 0,
                                        'reviewCount' => 0,
                                        'showDismiss' => false,
                                        'dismissible' => false,
                                        'ctaText' => null,
                                        'ctaUrl' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'center',
                    'type' => 'center',
                    'order' => 2,
                    'label' => 'Editorial Content',
                    'slots' => [
                        [
                            'name' => 'editorial_intro',
                            'label' => 'Editorial Intro (editor note + quotes)',
                            'required' => false,
                            'allowed_block_types' => ['text', 'quote', 'image', 'divider'],
                            'blocks' => [],
                        ],
                        [
                            'name' => 'sponsor_banner',
                            'label' => 'Sponsor / Ad Banner',
                            'required' => false,
                            'allowed_block_types' => ['banner', 'image'],
                            'blocks' => [],
                        ],
                        [
                            'name' => 'product_collection',
                            'label' => 'Shop the Collection',
                            'required' => false,
                            'allowed_block_types' => ['card-group', 'card', 'heading', 'cta'],
                            'blocks' => [],
                        ],
                        [
                            'name' => 'feature_editorial',
                            'label' => 'Feature / Two-Column Editorial',
                            'required' => false,
                            'allowed_block_types' => ['image', 'text', 'quote', 'divider', 'card-group'],
                            'blocks' => [],
                        ],
                        [
                            'name' => 'sign_off',
                            'label' => 'Editor Sign-Off',
                            'required' => false,
                            'allowed_block_types' => ['text', 'image', 'divider'],
                            'blocks' => [],
                        ],
                    ],
                ],
                [
                    'id' => 'bottom',
                    'type' => 'bottom',
                    'order' => 3,
                    'label' => 'Footer',
                    'slots' => [
                        [
                            'name' => 'footer',
                            'label' => 'Footer (auto-rendered)',
                            'required' => true,
                            'allowed_block_types' => ['banner'],
                            'blocks' => [
                                [
                                    'type' => 'banner',
                                    'data' => [
                                        'bannerType' => 'curates-footer',
                                        'title' => '{{site.name}}',
                                        'subtitle' => null,
                                        'backgroundColor' => '#f5f5f5',
                                        'textColor' => '#666666',
                                        'image' => null,
                                        'providers' => [],
                                        'rating' => 0,
                                        'reviewCount' => 0,
                                        'showDismiss' => false,
                                        'dismissible' => false,
                                        'ctaText' => null,
                                        'ctaUrl' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}