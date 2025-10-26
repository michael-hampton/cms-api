<?php

namespace App\Tests\Unit\Parsers;

use App\Enums\Alignment;
use App\Enums\ImageLayout;
use App\Framework\Validation\Validator;
use App\Models\Image;
use App\Parsers\ImageBlockParser;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use PHPUnit\Framework\TestCase;

class ImageBlockParserTest extends FunctionalTestCase
{
    public function testImageParserGetType(): void
    {
        $parser = new ImageBlockParser();
        $this->assertSame('image', $parser->getType());
    }

    public function testImageParserParse(): void
    {
        $parser = new ImageBlockParser();
        $data = ['src' => '/img.jpg', 'alt' => 'Alt text'];
        $parsed = $parser->parse($data);
        $this->assertSame('jpeg', $parsed['image_type']);
    }

    public function testImageBlockParserValidData()
    {
        $parser = new ImageBlockParser();
        $data = [
            'src' => 'https://example.com/image.jpg',
            'alt' => 'Image description',
            'caption' => 'Image caption',
            'linkUrl' => 'https://example.com',
            'alignment' => 'left',
            'layout' => 'full',
            'noFollow' => true,
            'openInNewTab' => true
        ];

        $result = $parser->parse($data);

        $this->assertEquals('left', $result['alignment']);
        $this->assertTrue($result['has_link']);
        $this->assertTrue($result['has_caption']);
        $this->assertArrayHasKey('alignment_css_class', $result);
        $this->assertEquals('image-align-left', $result['alignment_css_class']);
    }

    public function testImageBlockParserCalculatesScores()
    {
        $parser = new ImageBlockParser();
        $data = [
            'src' => 'descriptive-image-name.jpg',
            'alt' => 'A detailed description of the image',
            'caption' => 'Caption text'
        ];

        $result = $parser->parse($data);

        $this->assertGreaterThan(0, $result['seo_score']);
        $this->assertGreaterThan(0, $result['accessibility_score']);
    }

    public function testImageBlockParserGeneratesHtmlWithAlignment()
    {
        $parser = new ImageBlockParser();
        $parsed = [
            'src' => 'image.jpg',
            'alt' => 'Alt text',
            'caption' => '',
            'formatted_caption' => '',
            'linkUrl' => '',
            'layout_css_class' => 'image-layout-full',
            'alignment_css_class' => 'image-align-right',
            'link_attributes' => []
        ];

        $html = $parser->generateHtml($parsed);

        $this->assertStringContainsString('image-block', $html);
        $this->assertStringContainsString('image-align-right', $html);
        $this->assertStringContainsString('image.jpg', $html);
    }

    public function testImageBlockParserRequiredFields()
    {
        $parser = new ImageBlockParser();
        $validator = new Validator();

        $data = [
            // Missing required fields: src, alt
            'caption' => 'Caption'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testImageBlockParserCaptionMaxLength()
    {
        $parser = new ImageBlockParser();
        $validator = new Validator();

        $data = [
            'src' => 'image.jpg',
            'alt' => 'Alt text',
            'caption' => str_repeat('a', 501)
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testImageBlockParserInvalidLinkUrl()
    {
        $parser = new ImageBlockParser();
        $validator = new Validator();

        $data = [
            'src' => 'image.jpg',
            'alt' => 'Alt text',
            'linkUrl' => 'not_a_valid_url'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testImageBlockParserInvalidLayout()
    {
        $parser = new ImageBlockParser();
        $validator = new Validator();

        $data = [
            'src' => 'image.jpg',
            'alt' => 'Alt text',
            'layout' => 'invalid_layout'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testImageBlockParserValidLayouts()
    {
        $parser = new ImageBlockParser();

        foreach (ImageLayout::cases() as $layout) {
            $data = [
                'src' => 'image.jpg',
                'alt' => 'Alt text',
                'layout' => $layout->value
            ];

            $result = $parser->parse($data);
            $this->assertEquals($layout->value, $result['layout']);
        }
    }

    public function testImageBlockParserInvalidAlignment()
    {
        $parser = new ImageBlockParser();
        $validator = new Validator();

        $data = [
            'src' => 'image.jpg',
            'alt' => 'Alt text',
            'alignment' => 'invalid_alignment'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testImageBlockParserValidAlignments()
    {
        $parser = new ImageBlockParser();

        foreach (Alignment::cases() as $alignment) {
            $data = [
                'src' => 'image.jpg',
                'alt' => 'Alt text',
                'alignment' => $alignment->value
            ];

            $result = $parser->parse($data);
            $this->assertEquals($alignment->value, $result['alignment']);
        }
    }

    public function testImageBlockParserBooleanFields()
    {
        $parser = new ImageBlockParser();
        $validator = new Validator();

        $data = [
            'src' => 'image.jpg',
            'alt' => 'Alt text',
            'noFollow' => 'not_boolean',
            'sponsored' => 'not_boolean',
            'openInNewTab' => 'not_boolean'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testImageBlockParserDisplaysCreditForAttributionRequired()
    {
        $image = Image::create([
            'url' => 'test',
            'file_size' => 1024,
            'filename' => 'test.jpg',
            'file_path' => '/uploads/test.jpg',
            'mime_type' => 'image/jpeg',
            'original_name' => 'test.jpg',
            'credit' => 'John Photographer',
            'image_rights' => 'attribution_required',
            'site_id' => $this->siteId
        ]);

        $parser = new ImageBlockParser();
        $data = [
            'src' => 'test.jpg',
            'alt' => 'Alt text',
            'image_id' => $image->id
        ];

        $result = $parser->parse($data);

        $this->assertTrue($result['should_display_credit']);
        $this->assertEquals('John Photographer', $result['credit']);
        $this->assertEquals('attribution_required', $result['image_rights']);
    }

    public function testImageBlockParserHidesCreditForRoyaltyFree()
    {
        $image = Image::create([
            'url' => 'test',
            'file_size' => 1024,
            'filename' => 'test.jpg',
            'file_path' => '/uploads/test.jpg',
            'mime_type' => 'image/jpeg',
            'original_name' => 'test.jpg',
            'credit' => 'Stock Photo',
            'image_rights' => 'royalty_free',
            'site_id' => $this->siteId
        ]);

        $parser = new ImageBlockParser();
        $data = [
            'src' => 'test.jpg',
            'alt' => 'Alt text',
            'image_id' => $image->id
        ];

        $result = $parser->parse($data);

        $this->assertFalse($result['should_display_credit']);
    }

    public function testImageBlockParserDisplaysCreditForCreativeCommons()
    {
        $image = Image::create([
            'url' => 'test',
            'file_size' => 1024,
            'filename' => 'test.jpg',
            'file_path' => '/uploads/test.jpg',
            'mime_type' => 'image/jpeg',
            'original_name' => 'test.jpg',
            'credit' => 'CC Attribution',
            'image_rights' => 'creative_commons',
            'site_id' => $this->siteId
        ]);

        $parser = new ImageBlockParser();
        $data = [
            'src' => 'test.jpg',
            'alt' => 'Alt text',
            'image_id' => $image->id
        ];

        $result = $parser->parse($data);

        $this->assertTrue($result['should_display_credit']);
    }

    public function testImageBlockParserHtmlIncludesCreditWhenRequired()
    {
        $image = Image::create([
            'url' => 'test',
            'file_size' => 1024,
            'filename' => 'test.jpg',
            'file_path' => '/uploads/test.jpg',
            'mime_type' => 'image/jpeg',
            'original_name' => 'test.jpg',
            'credit' => 'Required Credit',
            'image_rights' => 'attribution_required',
            'site_id' => $this->siteId
        ]);

        $parser = new ImageBlockParser();
        $parsed = $parser->parse([
            'src' => 'image.jpg',
            'alt' => 'Alt text',
            'image_id' => $image->id
        ]);

        $html = $parser->generateHtml($parsed);

        $this->assertStringContainsString('image-credit', $html);
        $this->assertStringContainsString('Required Credit', $html);
    }

    public function testImageBlockParserHtmlExcludesCreditWhenNotRequired()
    {
        $image = Image::create([
            'url' => 'test',
            'file_size' => 1024,
            'filename' => 'test.jpg',
            'file_path' => '/uploads/test.jpg',
            'mime_type' => 'image/jpeg',
            'original_name' => 'test.jpg',
            'credit' => 'Optional Credit',
            'image_rights' => 'all_rights_reserved',
            'site_id' => $this->siteId
        ]);

        $parser = new ImageBlockParser();
        $parsed = $parser->parse([
            'src' => 'image.jpg',
            'alt' => 'Alt text',
            'image_id' => $image->id
        ]);

        $html = $parser->generateHtml($parsed);

        $this->assertStringNotContainsString('image-credit', $html);
        $this->assertStringNotContainsString('Optional Credit', $html);
    }

    public function testImageBlockParserHandlesEndorsements()
    {
        $parser = new ImageBlockParser();
        $data = [
            'src' => 'test.jpg',
            'alt' => 'Alt text',
            'endorsements' => [
                'top-left' => [
                    'url' => 'endorsement1.jpg',
                    'alt' => 'Endorsement 1'
                ],
                'bottom-right' => [
                    'url' => 'endorsement2.jpg',
                    'alt' => 'Endorsement 2'
                ]
            ]
        ];

        $result = $parser->parse($data);

        $this->assertTrue($result['has_endorsements']);
        $this->assertCount(2, $result['endorsements']);
        $this->assertContains('top-left', $result['endorsement_positions']);
        $this->assertContains('bottom-right', $result['endorsement_positions']);
    }

    public function testImageBlockParserHtmlIncludesEndorsements()
    {
        $parser = new ImageBlockParser();
        $parsed = [
            'src' => 'image.jpg',
            'alt' => 'Alt text',
            'caption' => '',
            'formatted_caption' => '',
            'linkUrl' => '',
            'layout_css_class' => 'image-layout-full',
            'alignment_css_class' => 'image-align-center',
            'link_attributes' => [],
            'endorsements' => [
                'top-left' => [
                    'url' => 'endorsement.jpg',
                    'alt' => 'Endorsement'
                ]
            ],
            'has_endorsements' => true
        ];

        $html = $parser->generateHtml($parsed);

        $this->assertStringContainsString('endorsement-image', $html);
        $this->assertStringContainsString('top-left', $html);
        $this->assertStringContainsString('endorsement.jpg', $html);
    }
}