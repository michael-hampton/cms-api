<?php

namespace App\Tests\Unit\Parsers;

use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\MaxRule;
use App\Framework\Validation\Rules\MinRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Framework\Validation\Validator;
use App\Parsers\AwardBlockParser;
use App\Parsers\Dtos\AwardBlockDto;
use App\Parsers\Renderers\AwardBlockRenderer;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class AwardBlockParserTest extends FunctionalTestCase
{
    public function testAwardParserGetType(): void
    {
        $parser = new AwardBlockParser();
        $this->assertSame('award', $parser->getType());
    }

    public function testAwardParserGetValidationRules(): void
    {
        $parser = new AwardBlockParser();
        $rules = $parser->getValidationRules();

        $this->assertArrayHasKey('subcategory', $rules);
        $this->assertContainsOnlyInstancesOf(RequiredRule::class, array_filter($rules['subcategory'], fn($r) => $r instanceof RequiredRule));

        $this->assertArrayHasKey('rating', $rules);
        $this->assertContainsOnlyInstancesOf(MinRule::class, array_filter($rules['rating'], fn($r) => $r instanceof MinRule));
        $this->assertContainsOnlyInstancesOf(MaxRule::class, array_filter($rules['rating'], fn($r) => $r instanceof MaxRule));

        $this->assertArrayHasKey('winner', $rules);
        $this->assertContainsOnlyInstancesOf(BooleanRule::class, array_filter($rules['winner'], fn($r) => $r instanceof BooleanRule));
    }

    public function testAwardBlockParserValidData()
    {
        $parser = new AwardBlockParser();
        $data = [
            'subcategory' => 'Best Tech',
            'productName' => 'Amazing Product',
            'image' => ['url' => 'test.jpg'],
            'caption' => 'Great product',
            'alt' => 'Product image',
            'winner' => true,
            'strapline' => 'The best choice',
            'rating' => 4.5
        ];

        $dto = AwardBlockDto::fromArray($data);
        $result = $dto->toArray();

        $this->assertEquals('Best Tech', $result['subcategory']);
        $this->assertEquals('Amazing Product', $result['productName']);
        $this->assertTrue($result['winner']);
        $this->assertEquals(4.5, $result['rating']);
        $this->assertArrayHasKey('caption_word_count', $result);
        $this->assertArrayHasKey('formatted_caption', $result);
    }

    public function testAwardParserGenerateHtml(): void
    {
        $parsedData = [
            'subcategory' => 'Best Value',
            'productName' => 'Item X',
            'image' => ['src' => '/award.png'],
            'alt' => 'Item X Award',
            'winner' => true,
            'strapline' => 'Amazing Value',
            'rating' => 4.0,
            'formatted_strapline' => 'Amazing Value',
            'caption' => '',
            'formatted_caption' => ''
        ];
        $dto = AwardBlockDto::fromArray($parsedData);
        $renderer = new AwardBlockRenderer();
        $html = $renderer->render($dto);

        $this->assertStringContainsString('<div class="award-block award-winner">', $html);
        $this->assertStringContainsString('<h3 class="award-product-name">Item X</h3>', $html);
        $this->assertStringContainsString('<div class="award-winner-badge">Winner</div>', $html);
        $this->assertStringContainsString('<div class="award-rating">Rating: 4/5</div>', $html);
    }

    public function testAwardNoSubcategory()
    {
        $parser = new AwardBlockParser();
        $validator = new Validator();

        $data = [
            'subcategory' => '',
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testAwardNoProductName()
    {
        $parser = new AwardBlockParser();
        $validator = new Validator();

        $data = [
            'subcategory' => 'test',
            'productName' => '',
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testAwardBlockParserRequiredFields()
    {
        $parser = new AwardBlockParser();
        $validator = new Validator();

        $data = [
            // Missing required fields: subcategory, productName, alt
            'caption' => 'Test'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testAwardBlockParserSubcategoryMaxLength()
    {
        $parser = new AwardBlockParser();
        $validator = new Validator();

        $data = [
            'subcategory' => str_repeat('a', 256), // Exceeds 255
            'productName' => 'Product',
            'alt' => 'Alt text'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testAwardBlockParserProductNameMaxLength()
    {
        $parser = new AwardBlockParser();
        $validator = new Validator();

        $data = [
            'subcategory' => 'Category',
            'productName' => str_repeat('a', 256), // Exceeds 255
            'alt' => 'Alt text'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testAwardBlockParserCaptionMaxLength()
    {
        $parser = new AwardBlockParser();
        $validator = new Validator();

        $data = [
            'subcategory' => 'Category',
            'productName' => 'Product',
            'alt' => 'Alt text',
            'caption' => str_repeat('a', 501) // Exceeds 500
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testAwardBlockParserRatingRange()
    {
        $parser = new AwardBlockParser();
        $validator = new Validator();

        // Test rating too low
        $data = [
            'subcategory' => 'Category',
            'productName' => 'Product',
            'alt' => 'Alt text',
            'rating' => -1
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);
        $this->assertFalse($result->isValid());

        // Test rating too high
        $data['rating'] = 6;
        $result = $validator->validate($data, $rules);
        $this->assertFalse($result->isValid());

        // Test valid rating
        $data['rating'] = 4;

        $result = $validator->validate($data, $rules);

        $this->assertTrue($result->isValid());
    }

    public function testAwardBlockParserWinnerBoolean()
    {
        $parser = new AwardBlockParser();
        $validator = new Validator();

        $data = [
            'subcategory' => 'Category',
            'productName' => 'Product',
            'alt' => 'Alt text',
            'winner' => 'not_a_boolean'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testAwardBlockParserImageArray()
    {
        $parser = new AwardBlockParser();
        $validator = new Validator();

        $data = [
            'subcategory' => 'Category',
            'productName' => 'Product',
            'alt' => 'Alt text',
            'image' => 'not_an_array'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }
}