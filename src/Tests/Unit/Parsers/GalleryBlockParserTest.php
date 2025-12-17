<?php

namespace App\Tests\Unit\Parsers;

use App\Enums\GalleryLayout;
use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Framework\Validation\Rules\UrlRule;
use App\Framework\Validation\Validator;
use App\Parsers\GalleryBlockParser;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class GalleryBlockParserTest extends FunctionalTestCase
{
    public function testGalleryParserGetType(): void
    {
        $parser = new GalleryBlockParser();
        $this->assertSame('gallery', $parser->getType());
    }

    public function testGalleryParserGetValidationRules(): void
    {
        $parser = new GalleryBlockParser();
        $rules = $parser->getValidationRules();

        $this->assertArrayHasKey('layout', $rules);
        $this->assertContainsOnlyInstancesOf(RequiredRule::class, array_filter($rules['layout'], fn($r) => $r instanceof RequiredRule));

        $this->assertArrayHasKey('slides', $rules);
        $this->assertContainsOnlyInstancesOf(ArrayRule::class, array_filter($rules['slides'], fn($r) => $r instanceof ArrayRule));

        $slideRules = $parser->getSlideValidationRules();
        $this->assertArrayHasKey('link', $slideRules);
        $this->assertContainsOnlyInstancesOf(UrlRule::class, array_filter($slideRules['link'], fn($r) => $r instanceof UrlRule));
        $this->assertContainsOnlyInstancesOf(BooleanRule::class, array_filter($slideRules['noFollow'], fn($r) => $r instanceof BooleanRule));
    }

    public function testGalleryParserParse(): void
    {
        $parser = new GalleryBlockParser();
        $data = [
            'layout' => 'grid',
            'slides' => [
                [
                    'title' => '  Slide One ',
                    'description' => 'Image description.',
                    'image' => '/img1.jpg',
                    'link' => 'https://link.com',
                    'noFollow' => true
                ],
                [
                    'title' => '', // Skipped as it's empty
                    'description' => '',
                    'image' => ''
                ],
                [
                    'title' => 'Slide Three',
                    'image' => '/img3.jpg',
                    'caption' => 'A great photo'
                ]
            ]
        ];
        $parsed = $parser->parse($data);

        $this->assertSame('grid', $parsed['layout']);
        $this->assertCount(2, $parsed['slides']); // Empty slide filtered out
        $this->assertSame(9, $parsed['total_word_count']); // 'Slide One Image description.' (4) + 'Slide Three A great photo' (4) = 8. Wait, 'Slide Three' (2) + 'A great photo' (3) = 5.
        // Slide 1: 'Slide One Image description' (4 words). Slide 2 is skipped. Slide 3: 'Slide Three A great photo' (5 words). Total 9 words.
        // Let's re-run word count: S1: title (2) + desc (2) = 4. S3: title (2) + caption (3) = 5. Total: 9 words.
        $this->assertSame(9, $parsed['total_word_count']);

        $this->assertTrue($parsed['slides'][0]['noFollow']);
        $this->assertTrue($parsed['slides'][0]['has_link']);
        $this->assertStringContainsString('Image description.', $parsed['slides'][0]['formatted_description']);
    }

    public function testGalleryParserGenerateHtmlCarousel(): void
    {
        $parser = new GalleryBlockParser();
        $parsedData = [
            'layout' => 'carousel',
            'slides' => [
                [
                    'image' => ['src' => '/img1.jpg'],
                    'formatted_title' => 'S1 Title',
                    'formatted_description' => 'S1 Desc',
                    'title' => 'S1 Title',
                    'description' => 'S1 Desc'
                ],
                [
                    'image' => ['src' => '/img2.jpg'],
                    'formatted_title' => 'S2 Title',
                    'formatted_description' => 'S2 Desc',
                    'title' => 'S2 Title',
                    'description' => 'S2 Desc'
                ]
            ]
        ];
        $html = $parser->generateHtml($parsedData);

        $this->assertStringContainsString('<div class="gallery-block gallery-carousel">', $html);
        $this->assertStringContainsString('<div class="carousel-slide active"', $html); // First slide is active
        $this->assertStringContainsString('<img src="/img1.jpg"', $html);
        $this->assertStringContainsString('<button class="carousel-btn carousel-prev"', $html); // Controls exist
        $this->assertStringContainsString('<script>', $html); // Contains JS logic
    }

    public function testGalleryParserGenerateHtmlGrid(): void
    {
        $parser = new GalleryBlockParser();
        $parsedData = [
            'layout' => 'grid',
            'slides' => [
                [
                    'image' => ['src' => '/img1.jpg'],
                    'formatted_title' => 'S1 Title',
                    'formatted_description' => 'S1 Desc',
                    'formatted_caption' => 'S1 Caption',
                    'has_link' => true,
                    'link' => 'https://link.com',
                    'noFollow' => false,
                    'sponsored' => false,
                    'openInNewTab' => true,
                    'title' => 'S1 Title',
                    'description' => 'S1 Desc',
                    'caption' => 'S1 Caption'
                ]
            ]
        ];
        $html = $parser->generateHtml($parsedData);

        $this->assertStringContainsString('<div class="gallery-block gallery-grid">', $html);
        $this->assertStringContainsString('<a href="https://link.com" target="_blank">', $html);
        $this->assertStringContainsString('<img src="/img1.jpg"', $html);
        $this->assertStringContainsString('<h3 class="gallery-slide-title">S1 Title</h3>', $html);
        $this->assertStringContainsString('<div class="gallery-slide-caption">S1 Caption</div>', $html);
    }

    public function testGalleryParserGenerateHtmlList(): void
    {
        $parser = new GalleryBlockParser();
        $parsedData = [
            'layout' => 'list',
            'slides' => [
                [
                    'image' => ['src' => '/img1.jpg'],
                    'formatted_title' => 'List Item 1',
                    'formatted_description' => 'Description for item 1',
                    'formatted_caption' => 'Photo credit',
                    'has_link' => true,
                    'link' => 'https://example.com',
                    'noFollow' => false,
                    'sponsored' => false,
                    'openInNewTab' => true,
                    'title' => 'List Item 1',
                    'description' => 'Description for item 1',
                    'caption' => 'Photo credit'
                ],
                [
                    'image' => ['src' => '/img2.jpg'],
                    'formatted_title' => 'List Item 2',
                    'formatted_description' => 'Description for item 2',
                    'formatted_caption' => '',
                    'has_link' => false,
                    'link' => '',
                    'noFollow' => false,
                    'sponsored' => false,
                    'openInNewTab' => false,
                    'title' => 'List Item 2',
                    'description' => 'Description for item 2',
                    'caption' => ''
                ]
            ]
        ];
        $html = $parser->generateHtml($parsedData);

        $this->assertStringContainsString('<div class="gallery-block gallery-list">', $html);
        $this->assertStringContainsString('<div class="gallery-list-item"', $html);
        $this->assertStringContainsString('<div class="list-item-image">', $html);
        $this->assertStringContainsString('<div class="list-item-content">', $html);
        $this->assertStringContainsString('<h3 class="list-item-title">', $html);
        $this->assertStringContainsString('<a href="https://example.com" target="_blank">List Item 1</a>', $html);
        $this->assertStringContainsString('<img src="/img1.jpg"', $html);
        $this->assertStringContainsString('Description for item 1', $html);
        $this->assertStringContainsString('<div class="list-item-caption">Photo credit</div>', $html);

        // Second item without link
        $this->assertStringContainsString('List Item 2', $html);
        $this->assertStringNotContainsString('<a href="">List Item 2</a>', $html);
    }

    public function testGalleryBlockParserParsesSlides()
    {
        $parser = new GalleryBlockParser();
        $data = [
            'layout' => 'carousel',
            'slides' => [
                [
                    'title' => 'Slide 1',
                    'description' => 'Description 1',
                    'image' => 'slide1.jpg',
                    'alt' => 'Slide 1 alt'
                ],
                [
                    'title' => 'Slide 2',
                    'description' => 'Description 2',
                    'image' => 'slide2.jpg',
                    'alt' => 'Slide 2 alt'
                ]
            ]
        ];

        $result = $parser->parse($data);

        $this->assertEquals('carousel', $result['layout']);
        $this->assertEquals(2, $result['slide_count']);
        $this->assertCount(2, $result['slides']);
    }

    public function testGalleryBlockParserGeneratesCarousel()
    {
        $parser = new GalleryBlockParser();
        $parsed = [
            'layout' => 'carousel',
            'slides' => [
                [
                    'title' => 'Slide 1',
                    'description' => 'Desc',
                    'image' => ['src' => 'slide1.jpg'],
                    'formatted_title' => 'Slide 1',
                    'formatted_description' => 'Desc',
                    'word_count' => 3
                ]
            ],
            'slide_count' => 1
        ];

        $html = $parser->generateHtml($parsed);

        $this->assertStringContainsString('gallery-carousel', $html);
        $this->assertStringContainsString('carousel-slides', $html);
        $this->assertStringContainsString('<script>', $html);
    }

    public function testGalleryBlockParserRequiredFields()
    {
        $parser = new GalleryBlockParser();
        $validator = new Validator();

        $data = [
            // Missing required fields: layout, slides
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testGalleryBlockParserInvalidLayout()
    {
        $parser = new GalleryBlockParser();
        $validator = new Validator();

        $data = [
            'layout' => 'invalid_layout',
            'slides' => []
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testGalleryBlockParserValidLayouts()
    {
        $parser = new GalleryBlockParser();

        foreach (GalleryLayout::cases() as $layout) {
            $data = [
                'layout' => $layout->value,
                'slides' => [
                    ['title' => 'Slide 1', 'image' => 'img.jpg', 'alt' => 'Alt']
                ]
            ];

            $result = $parser->parse($data);
            $this->assertEquals($layout->value, $result['layout']);
        }
    }

    public function testGalleryBlockParserSlidesArray()
    {
        $parser = new GalleryBlockParser();
        $validator = new Validator();

        $data = [
            'layout' => 'carousel',
            'slides' => 'not_an_array'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testGalleryBlockParserSlideValidation()
    {
        $parser = new GalleryBlockParser();
        $validator = new Validator();

        $slideRules = $parser->getSlideValidationRules();

        // Test slide with missing required title
        $slideData = [
            'description' => 'Description'
        ];

        $result = $validator->validate($slideData, $slideRules);
        $this->assertFalse($result->isValid());
    }

    public function testGalleryBlockParserSlideTitleMaxLength()
    {
        $parser = new GalleryBlockParser();
        $validator = new Validator();

        $slideRules = $parser->getSlideValidationRules();

        $slideData = [
            'title' => str_repeat('a', 256)
        ];

        $result = $validator->validate($slideData, $slideRules);
        $this->assertFalse($result->isValid());
    }

    public function testGalleryBlockParserSlideDescriptionMaxLength()
    {
        $parser = new GalleryBlockParser();
        $validator = new Validator();

        $slideRules = $parser->getSlideValidationRules();

        $slideData = [
            'title' => 'Title',
            'description' => str_repeat('a', 1001)
        ];

        $result = $validator->validate($slideData, $slideRules);
        $this->assertFalse($result->isValid());
    }

    public function testGalleryBlockParserListLayoutWithLinks()
    {
        $parser = new GalleryBlockParser();
        $parsedData = [
            'layout' => 'list',
            'slides' => [
                [
                    'image' => ['src' => '/img1.jpg'],
                    'formatted_title' => 'Linked Item',
                    'formatted_description' => 'With a link',
                    'formatted_caption' => '',
                    'has_link' => true,
                    'link' => 'https://test.com',
                    'noFollow' => true,
                    'sponsored' => true,
                    'openInNewTab' => true,
                    'title' => 'Linked Item',
                    'description' => 'With a link',
                    'caption' => ''
                ]
            ]
        ];

        $html = $parser->generateHtml($parsedData);

        $this->assertStringContainsString('rel="nofollow"', $html);
        $this->assertStringContainsString('rel="sponsored"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
    }
}