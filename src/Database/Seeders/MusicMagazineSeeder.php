<?php

namespace App\Database\Seeders;

use App\Framework\Container;
use App\Framework\Database\Seeder\Seeder;
use App\Models\CustomFieldDefinition;
use App\Models\Menu;
use App\Models\Page;
use App\Models\PageCustomField;
use App\Models\Site;
use App\Repositories\Cms\BlockRepository;
use App\Repositories\Cms\CategoryRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\TagRepository;
use App\Services\Cms\Pages\BlockParserService;

class MusicMagazineSeeder extends Seeder
{
    private $pageRepository;
    private $blockRepository;
    private $tagRepository;
    private $categoryRepository;
    private $blockParserService;
    private \App\Models\Model $site;
    private \App\Models\Model $menu;

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
        $this->createSite();
        $this->createMenu();
        $this->createTags();
        $this->createCategories();
        $this->createCustomFields();
        $this->createHomepage();
        $this->createAboutPage();
        $this->createContactPage();
        $this->createArticles();
    }

    private function createSite(): void
    {
        $this->site = Site::create([
            'name' => 'SOUNDWAVE',
            'slug' => 'soundwave',
            'is_active' => true,
        ]);
    }

    private function createMenu(): void
    {
        $this->menu = Menu::create([
            'name' => 'Main Menu',
            'slug' => 'main-menu',
            'site_id' => $this->site->id,
            'is_active' => true,
        ]);
    }

    private function createTags(): void
    {
        $tags = [
            // Genres
            'rock', 'pop', 'hip-hop', 'electronic', 'indie', 'jazz', 'metal',
            'punk', 'r&b', 'country', 'classical', 'reggae', 'blues', 'folk',

            // Content types
            'featured', 'interview', 'review', 'news', 'live-report',
            'album-review', 'single-review', 'concert-review',

            // Topics
            'new-release', 'comeback', 'debut', 'chart-topper', 'underground',
            'festival', 'tour', 'breakthrough-artist', 'legend', 'trending'
        ];

        foreach ($tags as $tagName) {
            $this->tagRepository->findOrCreateByName($tagName, $this->site->id);
        }
    }

    private function createCategories(): void
    {
        $categories = [
            'Features' => ['Interviews', 'Cover Stories', 'In-Depth', 'Profiles'],
            'Reviews' => ['Albums', 'Singles', 'EPs', 'Live Shows', 'Festivals'],
            'News' => ['Breaking', 'Industry', 'Tours', 'Releases'],
            'Genres' => [
                'Rock' => ['Classic Rock', 'Alternative', 'Indie Rock'],
                'Electronic' => ['House', 'Techno', 'Ambient'],
                'Urban' => ['Hip-Hop', 'R&B', 'Trap']
            ],
            'Culture' => ['Fashion', 'Art', 'Film', 'Books']
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
            ['key' => 'artist_name', 'name' => 'Artist/Band Name', 'type' => 'text'],
            ['key' => 'album_title', 'name' => 'Album Title', 'type' => 'text'],
            ['key' => 'release_date', 'name' => 'Release Date', 'type' => 'date'],
            ['key' => 'record_label', 'name' => 'Record Label', 'type' => 'text'],
            ['key' => 'rating', 'name' => 'Rating', 'type' => 'number'],
            ['key' => 'genre', 'name' => 'Genre', 'type' => 'text'],
            ['key' => 'author', 'name' => 'Article Author', 'type' => 'text'],
            ['key' => 'photographer', 'name' => 'Photographer', 'type' => 'text'],
            ['key' => 'venue', 'name' => 'Venue', 'type' => 'text'],
            ['key' => 'event_date', 'name' => 'Event Date', 'type' => 'date'],
            ['key' => 'spotify_link', 'name' => 'Spotify Link', 'type' => 'url'],
            ['key' => 'apple_music_link', 'name' => 'Apple Music Link', 'type' => 'url'],
            ['key' => 'youtube_link', 'name' => 'YouTube Link', 'type' => 'url']
        ];

        foreach ($fields as $field) {
            CustomFieldDefinition::create([
                'key' => $field['key'],
                'name' => $field['name'],
                'type' => $field['type'],
                'is_active' => true,
                'sort_order' => 10,
                'site_id' => $this->site->id
            ]);
        }
    }

    private function createHomepage(): void
    {
        $page = Page::create([
            'title' => 'SOUNDWAVE - Music Magazine',
            'page_type' => 'content',
            'slug' => 'home',
            'status' => 'published',
            'meta_title' => 'SOUNDWAVE - The Pulse of Music Culture',
            'meta_description' => 'Discover the latest in music news, album reviews, artist interviews, and live concert coverage. Your guide to everything music.',
            'site_id' => $this->site->id
        ]);

        $featuredTag = $this->tagRepository->findOrCreateByName('featured', 1);
        $page->tags(true)->attach($featuredTag->id);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'THE PULSE OF MUSIC CULTURE',
                    'subtitle' => 'Exclusive interviews, in-depth reviews, and breaking news from the world of music',
                    'ctaText' => 'Explore Latest',
                    'ctaUrl' => '#featured',
                    'secondaryCtaText' => 'Subscribe',
                    'secondaryCtaUrl' => '/subscribe',
                    'showSearch' => false,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Featured Stories',
                    'subtitle' => 'This Week\'s Must-Read Articles',
                    'level' => 2
                ],
                'order' => 2
            ],
            [
                'type' => 'page_grid',
                'data' => [
                    'title' => '',
                    'subtitle' => '',
                    'layout' => 'masonry',
                    'columns' => 3,
                    'showExcerpt' => true,
                    'showImage' => true,
                    'showFeatures' => true,
                    'showActions' => true,
                    'pages' => [
                        [
                            'title' => 'The Phoenix Rising: How Luna Eclipse Conquered Their Demons',
                            'slug' => 'luna-eclipse-cover-story',
                            'excerpt' => 'After a three-year hiatus, Luna Eclipse returns with their most vulnerable album yet. We sit down with the band to discuss addiction, recovery, and their triumphant comeback.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Luna Eclipse band photo'
                            ],
                            'badge' => [
                                'text' => 'Cover Story',
                                'color' => 'danger'
                            ],
                            'features' => [
                                '📅 December 2024',
                                '✍️ By Sarah Chen',
                                '🎸 Rock',
                                '⏱️ 15 min read'
                            ],
                            'actions' => [
                                [
                                    'text' => 'Read Full Story',
                                    'url' => 'luna-eclipse-cover-story',
                                    'style' => 'primary'
                                ]
                            ]
                        ],
                        [
                            'title' => 'Album Review: "Neon Dreams" by SYNTHWAVE',
                            'slug' => 'synthwave-neon-dreams-review',
                            'excerpt' => 'The electronic duo delivers a stunning exploration of 80s nostalgia meets modern production. This is synthpop perfection.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1514320291840-2e0a9bf2a9ae?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Neon Dreams album cover'
                            ],
                            'badge' => [
                                'text' => '★★★★★ 5/5',
                                'color' => 'warning'
                            ],
                            'features' => [
                                '💿 Album Review',
                                '🎹 Electronic',
                                '✍️ By Marcus Webb',
                                '⏱️ 8 min read'
                            ],
                            'actions' => [
                                [
                                    'text' => 'Read Review',
                                    'url' => 'synthwave-neon-dreams-review',
                                    'style' => 'outline'
                                ]
                            ]
                        ],
                        [
                            'title' => 'Coachella 2025: Everything You Need to Know',
                            'slug' => 'coachella-2025-guide',
                            'excerpt' => 'From headliners to hidden gems, we break down the most anticipated festival lineup of the year. Plus: survival tips from veteran festival-goers.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Festival crowd at sunset'
                            ],
                            'badge' => [
                                'text' => 'Festival Guide',
                                'color' => 'success'
                            ],
                            'features' => [
                                '🎪 Festival',
                                '📅 April 2025',
                                '✍️ By Jake Morrison',
                                '⏱️ 12 min read'
                            ],
                            'actions' => [
                                [
                                    'text' => 'Read Guide',
                                    'url' => 'coachella-2025-guide',
                                    'style' => 'outline'
                                ]
                            ]
                        ],
                        [
                            'title' => 'Rising Star: Meet Zara Quinn, Hip-Hop\'s New Voice',
                            'slug' => 'zara-quinn-interview',
                            'excerpt' => 'At just 22, Zara Quinn is redefining what it means to be a female rapper. We chat about her debut mixtape, industry challenges, and viral TikTok success.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1598387993441-a364f854c3e1?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Zara Quinn portrait'
                            ],
                            'badge' => [
                                'text' => 'Interview',
                                'color' => 'info'
                            ],
                            'features' => [
                                '🎤 Hip-Hop',
                                '🌟 Breakthrough Artist',
                                '✍️ By Keisha Williams',
                                '⏱️ 10 min read'
                            ],
                            'actions' => [
                                [
                                    'text' => 'Read Interview',
                                    'url' => 'zara-quinn-interview',
                                    'style' => 'outline'
                                ]
                            ]
                        ],
                        [
                            'title' => 'The Vinyl Renaissance: Why Physical Media Is Back',
                            'slug' => 'vinyl-renaissance-feature',
                            'excerpt' => 'Sales are up 300% in five years. We explore why collectors and Gen-Z alike are falling in love with records again.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1603048588665-791ca8aea617?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Vinyl records collection'
                            ],
                            'badge' => [
                                'text' => 'Culture',
                                'color' => 'secondary'
                            ],
                            'features' => [
                                '💿 Industry',
                                '📊 Analysis',
                                '✍️ By Tom Richardson',
                                '⏱️ 9 min read'
                            ],
                            'actions' => [
                                [
                                    'text' => 'Read Feature',
                                    'url' => 'vinyl-renaissance-feature',
                                    'style' => 'outline'
                                ]
                            ]
                        ],
                        [
                            'title' => 'Live Report: Arctic Monkeys at Madison Square Garden',
                            'slug' => 'arctic-monkeys-msg-review',
                            'excerpt' => 'The Sheffield legends prove they\'re still at the top of their game with a breathtaking two-hour set packed with hits and deep cuts.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1540039155733-5bb30b53aa14?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Concert stage lights'
                            ],
                            'badge' => [
                                'text' => 'Live Review',
                                'color' => 'primary'
                            ],
                            'features' => [
                                '🎸 Rock',
                                '📍 NYC',
                                '✍️ By Alex Turner (no relation)',
                                '⏱️ 7 min read'
                            ],
                            'actions' => [
                                [
                                    'text' => 'Read Review',
                                    'url' => 'arctic-monkeys-msg-review',
                                    'style' => 'outline'
                                ]
                            ]
                        ]
                    ]
                ],
                'order' => 3
            ],
            [
                'type' => 'divider',
                'data' => [
                    'style' => 'solid'
                ],
                'order' => 4
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Latest News',
                    'subtitle' => 'Stay updated with breaking music news',
                    'level' => 2
                ],
                'order' => 5
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        '🔥 **JUST IN**: Taylor Swift announces surprise acoustic album dropping midnight tonight',
                        '🎸 **CONFIRMED**: Foo Fighters to headline Glastonbury 2025',
                        '💿 **NEW RELEASE**: The Weeknd drops visual album "After Hours Deluxe"',
                        '🎤 **EXCLUSIVE**: Billie Eilish talks upcoming world tour in new interview'
                    ]
                ],
                'order' => 6
            ],
            [
                'type' => 'stats',
                'data' => [
                    'title' => 'SOUNDWAVE By The Numbers',
                    'stats' => [
                        ['number' => '2M+', 'label' => 'Monthly Readers', 'icon' => '👥'],
                        ['number' => '1000+', 'label' => 'Artist Interviews', 'icon' => '🎤'],
                        ['number' => '5000+', 'label' => 'Album Reviews', 'icon' => '💿'],
                        ['number' => '15', 'label' => 'Years Publishing', 'icon' => '🎵']
                    ],
                    'layout' => 'grid'
                ],
                'order' => 7
            ],
            [
                'type' => 'testimonial',
                'data' => [
                    'testimonials' => [
                        [
                            'text' => 'SOUNDWAVE has been my go-to music magazine for years. Their reviews are honest, their interviews are deep, and they always discover the best new artists before anyone else.',
                            'author' => 'David Bowie',
                            'role' => 'Music Producer',
                            'rating' => 5,
                            'image' => null
                        ],
                        [
                            'text' => 'The quality of writing is exceptional. SOUNDWAVE treats music journalism as an art form, and it shows in every article.',
                            'author' => 'Maya Rodriguez',
                            'role' => 'Record Label Executive',
                            'rating' => 5,
                            'image' => null
                        ],
                        [
                            'text' => 'As an artist, being featured in SOUNDWAVE is a career milestone. They really understand music and care about the stories behind it.',
                            'author' => 'James Chen',
                            'role' => 'Independent Artist',
                            'rating' => 5,
                            'image' => null
                        ]
                    ],
                    'layout' => 'carousel'
                ],
                'order' => 8
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);
    }

    private function createAboutPage(): void
    {
        $page = Page::create([
            'title' => 'About SOUNDWAVE',
            'page_type' => 'content',
            'slug' => 'about',
            'status' => 'published',
            'meta_title' => 'About Us - SOUNDWAVE Music Magazine',
            'meta_description' => 'Learn about SOUNDWAVE - 15 years of music journalism excellence, bringing you the best in reviews, interviews, and music culture.',
            'site_id' => $this->site->id
        ]);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'About SOUNDWAVE',
                    'subtitle' => 'Where Music Journalism Meets Passion',
                    'ctaText' => 'Meet The Team',
                    'ctaUrl' => '#team',
                    'showSearch' => false,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1511379938547-c1f69419868d?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Founded in 2009, SOUNDWAVE has been at the forefront of music journalism for over 15 years. We\'re more than just a magazine—we\'re a community of music lovers, critics, and storytellers dedicated to celebrating the art form that moves us all.',
                        'From garage bands to stadium superstars, from vinyl collectors to streaming enthusiasts, we cover every corner of the music world with depth, integrity, and passion. Our mission is simple: to tell the stories that matter and discover the sounds that will define tomorrow.',
                        'With a team of experienced writers, photographers, and editors, we bring you exclusive interviews, honest reviews, and insightful features that go beyond the surface. We believe music is more than entertainment—it\'s culture, it\'s history, it\'s life itself.'
                    ]
                ],
                'order' => 2
            ],
            [
                'type' => 'quote',
                'data' => [
                    'text' => 'Music gives a soul to the universe, wings to the mind, flight to the imagination, and life to everything.',
                    'attribution' => 'Plato'
                ],
                'order' => 3
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'What We Do',
                    'level' => 2
                ],
                'order' => 4
            ],
            [
                'type' => 'list',
                'data' => [
                    'listType' => 'ul',
                    'items' => [
                        '🎤 In-depth artist interviews with emerging and established musicians',
                        '💿 Comprehensive album, EP, and single reviews across all genres',
                        '🎸 Live concert and festival coverage from around the world',
                        '📰 Breaking news and industry analysis',
                        '🎵 Curated playlists and music discovery features',
                        '📸 Stunning photography and visual storytelling',
                        '🎧 Podcast episodes featuring artist conversations and music discussions',
                        '🌍 Global perspective on music culture and trends'
                    ]
                ],
                'order' => 5
            ],
            [
                'type' => 'team',
                'data' => [
                    'title' => 'Meet The Team',
                    'subtitle' => 'The passionate voices behind SOUNDWAVE',
                    'members' => [
                        [
                            'name' => 'Sarah Chen',
                            'role' => 'Editor-in-Chief',
                            'bio' => 'With 20 years in music journalism, Sarah has interviewed everyone from indie darlings to rock legends. Her passion for discovering new talent and telling authentic stories drives SOUNDWAVE\'s editorial vision.',
                            'email' => 'sarah@soundwavemag.com',
                            'specialties' => ['Rock', 'Indie', 'Feature Writing'],
                            'image' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'
                        ],
                        [
                            'name' => 'Marcus Webb',
                            'role' => 'Senior Music Critic',
                            'bio' => 'Marcus brings a discerning ear and sharp pen to every review. His background in music production gives him unique insight into the technical and artistic elements of recorded music.',
                            'email' => 'marcus@soundwavemag.com',
                            'specialties' => ['Album Reviews', 'Electronic', 'Production Analysis'],
                            'image' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'
                        ],
                        [
                            'name' => 'Keisha Williams',
                            'role' => 'Hip-Hop & R&B Editor',
                            'bio' => 'Keisha is a leading voice in urban music journalism. Her work has appeared in major publications, and she\'s known for championing underground artists and giving voice to diverse perspectives.',
                            'email' => 'keisha@soundwavemag.com',
                            'specialties' => ['Hip-Hop', 'R&B', 'Culture Writing'],
                            'image' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'
                        ],
                        [
                            'name' => 'Jake Morrison',
                            'role' => 'Live Music Editor',
                            'bio' => 'Jake has covered hundreds of concerts and festivals worldwide. His energetic writing captures the magic of live performance and helps readers feel like they were there.',
                            'email' => 'jake@soundwavemag.com',
                            'specialties' => ['Live Reviews', 'Festivals', 'On-Location Reporting'],
                            'image' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'
                        ]
                    ],
                    'layout' => 'grid'
                ],
                'order' => 6
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);
    }

    private function createContactPage(): void
    {
        $page = Page::create([
            'title' => 'Contact SOUNDWAVE',
            'page_type' => 'content',
            'slug' => 'contact',
            'status' => 'published',
            'meta_title' => 'Contact Us - SOUNDWAVE',
            'meta_description' => 'Get in touch with the SOUNDWAVE team. Send us your music, story ideas, or just say hello.',
            'site_id' => $this->site->id
        ]);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Get In Touch',
                    'subtitle' => 'Got a story tip? Want to submit your music? We\'d love to hear from you.',
                    'ctaText' => 'Email Us',
                    'ctaUrl' => 'mailto:hello@soundwavemag.com',
                    'showSearch' => false,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1487180144351-b8472da7d491?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        '**For editorial inquiries:** editorial@soundwavemag.com',
                        '**To submit your music:** submissions@soundwavemag.com',
                        '**For advertising:** advertising@soundwavemag.com',
                        '**Press inquiries:** press@soundwavemag.com',
                        '',
                        'We aim to respond to all inquiries within 48 hours. For urgent matters, please include "URGENT" in your subject line.'
                    ]
                ],
                'order' => 2
            ],
            [
                'type' => 'note',
                'data' => [
                    'title' => 'Submission Guidelines',
                    'paragraphs' => [
                        'Artists: Please include streaming links (Spotify, Apple Music, Bandcamp) rather than MP3 attachments.',
                        'Press releases: Keep it concise—we\'re more likely to read and respond to brief, well-written pitches.',
                        'Story ideas: Tell us why this story matters and why you\'re the right person to write it.',
                        'We receive hundreds of submissions weekly, so we can\'t respond to everything, but we read them all!'
                    ],
                    'alignment' => 'fullscreen'
                ],
                'order' => 3
            ],
            [
                'type' => 'contact-form',
                'data' => [
                    'title' => 'Send Us a Message',
                    'subtitle' => 'Fill out the form below and we\'ll get back to you soon',
                    'showName' => true,
                    'showEmail' => true,
                    'showPhone' => false,
                    'showSubject' => true,
                    'showMessage' => true,
                    'submitButtonText' => 'Send Message',
                    'requireName' => true,
                    'requireEmail' => true,
                    'requireMessage' => true
                ],
                'order' => 4
            ],
            [
                'type' => 'person',
                'data' => [
                    'name' => 'SOUNDWAVE Magazine',
                    'role' => 'Editorial Office',
                    'email' => 'hello@soundwavemag.com',
                    'phone' => '+1 (555) 123-4567',
                    'address' => '101 Music Row\nNashville, TN 37203\nUnited States',
                    'displayType' => 'contact',
                    'twitter' => '@soundwavemag',
                    'instagram' => '@soundwavemag',
                    'facebook' => 'soundwavemagazine'
                ],
                'order' => 5
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);
    }

    private function createArticles(): void
    {
        $articles = [
            [
                'title' => 'The Phoenix Rising: How Luna Eclipse Conquered Their Demons',
                'slug' => 'luna-eclipse-cover-story',
                'artist' => 'Luna Eclipse',
                'genre' => 'Rock',
                'author' => 'Sarah Chen',
                'rating' => null,
                'tags' => ['featured', 'interview', 'rock', 'comeback'],
                'categories' => ['Features', 'Interviews'],
                'excerpt' => 'After a three-year hiatus, Luna Eclipse returns with their most vulnerable album yet.',
                'hero_image' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                'content' => [
                    'It\'s a grey Tuesday afternoon in Los Angeles, and I\'m sitting across from the four members of Luna Eclipse in their rehearsal space. Three years ago, this room was where they wrote their breakthrough album "Midnight Garden." Today, it\'s where they\'re rebuilding from the ground up.',
                    'Lead singer Alex Storm looks different than the last time I saw him—healthier, more present, but with the weight of experience in his eyes. "We hit rock bottom," he says simply. "The success of \'Midnight Garden\' should have been the best thing that ever happened to us. Instead, it nearly destroyed us."',
                    'The story of Luna Eclipse\'s fall and rise is one of rock and roll\'s oldest narratives: success, excess, collapse, recovery. But what makes their story different is how willing they are to talk about it—openly, honestly, and without the typical rock star bravado.'
                ]
            ],
            [
                'title' => 'Album Review: "Neon Dreams" by SYNTHWAVE',
                'slug' => 'synthwave-neon-dreams-review',
                'artist' => 'SYNTHWAVE',
                'genre' => 'Electronic',
                'author' => 'Marcus Webb',
                'rating' => 5,
                'tags' => ['featured', 'review', 'album-review', 'electronic', 'new-release'],
                'categories' => ['Reviews', 'Albums'],
                'excerpt' => 'The electronic duo delivers a stunning exploration of 80s nostalgia meets modern production.',
                'hero_image' => 'https://images.unsplash.com/photo-1514320291840-2e0a9bf2a9ae?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                'content' => [
                    'SYNTHWAVE has done something remarkable with "Neon Dreams"—they\'ve captured the essence of 1980s synthpop while creating something that feels completely contemporary. This is no mere nostalgia trip; it\'s a masterclass in electronic production.',
                    'From the opening notes of "Electric Nights," it\'s clear that producers Maya and Leo have spent countless hours perfecting every sound. The synth patches are lush and warm, the drum programming is tight yet organic, and the production shimmers with a clarity that would have been impossible in the analog era they\'re paying homage to.',
                    'Standout track "Sunset Boulevard" perfectly encapsulates the album\'s aesthetic—a driving bassline anchors ethereal synth pads while vocoded vocals deliver surprisingly poignant lyrics about urban isolation. It\'s simultaneously uplifting and melancholic, exactly what the best synthpop should be.'
                ],
                'spotify_link' => 'https://open.spotify.com/album/example',
                'apple_music_link' => 'https://music.apple.com/album/example'
            ],
            [
                'title' => 'Coachella 2025: Everything You Need to Know',
                'slug' => 'coachella-2025-guide',
                'artist' => null,
                'genre' => 'Various',
                'author' => 'Jake Morrison',
                'rating' => null,
                'tags' => ['featured', 'festival', 'news', 'live-report'],
                'categories' => ['News', 'Festivals'],
                'excerpt' => 'From headliners to hidden gems, we break down the most anticipated festival lineup of the year.',
                'hero_image' => 'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                'content' => [
                    'The Coachella 2025 lineup has dropped, and it\'s a doozy. With headliners spanning three generations of music fans, this year\'s festival promises to be one of the most diverse yet.',
                    'Friday night belongs to The Cure, who will be performing their seminal album "Disintegration" in its entirety to celebrate its 35th anniversary. Expect tears, expect dancing, expect perfection.',
                    'Saturday sees Kendrick Lamar returning to the desert with a brand new stage show that promises to be his most ambitious production yet. Following his critically acclaimed "Mr. Morale & The Big Steppers" tour, expectations are sky-high.',
                    'Sunday\'s headliner Radiohead needs no introduction. In their first U.S. festival appearance in five years, the band will showcase material from across their legendary career alongside rumored new material.'
                ],
                'event_date' => '2025-04-11'
            ],
            [
                'title' => 'Rising Star: Meet Zara Quinn, Hip-Hop\'s New Voice',
                'slug' => 'zara-quinn-interview',
                'artist' => 'Zara Quinn',
                'genre' => 'Hip-Hop',
                'author' => 'Keisha Williams',
                'rating' => null,
                'tags' => ['featured', 'interview', 'hip-hop', 'breakthrough-artist', 'debut'],
                'categories' => ['Features', 'Interviews'],
                'excerpt' => 'At just 22, Zara Quinn is redefining what it means to be a female rapper.',
                'hero_image' => 'https://images.unsplash.com/photo-1598387993441-a364f854c3e1?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                'content' => [
                    'Zara Quinn doesn\'t fit into anyone\'s box, and that\'s exactly how she likes it. The 22-year-old rapper from Atlanta has exploded onto the scene with her debut mixtape "Quantum Leap," racking up over 50 million streams in its first month and earning comparisons to everyone from Lauryn Hill to Young M.A.',
                    '"I hate those comparisons," she laughs when I bring it up. "Not because those artists aren\'t incredible—they are—but because I\'m trying to be me, not the next anybody. I\'m the first Zara Quinn."',
                    'That confidence is earned. "Quantum Leap" is a stunning debut that showcases Quinn\'s versatility as both a rapper and a songwriter. She can spit rapid-fire bars with the best of them, but she\'s equally comfortable singing melodic hooks or delivering spoken-word poetry.',
                    'Her breakout single "No Cap" went viral on TikTok, with over 2 million videos using the track. But Quinn is careful not to be defined by social media success. "TikTok is a tool, not a career," she says. "I want longevity. I want to be making music that matters when I\'m 40."'
                ],
                'spotify_link' => 'https://open.spotify.com/artist/example'
            ],
            [
                'title' => 'The Vinyl Renaissance: Why Physical Media Is Back',
                'slug' => 'vinyl-renaissance-feature',
                'artist' => null,
                'genre' => 'Industry',
                'author' => 'Tom Richardson',
                'rating' => null,
                'tags' => ['featured', 'news', 'culture'],
                'categories' => ['Culture', 'Industry'],
                'excerpt' => 'Sales are up 300% in five years. We explore why collectors and Gen-Z alike are falling in love with records again.',
                'hero_image' => 'https://images.unsplash.com/photo-1603048588665-791ca8aea617?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                'content' => [
                    'Walk into any Urban Outfitters and you\'ll see them: rows of vinyl records, from Taylor Swift to The Beatles, from Billie Eilish to Pink Floyd. Vinyl sales in 2024 reached levels not seen since the 1980s, with over 40 million units sold in the U.S. alone.',
                    'But this isn\'t just nostalgia. While older collectors certainly drive part of the market, the fastest-growing demographic of vinyl buyers is 18-24 year olds—people who grew up in the age of Spotify and Apple Music.',
                    '"There\'s something about the physical object that streaming can\'t replicate," says Emma Chen, 23, showing me her collection of over 200 records. "When I buy a vinyl, I actually listen to the whole album. With streaming, I just skip around. Vinyl makes me slow down and really experience the music."',
                    'Record labels have taken notice. Major releases now routinely include vinyl variants in multiple colors, often with exclusive artwork and bonus tracks. Some artists, like Jack White, have made vinyl a central part of their artistic vision, with elaborate packages that treat the record as an art object.'
                ]
            ],
            [
                'title' => 'Live Report: Arctic Monkeys at Madison Square Garden',
                'slug' => 'arctic-monkeys-msg-review',
                'artist' => 'Arctic Monkeys',
                'genre' => 'Rock',
                'author' => 'Alex Turner (no relation)',
                'rating' => 5,
                'tags' => ['review', 'live-report', 'concert-review', 'rock'],
                'categories' => ['Reviews', 'Live Shows'],
                'excerpt' => 'The Sheffield legends prove they\'re still at the top of their game with a breathtaking two-hour set.',
                'hero_image' => 'https://images.unsplash.com/photo-1540039155733-5bb30b53aa14?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                'content' => [
                    'The lights dim at Madison Square Garden, and 20,000 people collectively hold their breath. Then, the opening riff of "Do I Wanna Know?" crashes through the arena, and all hell breaks loose.',
                    'Arctic Monkeys have been doing this for over 20 years now, and tonight they remind everyone why they\'re one of the best live bands in the world. Alex Turner prowls the stage with the confidence of a rock star who has nothing left to prove, while the band delivers a masterclass in dynamics and tension.',
                    'The setlist is a greatest hits parade mixed with deep cuts that reward longtime fans. "Brianstorm" is absolutely ferocious live, all jagged guitar riffs and thunderous drums. "505" brings a moment of intimacy to the massive arena, with the entire crowd singing every word.',
                    'By the time they close with "R U Mine?" the entire arena is a writhing mass of bodies, singing along at the top of their lungs. This is what rock and roll should be: dangerous, exciting, and utterly alive.'
                ],
                'venue' => 'Madison Square Garden, New York',
                'event_date' => '2024-12-15'
            ]
        ];

        foreach ($articles as $articleData) {
            $this->createArticlePage($articleData);
        }
    }

    private function createArticlePage(array $data): void
    {
        $page = Page::create([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'status' => 'published',
            'meta_title' => $data['title'] . ' - SOUNDWAVE',
            'meta_description' => $data['excerpt'],
            'page_type' => 'content',
            'site_id' => $this->site->id
        ]);

        // Add tags
        foreach ($data['tags'] as $tagName) {
            $tag = $this->tagRepository->findOrCreateByName($tagName, $this->site->id);
            $page->tags(true)->attach($tag->id);
        }

        // Add categories
        foreach ($data['categories'] as $categoryName) {
            $category = $this->categoryRepository->findOrCreateByName($categoryName, $this->site->id);
            $page->categories(true)->attach($category->id);
        }

        // Add custom fields
        $customFields = [];

        if (!empty($data['artist'])) {
            $customFields['artist_name'] = $data['artist'];
        }
        if (!empty($data['genre'])) {
            $customFields['genre'] = $data['genre'];
        }
        if (!empty($data['author'])) {
            $customFields['author'] = $data['author'];
        }
        if (!empty($data['rating'])) {
            $customFields['rating'] = $data['rating'];
        }
        if (!empty($data['spotify_link'])) {
            $customFields['spotify_link'] = $data['spotify_link'];
        }
        if (!empty($data['apple_music_link'])) {
            $customFields['apple_music_link'] = $data['apple_music_link'];
        }
        if (!empty($data['venue'])) {
            $customFields['venue'] = $data['venue'];
        }
        if (!empty($data['event_date'])) {
            $customFields['event_date'] = $data['event_date'];
        }

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

        // Create article blocks
        $blocks = [
            [
                'type' => 'image',
                'data' => [
                    'src' => $data['hero_image'],
                    'alt' => $data['title'],
                    'caption' => '',
                    'layout' => 'full',
                    'alignment' => 'fullscreen'
                ],
                'order' => 1
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => $data['title'],
                    'level' => 1
                ],
                'order' => 2
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'By ' . $data['author'] . ' | ' . ($data['genre'] ?? 'Music') .
                        ($data['rating'] ? ' | Rating: ' . str_repeat('★', $data['rating']) . str_repeat('☆', 5 - $data['rating']) : '')
                    ]
                ],
                'order' => 3
            ]
        ];

        // Add content paragraphs
        $order = 4;
        foreach ($data['content'] as $paragraph) {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [$paragraph]
                ],
                'order' => $order++
            ];
        }

        // Add rating if it's a review
        if (!empty($data['rating'])) {
            $blocks[] = [
                'type' => 'note',
                'data' => [
                    'title' => 'Our Verdict',
                    'paragraphs' => [
                        'Rating: ' . $data['rating'] . '/5',
                        $data['rating'] >= 4 ? 'Highly Recommended' : ($data['rating'] >= 3 ? 'Worth Your Time' : 'Mixed')
                    ],
                    'alignment' => 'fullscreen'
                ],
                'order' => $order++
            ];
        }

        // Add streaming links if available
        if (!empty($data['spotify_link']) || !empty($data['apple_music_link'])) {
            $streamingLinks = [];
            if (!empty($data['spotify_link'])) {
                $streamingLinks[] = '🎧 [Listen on Spotify](' . $data['spotify_link'] . ')';
            }
            if (!empty($data['apple_music_link'])) {
                $streamingLinks[] = '🍎 [Listen on Apple Music](' . $data['apple_music_link'] . ')';
            }

            $blocks[] = [
                'type' => 'note',
                'data' => [
                    'title' => 'Listen Now',
                    'paragraphs' => $streamingLinks,
                    'alignment' => 'fullscreen'
                ],
                'order' => $order++
            ];
        }

        // Add author bio
        $blocks[] = [
            'type' => 'person',
            'data' => [
                'name' => $data['author'],
                'role' => 'Music Journalist',
                'bio' => 'Writer for SOUNDWAVE Magazine',
                'email' => strtolower(str_replace(' ', '.', $data['author'])) . '@soundwavemag.com',
                'displayType' => 'profile'
            ],
            'order' => $order++
        ];

        // Add related articles section
        $blocks[] = [
            'type' => 'heading',
            'data' => [
                'text' => 'You Might Also Like',
                'level' => 2
            ],
            'order' => $order++
        ];

        $this->createBlocksForPage($page->id, $blocks);
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
}