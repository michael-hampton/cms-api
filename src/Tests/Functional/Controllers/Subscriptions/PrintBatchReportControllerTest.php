<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Models\PrintBatch;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class PrintBatchReportControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    // =========================================================================
    // GET /api/print-batches
    // =========================================================================

    public function testIndexReturnsPaginatedPrintBatches(): void
    {
        $this->createPrintBatch();
        $this->createPrintBatch();

        $response = $this->getForSite('/api/print-batches');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertGreaterThanOrEqual(2, $data['pagination']['total']);
    }

    public function testIndexFiltersByCreatedAtRange(): void
    {
        $inRange = $this->createPrintBatch();
        $outOfRange = $this->createPrintBatch();

        PrintBatch::where('id', $inRange->id)->update(['created_at' => '2026-03-15 10:00:00']);
        PrintBatch::where('id', $outOfRange->id)->update(['created_at' => '2026-01-01 10:00:00']);

        $response = $this->getForSite('/api/print-batches?from=2026-03-01&to=2026-03-31');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $ids = array_column($data['data'], 'id');
        $this->assertContains($inRange->id, $ids);
        $this->assertNotContains($outOfRange->id, $ids);
    }

    public function testIndexFiltersByUpdatedAtRange(): void
    {
        $inRange = $this->createPrintBatch();
        $outOfRange = $this->createPrintBatch();

        PrintBatch::where('id', $inRange->id)->update(['updated_at' => '2026-03-15 10:00:00']);
        PrintBatch::where('id', $outOfRange->id)->update(['updated_at' => '2026-01-01 10:00:00']);

        $response = $this->getForSite('/api/print-batches?updated_from=2026-03-01&updated_to=2026-03-31');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $ids = array_column($data['data'], 'id');
        $this->assertContains($inRange->id, $ids);
        $this->assertNotContains($outOfRange->id, $ids);
    }
}
