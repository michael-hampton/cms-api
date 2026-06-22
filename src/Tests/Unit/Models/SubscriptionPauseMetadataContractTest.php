<?php

namespace App\Tests\Unit\Models;

use App\Models\Subscription;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class SubscriptionPauseMetadataContractTest extends TestCase
{
    public function test_pause_metadata_is_fillable_and_cast_correctly(): void
    {
        $reflection = new ReflectionClass(Subscription::class);
        $subscription = $reflection->newInstanceWithoutConstructor();

        $fillableProperty = $reflection->getProperty('fillable');
        $fillableProperty->setAccessible(true);
        $fillable = $fillableProperty->getValue($subscription);

        $castsProperty = $reflection->getProperty('casts');
        $castsProperty->setAccessible(true);
        $casts = $castsProperty->getValue($subscription);

        foreach (['auto_renew_before_pause', 'paused_at', 'pause_until', 'resumed_at'] as $field) {
            self::assertContains($field, $fillable);
        }

        self::assertSame('boolean', $casts['auto_renew_before_pause']);
        self::assertSame('datetime', $casts['paused_at']);
        self::assertSame('datetime', $casts['pause_until']);
        self::assertSame('datetime', $casts['resumed_at']);
    }
}
