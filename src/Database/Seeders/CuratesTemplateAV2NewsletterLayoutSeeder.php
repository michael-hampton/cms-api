<?php

namespace App\Database\Seeders;

use App\Enums\Newsletters\LayoutVersionState;
use App\Models\NewsletterLayout;
use App\Models\NewsletterLayoutVersion;

class CuratesTemplateAV2NewsletterLayoutSeeder
{
    public function run(): void
    {
        $existing = NewsletterLayout::where('slug', 'curates-template-a-v2')
            ->whereNull('site_id')
            ->first();

        if ($existing) {
            $this->addNewVersion($existing);
            return;
        }

        $layout = NewsletterLayout::create([
            'name' => 'Curates Template A v2',
            'slug' => 'curates-template-a-v2',
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

    public function definition(): array
    {
        return [
            'schema_version' => 2,
            'template' => 'curates',
            'meta' => [
                'name' => 'Curates Template A v2',
                'description' => 'Fashion-editorial curated digest. '
                    . 'Approved design: masthead → editorial intro → sponsor → '
                    . '3-col shop grid → 2-col feature → 4-col picks → sign-off → legal footer.',
                'preview_url' => 'https://view.ceros.com/dennis/3-curates-template-a-approved/p/1',
            ],
            'regions' => [

                // ── Top — brand masthead ──────────────────────────────────────
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
                                        'bannerType' => 'masthead',
                                        'title' => '{{newsletter.title}}',
                                        'subtitle' => 'View in browser',
                                        'ctaText' => 'Shop Celebrity Street Style Fashion Trends',
                                        'ctaUrl' => null,
                                        'backgroundColor' => '{{newsletter.brand_color|default:#000000}}',
                                        'textColor' => '{{newsletter.brand_secondary_color|default:#ffffff}}',
                                        'image' => null,
                                        'providers' => [],
                                        'rating' => 0,
                                        'reviewCount' => 0,
                                        'showDismiss' => false,
                                        'dismissible' => false,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],

                // ── Center — all editorial content ────────────────────────────
                [
                    'id' => 'center',
                    'type' => 'center',
                    'order' => 2,
                    'label' => 'Editorial Content',
                    'slots' => [

                        [
                            'name' => 'editorial_intro',
                            'label' => 'Editorial Intro',
                            'required' => false,
                            'allowed_block_types' => ['quote', 'image', 'text', 'divider'],
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
                            'label' => 'Shop the Collection (3-col grid)',
                            'required' => false,
                            'allowed_block_types' => ['heading', 'card-group', 'card', 'cta'],
                            'blocks' => [],
                        ],

                        [
                            'name' => 'feature_editorial',
                            'label' => 'Feature Editorial (2-col)',
                            'required' => false,
                            'allowed_block_types' => ['card-group', 'image', 'text', 'quote', 'divider'],
                            'blocks' => [],
                        ],

                        [
                            'name' => 'more_picks',
                            'label' => 'More Picks (4-col grid)',
                            'required' => false,
                            'allowed_block_types' => ['heading', 'card-group', 'card', 'cta'],
                            'blocks' => [],
                        ],

                        [
                            'name' => 'sign_off',
                            'label' => 'Editor Sign-Off',
                            'required' => false,
                            'allowed_block_types' => ['person', 'note', 'text', 'image', 'divider'],
                            'blocks' => [],
                        ],
                    ],
                ],

                // ── Bottom — legal footer ─────────────────────────────────────
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
                                        'bannerType' => 'footer-legal',
                                        'title' => '{{site.name}}',
                                        'subtitle' => null,
                                        'ctaText' => '750 N. San Vicente Blvd, 8th Floor East, West Hollywood, CA 90069',
                                        'ctaUrl' => null,
                                        'backgroundColor' => '#f5f5f5',
                                        'textColor' => '#666666',
                                        'image' => null,
                                        'providers' => [
                                            ['platform' => 'instagram', 'url' => 'https://instagram.com/'],
                                            ['platform' => 'pinterest', 'url' => 'https://pinterest.com/'],
                                            ['platform' => 'facebook', 'url' => 'https://facebook.com/'],
                                        ],
                                        'rating' => 0,
                                        'reviewCount' => 0,
                                        'showDismiss' => false,
                                        'dismissible' => false,
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