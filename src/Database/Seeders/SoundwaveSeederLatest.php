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
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\TagRepository;
use App\Services\Cms\Pages\BlockParserService;

class SoundwaveSeederLatest extends Seeder
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
        $this->site = Site::where('slug', 'soundwave')->first();
        $this->createArticles();
    }


    // ... within the createArticles() method of DecanterSeeder.php
    private function createArticles(): void
    {
        $articles = [
            [
                'title' => 'The Mechanics of a Viral Pop Collab: Analyzing the Duet Formula',
                'slug' => 'viral-pop-collab-duet-formula',
                'tags' => ['pop', 'business', 'analysis', 'chart-news'],
                'categories' => ['Pop', 'Analysis', 'Industry News'],
                'custom_fields' => ['author_name' => 'Eliza Viera', 'read_time' => 8, 'excerpt' => 'It’s more than just two voices: we break down how strategic partnerships between artists from different markets are engineered to dominate global charts.'],
                'content' => [
                    ['type' => 'heading', 'data' => ['text' => 'The Global Crossover Strategy', 'level' => 2]],
                    ['type' => 'text', 'data' => ['paragraphs' => ['A collaboration between a US pop star and a K-Pop or Latin artist is a calculated move to merge two massive, dedicated fanbases, resulting in exponential first-week streaming numbers.', 'The music is typically crafted to be lingua franca: rhythmically accessible with clear, universal themes.']]]
                ]
            ],
            [
                'title' => 'Album Review: Sensation LUNA\'s Sophisticated Second Act',
                'slug' => 'luna-second-album-review',
                'tags' => ['pop', 'review', 'new-music', 'artist-feature'],
                'categories' => ['Pop', 'Reviews'],
                'custom_fields' => ['author_name' => 'Julian Hayes', 'read_time' => 7, 'excerpt' => 'LUNA’s sophomore album sheds the bubblegum image for a complex, 80s-synth-infused sound that proves her staying power is not a fluke.'],
                'content' => [
                    ['type' => 'heading', 'data' => ['text' => 'Maturing the Sound', 'level' => 2]],
                    ['type' => 'text', 'data' => ['paragraphs' => ['The new record is rich with analog synthesisers and unexpected time signature changes, showing a clear artistic maturity. The production is deliberately less compressed than her debut, allowing the vocals to breathe.']]],
                    ['type' => 'stats', 'data' => ['title' => 'Album Rating', 'stats' => [['number' => '4.5/5', 'label' => 'Score', 'icon' => '⭐️'], ['number' => '72m', 'label' => 'Tracks', 'icon' => '💿']]]]
                ]
            ],
            [
                'title' => 'Why Pop Star Image Rebrands are Essential in the Streaming Era',
                'slug' => 'pop-star-image-rebrand-analysis',
                'tags' => ['pop', 'culture', 'marketing', 'opinion'],
                'categories' => ['Pop', 'Opinion', 'Culture'],
                'custom_fields' => ['author_name' => 'Marcus Bell', 'read_time' => 6, 'excerpt' => 'An analysis of how radical visual shifts every album cycle drive discourse, attention, and crucially, renewed playlist interest.'],
                'content' => [
                    ['type' => 'heading', 'data' => ['text' => 'The Content Carousel', 'level' => 2]],
                    ['type' => 'text', 'data' => ['paragraphs' => ['In a 15-second attention span economy, an artist’s visual identity must be as fluid as their song releases. The ‘era’ concept, defined by aesthetic changes, keeps an artist relevant and algorithm-friendly.']]],
                    ['type' => 'note', 'data' => ['title' => 'Key Examples', 'paragraphs' => ['Lady Gaga (Theatricality), Taylor Swift (Narrative Shifts), Madonna (Continuous Reinvention).']]]
                ]
            ],
            [
                'title' => 'Beyond the Drop: The Evolving Sound of Ambient Techno',
                'slug' => 'evolving-sound-of-ambient-techno',
                'tags' => ['electronic', 'techno', 'ambient', 'genre-evolution'],
                'categories' => ['Electronic', 'Analysis'],
                'custom_fields' => ['author_name' => 'Samir Patel', 'read_time' => 9, 'excerpt' => 'The genre is moving away from festival bangers and back into contemplative, cinematic compositions built for immersive listening.'],
                'content' => [
                    ['type' => 'heading', 'data' => ['text' => 'Soundscapes and Subtlety', 'level' => 2]],
                    ['type' => 'text', 'data' => ['paragraphs' => ['Modern ambient techno prioritizes textures and deep bass over frantic tempo. This reflects a growing demand for music that supports focus and mental well-being, rather than just dancing.', 'The production is often characterized by long reverb trails and modular synthesizer experimentation.']]]
                ]
            ],
            [
                'title' => 'Synth History: How the Roland TB-303 Defined Acid House',
                'slug' => 'roland-tb-303-acid-house-history',
                'tags' => ['electronic', 'history', 'gear', 'acid-house'],
                'categories' => ['Electronic', 'History', 'Gear'],
                'custom_fields' => ['author_name' => 'Mike Chen', 'read_time' => 6, 'excerpt' => 'The story of the quirky, failed bass synthesizer that accidentally created one of dance music\'s most distinctive and recognizable sounds: the squelching acid line.'],
                'content' => [
                    ['type' => 'heading', 'data' => ['text' => 'The Accidental Revolution', 'level' => 2]],
                    ['type' => 'text', 'data' => ['paragraphs' => ['Originally designed to provide backing bass lines for solo guitarists, the 303’s peculiar sequencer and filter controls were embraced by Chicago producers in the mid-80s, creating the signature psychedelic sound of acid house.']]],
                    ['type' => 'product', 'data' => ['name' => 'Roland TB-303 (Reissue)', 'brand' => 'Roland', 'productName' => 'Bass Line Synthesizer', 'price' => 399.00, 'currency' => '$', 'description' => 'Modern clone of the legendary machine.', 'linkText' => 'View Specs', 'image' => ['src' => 'https://images.unsplash.com/photo-1543163522-8758801d9f0f?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80']]]
                ]
            ],
            [
                'title' => 'Feature: The Best Electronic Music Festivals You Haven\'t Heard Of',
                'slug' => 'best-underground-electronic-festivals',
                'tags' => ['electronic', 'festivals', 'live-music', 'travel'],
                'categories' => ['Electronic', 'Features', 'Events'],
                'custom_fields' => ['author_name' => 'Anna Lee', 'read_time' => 5, 'excerpt' => 'From a remote Icelandic bunker rave to a Romanian deep house campout, we list the most unique and underground electronic music gatherings globally.'],
                'content' => [
                    ['type' => 'heading', 'data' => ['text' => 'Hidden Gems of the Rave Scene', 'level' => 2]],
                    ['type' => 'list', 'data' => ['listType' => 'ol', 'items' => ['**Into the Glacier (Iceland):** A techno event held inside an ice tunnel.', '**Sunwaves (Romania):** Famous for its marathon sets and minimalist house.', '**Dekmantel (Netherlands):** A smaller, highly curated festival focused on quality over quantity.']]]
                ]
            ],
            [
                'title' => 'The Producer Spotlight: Sampling Master DJ Zenith on His Craft',
                'slug' => 'dj-zenith-producer-spotlight',
                'tags' => ['hip-hop', 'production', 'interview', 'sampling'],
                'categories' => ['Hip Hop', 'Interviews', 'Production'],
                'custom_fields' => ['author_name' => 'Tom Viera', 'read_time' => 10, 'excerpt' => 'A conversation with the legendary beatmaker about digging for obscure vinyl, beat construction, and the art of modern sample clearance.'],
                'content' => [
                    ['type' => 'heading', 'data' => ['text' => 'The Philosophy of the Chop', 'level' => 2]],
                    ['type' => 'text', 'data' => ['paragraphs' => ['DJ Zenith explains that his process starts with the drums—the backbone of any track—before finding a loopable melodic fragment. He’s known for using dusty jazz and soul records to create his signature sound.', 'The biggest challenge now is not finding the sample, but legally clearing it for distribution.']]]
                ]
            ],
            [
                'title' => 'Lyrical Analysis: The Storytelling Genius of New York\'s MC Ghost',
                'slug' => 'mc-ghost-lyrical-analysis',
                'tags' => ['hip-hop', 'lyrics', 'analysis', 'east-coast'],
                'categories' => ['Hip Hop', 'Analysis'],
                'custom_fields' => ['author_name' => 'Amara Nwanze', 'read_time' => 8, 'excerpt' => 'A deep dive into the complex wordplay, multi-syllable rhyme schemes, and thematic depth of one of the East Coast’s most revered underground lyricists.'],
                'content' => [
                    ['type' => 'heading', 'data' => ['text' => 'The Art of the Internal Rhyme', 'level' => 2]],
                    ['type' => 'text', 'data' => ['paragraphs' => ['MC Ghost\'s genius lies in his ability to maintain a coherent narrative while threading complex rhyme patterns throughout his verses. This technique elevates his music from simple rapping to high-level poetry.']]],
                    ['type' => 'quote', 'data' => ['text' => 'He treats the sixteen bar verse like a sonnet, every word is deliberate and weighted.', 'attribution' => 'Amara Nwanze']]
                ]
            ],
            [
                'title' => 'West Coast Revival: How G-Funk is Influencing the Next Generation',
                'slug' => 'west-coast-g-funk-revival',
                'tags' => ['hip-hop', 'west-coast', 'g-funk', 'genre-evolution'],
                'categories' => ['Hip Hop', 'History', 'New Music'],
                'custom_fields' => ['author_name' => 'James Davies', 'read_time' => 7, 'excerpt' => 'The distinctive synth leads and slow, funky grooves of 90s G-Funk are making a stylish return in the production of today’s California rappers.'],
                'content' => [
                    ['type' => 'heading', 'data' => ['text' => 'The Synths and the Sunshine', 'level' => 2]],
                    ['type' => 'text', 'data' => ['paragraphs' => ['The core elements of G-Funk—pitched-up, melodic synthesizers, slow drum breaks, and minimal bass—are perfectly suited for modern trap rhythms. Artists are embracing the laid-back, yet hedonistic feel of the genre.']]]
                ]
            ],


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