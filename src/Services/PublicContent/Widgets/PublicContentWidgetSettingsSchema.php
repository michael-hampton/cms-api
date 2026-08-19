<?php

namespace App\Services\PublicContent\Widgets;

/**
 * Declares which site-level settings each public-content widget exposes
 * in the config editor, beyond shared page_types / region / priority.
 *
 * Runtime consumers read the same keys from PublicContentConfigSource
 * (e.g. widgets.activity-feed.limit).
 */
final class PublicContentWidgetSettingsSchema
{
    /**
     * @return array<string, array{
     *   label: string,
     *   description?: string,
     *   fields: list<array<string, mixed>>
     * }>
     */
    public function all(): array
    {
        return [
            'page-title' => [
                'label' => 'Page title',
                'fields' => [],
            ],
            'hero-block' => [
                'label' => 'Hero block',
                'fields' => [],
            ],
            'breadcrumbs' => [
                'label' => 'Breadcrumbs',
                'fields' => [],
            ],
            'category-pills' => [
                'label' => 'Category pills',
                'fields' => [],
            ],
            'tags' => [
                'label' => 'Tags',
                'fields' => [],
            ],
            'page-actions' => [
                'label' => 'Page actions',
                'fields' => [],
            ],
            'social-links' => [
                'label' => 'Social links',
                'fields' => [],
            ],
            'review-summary' => [
                'label' => 'Review summary',
                'fields' => [],
            ],
            'categories-widget' => [
                'label' => 'Categories carousel',
                'description' => 'Homepage category browser layout, how many categories to show, and labels.',
                'fields' => [
                    $this->numberField('limit', 'Category limit', 12, 1, 48),
                    $this->choiceField('layout', 'Layout', 'carousel', [
                        ['value' => 'carousel', 'label' => 'Carousel'],
                        ['value' => 'grid', 'label' => 'Grid'],
                    ]),
                    $this->textField('title', 'Title', 'Explore Categories'),
                    $this->textField('subtitle', 'Subtitle', 'Discover content by topic'),
                ],
            ],
            'activity-feed' => [
                'label' => 'Activity feed',
                'description' => 'Recent published pages shown on landing pages.',
                'fields' => [
                    $this->numberField('limit', 'Item limit', 10, 1, 50),
                    $this->textField('title', 'Title', 'Activity Feed'),
                ],
            ],
            'most-popular-articles' => [
                'label' => 'Most popular articles',
                'fields' => [
                    $this->numberField('limit', 'Article limit', 6, 1, 24),
                    $this->textField('title', 'Title', 'Most popular'),
                ],
            ],
            'trending' => [
                'label' => 'Trending',
                'fields' => [
                    $this->numberField('limit', 'Item limit', 3, 1, 20),
                    $this->textField('title', 'Title', 'Trending Now'),
                ],
            ],
            'recirculation' => [
                'label' => 'Recirculation',
                'fields' => [
                    $this->numberField('limit', 'Recommendation limit', 4, 1, 12),
                    $this->textField('title', 'Title', 'Read this next'),
                ],
            ],
            'products' => [
                'label' => 'Products',
                'fields' => [],
            ],
            'newsletter' => [
                'label' => 'Newsletter signup',
                'fields' => [],
            ],
            'comments' => [
                'label' => 'Comments',
                'fields' => [],
            ],
            'category-pages' => [
                'label' => 'Category page sections',
                'description' => 'Landing-page category sections. Minimum pages cannot exceed pages per section — otherwise every section is hidden.',
                'fields' => [
                    $this->numberField('pages_per_section', 'Pages per section', 6, 1, 24),
                    $this->numberField('min_pages', 'Minimum pages to show section', 3, 1, 24),
                ],
            ],
            'deals' => [
                'label' => 'Deals carousel',
                'description' => 'Active deals island. Uses any current deal, not only today\'s featured snapshot. Limit controls how many are composed.',
                'fields' => [
                    $this->numberField('limit', 'Deal limit', 10, 1, 30),
                    $this->textField('title', 'Title', "Today's Best Deals & Offers"),
                ],
            ],
            'vouchers' => [
                'label' => 'Voucher carousel',
                'fields' => [
                    $this->numberField('limit', 'Voucher limit', 8, 1, 24),
                    $this->textField('eyebrow', 'Eyebrow', 'Reader offers'),
                    $this->textField('title', 'Title', 'Latest voucher codes'),
                    $this->textField('intro', 'Intro', 'Hand-picked active codes you can reveal before checkout.'),
                ],
            ],
            'guest-contributors' => [
                'label' => 'Guest contributors',
                'fields' => [],
            ],
            'authors' => [
                'label' => 'Authors',
                'fields' => [],
            ],
            'adverts' => [
                'label' => 'Ads in the article',
                'description' => 'How often inline ads appear in article body content.',
                'fields' => [
                    [
                        'key' => 'frequency',
                        'type' => 'choice',
                        'label' => 'Ad frequency',
                        'default' => 'balanced',
                        'options' => [
                            ['value' => 'less', 'label' => 'Less often'],
                            ['value' => 'balanced', 'label' => 'Balanced'],
                            ['value' => 'more', 'label' => 'More often'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultsFor(string $widgetKey): array
    {
        $schema = $this->all()[$widgetKey] ?? null;
        if ($schema === null) {
            return [];
        }

        $defaults = [];
        foreach ($schema['fields'] as $field) {
            if (array_key_exists('default', $field)) {
                $defaults[$field['key']] = $field['default'];
            }
        }

        return $defaults;
    }

    /**
     * @return array{key: string, type: string, label: string, default: int, min: int, max: int}
     */
    private function numberField(string $key, string $label, int $default, int $min, int $max): array
    {
        return [
            'key' => $key,
            'type' => 'number',
            'label' => $label,
            'default' => $default,
            'min' => $min,
            'max' => $max,
        ];
    }

    /**
     * @return array{key: string, type: string, label: string, default: string}
     */
    private function textField(string $key, string $label, string $default): array
    {
        return [
            'key' => $key,
            'type' => 'text',
            'label' => $label,
            'default' => $default,
        ];
    }

    /**
     * @param list<array{value: string, label: string}> $options
     * @return array{key: string, type: string, label: string, default: string, options: list<array{value: string, label: string}>}
     */
    private function choiceField(string $key, string $label, string $default, array $options): array
    {
        return [
            'key' => $key,
            'type' => 'choice',
            'label' => $label,
            'default' => $default,
            'options' => $options,
        ];
    }
}
