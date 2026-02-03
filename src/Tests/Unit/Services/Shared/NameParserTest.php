<?php

namespace App\Tests\Unit\Services\Shared;

use App\Services\Shared\NameParser;
use PHPUnit\Framework\TestCase;

class NameParserTest extends TestCase
{
    private NameParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new NameParser();
    }

    public function test_it_parses_single_name()
    {
        $result = $this->parser->parse('John');

        $this->assertEquals([
            'first_name' => 'John',
            'last_name' => ''
        ], $result);
    }

    public function test_it_parses_two_part_name()
    {
        $result = $this->parser->parse('John Doe');

        $this->assertEquals([
            'first_name' => 'John',
            'last_name' => 'Doe'
        ], $result);
    }

    public function test_it_parses_three_part_name()
    {
        $result = $this->parser->parse('John Michael Doe');

        $this->assertEquals([
            'first_name' => 'John',
            'last_name' => 'Michael Doe'
        ], $result);
    }

    public function test_it_parses_hyphenated_last_name()
    {
        $result = $this->parser->parse('Mary Jane Parker-Watson');

        $this->assertEquals([
            'first_name' => 'Mary',
            'last_name' => 'Jane Parker-Watson'
        ], $result);
    }

    public function test_it_parses_name_with_prefix()
    {
        $result = $this->parser->parse('John van der Berg');

        $this->assertEquals([
            'first_name' => 'John',
            'last_name' => 'van der Berg'
        ], $result);
    }

    public function test_it_returns_empty_array_for_empty_string()
    {
        $result = $this->parser->parse('');

        $this->assertEquals([], $result);
    }

    public function test_it_returns_empty_array_for_whitespace_only()
    {
        $result = $this->parser->parse('   ');

        $this->assertEquals([], $result);
    }

    public function test_it_handles_extra_whitespace()
    {
        $result = $this->parser->parse('  John   Doe  ');

        $this->assertEquals([
            'first_name' => 'John',
            'last_name' => 'Doe'
        ], $result);
    }

    public function test_it_handles_multiple_spaces_between_names()
    {
        $result = $this->parser->parse('John    Michael    Doe');

        $this->assertEquals([
            'first_name' => 'John',
            'last_name' => 'Michael Doe'
        ], $result);
    }

    public function test_it_parses_single_name_with_middle_name_parser()
    {
        $result = $this->parser->parseWithMiddle('John');

        $this->assertEquals([
            'first_name' => 'John',
            'middle_name' => '',
            'last_name' => ''
        ], $result);
    }

    public function test_it_parses_two_part_name_with_middle_name_parser()
    {
        $result = $this->parser->parseWithMiddle('John Doe');

        $this->assertEquals([
            'first_name' => 'John',
            'middle_name' => '',
            'last_name' => 'Doe'
        ], $result);
    }

    public function test_it_parses_three_part_name_with_middle_name()
    {
        $result = $this->parser->parseWithMiddle('John Michael Doe');

        $this->assertEquals([
            'first_name' => 'John',
            'middle_name' => 'Michael',
            'last_name' => 'Doe'
        ], $result);
    }

    public function test_it_parses_four_part_name_with_multiple_middle_names()
    {
        $result = $this->parser->parseWithMiddle('John Michael James Doe');

        $this->assertEquals([
            'first_name' => 'John',
            'middle_name' => 'Michael James',
            'last_name' => 'Doe'
        ], $result);
    }

    public function test_it_parses_five_part_name_with_prefix()
    {
        $result = $this->parser->parseWithMiddle('Mary Jane Elizabeth van der Berg');

        $this->assertEquals([
            'first_name' => 'Mary',
            'middle_name' => 'Jane Elizabeth van der',
            'last_name' => 'Berg'
        ], $result);
    }

    public function test_it_returns_empty_array_for_empty_string_with_middle_parser()
    {
        $result = $this->parser->parseWithMiddle('');

        $this->assertEquals([], $result);
    }

    public function test_it_handles_extra_whitespace_with_middle_parser()
    {
        $result = $this->parser->parseWithMiddle('  John   Michael   Doe  ');

        $this->assertEquals([
            'first_name' => 'John',
            'middle_name' => 'Michael',
            'last_name' => 'Doe'
        ], $result);
    }

    public function test_it_preserves_case_sensitivity()
    {
        $result = $this->parser->parse('JOHN MICHAEL DOE');

        $this->assertEquals([
            'first_name' => 'JOHN',
            'last_name' => 'MICHAEL DOE'
        ], $result);
    }

    public function test_it_handles_special_characters_in_names()
    {
        $result = $this->parser->parse("Jean-Luc O'Brien");

        $this->assertEquals([
            'first_name' => 'Jean-Luc',
            'last_name' => "O'Brien"
        ], $result);
    }

    public function test_it_handles_unicode_characters()
    {
        $result = $this->parser->parse('José María García');

        $this->assertEquals([
            'first_name' => 'José',
            'last_name' => 'María García'
        ], $result);
    }
}