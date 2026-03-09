<?php

namespace App\Tests\Unit\Actions\Offers;

use App\Actions\Offers\BulkDeleteOffers;
use App\Actions\Offers\BulkPublishOffers;
use App\Framework\Database\Database;
use App\Models\ProductOffer;
use App\Repositories\Offers\ProductOfferRepository;
use Exception;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class BulkOfferActionsTest extends TestCase
{
    private MockInterface|ProductOfferRepository $offerRepository;
    private MockInterface|Database $database;

    public function test_bulk_publish_offers_publishes_pending_offers(): void
    {
        $offer1 = $this->makePendingOffer(1);
        $offer2 = $this->makePendingOffer(2);

        $this->offerRepository->shouldReceive('find')->with(1)->once()->andReturn($offer1);
        $this->offerRepository->shouldReceive('find')->with(2)->once()->andReturn($offer2);

        $this->offerRepository->shouldReceive('update')
            ->with(1, Mockery::on(fn($d) => $d['status'] === 'published' && isset($d['published_at']) && $d['published_by'] === 99))
            ->once();

        $this->offerRepository->shouldReceive('update')
            ->with(2, Mockery::on(fn($d) => $d['status'] === 'published' && isset($d['published_at']) && $d['published_by'] === 99))
            ->once();

        $action = new BulkPublishOffers($this->offerRepository, $this->database);
        $result = $action->handle([1, 2], 99);

        $this->assertEquals([1, 2], $result['published']);
        $this->assertEmpty($result['failed']);
        $this->assertEquals(2, $result['total']);
    }

    private function makePendingOffer(int $id): ProductOffer
    {
        return $this->makeOffer($id, 'pending');
    }

    // =========================================================================
    // BulkPublishOffers
    // =========================================================================

    private function makeOffer(int $id, string $status): ProductOffer
    {
        $offer = Mockery::mock(ProductOffer::class)->makePartial();
        $offer->id = $id;
        $offer->status = $status;

        return $offer;
    }

    public function test_bulk_publish_offers_skips_non_pending_offers(): void
    {
        $pending = $this->makePendingOffer(1);
        $published = $this->makeOffer(2, 'published');

        $this->offerRepository->shouldReceive('find')->with(1)->once()->andReturn($pending);
        $this->offerRepository->shouldReceive('find')->with(2)->once()->andReturn($published);

        $this->offerRepository->shouldReceive('update')
            ->with(1, Mockery::any())
            ->once();

        // Should NOT be updated — wrong status
        $this->offerRepository->shouldNotReceive('update')->with(2, Mockery::any());

        $action = new BulkPublishOffers($this->offerRepository, $this->database);
        $result = $action->handle([1, 2], 99);

        $this->assertEquals([1], $result['published']);
        $this->assertCount(1, $result['failed']);
        $this->assertStringContainsString("published", $result['failed'][0]['reason']);
    }

    public function test_bulk_publish_offers_records_not_found_as_failed(): void
    {
        $this->offerRepository->shouldReceive('find')->with(99)->once()->andReturn(null);
        $this->offerRepository->shouldNotReceive('update');

        $action = new BulkPublishOffers($this->offerRepository, $this->database);
        $result = $action->handle([99], 1);

        $this->assertEmpty($result['published']);
        $this->assertCount(1, $result['failed']);
        $this->assertEquals(99, $result['failed'][0]['id']);
        $this->assertStringContainsString('not found', $result['failed'][0]['reason']);
    }

    public function test_bulk_publish_offers_throws_when_ids_empty(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No offer IDs provided');

        $action = new BulkPublishOffers($this->offerRepository, $this->database);
        $action->handle([], 1);
    }

    public function test_bulk_publish_offers_wraps_in_transaction(): void
    {
        $database = Mockery::mock(Database::class);
        $database->shouldReceive('transaction')->once()->andReturnUsing(fn(callable $cb) => $cb());

        $offer = $this->makePendingOffer(1);
        $this->offerRepository->shouldReceive('find')->with(1)->andReturn($offer);
        $this->offerRepository->shouldReceive('update')->once();

        $action = new BulkPublishOffers($this->offerRepository, $database);
        $action->handle([1], 1);
        $this->assertTrue(true);
    }

    public function test_bulk_publish_offers_continues_after_individual_failure(): void
    {
        $offer1 = $this->makePendingOffer(1);
        $offer2 = $this->makePendingOffer(2);

        $this->offerRepository->shouldReceive('find')->with(1)->once()->andReturn($offer1);
        $this->offerRepository->shouldReceive('find')->with(2)->once()->andReturn($offer2);

        $this->offerRepository->shouldReceive('update')
            ->with(1, Mockery::any())
            ->once()
            ->andThrow(new Exception('DB error'));

        $this->offerRepository->shouldReceive('update')
            ->with(2, Mockery::any())
            ->once();

        $action = new BulkPublishOffers($this->offerRepository, $this->database);
        $result = $action->handle([1, 2], 1);

        $this->assertEquals([2], $result['published']);
        $this->assertCount(1, $result['failed']);
        $this->assertEquals(1, $result['failed'][0]['id']);
    }

    // =========================================================================
    // BulkDeleteOffers
    // =========================================================================

    public function test_bulk_delete_offers_deletes_existing_offers(): void
    {
        $offer1 = $this->makeOffer(1, 'pending');
        $offer2 = $this->makeOffer(2, 'published');

        $this->offerRepository->shouldReceive('find')->with(1)->once()->andReturn($offer1);
        $this->offerRepository->shouldReceive('find')->with(2)->once()->andReturn($offer2);
        $this->offerRepository->shouldReceive('delete')->with(1)->once()->andReturn(true);
        $this->offerRepository->shouldReceive('delete')->with(2)->once()->andReturn(true);

        $action = new BulkDeleteOffers($this->offerRepository, $this->database);
        $result = $action->handle([1, 2]);

        $this->assertEquals([1, 2], $result['deleted']);
        $this->assertEmpty($result['failed']);
        $this->assertEquals(2, $result['total']);
    }

    public function test_bulk_delete_offers_records_not_found_as_failed(): void
    {
        $this->offerRepository->shouldReceive('find')->with(99)->once()->andReturn(null);
        $this->offerRepository->shouldNotReceive('delete');

        $action = new BulkDeleteOffers($this->offerRepository, $this->database);
        $result = $action->handle([99]);

        $this->assertEmpty($result['deleted']);
        $this->assertCount(1, $result['failed']);
        $this->assertEquals(99, $result['failed'][0]['id']);
        $this->assertStringContainsString('not found', $result['failed'][0]['reason']);
    }

    public function test_bulk_delete_offers_records_delete_failure(): void
    {
        $offer = $this->makeOffer(1, 'pending');

        $this->offerRepository->shouldReceive('find')->with(1)->once()->andReturn($offer);
        $this->offerRepository->shouldReceive('delete')->with(1)->once()->andReturn(false);

        $action = new BulkDeleteOffers($this->offerRepository, $this->database);
        $result = $action->handle([1]);

        $this->assertEmpty($result['deleted']);
        $this->assertCount(1, $result['failed']);
        $this->assertStringContainsString('Delete failed', $result['failed'][0]['reason']);
    }

    public function test_bulk_delete_offers_throws_when_ids_empty(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No offer IDs provided');

        $action = new BulkDeleteOffers($this->offerRepository, $this->database);
        $action->handle([]);
    }

    public function test_bulk_delete_offers_wraps_in_transaction(): void
    {
        $database = Mockery::mock(Database::class);
        $database->shouldReceive('transaction')->once()->andReturnUsing(fn(callable $cb) => $cb());

        $offer = $this->makeOffer(1, 'pending');
        $this->offerRepository->shouldReceive('find')->with(1)->andReturn($offer);
        $this->offerRepository->shouldReceive('delete')->with(1)->andReturn(true);

        $action = new BulkDeleteOffers($this->offerRepository, $database);
        $action->handle([1]);
        $this->assertTrue(true);
    }

    public function test_bulk_delete_offers_continues_after_individual_exception(): void
    {
        $offer1 = $this->makeOffer(1, 'pending');
        $offer2 = $this->makeOffer(2, 'pending');

        $this->offerRepository->shouldReceive('find')->with(1)->once()->andReturn($offer1);
        $this->offerRepository->shouldReceive('find')->with(2)->once()->andReturn($offer2);

        $this->offerRepository->shouldReceive('delete')->with(1)->once()->andThrow(new Exception('FK violation'));
        $this->offerRepository->shouldReceive('delete')->with(2)->once()->andReturn(true);

        $action = new BulkDeleteOffers($this->offerRepository, $this->database);
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

        $this->offerRepository = Mockery::mock(ProductOfferRepository::class);
        $this->database = Mockery::mock(Database::class);

        // Unwrap the transaction closure immediately so we can test the logic inline
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