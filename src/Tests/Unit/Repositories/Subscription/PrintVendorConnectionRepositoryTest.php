<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repositories\Subscription;

use App\Enums\Subscriptions\PrintVendorConnectionType;
use App\Models\PrintVendorConnection;
use App\Repositories\Subscriptions\PrintVendorConnectionRepository;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class PrintVendorConnectionRepositoryTest extends RepositoryTestCase
{
    private PrintVendorConnectionRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PrintVendorConnectionRepository();
    }

    private function createConnection(array $overrides = []): PrintVendorConnection
    {
        return PrintVendorConnection::create(array_merge([
            'name' => 'Test Vendor ' . uniqid(),
            'code' => 'vendor-' . uniqid(),
            'connection_type' => PrintVendorConnectionType::Label->value,
            'host' => 'sftp.example.com',
            'port' => 22,
            'username' => 'test-user',
            'password' => 'test-password',
            'remote_path' => '/labels',
            'is_active' => true,
            'is_default' => false,
        ], $overrides));
    }

    public function test_find_default_for_type_returns_the_active_default_connection(): void
    {
        $this->createConnection(['is_default' => false]);
        $default = $this->createConnection(['is_default' => true]);

        $result = $this->repository->findDefaultForType(PrintVendorConnectionType::Label);

        $this->assertNotNull($result);
        $this->assertSame($default->id, $result->id);
    }

    public function test_find_default_for_type_ignores_inactive_default_connection(): void
    {
        $this->createConnection(['is_default' => true, 'is_active' => false]);

        $result = $this->repository->findDefaultForType(PrintVendorConnectionType::Label);

        $this->assertNull($result);
    }

    public function test_find_default_for_type_matches_both_connections(): void
    {
        $default = $this->createConnection([
            'is_default' => true,
            'connection_type' => PrintVendorConnectionType::Both->value,
        ]);

        $result = $this->repository->findDefaultForType(PrintVendorConnectionType::Batch);

        $this->assertNotNull($result);
        $this->assertSame($default->id, $result->id);
    }

    public function test_clear_default_for_type_unsets_every_other_default(): void
    {
        $first = $this->createConnection(['is_default' => true]);
        $second = $this->createConnection(['is_default' => true]);

        $this->repository->clearDefaultForType(PrintVendorConnectionType::Label);

        $this->assertFalse((bool)$this->repository->find($first->id)->is_default);
        $this->assertFalse((bool)$this->repository->find($second->id)->is_default);
    }

    public function test_clear_default_for_type_can_exclude_a_connection(): void
    {
        $keep = $this->createConnection(['is_default' => true]);
        $clear = $this->createConnection(['is_default' => true]);

        $this->repository->clearDefaultForType(PrintVendorConnectionType::Label, $keep->id);

        $this->assertTrue((bool)$this->repository->find($keep->id)->is_default);
        $this->assertFalse((bool)$this->repository->find($clear->id)->is_default);
    }

    public function test_code_exists_detects_a_duplicate_code(): void
    {
        $connection = $this->createConnection(['code' => 'duplicate-code']);

        $this->assertTrue($this->repository->codeExists('duplicate-code'));
        $this->assertFalse($this->repository->codeExists('duplicate-code', $connection->id));
    }

//    public function test_password_is_encrypted_at_rest_and_decrypts_back_to_the_original(): void
//    {
//        $connection = $this->createConnection(['password' => 'plaintext-secret']);
//
//        // Raw (un-mutated) attribute as persisted — must not be the plaintext value.
//        $this->assertNotSame('plaintext-secret', $connection->getAttributes()['password']);
//
//        // Reloaded via a fresh query, the accessor must decrypt it back.
//        $reloaded = $this->repository->find($connection->id);
//        $this->assertSame('plaintext-secret', $reloaded->password);
//    }
}