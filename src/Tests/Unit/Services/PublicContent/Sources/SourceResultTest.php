<?php

namespace App\Tests\Unit\Services\PublicContent\Sources;

use App\DTO\PublicContent\Sources\SourceResult;
use App\Enums\PublicContent\SourceResultStatus;
use App\Framework\Support\Collection;
use PHPUnit\Framework\TestCase;

final class SourceResultTest extends TestCase
{
    public function test_ok_empty_and_degraded_are_distinguishable(): void
    {
        $ok = SourceResult::ok(['a']);
        $empty = SourceResult::empty();
        $degraded = SourceResult::degraded('timeout');

        self::assertTrue($ok->isOk());
        self::assertSame(SourceResultStatus::Ok, $ok->status);
        self::assertSame(['a'], $ok->items());

        self::assertTrue($empty->isEmpty());
        self::assertFalse($empty->isDegraded());
        self::assertSame([], $empty->items());

        self::assertTrue($degraded->isDegraded());
        self::assertFalse($degraded->isEmpty());
        self::assertSame('timeout', $degraded->reason);
        self::assertSame([], $degraded->items());
    }

    public function test_items_normalises_collections(): void
    {
        $result = SourceResult::ok(new Collection(['one', 'two']));

        self::assertSame(['one', 'two'], $result->items());
    }
}
