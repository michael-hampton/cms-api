<?php

namespace App\Tests\Unit\Framework\Support\Cache;

use App\Framework\Database\Database;
use App\Framework\Support\Cache\Drivers\DatabaseCacheDriver;
use App\Repositories\Cache\CacheStoreRepository;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class DatabaseCacheDriverTest extends RepositoryTestCase
{
    private DatabaseCacheDriver $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = new DatabaseCacheDriver(
            new CacheStoreRepository(Database::getInstance())
        );
        $this->cache->flush();
    }

    public function test_put_get_overwrite_and_delete(): void
    {
        $this->cache->put('cache:test:key', ['a' => 1], 60);

        $this->assertSame(['a' => 1], $this->cache->get('cache:test:key'));
        $this->assertTrue($this->cache->has('cache:test:key'));

        $this->cache->put('cache:test:key', ['a' => 2], 60);
        $this->assertSame(['a' => 2], $this->cache->get('cache:test:key'));

        $this->cache->forget('cache:test:key');
        $this->assertNull($this->cache->get('cache:test:key'));
        $this->assertFalse($this->cache->has('cache:test:key'));
    }

    public function test_remember_runs_callback_only_on_miss(): void
    {
        $calls = 0;

        $first = $this->cache->remember('cache:test:remember', 60, function () use (&$calls) {
            $calls++;
            return 'built';
        });

        $second = $this->cache->remember('cache:test:remember', 60, function () use (&$calls) {
            $calls++;
            return 'rebuilt';
        });

        $this->assertSame('built', $first);
        $this->assertSame('built', $second);
        $this->assertSame(1, $calls);
    }

    public function test_expired_entries_are_not_returned(): void
    {
        $this->cache->put('cache:test:expired', 'gone', -1);

        $this->assertNull($this->cache->get('cache:test:expired'));
    }

    public function test_forget_many_removes_multiple_keys(): void
    {
        $this->cache->put('cache:test:one', 'one', 60);
        $this->cache->put('cache:test:two', 'two', 60);
        $this->cache->put('cache:test:three', 'three', 60);

        $this->cache->forgetMany(['cache:test:one', 'cache:test:two']);

        $this->assertNull($this->cache->get('cache:test:one'));
        $this->assertNull($this->cache->get('cache:test:two'));
        $this->assertSame('three', $this->cache->get('cache:test:three'));
    }

    public function test_objects_can_be_stored_and_restored(): void
    {
        $value = (object) ['name' => 'bundle', 'permissions' => ['content.create']];

        $this->cache->put('cache:test:object', $value, 60);

        $restored = $this->cache->get('cache:test:object');

        $this->assertEquals($value, $restored);
    }
}
