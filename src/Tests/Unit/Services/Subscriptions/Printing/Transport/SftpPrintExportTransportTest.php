<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\Printing\Transport;

use App\Framework\Support\Logger;
use App\Services\Subscriptions\Printing\Transport\SftpPrintExportTransport;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

/**
 * Focused coverage for the withConnection() failure/logging path added
 * alongside SftpLabelExportTransport's equivalent fix. This class had no
 * existing test file; a full behavioural suite (upload/retry/backoff) is a
 * separate, larger piece of work and out of scope here — this covers only
 * the fix made in this pass.
 */
class SftpPrintExportTransportTest extends TestCase
{
    private Logger&MockInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = Mockery::mock(Logger::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_with_connection_logs_a_warning_and_returns_null_when_the_connection_throws(): void
    {
        // Partial mock overriding connect() so this stays a unit test with
        // no real network call — only the connect() seam is faked; the
        // withConnection()/try-catch/logging logic under test is real.
        $transport = Mockery::mock(
            SftpPrintExportTransport::class . '[connect]',
            [
                'sftp.unreachable.example.com',
                22,
                'user',
                'secret',
                '/exports',
                $this->logger,
            ],
        )->shouldAllowMockingProtectedMethods();

        $transport->shouldReceive('connect')
            ->once()
            ->andThrow(new RuntimeException('connection refused'));

        $this->logger
            ->shouldReceive('warning')
            ->once()
            ->with(
                'SftpPrintExportTransport: read-only connection failed',
                Mockery::on(fn (array $context) => $context['host'] === 'sftp.unreachable.example.com'
                    && $context['port'] === 22
                    && $context['error'] === 'connection refused'),
            );

        $method = new ReflectionMethod(SftpPrintExportTransport::class, 'withConnection');
        $method->setAccessible(true);

        $result = $method->invoke($transport, fn () => 'unreachable');

        $this->assertNull($result);
    }
}
