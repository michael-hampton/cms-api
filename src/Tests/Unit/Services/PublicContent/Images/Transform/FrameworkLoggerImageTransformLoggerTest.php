<?php

namespace App\Tests\Unit\Services\PublicContent\Images\Transform;

use App\Framework\Support\Logger;
use App\Services\PublicContent\Images\Transform\FrameworkLoggerImageTransformLogger;
use Mockery;
use PHPUnit\Framework\TestCase;

final class FrameworkLoggerImageTransformLoggerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_delegates_warnings_to_the_framework_logger(): void
    {
        $logger = Mockery::mock(Logger::class);
        $logger->shouldReceive('warning')->once()->with('unrecognised host', ['url' => 'https://x.test']);

        $transformLogger = new FrameworkLoggerImageTransformLogger($logger);
        $transformLogger->warning('unrecognised host', ['url' => 'https://x.test']);

        self::assertTrue(true, 'Logger::warning was called with the expected arguments.');
    }

    public function test_it_defaults_context_to_an_empty_array(): void
    {
        $logger = Mockery::mock(Logger::class);
        $logger->shouldReceive('warning')->once()->with('message', []);

        $transformLogger = new FrameworkLoggerImageTransformLogger($logger);
        $transformLogger->warning('message');

        self::assertTrue(true, 'Logger::warning was called with a default empty context.');
    }
}