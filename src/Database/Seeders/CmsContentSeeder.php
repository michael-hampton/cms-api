<?php
// database/seeds/CmsContentSeeder.php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\Category;
use App\Models\PageCategory;
use App\Models\PageTag;
use App\Models\Tag;
use App\Models\Page;
use App\Models\Block;
use App\Models\PageSocial;
use App\Models\Comment;
use App\Models\EventSignup;

class CmsContentSeeder extends Seeder
{
    public function run(): void
    {
        // Create Categories
        $categories = $this->createCategories();

        // Create Tags
        $tags = $this->createTags();

        // Create Pages with different types
        $pages = $this->createPages();

        // Create Blocks for pages
        $this->createBlocks($pages);

        // Create Social Media settings for pages
        $this->createSocialSettings($pages);

        // Create Comments for blog pages
        $this->createComments($pages);

        // Create Event Signups
        $this->createEventSignups();

        // Associate pages with categories and tags
        $this->associatePageRelations($pages, $categories, $tags);
    }

    private function createCategories(): array
    {
        $categories = [
            [
                'name' => 'Real Estate',
                'slug' => 'real-estate',
                'description' => 'Everything related to property buying, selling, and renting',
                'color' => '#007bff',
                'icon' => '🏠',
                'is_active' => true,
                'sort_order' => 1,
                'site_id' => 1
            ],
            [
                'name' => 'Luxury Properties',
                'slug' => 'luxury-properties',
                'description' => 'Premium and luxury real estate listings',
                'color' => '#ffc107',
                'icon' => '✨',
                'parent_id' => 1, // Child of Real Estate
                'is_active' => true,
                'sort_order' => 1,
                'site_id' => 1
            ],
            [
                'name' => 'Investment Properties',
                'slug' => 'investment-properties',
                'description' => 'Properties for investment purposes',
                'color' => '#28a745',
                'icon' => '💰',
                'parent_id' => 1,
                'is_active' => true,
                'sort_order' => 2,
                'site_id' => 1
            ],
            [
                'name' => 'News & Updates',
                'slug' => 'news-updates',
                'description' => 'Latest news and market updates',
                'color' => '#dc3545',
                'icon' => '📰',
                'is_active' => true,
                'sort_order' => 2,
                'site_id' => 1
            ],
            [
                'name' => 'Events',
                'slug' => 'events',
                'description' => 'Property exhibitions, open houses, and seminars',
                'color' => '#6f42c1',
                'icon' => '🎪',
                'is_active' => true,
                'sort_order' => 3,
                'site_id' => 1
            ],
            [
                'name' => 'Guides & Resources',
                'slug' => 'guides-resources',
                'description' => 'Helpful guides for buyers and sellers',
                'color' => '#20c997',
                'icon' => '📚',
                'is_active' => true,
                'sort_order' => 4,
                'site_id' => 1
            ]
        ];

        $createdCategories = [];
        foreach ($categories as $categoryData) {
            $createdCategories[] = Category::create($categoryData);
        }

        return $createdCategories;
    }

    private function createTags(): array
    {
        $tagNames = [
            'London', 'Luxury', 'Investment', 'First Time Buyer', 'Family Home',
            'Modern', 'Victorian', 'Garden', 'Parking', 'New Build',
            'Waterfront', 'City Centre', 'Suburbs', 'Commercial', 'Residential',
            'Market Analysis', 'Property Tips', 'Mortgage Advice', 'Legal Advice',
            'Home Improvement', 'Interior Design', 'Property Valuation'
        ];

        $createdTags = [];
        foreach ($tagNames as $tagName) {
            $createdTags[] = Tag::create([
                'name' => $tagName,
                'slug' => strtolower(str_replace(' ', '-', $tagName)),
                'usage_count' => rand(5, 50),
                'is_featured' => rand(0, 1) === 1,
                'site_id' => 1
            ]);
        }

        return $createdTags;
    }

    private function createPages(): array
    {
        $pages = [
            // Blog Pages
            [
                'title' => 'London Property Market Trends 2024',
                'slug' => 'london-property-market-trends-2024',
                //'content' => 'A comprehensive analysis of the London property market...',
                'page_type' => 'blog',
                'status' => 'published',
                'meta_title' => 'London Property Market Trends 2024 - Premier Properties',
                'meta_description' => 'Discover the latest trends in London\'s property market for 2024',
                'published_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
                'site_id' => 1
            ],
            [
                'title' => 'First Time Buyer\'s Guide to London Real Estate',
                'slug' => 'first-time-buyers-guide-london',
                //'content' => 'Everything you need to know as a first-time buyer...',
                'page_type' => 'blog',
                'status' => 'published',
                'meta_title' => 'First Time Buyer\'s Guide - Premier Properties',
                'meta_description' => 'Complete guide for first-time property buyers in London',
                'published_at' => date('Y-m-d H:i:s', strtotime('-20 days')),
                'site_id' => 1
            ],

            // Event Pages
            [
                'title' => 'Luxury Property Exhibition 2024',
                'slug' => 'luxury-property-exhibition-2024',
                //'content' => 'Join us for an exclusive showcase of luxury properties...',
                'page_type' => 'event',
                'status' => 'published',
                'meta_title' => 'Luxury Property Exhibition 2024',
                'meta_description' => 'Exclusive luxury property exhibition featuring premium listings',
                'published_at' => date('Y-m-d H:i:s', strtotime('-10 days')),
                'site_id' => 1
            ],
            [
                'title' => 'Property Investment Seminar',
                'slug' => 'property-investment-seminar',
               // 'content' => 'Learn the secrets of successful property investment...',
                'page_type' => 'event',
                'status' => 'published',
                'meta_title' => 'Property Investment Seminar',
                'meta_description' => 'Professional seminar on property investment strategies',
                'published_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
                'site_id' => 1
            ],

            // Regular Pages
            [
                'title' => 'About Premier Properties',
                'slug' => 'about-us',
                //'content' => 'Premier Properties has been serving London since 2010...',
                'page_type' => 'page',
                'status' => 'published',
                'meta_title' => 'About Us - Premier Properties',
                'meta_description' => 'Learn about Premier Properties and our commitment to excellence',
                'published_at' => date('Y-m-d H:i:s', strtotime('-60 days')),
                'site_id' => 1
            ],
            [
                'title' => 'Contact Us',
                'slug' => 'contact',
                //'content' => 'Get in touch with our expert team...',
                'page_type' => 'page',
                'status' => 'published',
                'meta_title' => 'Contact Premier Properties',
                'meta_description' => 'Contact Premier Properties for all your real estate needs',
                'published_at' => date('Y-m-d H:i:s', strtotime('-50 days')),
                'site_id' => 1
            ]
        ];

        $createdPages = [];
        foreach ($pages as $pageData) {
            $createdPages[] = Page::create($pageData);
        }

        return $createdPages;
    }

    private function createBlocks(array $pages): void
    {
        $blockConfigurations = [
            // Blog page blocks
            1 => [ // London Property Market Trends
                [
                    'type' => 'text',
                    'order' => 1,
                    'data' => [
                        'paragraphs' => ['<h2>Market Overview</h2><p>The London property market in 2024 shows resilient growth despite economic uncertainties. Average property prices have increased by 3.2% year-on-year, with prime central London areas leading the recovery.</p>'],
                        'context' => 'main'
                    ]
                ],
                [
                    'type' => 'contact-form',
                    'order' => 1,
                    'data' => [
                        'title' => 'Get Market Insights',
                        'subtitle' => 'Subscribe to our market updates',
                        'context' => 'sidebar',
                        'showName' => true,
                        'showEmail' => true,
                        'showPhone' => false,
                        'requireName' => true,
                        'requireEmail' => true,
                        'submitButtonText' => 'Subscribe'
                    ]
                ]
            ],

            // Event page blocks
            3 => [ // Luxury Property Exhibition
                [
                    'type' => 'event',
                    'order' => 1,
                    'data' => [
                        'title' => 'Luxury Property Exhibition 2024',
                        'description' => 'Discover London\'s most exclusive properties at our annual luxury exhibition. Meet leading developers, architects, and real estate professionals.',
                        'startDate' => date('Y-m-d', strtotime('+30 days')),
                        'startTime' => '10:00',
                        'endTime' => '18:00',
                        'location' => 'The Shard, Level 31',
                        'address' => '31 St Thomas Street, London SE1 9QU',
                        'mapUrl' => 'https://maps.google.com/?q=The+Shard+London',
                        'ticketPrice' => 25.00,
                        'currency' => '£',
                        'capacity' => 200,
                        'organizerName' => 'Premier Properties Events',
                        'organizerEmail' => 'events@premierproperties.co.uk',
                        'organizerPhone' => '+44 20 7123 4567',
                        'category' => 'Property Exhibition',
                        'showSignupForm' => true,
                        'featured' => true,
                        'context' => 'main'
                    ]
                ],
                [
                    'type' => 'event-signup',
                    'order' => 1,
                    'data' => [
                        'title' => 'Quick Registration',
                        'subtitle' => 'Reserve your spot today',
                        'context' => 'sidebar',
                        'showName' => true,
                        'showEmail' => true,
                        'showPhone' => true,
                        'showCompany' => true,
                        'requireName' => true,
                        'requireEmail' => true,
                        'submitButtonText' => 'Register Now'
                    ]
                ]
            ],

            4 => [ // Property Investment Seminar
                [
                    'type' => 'event',
                    'order' => 1,
                    'data' => [
                        'title' => 'Property Investment Seminar',
                        'description' => 'Learn from industry experts about successful property investment strategies, market analysis, and portfolio management.',
                        'startDate' => date('Y-m-d', strtotime('+45 days')),
                        'startTime' => '19:00',
                        'endTime' => '21:30',
                        'location' => 'Premier Properties Head Office',
                        'address' => '123 Premium Street, London SW1A 1AA',
                        'ticketPrice' => 0,
                        'capacity' => 50,
                        'organizerName' => 'Premier Properties',
                        'organizerEmail' => 'seminars@premierproperties.co.uk',
                        'category' => 'Educational Seminar',
                        'showSignupForm' => true,
                        'context' => 'main'
                    ]
                ]
            ],

            // Contact page blocks
            6 => [
                [
                    'type' => 'contact-form',
                    'order' => 1,
                    'data' => [
                        'title' => 'Get In Touch',
                        'subtitle' => 'We\'re here to help with all your property needs',
                        'showName' => true,
                        'showEmail' => true,
                        'showPhone' => true,
                        'showSubject' => true,
                        'showMessage' => true,
                        'requireName' => true,
                        'requireEmail' => true,
                        'requireMessage' => true,
                        'submitButtonText' => 'Send Message',
                        'recipientEmail' => 'info@premierproperties.co.uk',
                        'context' => 'main'
                    ]
                ]
            ]
        ];

        foreach ($blockConfigurations as $pageId => $blocks) {
            foreach ($blocks as $blockData) {
                Block::create([
                    'page_id' => $pageId,
                    'type' => $blockData['type'],
                    'order' => $blockData['order'],
                    'data' => $blockData['data']
                ]);
            }
        }
    }

    private function createSocialSettings(array $pages): void
    {
        foreach ($pages as $page) {
            PageSocial::create([
                'page_id' => $page->id,
                'enable_sharing' => true,
                'platforms' => ['facebook', 'twitter', 'linkedin', 'email'],
                'share_text' => $page->title,
                'share_hashtags' => '#LondonProperty #RealEstate #PremierProperties',
                'track_shares' => true,
                'track_clicks' => true,
                'show_share_count' => $page->page_type === 'blog'
            ]);
        }
    }

    private function createComments(array $pages): void
    {
        $blogPages = array_filter($pages, fn($page) => $page->page_type === 'blog');

        $commentData = [
            [
                'name' => 'Sarah Johnson',
                'email' => 'sarah.johnson@email.com',
                'content' => 'Great analysis! This really helps understand the current market conditions. I\'m particularly interested in the trends for first-time buyers.',
                'status' => 'approved',
                'site_id' => 1
            ],
            [
                'name' => 'Michael Chen',
                'email' => 'michael.chen@email.com',
                'content' => 'Thanks for sharing these insights. The data on luxury properties is especially valuable for investors like myself.',
                'status' => 'approved',
                'site_id' => 1
            ],
            [
                'name' => 'Emma Thompson',
                'email' => 'emma.thompson@email.com',
                'content' => 'Very informative article. Could you provide more details about the areas showing the highest growth?',
                'status' => 'approved',
                'site_id' => 1
            ],
            [
                'name' => 'David Wilson',
                'email' => 'david.wilson@email.com',
                'content' => 'This spam comment should be filtered out.',
                'status' => 'spam',
                'site_id' => 1
            ]
        ];

        foreach ($blogPages as $page) {
            foreach ($commentData as $comment) {
                Comment::create([
                    'page_id' => $page->id,
                    'name' => $comment['name'],
                    'email' => $comment['email'],
                    'content' => $comment['content'],
                    'status' => $comment['status'],
                    'created_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 10) . ' days'))
                ]);
            }
        }
    }

    private function createEventSignups(): void
    {
        $signups = [
            [
                'event_title' => 'Luxury Property Exhibition 2024',
                'event_date' => date('Y-m-d', strtotime('+30 days')),
                'name' => 'James Mitchell',
                'email' => 'james.mitchell@email.com',
                'phone' => '+44 7700 900001',
                'company' => 'Mitchell Investments',
                'newsletter' => true,
                'notifications' => ['reminders', 'similar_events'],
                'status' => 'confirmed'
            ],
            [
                'event_title' => 'Luxury Property Exhibition 2024',
                'event_date' => date('Y-m-d', strtotime('+30 days')),
                'name' => 'Anna Rodriguez',
                'email' => 'anna.rodriguez@email.com',
                'phone' => '+44 7700 900002',
                'newsletter' => true,
                'status' => 'confirmed'
            ],
            [
                'event_title' => 'Property Investment Seminar',
                'event_date' => date('Y-m-d', strtotime('+45 days')),
                'name' => 'Robert Taylor',
                'email' => 'robert.taylor@email.com',
                'company' => 'Taylor Property Group',
                'newsletter' => true,
                'notifications' => ['reminders'],
                'status' => 'pending'
            ]
        ];

        foreach ($signups as $signup) {
            $signup['confirmation_token'] = bin2hex(random_bytes(16));
            EventSignup::create($signup);
        }
    }

    private function associatePageRelations(array $pages, array $categories, array $tags): void
    {
        // Associate pages with categories
        $pageCategories = [
            1 => [4], // Market trends -> News & Updates
            2 => [1, 6], // First time buyer guide -> Real Estate, Guides
            3 => [5, 2], // Luxury exhibition -> Events, Luxury Properties
            4 => [5, 3], // Investment seminar -> Events, Investment Properties
            5 => [1], // About -> Real Estate
            6 => [1]  // Contact -> Real Estate
        ];

        foreach ($pageCategories as $pageIndex => $categoryIndexes) {
            $page = $pages[$pageIndex - 1];
            foreach ($categoryIndexes as $categoryIndex) {
                $category = $categories[$categoryIndex - 1];
               PageCategory::create([
                   'page_id' => $page->id,
                   'category_id' => $category->id,
               ]);
            }
        }

        // Associate pages with tags
        $pageTags = [
            1 => [1, 6, 16], // London, Modern, Market Analysis
            2 => [1, 4, 17, 18], // London, First Time Buyer, Property Tips, Mortgage Advice
            3 => [1, 2, 11], // London, Luxury, Waterfront
            4 => [3, 17], // Investment, Property Tips
            5 => [1], // London
            6 => [1] // London
        ];

        foreach ($pageTags as $pageIndex => $tagIndexes) {
            $page = $pages[$pageIndex - 1];
            foreach ($tagIndexes as $tagIndex) {
                $tag = $tags[$tagIndex - 1];
                PageTag::create([
                    'page_id' => $page->id,
                    'tag_id' => $tag->id,
                ]);
                // In a real implementation, you'd use pivot table inserts
                // Increment tag usage
                $tag->usage_count++;
                $tag->save();
            }
        }
    }
}