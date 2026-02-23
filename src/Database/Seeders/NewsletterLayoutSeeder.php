<?php

namespace App\Database\Seeders;

use App\Enums\Newsletters\LayoutVersionState;
use App\Models\Newsletter;
use App\Models\NewsletterBrandingConfiguration;
use App\Models\NewsletterBrandingVersion;
use App\Models\NewsletterLayout;
use App\Models\NewsletterLayoutVersion;

/**
 * Seeds:
 *  - 4 system layouts (matching existing templates: default, digest, featured, simple)
 *  - 1 user-created layout (cloned from digest, customised)
 *  - Branding configurations for each newsletter in each site
 *  - All system layouts are seeded as Published so they are immediately usable
 */
class NewsletterLayoutSeeder
{
    public function run(): void
    {
        $this->seedSystemLayouts();
        $this->seedUserLayout();
        $this->seedBrandingForExistingNewsletters();
    }

    // ─── System Layouts ──────────────────────────────────────────────────────

    private function seedSystemLayouts(): void
    {
        $layouts = [
            [
                'name' => 'Default',
                'slug' => 'system-default',
                'def' => $this->defaultLayoutDefinition(),
            ],
            [
                'name' => 'Digest',
                'slug' => 'system-digest',
                'def' => $this->digestLayoutDefinition(),
            ],
            [
                'name' => 'Featured',
                'slug' => 'system-featured',
                'def' => $this->featuredLayoutDefinition(),
            ],
            [
                'name' => 'Simple',
                'slug' => 'system-simple',
                'def' => $this->simpleLayoutDefinition(),
            ],
        ];

        foreach ($layouts as $layoutData) {
            if (NewsletterLayout::where('slug', $layoutData['slug'])->exists()) {
                continue;
            }

            $layout = NewsletterLayout::create([
                'name' => $layoutData['name'],
                'slug' => $layoutData['slug'],
                'layout_definition_json' => $layoutData['def'],
                'is_system_layout' => true,
                'created_by' => null,
            ]);

            // Seed version 1 directly as Published — system layouts are always live
            NewsletterLayoutVersion::create([
                'layout_id' => $layout->id,
                'version_number' => 1,
                'layout_definition_json' => $layoutData['def'],
                'state' => LayoutVersionState::Published->value,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function seedUserLayout(): void
    {
        $slug = 'user-custom-digest';

        if (NewsletterLayout::where('slug', $slug)->exists()) {
            return;
        }

        $def = $this->digestLayoutDefinition();
        $def['description'] = 'Custom digest layout cloned for editorial team use';
        $def['slots'][] = [
            'key' => 'sponsored_block',
            'label' => 'Sponsored Content',
            'required' => false,
            'allowed_block_types' => ['banner', 'cta', 'deal'],
        ];

        $layout = NewsletterLayout::create([
            'name' => 'Custom Editorial Digest',
            'slug' => $slug,
            'layout_definition_json' => $def,
            'is_system_layout' => false,
            'created_by' => 1,
        ]);

        // Version 1 = Draft (user layouts start as draft — must be published)
        NewsletterLayoutVersion::create([
            'layout_id' => $layout->id,
            'version_number' => 1,
            'layout_definition_json' => $def,
            'state' => LayoutVersionState::Draft->value,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Version 2 = Published
        $defV2 = $def;
        $defV2['version_notes'] = 'Added social share slot';
        $defV2['slots'][] = [
            'key' => 'social_share',
            'label' => 'Social Share',
            'required' => false,
        ];

        NewsletterLayoutVersion::create([
            'layout_id' => $layout->id,
            'version_number' => 2,
            'layout_definition_json' => $defV2,
            'state' => LayoutVersionState::Published->value,
            'created_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
        ]);
    }

    // ─── Branding ────────────────────────────────────────────────────────────

    private function seedBrandingForExistingNewsletters(): void
    {
        $newsletters = Newsletter::all();

        foreach ($newsletters as $newsletter) {
            if (NewsletterBrandingConfiguration::where('newsletter_id', $newsletter->id)->exists()) {
                continue;
            }

            $template = $newsletter->template ?? 'default';

            $branding = NewsletterBrandingConfiguration::create([
                'newsletter_id' => $newsletter->id,
                'logo_url' => null, // will fall back to site logo
                'header_text' => $this->headerTextForTemplate($template, $newsletter->title),
                'footer_text' => $this->footerTextForTemplate($template),
                'theme_json' => $this->themeForTemplate($template),
                'custom_css' => $this->cssForTemplate($template, $newsletter->id),
            ]);

            // Seed initial version
            NewsletterBrandingVersion::create([
                'branding_config_id' => $branding->id,
                'version_number' => 1,
                'branding_json_snapshot' => $branding->toSnapshot(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    // ─── Layout Definitions ───────────────────────────────────────────────────

    private function defaultLayoutDefinition(): array
    {
        return [
            'template' => 'default',
            'description' => 'Standard newsletter layout with hero header and card-based articles.',
            'columns' => 1,
            'slots' => [
                [
                    'key' => 'header',
                    'label' => 'Header / Logo Area',
                    'required' => true,
                    'allowed_block_types' => ['hero', 'image', 'heading'],
                ],
                [
                    'key' => 'content',
                    'label' => 'Article Cards',
                    'required' => true,
                    'allowed_block_types' => ['text', 'heading', 'image', 'cta', 'product', 'table'],
                ],
                [
                    'key' => 'footer',
                    'label' => 'Footer',
                    'required' => false,
                    'allowed_block_types' => ['text', 'cta'],
                ],
            ],
            'preview_columns' => [
                ['width' => '100%', 'label' => 'Full Width'],
            ],
        ];
    }

    private function digestLayoutDefinition(): array
    {
        return [
            'template' => 'digest',
            'description' => 'Compact, scannable digest with thumbnail + title format.',
            'columns' => 1,
            'slots' => [
                [
                    'key' => 'header',
                    'label' => 'Digest Header',
                    'required' => true,
                    'allowed_block_types' => ['heading', 'text'],
                ],
                [
                    'key' => 'digest_items',
                    'label' => 'Digest Items',
                    'required' => true,
                    'allowed_block_types' => ['text', 'image', 'cta'],
                ],
                [
                    'key' => 'footer',
                    'label' => 'Footer',
                    'required' => false,
                    'allowed_block_types' => ['text'],
                ],
            ],
            'preview_columns' => [
                ['width' => '100%', 'label' => 'Single Column Digest'],
            ],
        ];
    }

    private function featuredLayoutDefinition(): array
    {
        return [
            'template' => 'featured',
            'description' => 'Hero-first layout with dramatic full-width featured article followed by compact secondary cards.',
            'columns' => 1,
            'slots' => [
                [
                    'key' => 'hero',
                    'label' => 'Featured Hero Article',
                    'required' => true,
                    'allowed_block_types' => ['hero', 'image', 'heading', 'text'],
                ],
                [
                    'key' => 'secondary_articles',
                    'label' => 'Secondary Articles',
                    'required' => false,
                    'allowed_block_types' => ['text', 'image', 'heading', 'cta'],
                ],
                [
                    'key' => 'footer',
                    'label' => 'Footer',
                    'required' => false,
                    'allowed_block_types' => ['text', 'cta'],
                ],
            ],
            'preview_columns' => [
                ['width' => '100%', 'label' => 'Hero + Secondary'],
            ],
        ];
    }

    private function simpleLayoutDefinition(): array
    {
        return [
            'template' => 'simple',
            'description' => 'Minimal, editorial-style layout. Clean typography, no images in header.',
            'columns' => 1,
            'slots' => [
                [
                    'key' => 'header',
                    'label' => 'Title Header',
                    'required' => true,
                    'allowed_block_types' => ['heading', 'text'],
                ],
                [
                    'key' => 'content',
                    'label' => 'Article List',
                    'required' => true,
                    'allowed_block_types' => ['text', 'heading', 'list', 'cta'],
                ],
                [
                    'key' => 'footer',
                    'label' => 'Footer',
                    'required' => false,
                    'allowed_block_types' => ['text'],
                ],
            ],
            'preview_columns' => [
                ['width' => '100%', 'label' => 'Single Column Simple'],
            ],
        ];
    }

    // ─── Branding Helpers ─────────────────────────────────────────────────────

    private function headerTextForTemplate(string $template, string $newsletterTitle): string
    {
        return match ($template) {
            'digest' => "Your {$newsletterTitle} digest — curated for you",
            'featured' => "This week's top story from {$newsletterTitle}",
            'simple' => $newsletterTitle,
            default => "Welcome to {$newsletterTitle}",
        };
    }

    private function footerTextForTemplate(string $template): string
    {
        $year = date('Y');

        return match ($template) {
            'simple' => "© {$year}. You are receiving this because you subscribed.",
            default => "© {$year}. Unsubscribe at any time using the link below. We respect your privacy.",
        };
    }

    private function themeForTemplate(string $template): array
    {
        return match ($template) {
            'digest' => [
                'primary_color' => '#007bff',
                'secondary_color' => '#6c757d',
                'background_color' => '#f5f5f5',
                'text_color' => '#333333',
            ],
            'featured' => [
                'primary_color' => '#000000',
                'secondary_color' => '#ffffff',
                'background_color' => '#000000',
                'text_color' => '#ffffff',
            ],
            'simple' => [
                'primary_color' => '#000000',
                'secondary_color' => '#666666',
                'background_color' => '#ffffff',
                'text_color' => '#000000',
            ],
            default => [
                'primary_color' => '#667eea',
                'secondary_color' => '#764ba2',
                'background_color' => '#f4f4f4',
                'text_color' => '#1a1a1a',
            ],
        };
    }

    private function cssForTemplate(string $template, int $newsletterId): string
    {
        return match ($template) {
            'digest' => ".article-title { font-size: 16px; font-weight: 600; color: #1a1a1a; }\n.article-meta { color: #999; font-size: 12px; }",
            'simple' => ".article-title { font-family: Georgia, serif; font-size: 18px; }\n.article-link { color: #000; }",
            default => ".newsletter-header { padding: 20px; }\n.article-card { margin-bottom: 20px; }",
        };
    }
}