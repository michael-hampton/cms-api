<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support;

use App\Tests\Support\TestDatabase;
use App\Tests\Unit\UnitTestCase;
use RuntimeException;

final class TestDatabaseGuardTest extends UnitTestCase
{
    public function test_it_allows_default_test_database_name(): void
    {
        TestDatabase::assertSafeConfig(['database' => 'test_db']);
        $this->assertTrue(true);
    }

    public function test_it_allows_names_containing_test(): void
    {
        TestDatabase::assertSafeConfig(['database' => 'test_db_worker_1']);
        $this->assertTrue(true);
    }

    public function test_it_rejects_production_mydb(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('mydb');
        TestDatabase::assertSafeConfig(['database' => 'mydb']);
    }

    public function test_it_rejects_names_without_test(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must contain "test"');
        TestDatabase::assertSafeConfig(['database' => 'app_database']);
    }

    public function test_it_rejects_empty_name(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('empty');
        TestDatabase::assertSafeConfig(['database' => '']);
    }
}
