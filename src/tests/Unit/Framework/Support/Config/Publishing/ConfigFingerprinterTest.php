<?php

namespace App\Tests\Unit\Framework\Support\Config\Publishing;

use App\Framework\Support\Config\ConfigEntry;
use App\Framework\Support\Config\ConfigModel;
use App\Framework\Support\Config\Publishing\ConfigFingerprinter;
use PHPUnit\Framework\TestCase;

final class ConfigFingerprinterTest extends TestCase
{
    public function test_same_content_same_order_produces_same_fingerprint(): void
    {
        $fp = new ConfigFingerprinter();
        $a = ConfigModel::fromArray(['a' => 1, 'b' => 2]);
        $b = ConfigModel::fromArray(['a' => 1, 'b' => 2]);

        $this->assertSame($fp->fingerprint($a), $fp->fingerprint($b));
    }

    public function test_semantically_identical_documents_in_different_order_produce_the_same_fingerprint(): void
    {
        $fp = new ConfigFingerprinter();
        $a = ConfigModel::fromArray(['a' => 1, 'b' => 2]);
        $b = ConfigModel::fromArray(['b' => 2, 'a' => 1]);

        $this->assertSame($fp->fingerprint($a), $fp->fingerprint($b));
    }

    public function test_different_entry_ids_do_not_affect_the_fingerprint(): void
    {
        $fp = new ConfigFingerprinter();
        $a = ConfigModel::fromPairs([['a', 1]]);
        $b = new ConfigModel([new ConfigEntry('a', 1, 'totally-different-id')]);

        $this->assertSame($fp->fingerprint($a), $fp->fingerprint($b));
    }

    public function test_different_value_produces_a_different_fingerprint(): void
    {
        $fp = new ConfigFingerprinter();
        $a = ConfigModel::fromArray(['a' => 1]);
        $b = ConfigModel::fromArray(['a' => 2]);

        $this->assertNotSame($fp->fingerprint($a), $fp->fingerprint($b));
    }

    public function test_extra_or_missing_key_produces_a_different_fingerprint(): void
    {
        $fp = new ConfigFingerprinter();
        $a = ConfigModel::fromArray(['a' => 1]);
        $b = ConfigModel::fromArray(['a' => 1, 'b' => 2]);

        $this->assertNotSame($fp->fingerprint($a), $fp->fingerprint($b));
    }

    public function test_nested_array_key_order_does_not_affect_fingerprint(): void
    {
        $fp = new ConfigFingerprinter();
        $a = ConfigModel::fromArray(['a' => ['x' => 1, 'y' => 2]]);
        $b = ConfigModel::fromArray(['a' => ['y' => 2, 'x' => 1]]);

        $this->assertSame($fp->fingerprint($a), $fp->fingerprint($b));
    }

    public function test_list_order_does_affect_fingerprint(): void
    {
        $fp = new ConfigFingerprinter();
        $a = ConfigModel::fromArray(['a' => [1, 2, 3]]);
        $b = ConfigModel::fromArray(['a' => [3, 2, 1]]);

        $this->assertNotSame($fp->fingerprint($a), $fp->fingerprint($b));
    }

    public function test_is_deterministic_across_repeated_calls(): void
    {
        $fp = new ConfigFingerprinter();
        $model = ConfigModel::fromArray(['a' => 1, 'b' => ['x' => 1]]);

        $this->assertSame($fp->fingerprint($model), $fp->fingerprint($model));
    }

    public function test_empty_model_has_a_stable_fingerprint(): void
    {
        $fp = new ConfigFingerprinter();

        $this->assertSame($fp->fingerprint(new ConfigModel()), $fp->fingerprint(new ConfigModel()));
    }
}