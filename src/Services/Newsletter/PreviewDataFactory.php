<?php

namespace App\Services\Newsletter;

use App\Models\Member;
use App\Models\Site;

/**
 * Builds predefined mock data contexts for email template previews.
 *
 * Used by EmailTemplateRenderer when rendering a preview without live runtime data.
 * Datasets mirror the variable namespaces the block system supports:
 *   mock_user   — user.*, order.* (minimal)
 *   mock_order  — user.*, order.* (full line items + totals)
 *   mock_seller — user.*, seller.*
 *
 * The arrays returned here are passed into EmailTemplateRenderer::renderPreview()
 * and merged with block-level data before variable interpolation.
 */
class PreviewDataFactory
{
    private const DATASETS = [
        'mock_user' => [
            'user' => [
                'first_name' => 'Sarah',
                'last_name' => 'Johnson',
                'email' => 'sarah.johnson@example.com',
                'full_name' => 'Sarah Johnson',
                'account_url' => 'https://example.com/account',
            ],
            'order' => [
                'number' => 'ORD-2024-0001',
                'total' => '$49.00',
                'date' => 'January 15, 2024',
            ],
            'site' => [
                'name' => 'Example Store',
                'url' => 'https://example.com',
            ],
        ],

        'mock_order' => [
            'user' => [
                'first_name' => 'Michael',
                'last_name' => 'Chen',
                'email' => 'michael.chen@example.com',
                'full_name' => 'Michael Chen',
                'account_url' => 'https://example.com/account',
            ],
            'order' => [
                'number' => 'ORD-2024-8821',
                'total' => '$58.00',
                'subtotal' => '$58.00',
                'shipping_cost' => '$0.00',
                'tax' => '$0.00',
                'date' => 'January 15, 2024',
                'status' => 'Confirmed',
                'tracking_url' => 'https://example.com/orders/8821/track',
                'items_count' => 2,
                'items' => [
                    ['name' => 'Premium Plan Subscription', 'qty' => 1, 'price' => '$49.00'],
                    ['name' => 'Add-on: Extra Storage (50GB)', 'qty' => 1, 'price' => '$9.00'],
                ],
            ],
            'product' => [
                'name' => 'Premium Plan Subscription',
                'description' => 'Full access to all premium features including unlimited projects and priority support.',
                'price' => '$49.00',
                'image_url' => 'https://via.placeholder.com/600x300?text=Product+Image',
                'url' => 'https://example.com/products/premium-plan',
            ],
            'site' => [
                'name' => 'Example Store',
                'url' => 'https://example.com',
            ],
        ],

        'mock_seller' => [
            'user' => [
                'first_name' => 'Emma',
                'last_name' => 'Williams',
                'email' => 'emma.williams@example.com',
                'full_name' => 'Emma Williams',
            ],
            'seller' => [
                'name' => 'Artisan Crafts Co.',
                'email' => 'hello@artisancrafts.example.com',
                'store_url' => 'https://example.com/stores/artisan-crafts',
                'logo_url' => 'https://via.placeholder.com/200x60?text=Logo',
                'primary_color' => '#2c7be5',
                'tagline' => 'Handmade with love',
            ],
            'site' => [
                'name' => 'Marketplace',
                'url' => 'https://example.com',
            ],
        ],
    ];

    /**
     * Return the flat variable map for the requested dataset.
     *
     * Keys are dot-notation paths matching {{ variable }} token syntax.
     * e.g. 'user.first_name' => 'Sarah'
     */
    public function build(string $dataset): array
    {
        $data = self::DATASETS[$dataset] ?? self::DATASETS['mock_order'];
        return $this->flatten($data);
    }

    /**
     * Flatten a nested array into dot-notation keys.
     * ['user' => ['first_name' => 'Sarah']] → ['user.first_name' => 'Sarah']
     */
    private function flatten(array $data, string $prefix = ''): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $fullKey = $prefix !== '' ? "{$prefix}.{$key}" : $key;

            if (is_array($value) && !$this->isSequential($value)) {
                // Associative — recurse
                foreach ($this->flatten($value, $fullKey) as $k => $v) {
                    $result[$k] = $v;
                }
            } else {
                // Scalar or sequential array (leave as-is for renderers that iterate)
                $result[$fullKey] = $value;
            }
        }

        return $result;
    }

    private function isSequential(array $arr): bool
    {
        return array_is_list($arr);
    }

    // ── Private ───────────────────────────────────────────────

    /**
     * Build from a real member and optional site context.
     * Used when previewing as a specific user rather than a mock dataset.
     */
    public function buildFromMember(Member $member, ?Site $site = null): array
    {
        $vars = [
            'user.first_name' => $member->first_name ?? 'there',
            'user.last_name' => $member->last_name ?? '',
            'user.email' => $member->email ?? '',
            'user.full_name' => trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? '')),
        ];

        if ($site) {
            $vars['site.name'] = $site->name ?? '';
            $vars['site.url'] = $site->url ?? '';
        }

        return $vars;
    }

    /**
     * Return dataset identifiers available for selection in the preview UI.
     */
    public function availableDatasets(): array
    {
        return array_keys(self::DATASETS);
    }
}