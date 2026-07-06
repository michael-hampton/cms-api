<?php

namespace App\Tests\Unit\Framework\Support\Config;

use App\Framework\Support\Config\ConfigModel;
use App\Framework\Support\Config\ConfigValidator;
use PHPUnit\Framework\TestCase;

final class ConfigValidatorTest extends TestCase
{
    public function test_valid_model_has_no_errors(): void
    {
        $model = ConfigModel::fromArray(['a' => 1, 'b' => 2]);
        $validator = new ConfigValidator();

        $this->assertSame([], $validator->validate($model));
        $this->assertTrue($validator->isValid($model));
    }

    public function test_rejects_empty_key(): void
    {
        $model = ConfigModel::fromPairs([['', 1]]);
        $errors = (new ConfigValidator())->validate($model);

        $this->assertCount(1, $errors);
        $this->assertSame('empty_key', $errors[0]->code);
    }

    public function test_rejects_whitespace_only_key(): void
    {
        $model = ConfigModel::fromPairs([['   ', 1]]);
        $errors = (new ConfigValidator())->validate($model);

        $this->assertCount(1, $errors);
        $this->assertSame('empty_key', $errors[0]->code);
    }

    public function test_flags_every_entry_involved_in_a_duplicate(): void
    {
        $model = ConfigModel::fromPairs([['a', 1], ['a', 2], ['b', 3]]);
        $errors = (new ConfigValidator())->validate($model);

        $this->assertCount(2, $errors);
        $this->assertSame(['duplicate_key', 'duplicate_key'], [$errors[0]->code, $errors[1]->code]);
        $this->assertFalse((new ConfigValidator())->isValid($model));
    }

    public function test_reports_both_empty_key_and_duplicate_errors_together(): void
    {
        $model = ConfigModel::fromPairs([['', 1], ['a', 2], ['a', 3]]);
        $errors = (new ConfigValidator())->validate($model);

        $codes = array_map(fn ($e) => $e->code, $errors);
        $this->assertContains('empty_key', $codes);
        $this->assertContains('duplicate_key', $codes);
    }
}