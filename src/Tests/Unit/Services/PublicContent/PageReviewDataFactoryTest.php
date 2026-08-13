<?php

namespace App\Tests\Unit\Services\PublicContent;

use App\DTO\PublicContent\PageReviewData;
use App\Models\Page;
use App\Services\PublicContent\PageReviewDataFactory;
use Mockery;
use PHPUnit\Framework\TestCase;

final class PageReviewDataFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_from_page_returns_null_when_review_data_is_not_an_array(): void
    {
        $factory = new PageReviewDataFactory();

        self::assertNull($factory->fromPage($this->page(null)));
        self::assertNull($factory->fromPage($this->page('not-an-array')));
    }

    public function test_from_page_returns_null_when_review_data_is_an_empty_array(): void
    {
        $factory = new PageReviewDataFactory();

        self::assertNull($factory->fromPage($this->page([])));
    }

    public function test_from_page_builds_review_data_from_a_populated_array(): void
    {
        $factory = new PageReviewDataFactory();

        $result = $factory->fromPage($this->page([
            'rating' => 4.5,
            'max_rating' => 5,
            'sub_rating' => 3.5,
            'product' => 'Widget X',
            'category' => 'Gadgets',
            'verdict' => 'Great buy',
            'pros' => ['Fast', 'Cheap'],
            'cons' => ['Loud'],
        ]));

        self::assertInstanceOf(PageReviewData::class, $result);
        self::assertSame(4.5, $result->rating);
        self::assertSame(5, $result->maxRating);
        self::assertSame(3.5, $result->subRating);
        self::assertSame('Widget X', $result->product);
        self::assertSame('Gadgets', $result->category);
        self::assertSame('Great buy', $result->verdict);
        self::assertSame(['Fast', 'Cheap'], $result->pros);
        self::assertSame(['Loud'], $result->cons);
    }

    public function test_from_array_clamps_rating_and_sub_rating_between_zero_and_five(): void
    {
        $factory = new PageReviewDataFactory();

        $tooHigh = $factory->fromArray(['rating' => 10, 'sub_rating' => -3]);

        self::assertSame(5.0, $tooHigh->rating);
        self::assertSame(0.0, $tooHigh->subRating);
    }

    public function test_from_array_accepts_camel_case_keys_as_a_fallback(): void
    {
        $factory = new PageReviewDataFactory();

        $result = $factory->fromArray(['maxRating' => 10, 'subRating' => 2.5]);

        self::assertSame(10, $result->maxRating);
        self::assertSame(2.5, $result->subRating);
    }

    public function test_from_array_defaults_max_rating_to_five_and_enforces_a_minimum_of_one(): void
    {
        $factory = new PageReviewDataFactory();

        self::assertSame(5, $factory->fromArray([])->maxRating);
        self::assertSame(1, $factory->fromArray(['max_rating' => 0])->maxRating);
    }

    public function test_from_array_trims_and_drops_blank_product_and_category(): void
    {
        $factory = new PageReviewDataFactory();

        $result = $factory->fromArray(['product' => '   ', 'category' => '  Gadgets  ']);

        self::assertNull($result->product);
        self::assertSame('Gadgets', $result->category);
    }

    public function test_from_array_filters_blank_entries_out_of_pros_and_cons(): void
    {
        $factory = new PageReviewDataFactory();

        $result = $factory->fromArray(['pros' => ['Fast', '  ', 'Cheap'], 'cons' => 'not-an-array']);

        self::assertSame(['Fast', 'Cheap'], $result->pros);
        self::assertSame([], $result->cons);
    }

    public function test_from_array_defaults_verdict_to_an_empty_string(): void
    {
        $factory = new PageReviewDataFactory();

        self::assertSame('', $factory->fromArray([])->verdict);
    }

    private function page(mixed $reviewData): Page
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->review_data = $reviewData;

        return $page;
    }
}