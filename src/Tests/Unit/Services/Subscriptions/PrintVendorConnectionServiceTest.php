<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Enums\Subscriptions\PrintVendorConnectionType;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\PrintVendorConnection;
use App\Repositories\Subscriptions\PrintVendorConnectionRepository;
use App\Services\Subscriptions\PrintVendorConnectionService;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\TestCase;

class PrintVendorConnectionServiceTest extends TestCase
{
    private $repository;
    private $database;
    private $logger;
    private PrintVendorConnectionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(PrintVendorConnectionRepository::class);
        $this->database = Mockery::mock(Database::class);
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $this->service = new PrintVendorConnectionService($this->repository, $this->database, $this->logger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function connection(array $attrs = []): PrintVendorConnection
    {
        $connection = Mockery::mock(PrintVendorConnection::class)->makePartial();
        $connection->id = $attrs['id'] ?? 1;
        $connection->is_default = $attrs['is_default'] ?? false;
        $connection->is_active = $attrs['is_active'] ?? true;
        $connection->connection_type = $attrs['connection_type'] ?? 'label';

        if (isset($attrs['type'])) {
            $connection->shouldReceive('type')->andReturn($attrs['type']);
        }

        return $connection;
    }

    // ── create() ─────────────────────────────────────────────────────────

    public function test_create_throws_when_code_already_exists(): void
    {
        $this->repository->shouldReceive('codeExists')->with('vendor-a')->andReturn(true);
        $this->database->shouldNotReceive('transaction');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("A vendor connection with code 'vendor-a' already exists.");

        $this->service->create(['code' => 'vendor-a', 'connection_type' => 'label']);
    }

    public function test_create_without_default_flag_skips_transaction(): void
    {
        $this->repository->shouldReceive('codeExists')->with('vendor-a')->andReturn(false);
        $this->database->shouldNotReceive('transaction');

        $created = $this->connection();
        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with(['code' => 'vendor-a', 'connection_type' => 'label'])
            ->andReturn($created);

        $result = $this->service->create(['code' => 'vendor-a', 'connection_type' => 'label']);

        $this->assertSame($created, $result);
    }

    public function test_create_as_default_clears_existing_default_inside_transaction(): void
    {
        // Regression coverage: this call previously went through the
        // static Database::runTransaction() facade, which cannot be
        // mocked (static method mocking isn't allowed), so this behaviour
        // had zero test coverage before the fix.
        $this->repository->shouldReceive('codeExists')->with('vendor-a')->andReturn(false);

        $created = $this->connection(['is_default' => true]);

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn (callable $callback) => $callback());

        $this->repository
            ->shouldReceive('clearDefaultForType')
            ->once()
            ->with(PrintVendorConnectionType::Label);

        $data = ['code' => 'vendor-a', 'connection_type' => 'label', 'is_default' => true];
        $this->repository->shouldReceive('create')->once()->with($data)->andReturn($created);

        $result = $this->service->create($data);

        $this->assertSame($created, $result);
    }

    public function test_create_as_default_does_not_clear_or_create_when_transaction_fails(): void
    {
        $this->repository->shouldReceive('codeExists')->andReturn(false);
        $this->repository->shouldNotReceive('clearDefaultForType');
        $this->repository->shouldNotReceive('create');

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andThrow(new \RuntimeException('could not open transaction'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('could not open transaction');

        $this->service->create(['code' => 'vendor-a', 'connection_type' => 'label', 'is_default' => true]);
    }

    // ── update() ─────────────────────────────────────────────────────────

    public function test_update_throws_when_connection_not_found(): void
    {
        $this->repository->shouldReceive('find')->with(1)->andReturn(null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Print vendor connection not found.');

        $this->service->update(1, ['code' => 'x']);
    }

    public function test_update_becoming_default_clears_others_inside_transaction(): void
    {
        $connection = $this->connection(['id' => 1, 'is_default' => false, 'type' => PrintVendorConnectionType::Label]);
        $updated = $this->connection(['id' => 1, 'is_default' => true]);

        $this->repository->shouldReceive('find')->with(1)->andReturn($connection);

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn (callable $callback) => $callback());

        $this->repository
            ->shouldReceive('clearDefaultForType')
            ->once()
            ->with(PrintVendorConnectionType::Label, 1);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with(1, ['is_default' => true])
            ->andReturn($updated);

        $result = $this->service->update(1, ['is_default' => true]);

        $this->assertSame($updated, $result);
    }

    public function test_update_blank_password_leaves_existing_password_unchanged(): void
    {
        $connection = $this->connection(['id' => 1, 'is_default' => false]);
        $updated = $this->connection(['id' => 1]);

        $this->repository->shouldReceive('find')->with(1)->andReturn($connection);
        $this->database->shouldNotReceive('transaction');

        $this->repository
            ->shouldReceive('codeExists')
            ->once()
            ->with('vendor-a', 1)
            ->andReturn(false);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with(1, ['code' => 'vendor-a'])
            ->andReturn($updated);

        $result = $this->service->update(1, ['code' => 'vendor-a', 'password' => '']);

        $this->assertSame($updated, $result);
    }

    // ── deactivate() ─────────────────────────────────────────────────────

    public function test_deactivate_throws_when_it_is_the_only_active_default(): void
    {
        $connection = $this->connection([
            'id' => 1,
            'is_default' => true,
            'is_active' => true,
            'type' => PrintVendorConnectionType::Label,
        ]);

        $this->repository->shouldReceive('find')->with(1)->andReturn($connection);
        $this->repository
            ->shouldReceive('findOtherActiveDefault')
            ->with(PrintVendorConnectionType::Label, 1)
            ->andReturn(null);
        $this->repository->shouldNotReceive('update');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Cannot deactivate the only active default connection for this pipeline type. Assign a new default first.'
        );

        $this->service->deactivate(1);
    }

    public function test_deactivate_succeeds_when_another_default_exists(): void
    {
        $connection = $this->connection([
            'id' => 1,
            'is_default' => true,
            'is_active' => true,
            'type' => PrintVendorConnectionType::Label,
        ]);
        $other = $this->connection(['id' => 2]);
        $updated = $this->connection(['id' => 1, 'is_active' => false]);

        $this->repository->shouldReceive('find')->with(1)->andReturn($connection);
        $this->repository
            ->shouldReceive('findOtherActiveDefault')
            ->with(PrintVendorConnectionType::Label, 1)
            ->andReturn($other);
        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with(1, ['is_active' => false])
            ->andReturn($updated);

        $result = $this->service->deactivate(1);

        $this->assertSame($updated, $result);
    }
}
