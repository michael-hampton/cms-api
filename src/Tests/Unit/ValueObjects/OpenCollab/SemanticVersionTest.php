<?php

namespace App\Tests\Unit\ValueObjects\OpenCollab;

use App\ValueObjects\OpenCollab\SemanticVersion;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SemanticVersionTest extends TestCase
{
    public function test_valid_version_is_preserved(): void
    {
        $version = SemanticVersion::fromString(' 1.10.3 ');

        $this->assertSame('1.10.3', $version->value());
        $this->assertSame('1.10.3', (string)$version);
    }

    #[DataProvider('invalidVersions')]
    public function test_invalid_version_is_rejected(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        SemanticVersion::fromString($value);
    }

    public static function invalidVersions(): array
    {
        return [
            [''],
            ['1'],
            ['1.0'],
            ['1.0.0.0'],
            ['1.a.0'],
            ['v1.0.0'],
        ];
    }
}
