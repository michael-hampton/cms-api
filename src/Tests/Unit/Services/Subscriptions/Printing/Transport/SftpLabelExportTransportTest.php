<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\Printing\Transport;

use App\Enums\Subscriptions\PrintVendorConnectionType;
use App\Framework\Support\Logger;
use App\Models\PrintVendorConnection;
use App\Repositories\Subscriptions\PrintVendorConnectionRepository;
use App\Services\Subscriptions\Printing\Transport\SftpLabelExportTransport;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

class SftpLabelExportTransportTest extends TestCase
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

    public function test_from_vendor_connection_builds_identifier_from_host_and_path(): void
    {
        $connection = $this->makeConnection([
            'host' => 'sftp.vendor-a.example.com',
            'port' => 2222,
            'username' => 'vendor-a-user',
            'password' => 'super-secret',
            'remote_path' => '/incoming/labels',
        ]);

        $transport = SftpLabelExportTransport::fromVendorConnection($connection, $this->logger);

        $this->assertSame('sftp://sftp.vendor-a.example.com/incoming/labels', $transport->identifier());
    }

    public function test_from_default_resolves_the_active_default_label_connection(): void
    {
        $connection = $this->makeConnection([
            'host' => 'sftp.vendor-b.example.com',
            'remote_path' => '/labels',
        ]);

        $repository = Mockery::mock(PrintVendorConnectionRepository::class);
        $repository->shouldReceive('findDefaultForType')
            ->once()
            ->with(PrintVendorConnectionType::Label)
            ->andReturn($connection);

        $transport = SftpLabelExportTransport::fromDefault($repository, $this->logger);

        $this->assertSame('sftp://sftp.vendor-b.example.com/labels', $transport->identifier());
    }

    public function test_from_default_throws_when_no_active_default_connection_is_configured(): void
    {
        $repository = Mockery::mock(PrintVendorConnectionRepository::class);
        $repository->shouldReceive('findDefaultForType')
            ->once()
            ->with(PrintVendorConnectionType::Label)
            ->andReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No active default print vendor connection is configured for the label pipeline.');

        SftpLabelExportTransport::fromDefault($repository, $this->logger);
    }

    // ── withConnection(): failures are now logged, not silent ──────────

    public function test_with_connection_logs_a_warning_and_returns_null_when_the_connection_throws(): void
    {
        $connection = $this->makeConnection(['host' => 'sftp.unreachable.example.com', 'port' => 22]);

        // Partial mock overriding connect() so this stays a unit test with
        // no real network call — only the connect() seam is faked; the
        // withConnection()/try-catch/logging logic under test is real.
        $transport = Mockery::mock(
            SftpLabelExportTransport::class . '[connect]',
            [
                $connection->host,
                $connection->port,
                $connection->username,
                $connection->password,
                $connection->remote_path,
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
                'SftpLabelExportTransport: read-only connection failed',
                Mockery::on(fn (array $context) => $context['host'] === 'sftp.unreachable.example.com'
                    && $context['port'] === 22
                    && $context['error'] === 'connection refused'),
            );

        $method = new ReflectionMethod(SftpLabelExportTransport::class, 'withConnection');
        $method->setAccessible(true);

        $result = $method->invoke($transport, fn () => 'unreachable');

        $this->assertNull($result);
    }

    private function makeConnection(array $overrides = []): MockInterface
    {
        $connection = Mockery::mock(PrintVendorConnection::class)->makePartial();

        $attributes = array_merge([
            'host' => 'sftp.example.com',
            'port' => 22,
            'username' => 'user',
            'password' => 'secret',
            'remote_path' => '/labels',
        ], $overrides);

        foreach ($attributes as $key => $value) {
            $connection->{$key} = $value;
        }

        return $connection;
    }
}