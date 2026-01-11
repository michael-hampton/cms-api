<?php

namespace App\Database\Seeders;

use App\Framework\Container;
use App\Framework\Database\Seeder\Seeder;
use App\Models\CustomFieldDefinition;
use App\Models\Page;
use App\Models\PageCustomField;
use App\Models\Site;
use App\Repositories\Cms\BlockRepository;
use App\Repositories\Cms\CategoryRepository;
use App\Repositories\Cms\PageRepository;
use App\Repositories\Cms\TagRepository;
use App\Services\Cms\BlockParserService;

class MusicWeekSeederLatest extends Seeder
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
        $this->site = Site::where('slug', 'music-week')->first();
        $this->createArticles();
    }


    // ... within the createArticles() method of DecanterSeeder.php
    private function createArticles(): void
    {
        $articles = [
// 1. Industry News
            ['title' => 'Streaming Giants Face Regulatory Scrutiny Over Artist Royalty Structures', 'slug' => 'streaming-giants-royalty-scrutiny', 'tags' => ['industry', 'business', 'legal', 'streaming'], 'categories' => ['Industry News', 'Analysis'], 'custom_fields' => ['author_name' => 'John Davies', 'read_time' => 8, 'excerpt' => 'Governments across Europe are examining the \'pro-rata\' payment model, with potential shift to a user-centric system.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['The debate over fair pay for artists has intensified, leading to formal regulatory reviews of major streaming platforms.']]]]],
            ['title' => 'The Vinyl Supply Chain Crisis: How Labels Are Adapting to Record Demand', 'slug' => 'vinyl-supply-chain-crisis', 'tags' => ['industry', 'physical-media', 'manufacturing', 'logistics'], 'categories' => ['Industry News', 'Business'], 'custom_fields' => ['author_name' => 'Sarah Thompson', 'read_time' => 7, 'excerpt' => 'With pressing plants struggling to keep up, major and indie labels are investing in new infrastructure to meet the vinyl boom.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['Wait times for vinyl manufacturing are now exceeding 18 months for many small artists.']]]]],
            ['title' => 'Major Labels Announce $500M Fund for Emerging Market Development', 'slug' => 'major-labels-emerging-market-fund', 'tags' => ['industry', 'investment', 'global-music', 'business'], 'categories' => ['Industry News', 'Global Music'], 'custom_fields' => ['author_name' => 'Alex Rivera', 'read_time' => 6, 'excerpt' => 'A consortium of labels has launched a massive fund aimed at developing local talent and infrastructure in Africa and Southeast Asia.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['This initiative focuses on localized licensing, distribution, and artist promotion to tap into under-monetized territories.']]]]],

            // 2. Chart News
            ['title' => 'Taylor Swift Breaks All-Time Record for Most Weeks at Number 1 Globally', 'slug' => 'taylor-swift-number-one-record', 'tags' => ['chart-news', 'pop', 'artist-milestone', 'global'], 'categories' => ['Chart News', 'Pop'], 'custom_fields' => ['author_name' => 'Jane Wilson', 'read_time' => 5, 'excerpt' => 'An analysis of the streaming and sales data that led to the pop star shattering a multi-decade-old chart record.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['Her latest album and sustained catalog consumption have driven an unprecedented run of chart dominance.']]]]],
            ['title' => 'The Midweek Update: Who is Set to Top the UK Singles and Album Charts This Friday?', 'slug' => 'midweek-uk-chart-update', 'tags' => ['chart-news', 'uk-music', 'prediction', 'sales'], 'categories' => ['Chart News', 'UK Music'], 'custom_fields' => ['author_name' => 'Tom Harrison', 'read_time' => 4, 'excerpt' => 'Our expert predictions for the official UK charts this week, with a tight race for the Number 1 album spot.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['A new breakout indie band is challenging a veteran rock act for the top spot.']]]]],
            ['title' => 'Decoding the Algorithm: How Viral TikTok Hits Translate to Chart Success', 'slug' => 'tiktok-to-chart-success-analysis', 'tags' => ['chart-news', 'analysis', 'social-media', 'streaming'], 'categories' => ['Chart News', 'Analysis'], 'custom_fields' => ['author_name' => 'Marcus Bell', 'read_time' => 9, 'excerpt' => 'A deep dive into the correlation between short-form video virality and long-term chart performance.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['Not all viral hits convert to consistent streams, but a clear pattern emerges for the most successful tracks.']]]]],

            // 3. UK Music
            ['title' => 'The State of British Festivals: Post-Pandemic Challenges and the Rise of Boutique Events', 'slug' => 'british-festivals-post-pandemic', 'tags' => ['uk-music', 'events', 'live-music', 'business'], 'categories' => ['UK Music', 'Industry News'], 'custom_fields' => ['author_name' => 'Chloe Davies', 'read_time' => 7, 'excerpt' => 'Soaring operating costs and insurance hurdles are reshaping the UK festival landscape, favoring smaller, curated experiences.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['From Glastonbury to smaller city events, the industry faces severe financial pressures, leading to increased ticket prices.']]]]],
            ['title' => 'New Talent Spotlight: Meet the Grime Artist Bringing South London Back to the Forefront', 'slug' => 'south-london-grime-spotlight', 'tags' => ['uk-music', 'grime', 'new-talent', 'interview'], 'categories' => ['UK Music', 'Interviews'], 'custom_fields' => ['author_name' => 'David Chen', 'read_time' => 6, 'excerpt' => 'A feature on the emerging MC who is infusing classic grime sounds with modern trap production.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['This artist is being hailed as the voice of the next generation of UK urban music.']]]]],
            ['title' => 'Why UK Drill Music is Evolving Beyond its Controversial Roots', 'slug' => 'uk-drill-music-evolution', 'tags' => ['uk-music', 'drill', 'analysis', 'genre-evolution'], 'categories' => ['UK Music', 'Analysis'], 'custom_fields' => ['author_name' => 'Sara Khan', 'read_time' => 8, 'excerpt' => 'Examining how UK drill is becoming more melodic and structurally complex, moving into mainstream acceptance.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['The influence of US trap and R&B is pushing UK drill towards new compositional forms.']]]]],

            // 4. Global Music
            ['title' => 'K-Pop\'s Next Frontier: Why Southeast Asia is the Industry\'s New Target Market', 'slug' => 'kpop-southeast-asia-market', 'tags' => ['global-music', 'k-pop', 'business', 'emerging-markets'], 'categories' => ['Global Music', 'Industry News'], 'custom_fields' => ['author_name' => 'Eliza Viera', 'read_time' => 9, 'excerpt' => 'Beyond Japan and the US, K-Pop agencies are dedicating massive resources to fan engagement and content localization in SEA.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['The high digital penetration and youthful populations make this region a key growth area for Hallyu.']]]]],
            ['title' => 'Latin American Pop Dominates Global Streams: An Analysis of the Key Players', 'slug' => 'latin-pop-global-dominance', 'tags' => ['global-music', 'latin-pop', 'streaming', 'analysis'], 'categories' => ['Global Music', 'Analysis'], 'custom_fields' => ['author_name' => 'Javier Morales', 'read_time' => 7, 'excerpt' => 'Examining the artists, producers, and genres—from Reggaeton to Cumbia—driving Latin music\'s unprecedented global streaming numbers.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['The seamless blend of rhythms and accessibility of Spanish-language music has transcended cultural borders.']]]]],
            ['title' => 'The Return of Afrobeats: Lagos to London, Mapping the Genre\'s Worldwide Influence', 'slug' => 'afrobeats-global-influence', 'tags' => ['global-music', 'afrobeats', 'nigeria', 'genre-evolution'], 'categories' => ['Global Music', 'UK Music'], 'custom_fields' => ['author_name' => 'Amara Nwanze', 'read_time' => 10, 'excerpt' => 'Tracing the journey of Afrobeats from its West African roots to chart domination and collaborations with major Western artists.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['The genre is now a global cultural export, thanks to artists like Burna Boy and Rema.']]]]],

            // 5. Interviews
            ['title' => 'In Conversation with Producer Mark \'Spike\' Jones: 30 Years of Shaping Sound', 'slug' => 'interview-mark-spike-jones', 'tags' => ['interviews', 'producer', 'rock', 'hip-hop', 'veteran'], 'categories' => ['Interviews', 'Analysis'], 'custom_fields' => ['author_name' => 'Julian Hayes', 'read_time' => 12, 'excerpt' => 'The legendary producer discusses his sonic philosophy, from early grunge sessions to producing modern pop mega-hits.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['Jones shares his unique microphone techniques and his thoughts on the diminishing role of the studio engineer in the digital age.']]]]],
            ['title' => '\'I Never Felt Like I Fit In\': Indie Artist Chloe S. on Her Journey to Creative Independence', 'slug' => 'chloe-s-indie-artist-interview', 'tags' => ['interviews', 'indie', 'new-talent', 'creative-process'], 'categories' => ['Interviews', 'UK Music'], 'custom_fields' => ['author_name' => 'Tom Harrison', 'read_time' => 7, 'excerpt' => 'An intimate discussion with the rising singer-songwriter about managing anxiety, rejecting label pressure, and finding success on her own terms.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['Her new album is a testament to embracing vulnerability and artistic control.']]]]],
            ['title' => 'Manager\'s Cut: How Jane Doe Guided a Breakout Artist to Arena Status in 18 Months', 'slug' => 'manager-jane-doe-breakout-strategy', 'tags' => ['interviews', 'business', 'management', 'case-study'], 'categories' => ['Interviews', 'Industry News', 'Analysis'], 'custom_fields' => ['author_name' => 'John Davies', 'read_time' => 9, 'excerpt' => 'Talent manager Jane Doe reveals the strategic decisions and key partnerships that turned an unknown artist into a global headliner.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['The strategy hinged on leveraging gaming streams and highly curated visual content before radio plays.']]]]],

            // 6. Analysis
            ['title' => 'The Death of the Album Cycle? Assessing the Impact of Constant Single Releases', 'slug' => 'death-of-album-cycle-analysis', 'tags' => ['analysis', 'streaming', 'pop', 'business'], 'categories' => ['Analysis', 'Industry News'], 'custom_fields' => ['author_name' => 'Sarah Thompson', 'read_time' => 10, 'excerpt' => 'The economics of streaming favor continuous content drops over traditional 18-month album campaigns. We analyze the shift.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['Is the long-form artistic statement being replaced by a conveyor belt of snackable, algorithm-friendly tracks?']]]]],
            ['title' => 'The True Cost of Touring: Why Even Headliners Are Struggling to Break Even', 'slug' => 'true-cost-of-touring', 'tags' => ['analysis', 'live-music', 'business', 'finance'], 'categories' => ['Analysis', 'Industry News'], 'custom_fields' => ['author_name' => 'Alex Rivera', 'read_time' => 8, 'excerpt' => 'A financial breakdown of modern arena tours, revealing how inflation, fuel costs, and production demands are squeezing artist profits.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['Merchandise and ticket VIP packages have become essential income streams, rather than supplementary.']]]]],
            ['title' => 'Will AI Composers Change Music Publishing Forever? An In-Depth Look', 'slug' => 'ai-composers-publishing-future', 'tags' => ['analysis', 'ai', 'legal', 'future-tech'], 'categories' => ['Analysis', 'Industry News'], 'custom_fields' => ['author_name' => 'Eliza Viera', 'read_time' => 11, 'excerpt' => 'We investigate the current legal and ethical challenges posed by AI-generated music and its potential to disrupt traditional publishing royalties.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['The core question remains: who owns the copyright to a composition created by an algorithm?']]]]],

            // 7. Opinion
            ['title' => 'It\'s Time for Streaming Services to Adopt a User-Centric Payout Model (Opinion)', 'slug' => 'opinion-user-centric-payouts', 'tags' => ['opinion', 'industry', 'streaming', 'fair-pay'], 'categories' => ['Opinion', 'Industry News'], 'custom_fields' => ['author_name' => 'John Davies', 'read_time' => 6, 'excerpt' => 'Our editorial argues that the current \'pro-rata\' model unfairly benefits superstars and major labels at the expense of niche artists.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['The user-centric model would see a subscriber\'s fee distributed only to the artists they personally listen to.']]]]],
            ['title' => 'Why Gatekeeping is Ruining Music Discovery for the Next Generation of Fans (Opinion)', 'slug' => 'opinion-gatekeeping-ruins-discovery', 'tags' => ['opinion', 'culture', 'discovery', 'indie'], 'categories' => ['Opinion', 'Culture'], 'custom_fields' => ['author_name' => 'Sarah Thompson', 'read_time' => 5, 'excerpt' => 'The constant insistence on arbitrary "authenticity" by certain critics is stifling genre-blending and experimentation.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['We need to embrace music made by and for the digital age, without the baggage of past rules.']]]]],
            ['title' => 'The Critics Are Wrong: The Art of the Pop Banger is More Complex Than Ever (Opinion)', 'slug' => 'opinion-pop-banger-complexity', 'tags' => ['opinion', 'pop', 'analysis', 'production'], 'categories' => ['Opinion', 'Analysis'], 'custom_fields' => ['author_name' => 'Alex Rivera', 'read_time' => 7, 'excerpt' => 'A defense of modern pop music, arguing that the technical skill and structural complexity of a chart-topping single is often underestimated.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['The modern pop song is a masterclass in economy and highly compressed sound design.']]]]],
        ];

        foreach ($articles as $articleData) {
            $this->createArticle($articleData);
        }
    }

    private function createArticle(array $data): void
    {
        $page = Page::create([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'status' => 'published',
            'page_type' => 'content',
            'meta_title' => $data['title'] . ' - The Wine Chronicle',
            'meta_description' => $data['custom_fields']['excerpt'] ?? '',
            'site_id' => $this->site->id,
        ]);

        foreach ($data['tags'] as $tagName) {
            $tag = $this->tagRepository->findOrCreateByName($tagName, $this->site->id);
            $page->tags(true)->attach($tag->id);
        }

        foreach ($data['categories'] as $categoryName) {
            $category = $this->categoryRepository->findOrCreateByName($categoryName, $this->site->id);
            $page->categories(true)->attach($category->id);
        }

        foreach ($data['custom_fields'] as $key => $value) {
            $fieldDef = CustomFieldDefinition::where('key', $key)->first();
            if ($fieldDef) {
                PageCustomField::create([
                    'custom_field_definition_id' => $fieldDef->id,
                    'field_value' => (string)$value,
                    'page_id' => $page->id
                ]);
            }
        }

        foreach ($data['content'] as $index => $blockData) {
            $this->blockRepository->create([
                'page_id' => $page->id,
                'type' => $blockData['type'],
                'data' => json_encode($blockData['data']),
                'order' => $index + 1
            ]);
        }
    }
}