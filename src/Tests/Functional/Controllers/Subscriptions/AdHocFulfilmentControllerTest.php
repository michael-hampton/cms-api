<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Enums\Subscriptions\PrintBatchStatus;
use App\Models\PrintBatch;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class AdHocFulfilmentControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    // =========================================================================
    // POST /api/{site}/ad-hoc-fulfilment-requests/print-batches/{printBatchId}
    // =========================================================================

    public function testGenerateForPrintBatchQueuesExportAndRecordsRequest(): void
    {
        $batch = $this->createPrintBatch(['status' => PrintBatchStatus::QUEUED->value]);

        $response = $this->postForSite("/api/ad-hoc-fulfilment-requests/print-batches/{$batch->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals($batch->id, $data['data']['print_batch_id']);
        $this->assertEquals('print_batch', $data['data']['process']);
        $this->assertArrayHasKey('requested_by_user_id', $data['data']);
    }

    public function testGenerateForPrintBatchReturns404WhenBatchDoesNotExist(): void
    {
        $response = $this->postForSite('/api/ad-hoc-fulfilment-requests/print-batches/999999');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGenerateForPrintBatchReturns422WhenBatchAlreadyExported(): void
    {
        $batch = $this->createPrintBatch(['status' => PrintBatchStatus::BATCH_EXPORTED->value]);

        $response = $this->postForSite("/api/ad-hoc-fulfilment-requests/print-batches/{$batch->id}");

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testGenerateForPrintBatchReturns422WhenBatchIsCurrentlyExporting(): void
    {
        $batch = $this->createPrintBatch(['status' => PrintBatchStatus::BATCH_EXPORTING->value]);

        $response = $this->postForSite("/api/ad-hoc-fulfilment-requests/print-batches/{$batch->id}");

        $this->assertEquals(422, $response->getStatusCode());
    }

    // =========================================================================
    // GET /api/{site}/ad-hoc-fulfilment-requests
    // =========================================================================

    public function testIndexReturnsPaginatedRequests(): void
    {
        $batch = $this->createPrintBatch(['status' => PrintBatchStatus::QUEUED->value]);
        $this->postForSite("/api/ad-hoc-fulfilment-requests/print-batches/{$batch->id}");

        $response = $this->getForSite('/api/ad-hoc-fulfilment-requests');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertGreaterThanOrEqual(1, $data['pagination']['total']);
    }

    public function testIndexFiltersOnProcess(): void
    {
        $batch = $this->createPrintBatch(['status' => PrintBatchStatus::QUEUED->value]);
        $this->postForSite("/api/ad-hoc-fulfilment-requests/print-batches/{$batch->id}");

        $response = $this->getForSite('/api/ad-hoc-fulfilment-requests?process=print_batch');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        foreach ($data['items'] as $item) {
            $this->assertEquals('print_batch', $item['process']);
        }
    }

    // =========================================================================
    // GET /api/{site}/ad-hoc-fulfilment-requests/{id}
    // =========================================================================

    public function testShowReturnsSingleRequestDetail(): void
    {
        $batch = $this->createPrintBatch(['status' => PrintBatchStatus::QUEUED->value]);
        $createResponse = $this->postForSite("/api/ad-hoc-fulfilment-requests/print-batches/{$batch->id}");
        $created = json_decode($createResponse->getContent(), true)['data'];

        $response = $this->getForSite("/api/ad-hoc-fulfilment-requests/{$created['id']}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals($created['id'], $data['data']['id']);
        $this->assertEquals($batch->id, $data['data']['print_batch_id']);
    }

    public function testShowReturns404WhenRequestNotFound(): void
    {
        $response = $this->getForSite('/api/ad-hoc-fulfilment-requests/999999');

        $this->assertEquals(404, $response->getStatusCode());
    }
}