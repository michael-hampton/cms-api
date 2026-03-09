<?php

namespace App\Tests\Unit\Actions\Offers;

use App\Actions\Offers\BulkDeleteBundles;
use App\Actions\Offers\BulkPublishBundles;
use App\Framework\Database\Database;
use App\Models\ProductOfferBundle;
use App\Repositories\Offers\ProductOfferBundleRepository;
use Exception;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class BulkBundleActionsTest extends TestCase
{
    private MockInterface|ProductOfferBundleRepository $bundleRepository;
    private MockInterface|Database $database;

    public function test_bulk_publish_bundles_publishes_pending_bundles(): void
    {
        $bundle1 = $this->makePendingBundle(1);
        $bundle2 = $this->makePendingBundle(2);

        $this->bundleRepository->shouldReceive('find')->with(1)->once()->andReturn($bundle1);
        $this->bundleRepository->shouldReceive('find')->with(2)->once()->andReturn($bundle2);

        $this->bundleRepository->shouldReceive('update')
            ->with(1, Mockery::on(fn($d) => $d['status'] === 'published' && isset($d['published_at']) && $d['published_by'] === 5))
            ->once();

        $this->bundleRepository->shouldReceive('update')
            ->with(2, Mockery::on(fn($d) => $d['status'] === 'published' && $d['published_by'] === 5))
            ->once();

        $action = new BulkPublishBundles($this->bundleRepository, $this->database);
        $result = $action->handle([1, 2], 5);

        $this->assertEquals([1, 2], $result['published']);
        $this->assertEmpty($result['failed']);
        $this->assertEquals(2, $result['total']);
    }

    private function makePendingBundle(int $id): ProductOfferBundle
    {
        return $this->makeBundle($id, 'pending');
    }

    // =========================================================================
    // BulkPublishBundles
    // =========================================================================

    private function makeBundle(int $id, string $status): ProductOfferBundle
    {
        $bundle = Mockery::mock(ProductOfferBundle::class)->makePartial();
        $bundle->id = $id;
        $bundle->status = $status;

        return $bundle;
    }

    public function test_bulk_publish_bundles_skips_non_pending_bundles(): void
    {
        $pending = $this->makePendingBundle(1);
        $rejected = $this->makeBundle(2, 'rejected');

        $this->bundleRepository->shouldReceive('find')->with(1)->once()->andReturn($pending);
        $this->bundleRepository->shouldReceive('find')->with(2)->once()->andReturn($rejected);

        $this->bundleRepository->shouldReceive('update')->with(1, Mockery::any())->once();
        $this->bundleRepository->shouldNotReceive('update')->with(2, Mockery::any());

        $action = new BulkPublishBundles($this->bundleRepository, $this->database);
        $result = $action->handle([1, 2], 5);

        $this->assertEquals([1], $result['published']);
        $this->assertCount(1, $result['failed']);
        $this->assertStringContainsString('rejected', $result['failed'][0]['reason']);
    }

    public function test_bulk_publish_bundles_records_not_found_as_failed(): void
    {
        $this->bundleRepository->shouldReceive('find')->with(99)->once()->andReturn(null);
        $this->bundleRepository->shouldNotReceive('update');

        $action = new BulkPublishBundles($this->bundleRepository, $this->database);
        $result = $action->handle([99], 1);

        $this->assertEmpty($result['published']);
        $this->assertCount(1, $result['failed']);
        $this->assertEquals(99, $result['failed'][0]['id']);
        $this->assertStringContainsString('not found', $result['failed'][0]['reason']);
    }

    public function test_bulk_publish_bundles_throws_when_ids_empty(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No bundle IDs provided');

        $action = new BulkPublishBundles($this->bundleRepository, $this->database);
        $action->handle([], 1);
    }

    public function test_bulk_publish_bundles_wraps_in_transaction(): void
    {
        $database = Mockery::mock(Database::class);
        $database->shouldReceive('transaction')->once()->andReturnUsing(fn(callable $cb) => $cb());

        $bundle = $this->makePendingBundle(1);
        $this->bundleRepository->shouldReceive('find')->with(1)->andReturn($bundle);
        $this->bundleRepository->shouldReceive('update')->once();

        $action = new BulkPublishBundles($this->bundleRepository, $database);
        $action->handle([1], 1);
        $this->assertTrue(true);
    }

    public function test_bulk_publish_bundles_continues_after_individual_failure(): void
    {
        $bundle1 = $this->makePendingBundle(1);
        $bundle2 = $this->makePendingBundle(2);

        $this->bundleRepository->shouldReceive('find')->with(1)->once()->andReturn($bundle1);
        $this->bundleRepository->shouldReceive('find')->with(2)->once()->andReturn($bundle2);

        $this->bundleRepository->shouldReceive('update')
            ->with(1, Mockery::any())
            ->once()
            ->andThrow(new Exception('DB error'));

        $this->bundleRepository->shouldReceive('update')
            ->with(2, Mockery::any())
            ->once();

        $action = new BulkPublishBundles($this->bundleRepository, $this->database);
        $result = $action->handle([1, 2], 1);

        $this->assertEquals([2], $result['published']);
        $this->assertCount(1, $result['failed']);
        $this->assertEquals(1, $result['failed'][0]['id']);
    }

    // =========================================================================
    // BulkDeleteBundles
    // =========================================================================

    public function test_bulk_delete_bundles_deletes_existing_bundles(): void
    {
        $bundle1 = $this->makeBundle(1, 'pending');
        $bundle2 = $this->makeBundle(2, 'rejected');

        $this->bundleRepository->shouldReceive('find')->with(1)->once()->andReturn($bundle1);
        $this->bundleRepository->shouldReceive('find')->with(2)->once()->andReturn($bundle2);
        $this->bundleRepository->shouldReceive('delete')->with(1)->once()->andReturn(true);
        $this->bundleRepository->shouldReceive('delete')->with(2)->once()->andReturn(true);

        $action = new BulkDeleteBundles($this->bundleRepository, $this->database);
        $result = $action->handle([1, 2]);

        $this->assertEquals([1, 2], $result['deleted']);
        $this->assertEmpty($result['failed']);
        $this->assertEquals(2, $result['total']);
    }

    public function test_bulk_delete_bundles_records_not_found_as_failed(): void
    {
        $this->bundleRepository->shouldReceive('find')->with(99)->once()->andReturn(null);
        $this->bundleRepository->shouldNotReceive('delete');

        $action = new BulkDeleteBundles($this->bundleRepository, $this->database);
        $result = $action->handle([99]);

        $this->assertEmpty($result['deleted']);
        $this->assertCount(1, $result['failed']);
        $this->assertEquals(99, $result['failed'][0]['id']);
        $this->assertStringContainsString('not found', $result['failed'][0]['reason']);
    }

    public function test_bulk_delete_bundles_records_delete_failure(): void
    {
        $bundle = $this->makeBundle(1, 'pending');

        $this->bundleRepository->shouldReceive('find')->with(1)->once()->andReturn($bundle);
        $this->bundleRepository->shouldReceive('delete')->with(1)->once()->andReturn(false);

        $action = new BulkDeleteBundles($this->bundleRepository, $this->database);
        $result = $action->handle([1]);

        $this->assertEmpty($result['deleted']);
        $this->assertCount(1, $result['failed']);
        $this->assertStringContainsString('Delete failed', $result['failed'][0]['reason']);
    }

    public function test_bulk_delete_bundles_throws_when_ids_empty(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No bundle IDs provided');

        $action = new BulkDeleteBundles($this->bundleRepository, $this->database);
        $action->handle([]);
    }

    public function test_bulk_delete_bundles_wraps_in_transaction(): void
    {
        $database = Mockery::mock(Database::class);
        $database->shouldReceive('transaction')->once()->andReturnUsing(fn(callable $cb) => $cb());

        $bundle = $this->makeBundle(1, 'pending');
        $this->bundleRepository->shouldReceive('find')->with(1)->andReturn($bundle);
        $this->bundleRepository->shouldReceive('delete')->with(1)->andReturn(true);

        $action = new BulkDeleteBundles($this->bundleRepository, $database);
        $action->handle([1]);
        $this->assertTrue(true);
    }

    public function test_bulk_delete_bundles_continues_after_individual_exception(): void
    {
        $bundle1 = $this->makeBundle(1, 'pending');
        $bundle2 = $this->makeBundle(2, 'pending');

        $this->bundleRepository->shouldReceive('find')->with(1)->once()->andReturn($bundle1);
        $this->bundleRepository->shouldReceive('find')->with(2)->once()->andReturn($bundle2);

        $this->bundleRepository->shouldReceive('delete')->with(1)->once()->andThrow(new Exception('FK violation'));
        $this->bundleRepository->shouldReceive('delete')->with(2)->once()->andReturn(true);

        $action = new BulkDeleteBundles($this->bundleRepository, $this->database);
        $result = $action->handle([1, 2]);

        $this->assertEquals([2], $result['deleted']);
        $this->assertCount(1, $result['failed']);
        $this->assertEquals(1, $result['failed'][0]['id']);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    protected function setUp(): void
    {
        parent::setUp();

        $this->bundleRepository = Mockery::mock(ProductOfferBundleRepository::class);
        $this->database = Mockery::mock(Database::class);

        $this->database
            ->shouldReceive('transaction')
            ->andReturnUsing(fn(callable $callback) => $callback());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}