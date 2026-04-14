<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\Menu;
use App\Models\MenuItem;

class GoCompareFooterSeeder extends Seeder
{
    public function run(): void
    {
        $siteId = 10; // Adjust based on your site ID

        $menu = Menu::create([
            'name' => 'GoCompare Footer Menu',
            'site_id' => $siteId,
            'menu_type' => 'footer',
            'slug' => 'gocompare-footer-menu',
            'layout_config' => json_encode([
                'footer_style' => 'modern',
                'max_columns' => 5,
                'show_brand_section' => true,
                'logo_type' => 'text',
                'brand_name' => 'GoCompare',
                'footer_description' => 'Compare prices and save money on insurance, utilities, money products and more. Get quotes from leading UK providers and switch in minutes.',
                'social_style' => 'simple',
                'social_links' => [
                    'facebook' => 'https://facebook.com/gocompare',
                    'twitter' => 'https://twitter.com/gocompare',
                    'instagram' => 'https://instagram.com/gocompare',
                    'youtube' => 'https://youtube.com/gocompare'
                ],
                'show_newsletter' => true,
                'newsletter_title' => 'Money Saving Tips',
                'newsletter_description' => 'Get exclusive deals and advice delivered to your inbox',
                'newsletter_placeholder' => 'Your email address',
                'newsletter_button_text' => 'Subscribe',
                'newsletter_action' => '/subscribe',
                'copyright_text' => '© {year} GoCompare. All rights reserved. GoCompare is a trading name of Go Compare Limited. Registered in England & Wales.',
                'legal_links' => [
                    ['label' => 'Privacy Policy', 'url' => '/privacy'],
                    ['label' => 'Terms & Conditions', 'url' => '/terms'],
                    ['label' => 'Cookie Policy', 'url' => '/cookies'],
                    ['label' => 'Accessibility', 'url' => '/accessibility'],
                    ['label' => 'Sitemap', 'url' => '/sitemap']
                ],
                'show_awards' => true,
                'awards' => [
                    'Defaqto 5 Star Rating',
                    'Feefo Platinum Trusted Service',
                    'British Insurance Awards Winner'
                ]
            ]),
            'is_active' => true,
        ]);

        $menuId = $menu->id;

        // Column 1: Insurance
        $this->createFooterColumn($menuId, 1, 'Insurance', [
            ['label' => 'Car Insurance', 'url' => '/car-insurance'],
            ['label' => 'Home Insurance', 'url' => '/home-insurance'],
            ['label' => 'Travel Insurance', 'url' => '/travel-insurance'],
            ['label' => 'Pet Insurance', 'url' => '/pet-insurance'],
            ['label' => 'Life Insurance', 'url' => '/life-insurance'],
            ['label' => 'Van Insurance', 'url' => '/van-insurance'],
            ['label' => 'Breakdown Cover', 'url' => '/breakdown-cover']
        ]);

        // Column 2: Money
        $this->createFooterColumn($menuId, 2, 'Money', [
            ['label' => 'Credit Cards', 'url' => '/credit-cards'],
            ['label' => 'Loans', 'url' => '/loans'],
            ['label' => 'Mortgages', 'url' => '/mortgages'],
            ['label' => 'Bank Accounts', 'url' => '/bank-accounts'],
            ['label' => 'Savings Accounts', 'url' => '/savings'],
            ['label' => 'Investments', 'url' => '/investments']
        ]);

        // Column 3: Utilities
        $this->createFooterColumn($menuId, 3, 'Utilities', [
            ['label' => 'Energy', 'url' => '/energy'],
            ['label' => 'Broadband', 'url' => '/broadband'],
            ['label' => 'Mobile Phones', 'url' => '/mobile'],
            ['label' => 'TV & Streaming', 'url' => '/tv']
        ]);

        // Column 4: Help & Guides
        $this->createFooterColumn($menuId, 4, 'Help & Guides', [
            ['label' => 'How It Works', 'url' => '/how-it-works'],
            ['label' => 'Money Saving Tips', 'url' => '/tips'],
            ['label' => 'Insurance Guides', 'url' => '/guides/insurance'],
            ['label' => 'Utilities Guides', 'url' => '/guides/utilities'],
            ['label' => 'FAQs', 'url' => '/faq'],
            ['label' => 'Blog', 'url' => '/blog']
        ]);

        // Column 5: About
        $this->createFooterColumn($menuId, 5, 'About GoCompare', [
            ['label' => 'About Us', 'url' => '/about'],
            ['label' => 'Contact Us', 'url' => '/contact'],
            ['label' => 'Press Centre', 'url' => '/press'],
            ['label' => 'Careers', 'url' => '/careers'],
            ['label' => 'Partners', 'url' => '/partners'],
            ['label' => 'Affiliates', 'url' => '/affiliates']
        ]);
    }

    private function createFooterColumn(int $menuId, int $columnGroup, string $header, array $links): void
    {
        MenuItem::create([
            'menu_id' => $menuId,
            'label' => $header,
            'column_group' => $columnGroup,
            'sort_order' => 0,
            'is_active' => 1,
            'target_type' => 'custom',
            'custom_url' => '#'
        ]);

        $order = 1;
        foreach ($links as $link) {
            MenuItem::create([
                'menu_id' => $menuId,
                'label' => $link['label'],
                'column_group' => $columnGroup,
                'sort_order' => $order++,
                'is_active' => 1,
                'target_type' => 'custom',
                'custom_url' => $link['url']
            ]);
        }
    }
}