<?php

namespace App\Database\Seeders;

use App\Models\Menu;
use App\Models\MenuItem;

class SeedEstateFooterMenu
{
    public function run(): void
    {
        $siteId = 1;

        $menu = Menu::create([
            'name' => 'Fashion Footer Menu',
            'site_id' => $siteId,
            'menu_type' => 'footer',
            'slug' => 'fashion-footer-menu',
            'layout_config' => json_encode([
                'footer_style' => 'corporate',
                'max_columns' => 5,
                'show_brand_section' => true,
                'logo_type' => 'icon',
                'logo_icon' => '🏘️',
                'brand_name' => 'Premier Properties',
                'footer_description' => 'London\'s leading estate agency, specializing in luxury residential and commercial properties. With over 25 years of experience, we deliver exceptional service and results.',
                'social_style' => 'simple',
                'social_links' => [
                    'facebook' => '#',
                    'twitter' => '#',
                    'instagram' => '#',
                    'linkedin' => '#',
                    'youtube' => '#'
                ],
                'show_newsletter' => true,
                'newsletter_title' => 'Property Alerts',
                'newsletter_description' => 'Be the first to know about new properties matching your criteria. Get instant alerts directly to your inbox.',
                'newsletter_placeholder' => 'Your email address',
                'newsletter_button_text' => 'Get Alerts',
                'newsletter_action' => '/property-alerts/subscribe',
                'copyright_text' => '© {year} {brand}. All rights reserved. Registered in England and Wales.',
                'footer_bottom_text' => 'Regulated by The Property Ombudsman. Member of the National Association of Estate Agents.',
                'legal_links' => [
                    ['label' => 'Privacy Policy', 'url' => '/privacy'],
                    ['label' => 'Terms & Conditions', 'url' => '/terms'],
                    ['label' => 'Cookie Policy', 'url' => '/cookies'],
                    ['label' => 'Complaints Procedure', 'url' => '/complaints'],
                    ['label' => 'Sitemap', 'url' => '/sitemap']
                ]
            ]),
            'is_active' => true,
        ]);

        $menuId = $menu->id;

        // Column 1: Fashion
        $this->createFooterColumn($menuId, 1, 'Property Search', [
            ['label' => 'Properties for Sale', 'url' => '/for-sale'],
            ['label' => 'Properties to Rent', 'url' => '/to-rent'],
            ['label' => 'New Homes', 'url' => '/new-homes'],
            ['label' => 'Commercial', 'url' => '/commercial'],
            ['label' => 'International', 'url' => '/international'],
            ['label' => 'Land & Development', 'url' => '/land']
        ]);

        // Column 2: Beauty
        $this->createFooterColumn($menuId, 2, 'Areas We Cover', [
            ['label' => 'Central London', 'url' => '/areas/central-london'],
            ['label' => 'West London', 'url' => '/areas/west-london'],
            ['label' => 'North London', 'url' => '/areas/north-london'],
            ['label' => 'South London', 'url' => '/areas/south-london'],
            ['label' => 'East London', 'url' => '/areas/east-london'],
            ['label' => 'View All Areas', 'url' => '/areas']
        ]);

        // Column 3: About
        $this->createFooterColumn($menuId, 3, 'Our Services', [
            ['label' => 'Property Valuation', 'url' => '/valuation'],
            ['label' => 'Selling Guide', 'url' => '/guides/selling'],
            ['label' => 'Buying Guide', 'url' => '/guides/buying'],
            ['label' => 'Landlord Services', 'url' => '/landlords'],
            ['label' => 'Tenant Services', 'url' => '/tenants'],
            ['label' => 'Mortgage Advice', 'url' => '/mortgages']
        ]);

        $this->createFooterColumn($menuId, 4, 'Resources', [
            ['label' => 'Market Insights', 'url' => '/market-insights'],
            ['label' => 'Property News', 'url' => '/news'],
            ['label' => 'Area Guides', 'url' => '/area-guides'],
            ['label' => 'Stamp Duty Calculator', 'url' => '/calculators/stamp-duty'],
            ['label' => 'Mortgage Calculator', 'url' => '/calculators/mortgage'],
            ['label' => 'Blog', 'url' => '/blog']
        ]);

        $this->createFooterColumn($menuId, 5, 'Company', [
            ['label' => 'About Us', 'url' => '/about'],
            ['label' => 'Our Team', 'url' => '/team'],
            ['label' => 'Careers', 'url' => '/careers'],
            ['label' => 'Testimonials', 'url' => '/testimonials'],
            ['label' => 'Contact Us', 'url' => '/contact'],
            ['label' => 'Office Locations', 'url' => '/offices']
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

        // Link items
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