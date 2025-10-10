<?php

namespace App\Database\Seeders;

use App\Framework\Container;
use App\Framework\Database\Seeder\Seeder;
use App\Models\CustomFieldDefinition;
use App\Models\Page;
use App\Models\PageCustomField;
use App\Repositories\BlockRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\PageRepository;
use App\Repositories\TagRepository;
use App\Services\BlockParserService;

class EstateWebsiteSeeder extends Seeder
{
    private $pageRepository;
    private $blockRepository;
    private $tagRepository;
    private $categoryRepository;
    private $blockParserService;

    public function __construct()
    {
        $this->pageRepository = new PageRepository();
        $this->blockRepository = new BlockRepository();
        $this->tagRepository = new TagRepository();
        $this->categoryRepository = new CategoryRepository();
        $this->blockParserService = (new Container())->resolve(BlockParserService::class);

        parent::__construct();
    }

    public function run(): void
    {
//        $this->createTags();
//        $this->createCategories();
//        $this->createCustomFields();
//        $this->createHomepage();
//        $this->createAboutPage();
//        $this->createContactPage();
//        $this->createPropertyPages();
//        $this->createPropertiesPage();
//        $this->addSearchFields();
//        $this->generateRelatedPageBlocks();;
    }

    private function addSearchFields()
    {
        $page = Page::where('slug', 'home')->first();

        foreach ([16, 17, 18] as $item) {
            PageCustomField::create([
                'page_id' => $page->id,
                'custom_field_definition_id' => $item,
            ]);
        }
    }

    private function createTags(): void
    {
        $tags = [
            // Property features
            'featured', '3-bed', '4-bed', '5-bed', '2-bath', '3-bath', '4-bath',
            'garden', 'garage', 'parking', 'balcony', 'terrace', 'pool', 'gym',
            'concierge', 'new-build', 'period-property', 'chain-free',

            // Property types
            'house', 'apartment', 'flat', 'townhouse', 'villa', 'penthouse',
            'studio', 'maisonette', 'cottage',

            // Areas
            'central-london', 'north-london', 'south-london', 'east-london', 'west-london',

            // Price ranges
            'under-500k', '500k-1m', '1m-2m', 'over-2m',

            // Features
            '1000-sqft', '1500-sqft', '2000-sqft', '2500-sqft', '3000-sqft'
        ];

        foreach ($tags as $tagName) {
            $this->tagRepository->findOrCreateByName($tagName, 1);
        }
    }

    private function createCategories(): void
    {
        $categories = [
            'Properties' => [
                'Residential' => ['Houses', 'Apartments', 'Townhouses'],
                'Commercial' => ['Offices', 'Retail', 'Industrial'],
                'Luxury' => ['Penthouse', 'Mansion', 'Waterfront']
            ],
            'Locations' => [
                'Central London' => ['Mayfair', 'Kensington', 'Chelsea'],
                'North London' => ['Hampstead', 'Islington', 'Camden'],
                'South London' => ['Clapham', 'Greenwich', 'Richmond']
            ],
            'Services' => ['Sales', 'Lettings', 'Property Management', 'Valuations'],
            'Content' => ['Blog', 'Guides', 'Market Reports']
        ];

        $this->createCategoriesRecursively($categories);
    }

    private function createCategoriesRecursively(array $categories, ?int $parentId = null): void
    {
        foreach ($categories as $name => $children) {
            $category = $this->categoryRepository->findOrCreateByName($name, 1);
            if ($parentId) {
                $category->parent_id = $parentId;
                $category->save();
            }

            if (is_array($children)) {
                $this->createCategoriesRecursively($children, $category->id);
            }
        }
    }

    private function createCustomFields(): void
    {
        $fields = [
            ['key' => 'price', 'name' => 'Price', 'type' => 'number'],
            ['key' => 'assigned_agent', 'name' => 'Assigned Agent', 'type' => 'select', 'options' => '{"source":"persons","value_field":"id","label_field":"name"}'],
            ['key' => 'bedrooms', 'name' => 'Bedrooms', 'type' => 'number'],
            ['key' => 'bathrooms', 'name' => 'Bathrooms', 'type' => 'number'],
            ['key' => 'square_feet', 'name' => 'Square Feet', 'type' => 'number'],
            ['key' => 'property_type', 'name' => 'Property Type', 'type' => 'text'],
            ['key' => 'address', 'name' => 'Address', 'type' => 'textarea'],
            ['key' => 'area', 'name' => 'Area', 'type' => 'text'],
            ['key' => 'postcode', 'name' => 'Postcode', 'type' => 'text'],
            ['key' => 'latitude', 'name' => 'Latitude', 'type' => 'number'],
            ['key' => 'longitude', 'name' => 'Longitude', 'type' => 'number'],
            ['key' => 'year_built', 'name' => 'Year Built', 'type' => 'number'],
            ['key' => 'agent_name', 'name' => 'Agent Name', 'type' => 'text'],
            ['key' => 'agent_email', 'name' => 'Agent Email', 'type' => 'email'],
            ['key' => 'agent_phone', 'name' => 'Agent Phone', 'type' => 'text'],
            ['key' => 'location', 'name' => 'Location', 'type' => 'text', 'is_searchable' => true],
            ['key' => 'property_type', 'name' => 'Property Type', 'type' => 'select', 'is_searchable' => true, 'options' => '{"house":"House","apartment":"Apartment","townhouse":"Townhouse"}'],
            ['key' => 'price_range', 'name' => 'Price Range', 'type' => 'select', 'is_searchable' => true, 'options' => '{"0-500000":"Up to £500k","500000-1000000":"£500k-£1m"}']
        ];

        foreach ($fields as $field) {
            CustomFieldDefinition::create([
                'key' => $field['key'],
                'name' => $field['name'],
                'type' => $field['type'],
                'is_active' => true,
                'sort_order' => 10,
                'is_searchable' => $field['is_searchable'] ?? false,
                'options' => $field['options'] ?? null,
                'site_id' => 1
            ]);
        }
    }

    private function createHomepage(): void
    {
        $page = Page::create([
            'title' => 'Premier Properties - Your Dream Home Awaits',
            'page_type' => 'content',
            'slug' => 'home',
            'status' => 'published',
            'meta_title' => 'Premier Properties - Luxury Real Estate in London',
            'meta_description' => 'Find your perfect home with Premier Properties. Luxury estates, family homes, and investment properties across London and the Home Counties.',
            'site_id' => 1
        ]);

        // Add featured tag
        $featuredTag = $this->tagRepository->findOrCreateByName('featured', 1);
        $page->tags(true)->attach($featuredTag->id);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Find Your Perfect Home',
                    'subtitle' => 'Discover exceptional properties with Premier Properties. From luxury estates to cozy starter homes, we help you find the perfect place to call home.',
                    'ctaText' => 'Browse Properties',
                    'ctaUrl' => '/properties',
                    'secondaryCtaText' => 'Our Services',
                    'secondaryCtaUrl' => '#services',
                    'showSearch' => true,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ],
            [
                'type' => 'stats',
                'data' => [
                    'title' => '',
                    'stats' => [
                        ['number' => '500+', 'label' => 'Properties Sold', 'icon' => '🏠'],
                        ['number' => '15+', 'label' => 'Years Experience', 'icon' => '⭐'],
                        ['number' => '98%', 'label' => 'Client Satisfaction', 'icon' => '😊'],
                        ['number' => '50+', 'label' => 'Expert Agents', 'icon' => '👥']
                    ],
                    'layout' => 'grid'
                ],
                'order' => 2
            ],
            [
                'type' => 'services',
                'data' => [
                    'title' => 'Our Services',
                    'subtitle' => 'From property search to closing day and beyond, we provide comprehensive real estate services tailored to your needs.',
                    'services' => [
                        [
                            'title' => 'Property Sales',
                            'description' => 'Expert guidance through the entire selling process, from market valuation to completion. We ensure maximum value and minimal stress.',
                            'icon' => '🏡',
                            'url' => '/services/sales'
                        ],
                        [
                            'title' => 'Property Search',
                            'description' => 'Our dedicated team helps you find the perfect property that matches your criteria, budget, and lifestyle requirements.',
                            'icon' => '🔍',
                            'url' => '/services/search'
                        ],
                        [
                            'title' => 'Investment Advisory',
                            'description' => 'Strategic property investment advice to help you build a profitable portfolio and secure your financial future.',
                            'icon' => '💰',
                            'url' => '/services/investment'
                        ],
                        [
                            'title' => 'Market Analysis',
                            'description' => 'Comprehensive market reports and trend analysis to help you make informed decisions in today\'s dynamic property market.',
                            'icon' => '📊',
                            'url' => '/services/analysis'
                        ],
                        [
                            'title' => 'Property Management',
                            'description' => 'Full-service property management for landlords, handling everything from tenant screening to maintenance coordination.',
                            'icon' => '🔧',
                            'url' => '/services/management'
                        ],
                        [
                            'title' => 'Legal Support',
                            'description' => 'Professional legal assistance throughout your property transaction, ensuring all documentation and contracts are properly handled.',
                            'icon' => '⚖️',
                            'url' => '/services/legal'
                        ]
                    ],
                    'layout' => 'grid'
                ],
                'order' => 3
            ],
            [
                'type' => 'testimonial',
                'data' => [
                    'testimonials' => [
                        [
                            'text' => 'Premier Properties made our home buying experience seamless and stress-free. Their attention to detail and market expertise helped us find our dream home at the perfect price.',
                            'author' => 'Sarah Johnson',
                            'role' => 'First-time Buyer',
                            'rating' => 5,
                            'image' => null
                        ],
                        [
                            'text' => 'The team\'s professionalism and dedication exceeded our expectations. They sold our property 20% above asking price and handled every detail with care.',
                            'author' => 'Michael Chen',
                            'role' => 'Property Investor',
                            'rating' => 5,
                            'image' => null
                        ],
                        [
                            'text' => 'Outstanding service from start to finish. The market analysis was thorough and the marketing strategy was brilliant. Highly recommended!',
                            'author' => 'Emma Phillips',
                            'role' => 'Property Seller',
                            'rating' => 5,
                            'image' => null
                        ]
                    ],
                    'layout' => 'grid'
                ],
                'order' => 4
            ],
            [
                'type' => 'person',
                'data' => [
                    'name' => 'Premier Properties Contact',
                    'role' => 'Contact Information',
                    'email' => 'info@premierproperties.co.uk',
                    'phone' => '+44 20 7123 4567',
                    'address' => '123 Premium Street\nLondon, SW1A 1AA',
                    'displayType' => 'contact'
                ],
                'order' => 5
            ],
            [
                'type' => 'contact-form',
                'data' => [
                    'title' => 'Send Us a Message',
                    'subtitle' => '',
                    'showName' => true,
                    'showEmail' => true,
                    'showPhone' => true,
                    'showSubject' => true,
                    'showMessage' => true,
                    'submitButtonText' => 'Send Message',
                    'requireName' => true,
                    'requireEmail' => true,
                    'requireMessage' => true
                ],
                'order' => 6
            ],
            [
                'type' => 'page_grid',
                'data' => [
                    'title' => 'Featured Properties',
                    'subtitle' => 'Discover exceptional properties with Premier Properties. From luxury estates to cozy starter homes.',
                    'layout' => 'grid',
                    'columns' => 3,
                    'showExcerpt' => true,
                    'showImage' => true,
                    'showFeatures' => true,
                    'showActions' => true,
                    'pages' => [
                        [
                            'title' => 'Luxury Penthouse in Mayfair',
                            'slug' => 'luxury-penthouse-mayfair',
                            'excerpt' => 'Stunning 3-bedroom penthouse with panoramic views of London. Features modern amenities and premium finishes throughout.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Luxury Penthouse Interior'
                            ],
                            'badge' => [
                                'text' => 'For Sale',
                                'color' => 'success'
                            ],
                            'price' => '£2,500,000',
                            'location' => 'Mayfair, London',
                            'features' => [
                                '🛏️ 3 bedrooms',
                                '🚿 2 bathrooms',
                                '📐 2,100 sq ft',
                                '🅿️ Parking'
                            ],
                            'actions' => [
                                [
                                    'text' => 'View Details',
                                    'url' => 'luxury-penthouse-mayfair',
                                    'style' => 'outline'
                                ],
                                [
                                    'text' => 'Enquire',
                                    'url' => '/contact?property=luxury-penthouse-mayfair',
                                    'style' => 'primary'
                                ]
                            ]
                        ],
                        [
                            'title' => 'Victorian Townhouse in Chelsea',
                            'slug' => 'victorian-townhouse-chelsea',
                            'excerpt' => 'Beautifully restored Victorian townhouse with period features and modern conveniences. Perfect family home.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Victorian Townhouse Exterior'
                            ],
                            'badge' => [
                                'text' => 'For Sale',
                                'color' => 'success'
                            ],
                            'price' => '£4,200,000',
                            'location' => 'Chelsea, London',
                            'features' => [
                                '🛏️ 5 bedrooms',
                                '🚿 3 bathrooms',
                                '📐 3,500 sq ft',
                                '🌳 Garden'
                            ],
                            'actions' => [
                                [
                                    'text' => 'View Details',
                                    'url' => 'victorian-townhouse-chelsea',
                                    'style' => 'outline'
                                ],
                                [
                                    'text' => 'Enquire',
                                    'url' => '/contact?property=victorian-townhouse-chelsea',
                                    'style' => 'primary'
                                ]
                            ]
                        ],
                        [
                            'title' => 'Modern Apartment in Canary Wharf',
                            'slug' => 'modern-apartment-canary-wharf',
                            'excerpt' => 'Contemporary 2-bedroom apartment with river views. Features gym, concierge, and rooftop terrace access.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Modern Apartment Living Room'
                            ],
                            'badge' => [
                                'text' => 'New',
                                'color' => 'primary'
                            ],
                            'price' => '£875,000',
                            'location' => 'Canary Wharf, London',
                            'features' => [
                                '🛏️ 2 bedrooms',
                                '🚿 2 bathrooms',
                                '📐 1,200 sq ft',
                                '🏢 Concierge'
                            ],
                            'actions' => [
                                [
                                    'text' => 'View Details',
                                    'url' => 'modern-apartment-canary-wharf',
                                    'style' => 'outline'
                                ],
                                [
                                    'text' => 'Enquire',
                                    'url' => '/contact?property=modern-apartment-canary-wharf',
                                    'style' => 'primary'
                                ]
                            ]
                        ],
                        [
                            'title' => 'Charming Cottage in Notting Hill',
                            'slug' => 'charming-cottage-notting-hill',
                            'excerpt' => 'Quaint 2-bedroom cottage with original features and private courtyard garden. Rare find in prime location.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Charming Cottage Exterior'
                            ],
                            'badge' => [
                                'text' => 'Sold',
                                'color' => 'danger'
                            ],
                            'price' => '£1,450,000',
                            'location' => 'Notting Hill, London',
                            'features' => [
                                '🛏️ 2 bedrooms',
                                '🚿 1 bathroom',
                                '📐 950 sq ft',
                                '🌻 Garden'
                            ],
                            'actions' => [
                                [
                                    'text' => 'View Details',
                                    'url' => 'charming-cottage-notting-hill',
                                    'style' => 'outline'
                                ],
                                [
                                    'text' => 'Similar Properties',
                                    'url' => '/properties?area=notting-hill',
                                    'style' => 'secondary'
                                ]
                            ]
                        ],
                        [
                            'title' => 'Riverside Loft in Bermondsey',
                            'slug' => 'riverside-loft-bermondsey',
                            'excerpt' => 'Spacious converted warehouse loft with exposed brick walls and Thames views. Industrial chic meets modern living.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1556020685-ae41abfc9365?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Industrial Loft Interior'
                            ],
                            'badge' => [
                                'text' => 'Under Offer',
                                'color' => 'warning'
                            ],
                            'price' => '£1,200,000',
                            'location' => 'Bermondsey, London',
                            'features' => [
                                '🛏️ 3 bedrooms',
                                '🚿 2 bathrooms',
                                '📐 1,800 sq ft',
                                '🌊 River views'
                            ],
                            'actions' => [
                                [
                                    'text' => 'View Details',
                                    'url' => 'riverside-loft-bermondsey',
                                    'style' => 'outline'
                                ],
                                [
                                    'text' => 'Join Waitlist',
                                    'url' => '/waitlist?property=riverside-loft-bermondsey',
                                    'style' => 'secondary'
                                ]
                            ]
                        ],
                        [
                            'title' => 'Garden Flat in Hampstead',
                            'slug' => 'garden-flat-hampstead',
                            'excerpt' => 'Ground floor garden flat with direct access to communal gardens. Perfect for those seeking tranquility in the city.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1571055107559-3e67626fa8be?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Garden Flat with Patio'
                            ],
                            'badge' => [
                                'text' => 'For Rent',
                                'color' => 'info'
                            ],
                            'price' => '£3,500/month',
                            'location' => 'Hampstead, London',
                            'features' => [
                                '🛏️ 2 bedrooms',
                                '🚿 1 bathroom',
                                '📐 1,100 sq ft',
                                '🏡 Private entrance'
                            ],
                            'actions' => [
                                [
                                    'text' => 'View Details',
                                    'url' => 'garden-flat-hampstead',
                                    'style' => 'outline'
                                ],
                                [
                                    'text' => 'Book Viewing',
                                    'url' => '/book-viewing?property=garden-flat-hampstead',
                                    'style' => 'primary'
                                ]
                            ]
                        ]
                    ]
                ],
                'order' => 4
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);
    }

    private function createPropertiesPage(): void
    {
        $page = Page::create([
            'title' => 'Properties',
            'page_type' => 'custom',
            'slug' => 'properties',
            'status' => 'published',
            'custom_handler' => 'App\\Controllers\\EstateWebsiteController@properties',
            'meta_title' => 'About Us - Premier Properties',
            'meta_description' => 'Learn about Premier Properties - 15 years of excellence in luxury real estate across London and the Home Counties.',
            'site_id' => 1
        ]);
    }

    private function createAboutPage(): void
    {
        $page = Page::create([
            'title' => 'About Premier Properties',
            'page_type' => 'content',
            'slug' => 'about',
            'status' => 'published',
            'meta_title' => 'About Us - Premier Properties',
            'meta_description' => 'Learn about Premier Properties - 15 years of excellence in luxury real estate across London and the Home Counties.',
            'site_id' => 1
        ]);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'About Premier Properties',
                    'subtitle' => 'Your trusted partner in luxury real estate for over 15 years',
                    'ctaText' => 'Contact Us',
                    'ctaUrl' => '/contact',
                    'showSearch' => false,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'For over 15 years, Premier Properties has been the trusted name in luxury real estate across London and the Home Counties. Our team of experienced professionals combines deep market knowledge with personalized service to deliver exceptional results for our clients.',
                        'We understand that buying or selling a property is one of life\'s most significant decisions. That\'s why we\'re committed to providing expert guidance, innovative marketing strategies, and unwavering support throughout your real estate journey.',
                        'Our success is built on relationships, trust, and an unwavering commitment to excellence. We don\'t just sell properties – we help people find their perfect homes and make smart investment decisions.'
                    ]
                ],
                'order' => 2
            ],
            [
                'type' => 'stats',
                'data' => [
                    'title' => 'Our Track Record',
                    'stats' => [
                        ['number' => '£2.5B', 'label' => 'Properties Sold', 'description' => 'Total value of properties sold'],
                        ['number' => '4.9★', 'label' => 'Client Rating', 'description' => 'Average customer satisfaction'],
                        ['number' => '15+', 'label' => 'Years Experience', 'description' => 'Established in 2009'],
                        ['number' => '50+', 'label' => 'Team Members', 'description' => 'Experienced professionals']
                    ]
                ],
                'order' => 3
            ],
            [
                'type' => 'team',
                'data' => [
                    'title' => 'Meet Our Leadership Team',
                    'subtitle' => 'Experienced professionals dedicated to your success',
                    'members' => [
                        [
                            'name' => 'James Wilson',
                            'role' => 'Managing Director',
                            'bio' => 'With over 20 years in luxury real estate, James leads our team with vision and expertise. He specializes in high-end residential sales and has personally overseen transactions worth over £500 million.',
                            'email' => 'james.wilson@premierproperties.co.uk',
                            'phone' => '+44 20 7123 4568',
                            'specialties' => ['Luxury Properties', 'Commercial Sales', 'Investment Strategy'],
                            'image' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'
                        ],
                        [
                            'name' => 'Sarah Thompson',
                            'role' => 'Senior Sales Manager',
                            'bio' => 'Sarah brings 12 years of experience in residential sales and specializes in helping first-time buyers navigate the London property market. Her patient approach and market knowledge have helped hundreds of families find their perfect homes.',
                            'email' => 'sarah.thompson@premierproperties.co.uk',
                            'phone' => '+44 20 7123 4569',
                            'specialties' => ['First-time Buyers', 'Residential Sales', 'Market Analysis'],
                            'image' => 'https://images.unsplash.com/photo-1494790108755-2616b332e234?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'
                        ],
                        [
                            'name' => 'David Clarke',
                            'role' => 'Head of Lettings',
                            'bio' => 'David oversees our lettings division and has built strong relationships with landlords and tenants across London. His expertise in property management and rental market trends is unmatched.',
                            'email' => 'david.clarke@premierproperties.co.uk',
                            'phone' => '+44 20 7123 4570',
                            'specialties' => ['Property Lettings', 'Property Management', 'Rental Market Analysis'],
                            'image' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'
                        ]
                    ],
                    'layout' => 'grid'
                ],
                'order' => 4
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);
    }

    private function createContactPage(): void
    {
        $page = Page::create([
            'title' => 'Contact Premier Properties',
            'page_type' => 'content',
            'slug' => 'contact',
            'status' => 'published',
            'meta_title' => 'Contact Us - Premier Properties',
            'meta_description' => 'Get in touch with Premier Properties. Visit our London office, call us, or send us a message. We\'re here to help with all your property needs.',
            'site_id' => 1
        ]);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Get In Touch',
                    'subtitle' => 'Ready to start your property journey? Contact our expert team today for personalized advice and exceptional service.',
                    'ctaText' => 'Call Now',
                    'ctaUrl' => 'tel:+442071234567',
                    'secondaryCtaText' => 'Email Us',
                    'secondaryCtaUrl' => 'mailto:info@premierproperties.co.uk',
                    'showSearch' => false
                ],
                'order' => 1
            ],
            [
                'type' => 'person',
                'data' => [
                    'name' => 'Premier Properties',
                    'role' => 'Contact Information',
                    'email' => 'info@premierproperties.co.uk',
                    'phone' => '+44 20 7123 4567',
                    'address' => '123 Premium Street\nLondon, SW1A 1AA\n\nOffice Hours:\nMon-Fri: 9AM-6PM\nSaturday: 10AM-4PM\nSunday: Closed',
                    'displayType' => 'contact'
                ],
                'order' => 2
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Whether you\'re looking to buy, sell, rent, or invest in property, our experienced team is here to help. We offer personalized consultations and can arrange property viewings at your convenience.',
                        'For urgent matters outside office hours, please call our emergency line at +44 7700 123456.',
                        'We typically respond to all enquiries within 2 hours during business hours.'
                    ]
                ],
                'order' => 3
            ],
            [
                'type' => 'map-location',
                'data' => [
                    'title' => 'Visit Our Office',
                    'address' => '123 Premium Street, London, SW1A 1AA',
                    'latitude' => 51.5074,
                    'longitude' => -0.1278,
                    'zoom' => 15,
                    'mapType' => 'roadmap',
                    'showMarker' => true,
                    'height' => 400,
                    'description' => 'Our central London office is easily accessible by public transport.'
                ],
                'order' => 3
            ],
            [
                'type' => 'contact-form',
                'data' => [
                    'title' => 'Send Us a Message',
                    'showName' => true,
                    'showEmail' => true,
                    'showPhone' => true,
                    'showSubject' => true,
                    'showMessage' => true,
                    'submitButtonText' => 'Send Message',
                    'requireName' => true,
                    'requireEmail' => true,
                    'requireMessage' => true
                ],
                'order' => 2
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);
    }

    private function createPropertyPages(bool $returnDataOnly = false): array
    {
        $properties = [
            [
                'title' => 'Modern Victorian Townhouse in Kensington',
                'slug' => 'riverside-loft-bermondsey',
                'price' => 750000,
                'bedrooms' => 4,
                'bathrooms' => 3,
                'sqft' => 2400,
                'area' => 'Kensington, London',
                'address' => '45 Victorian Gardens, Kensington, London',
                'postcode' => 'SW7 4DP',
                'property_type' => 'townhouse',
                'tags' => ['featured', '4-bed', '3-bath', 'townhouse', 'garden', 'parking', '2400-sqft'],
                'categories' => ['Properties', 'Residential', 'Houses'],
                'images' => [
                    'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                    'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                    'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                ],
                'description' => 'A beautifully restored Victorian townhouse featuring original period details with modern luxury amenities. This exceptional property offers spacious living across four floors with a private garden.'
            ],
            [
                'title' => 'Luxury Penthouse Suite in Mayfair',
                'slug' => 'victorian-townhouse-chelsea',
                'price' => 2800000,
                'bedrooms' => 3,
                'bathrooms' => 3,
                'sqft' => 2800,
                'area' => 'Mayfair, London',
                'address' => 'Penthouse, 1 Berkeley Square, Mayfair, London',
                'postcode' => 'W1J 6BD',
                'property_type' => 'penthouse',
                'tags' => ['featured', '3-bed', '3-bath', 'penthouse', 'luxury', 'concierge', 'terrace', '2800-sqft'],
                'categories' => ['Properties', 'Luxury', 'Penthouse'],
                'images' => [
                    'https://images.unsplash.com/photo-1600047509807-ba8f99d2cdde?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                    'https://images.unsplash.com/photo-1600566752355-35792bedcfea?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                ],
                'description' => 'An extraordinary penthouse in the heart of Mayfair offering unparalleled luxury and sophistication. Floor-to-ceiling windows provide stunning views across London\'s skyline.'
            ],
            [
                'title' => 'Contemporary Family Home in Richmond',
                'slug' => 'modern-apartment-canary-wharf',
                'price' => 1200000,
                'bedrooms' => 5,
                'bathrooms' => 4,
                'sqft' => 3200,
                'area' => 'Richmond, London',
                'address' => '78 Richmond Hill Road, Richmond, London',
                'postcode' => 'TW10 6RN',
                'property_type' => 'house',
                'tags' => ['featured', '5-bed', '4-bath', 'house', 'garden', 'garage', 'new-build', '3200-sqft'],
                'categories' => ['Properties', 'Residential', 'Houses'],
                'images' => [
                    'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                ],
                'description' => 'A stunning new-build family home in prestigious Richmond. This contemporary property features an open-plan design with high-end finishes throughout and a beautiful landscaped garden.'
            ],
            [
                'title' => 'Charming Garden Flat in Hampstead',
                'slug' => 'charming-cottage-notting-hill',
                'price' => 450000,
                'bedrooms' => 2,
                'bathrooms' => 1,
                'sqft' => 950,
                'area' => 'Hampstead, London',
                'address' => 'Garden Flat, 23 Hampstead Lane, London',
                'postcode' => 'NW3 2RA',
                'property_type' => 'flat',
                'tags' => ['2-bed', '1-bath', 'flat', 'garden', 'chain-free', '950-sqft'],
                'categories' => ['Properties', 'Residential', 'Apartments'],
                'images' => [
                    'https://images.unsplash.com/photo-1600607687644-aac4c3eac7f4?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                ],
                'description' => 'A delightful garden flat in the heart of Hampstead village. This charming property offers peaceful living with direct access to a private garden and is chain-free for a quick sale.'
            ],
            [
                'title' => 'Riverside Conversion in Greenwich',
                'slug' => 'garden-flat-hampstead',
                'price' => 680000,
                'bedrooms' => 3,
                'bathrooms' => 2,
                'sqft' => 1800,
                'area' => 'Greenwich, London',
                'address' => 'Apartment 15, Riverside Wharf, Greenwich, London',
                'postcode' => 'SE10 9PH',
                'property_type' => 'apartment',
                'tags' => ['3-bed', '2-bath', 'apartment', 'balcony', 'river-view', 'parking', '1800-sqft'],
                'categories' => ['Properties', 'Residential', 'Apartments'],
                'images' => [
                    'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                ],
                'description' => 'A beautifully converted riverside apartment with stunning Thames views. This spacious three-bedroom property features a large balcony and comes with secure underground parking.'
            ],
            [
                'title' => 'Georgian Townhouse in Marylebone',
                'slug' => 'luxury-penthouse-mayfair',
                'price' => 1800000,
                'bedrooms' => 4,
                'bathrooms' => 3,
                'sqft' => 2600,
                'area' => 'Marylebone, London',
                'address' => '12 Wimpole Street, Marylebone, London',
                'postcode' => 'W1G 9ST',
                'property_type' => 'townhouse',
                'tags' => ['4-bed', '3-bath', 'townhouse', 'period-property', 'central-london', '2600-sqft'],
                'categories' => ['Properties', 'Residential', 'Houses'],
                'images' => [
                    'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                ],
                'description' => 'An elegant Georgian townhouse in the heart of Marylebone. This historic property has been sympathetically restored while retaining its original character and period features.'
            ]
        ];

        if($returnDataOnly) {
            return $properties;
        }

        foreach ($properties as $propertyData) {
            $this->createPropertyPage($propertyData);
        }

        return $properties;
    }

    private function createPropertyPage(array $data): void
    {
        $page = Page::create([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'status' => 'published',
            'meta_title' => $data['title'] . ' - Premier Properties',
            'meta_description' => $data['description'],
            'page_type' => 'content',
            'site_id' => 1
        ]);

        // Add tags
        foreach ($data['tags'] as $tagName) {
            $tag = $this->tagRepository->findOrCreateByName($tagName, 1);
            $page->tags(true)->attach($tag->id);
        }

        // Add categories
        foreach ($data['categories'] as $categoryName) {
            $category = $this->categoryRepository->findOrCreateByName($categoryName, 1);
            $page->categories(true)->attach($category->id);
        }

        // Add custom fields
        $customFields = [
            'price' => $data['price'],
            'bedrooms' => $data['bedrooms'],
            'bathrooms' => $data['bathrooms'],
            'square_feet' => $data['sqft'],
            'property_type' => $data['property_type'],
            'address' => $data['address'],
            'area' => $data['area'],
            'postcode' => $data['postcode'],
            'agent_name' => 'Sarah Thompson',
            'agent_email' => 'sarah.thompson@premierproperties.co.uk',
            'agent_phone' => '+44 20 7123 4569'
        ];

        foreach ($customFields as $key => $value) {
            $fieldDef = CustomFieldDefinition::where('key', $key)->first();
            if ($fieldDef) {
                PageCustomField::create([
                    'custom_field_definition_id' => $fieldDef->id,
                    'field_value' => (string)$value,
                    'page_id' => $page->id
                ]);
            }
        }

        // Create blocks for property page
        $blocks = [
            [
                'type' => 'gallery',
                'data' => [
                    'layout' => 'carousel',
                    'slides' => array_map(function ($imageUrl, $index) use ($data) {
                        return [
                            'title' => $data['title'] . ' - Image ' . ($index + 1),
                            'image' => $imageUrl,
                            'alt' => $data['title'] . ' interior view ' . ($index + 1),
                            'caption' => '',
                            'description' => ''
                        ];
                    }, $data['images'], array_keys($data['images']))
                ],
                'order' => 1
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        $data['description'],
                        'This exceptional property represents the perfect blend of luxury and comfort. Every detail has been carefully considered to create a home that exceeds expectations.',
                        'The location offers excellent transport links and is within walking distance of shops, restaurants, and local amenities.'
                    ]
                ],
                'order' => 2
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Property Features',
                    'level' => 2
                ],
                'order' => 3
            ],
            [
                'type' => 'list',
                'data' => [
                    'listType' => 'ul',
                    'items' => $this->generatePropertyFeatures($data)
                ],
                'order' => 4
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Location & Transport',
                    'level' => 2
                ],
                'order' => 5
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Situated in ' . $data['area'] . ', this property benefits from excellent transport connections throughout London.',
                        'The area is renowned for its vibrant community, excellent schools, and diverse dining and shopping options.',
                        'Perfect for both families and professionals seeking a prime London location.'
                    ]
                ],
                'order' => 6
            ],
            [
                'type' => 'quote',
                'data' => [
                    'text' => 'This is an exceptional property in a highly sought-after location. Properties of this caliber rarely come to market.',
                    'attribution' => 'Sarah Thompson, Senior Sales Manager'
                ],
                'order' => 7
            ],
            [
                'type' => 'agent-profile',
                'data' => [
                    'context' => 'sidebar',
                    'name' => 'Sarah Thompson',
                    'title' => 'Senior Sales Manager',
                    'bio' => 'Sarah brings 12 years of experience in residential sales and specializes in helping first-time buyers navigate the London property market.',
                    'email' => 'sarah.thompson@premierproperties.co.uk',
                    'phone' => '+44 20 7123 4569',
                    'profileImageUrl' => 'https://images.unsplash.com/photo-1494790108755-2616b332e234?auto=format&fit=crop&w=400&q=80',
                    'license' => 'RICS Qualified',
                    'experience' => '12+ years',
                    'specialties' => 'First-time buyers, Residential sales, Market analysis',
                    'socialMedia' => [
                        'linkedin' => 'https://linkedin.com/in/sarah-thompson'
                    ]
                ],
                'order' => 8
            ],
            [
                'type' => 'contact-form',
                'data' => [
                    'title' => 'Interested in this property?',
                    'subtitle' => '',
                    'showName' => true,
                    'showEmail' => true,
                    'showPhone' => true,
                    'showSubject' => false,
                    'showMessage' => true,
                    'showPropertyInterest' => true,
                    'submitButtonText' => 'Send Enquiry',
                    'requireName' => true,
                    'requireEmail' => true,
                    'requireMessage' => true
                ],
                'order' => 9
            ]
        ];


        $this->createBlocksForPage($page->id, $blocks);
    }

    private function generatePropertyFeatures(array $data): array
    {
        $features = [
            $data['bedrooms'] . ' spacious bedrooms',
            $data['bathrooms'] . ' modern bathrooms',
            $data['sqft'] . ' square feet of living space',
            'Prime ' . $data['area'] . ' location'
        ];

        // Add additional features based on tags
        $tagFeatures = [
            'garden' => 'Private garden',
            'garage' => 'Garage parking',
            'parking' => 'Parking space',
            'balcony' => 'Private balcony',
            'terrace' => 'Roof terrace',
            'concierge' => '24-hour concierge',
            'gym' => 'Residents gym',
            'pool' => 'Swimming pool',
            'new-build' => 'New build property',
            'period-property' => 'Period features',
            'chain-free' => 'Chain free sale'
        ];

        foreach ($data['tags'] as $tag) {
            if (isset($tagFeatures[$tag])) {
                $features[] = $tagFeatures[$tag];
            }
        }

        return array_unique($features);
    }

    private function createBlocksForPage(int $pageId, array $blocks): void
    {
        foreach ($blocks as $blockData) {
            $this->blockRepository->create([
                'page_id' => $pageId,
                'type' => $blockData['type'],
                'data' => json_encode($blockData['data']),
                'order' => $blockData['order']
            ]);
        }
    }

    private function generateRelatedPageBlocks()
    {
        $blocks = [
            [
                'type' => 'page_grid',
                'data' => [
                    'title' => 'Related Properties',
                    'subtitle' => 'Discover exceptional properties with Premier Properties. From luxury estates to cozy starter homes.',
                    'layout' => 'grid',
                    'columns' => 3,
                    'showExcerpt' => true,
                    'showImage' => true,
                    'showFeatures' => true,
                    'showActions' => true,
                    'pages' => [
                        [
                            'title' => 'Luxury Penthouse in Mayfair',
                            'slug' => 'luxury-penthouse-mayfair',
                            'excerpt' => 'Stunning 3-bedroom penthouse with panoramic views of London. Features modern amenities and premium finishes throughout.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Luxury Penthouse Interior'
                            ],
                            'badge' => [
                                'text' => 'For Sale',
                                'color' => 'success'
                            ],
                            'price' => '£2,500,000',
                            'location' => 'Mayfair, London',
                            'features' => [
                                '🛏️ 3 bedrooms',
                                '🚿 2 bathrooms',
                                '📐 2,100 sq ft',
                                '🅿️ Parking'
                            ],
                            'actions' => [
                                [
                                    'text' => 'View Details',
                                    'url' => 'luxury-penthouse-mayfair',
                                    'style' => 'outline'
                                ],
                                [
                                    'text' => 'Enquire',
                                    'url' => '/contact?property=luxury-penthouse-mayfair',
                                    'style' => 'primary'
                                ]
                            ]
                        ],
                        [
                            'title' => 'Victorian Townhouse in Chelsea',
                            'slug' => 'victorian-townhouse-chelsea',
                            'excerpt' => 'Beautifully restored Victorian townhouse with period features and modern conveniences. Perfect family home.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Victorian Townhouse Exterior'
                            ],
                            'badge' => [
                                'text' => 'For Sale',
                                'color' => 'success'
                            ],
                            'price' => '£4,200,000',
                            'location' => 'Chelsea, London',
                            'features' => [
                                '🛏️ 5 bedrooms',
                                '🚿 3 bathrooms',
                                '📐 3,500 sq ft',
                                '🌳 Garden'
                            ],
                            'actions' => [
                                [
                                    'text' => 'View Details',
                                    'url' => 'victorian-townhouse-chelsea',
                                    'style' => 'outline'
                                ],
                                [
                                    'text' => 'Enquire',
                                    'url' => '/contact?property=victorian-townhouse-chelsea',
                                    'style' => 'primary'
                                ]
                            ]
                        ],
                        [
                            'title' => 'Modern Apartment in Canary Wharf',
                            'slug' => 'modern-apartment-canary-wharf',
                            'excerpt' => 'Contemporary 2-bedroom apartment with river views. Features gym, concierge, and rooftop terrace access.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Modern Apartment Living Room'
                            ],
                            'badge' => [
                                'text' => 'New',
                                'color' => 'primary'
                            ],
                            'price' => '£875,000',
                            'location' => 'Canary Wharf, London',
                            'features' => [
                                '🛏️ 2 bedrooms',
                                '🚿 2 bathrooms',
                                '📐 1,200 sq ft',
                                '🏢 Concierge'
                            ],
                            'actions' => [
                                [
                                    'text' => 'View Details',
                                    'url' => 'modern-apartment-canary-wharf',
                                    'style' => 'outline'
                                ],
                                [
                                    'text' => 'Enquire',
                                    'url' => '/contact?property=modern-apartment-canary-wharf',
                                    'style' => 'primary'
                                ]
                            ]
                        ],
                        [
                            'title' => 'Charming Cottage in Notting Hill',
                            'slug' => 'charming-cottage-notting-hill',
                            'excerpt' => 'Quaint 2-bedroom cottage with original features and private courtyard garden. Rare find in prime location.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Charming Cottage Exterior'
                            ],
                            'badge' => [
                                'text' => 'Sold',
                                'color' => 'danger'
                            ],
                            'price' => '£1,450,000',
                            'location' => 'Notting Hill, London',
                            'features' => [
                                '🛏️ 2 bedrooms',
                                '🚿 1 bathroom',
                                '📐 950 sq ft',
                                '🌻 Garden'
                            ],
                            'actions' => [
                                [
                                    'text' => 'View Details',
                                    'url' => 'charming-cottage-notting-hill',
                                    'style' => 'outline'
                                ],
                                [
                                    'text' => 'Similar Properties',
                                    'url' => '/properties?area=notting-hill',
                                    'style' => 'secondary'
                                ]
                            ]
                        ],
                        [
                            'title' => 'Riverside Loft in Bermondsey',
                            'slug' => 'riverside-loft-bermondsey',
                            'excerpt' => 'Spacious converted warehouse loft with exposed brick walls and Thames views. Industrial chic meets modern living.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1556020685-ae41abfc9365?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Industrial Loft Interior'
                            ],
                            'badge' => [
                                'text' => 'Under Offer',
                                'color' => 'warning'
                            ],
                            'price' => '£1,200,000',
                            'location' => 'Bermondsey, London',
                            'features' => [
                                '🛏️ 3 bedrooms',
                                '🚿 2 bathrooms',
                                '📐 1,800 sq ft',
                                '🌊 River views'
                            ],
                            'actions' => [
                                [
                                    'text' => 'View Details',
                                    'url' => 'riverside-loft-bermondsey',
                                    'style' => 'outline'
                                ],
                                [
                                    'text' => 'Join Waitlist',
                                    'url' => '/waitlist?property=riverside-loft-bermondsey',
                                    'style' => 'secondary'
                                ]
                            ]
                        ],
                        [
                            'title' => 'Garden Flat in Hampstead',
                            'slug' => 'garden-flat-hampstead',
                            'excerpt' => 'Ground floor garden flat with direct access to communal gardens. Perfect for those seeking tranquility in the city.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1571055107559-3e67626fa8be?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Garden Flat with Patio'
                            ],
                            'badge' => [
                                'text' => 'For Rent',
                                'color' => 'info'
                            ],
                            'price' => '£3,500/month',
                            'location' => 'Hampstead, London',
                            'features' => [
                                '🛏️ 2 bedrooms',
                                '🚿 1 bathroom',
                                '📐 1,100 sq ft',
                                '🏡 Private entrance'
                            ],
                            'actions' => [
                                [
                                    'text' => 'View Details',
                                    'url' => 'garden-flat-hampstead',
                                    'style' => 'outline'
                                ],
                                [
                                    'text' => 'Book Viewing',
                                    'url' => '/book-viewing?property=garden-flat-hampstead',
                                    'style' => 'primary'
                                ]
                            ]
                        ]
                    ]
                ],
                'order' => 10
            ]
        ];

        $properties = $this->createPropertyPages(true);

        foreach ($properties as $property) {
            $page = $this->pageRepository->findBySlug($property['slug']);
            $this->createBlocksForPage($page->id, $blocks);
        }
    }
}