<?php

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Enums\Subscriptions\PrintVendorConnectionType;
use App\Framework\Container;
use App\Models\PrintVendorConnection;
use App\Services\Subscriptions\PrintVendorConnectionService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class PrintVendorConnectionControllerTest extends FunctionalTestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Functional Vendor',
            'code' => 'functional-vendor-' . uniqid(),
            'connection_type' => PrintVendorConnectionType::Label->value,
            'host' => 'sftp.example.com',
            'port' => 22,
            'username' => 'vendor-user',
            'password' => 'secret-password',
            'remote_path' => '/uploads/labels',
            'is_active' => true,
            'is_default' => false,
            'notes' => 'Created by functional test',
        ], $overrides);
    }

    public function test_index_lists_connections(): void
    {
        PrintVendorConnection::create($this->payload([
            'code' => 'list-vendor-a',
            'name' => 'List Vendor A',
        ]));
        PrintVendorConnection::create($this->payload([
            'code' => 'list-vendor-b',
            'name' => 'List Vendor B',
            'connection_type' => PrintVendorConnectionType::Batch->value,
        ]));

        $response = $this->getForSite('/api/print-vendor-connections');

        $this->assertResponseStatus(200, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertIsArray($data['data']);
        $this->assertGreaterThanOrEqual(2, count($data['data']));
        $this->assertArrayHasKey('has_password', $data['data'][0]);
        $this->assertArrayNotHasKey('password', $data['data'][0]);
    }

    public function test_index_filters_by_connection_type(): void
    {
        PrintVendorConnection::create($this->payload([
            'code' => 'label-only-vendor',
            'connection_type' => PrintVendorConnectionType::Label->value,
        ]));
        PrintVendorConnection::create($this->payload([
            'code' => 'batch-only-vendor',
            'connection_type' => PrintVendorConnectionType::Batch->value,
        ]));

        $response = $this->getForSite('/api/print-vendor-connections?connection_type=label');

        $this->assertResponseStatus(200, $response);
        $data = json_decode($response->getContent(), true);
        $codes = array_column($data['data'], 'code');
        $this->assertContains('label-only-vendor', $codes);
        $this->assertNotContains('batch-only-vendor', $codes);
    }

    public function test_index_rejects_invalid_connection_type(): void
    {
        $response = $this->getForSite('/api/print-vendor-connections?connection_type=not-a-type');

        $this->assertResponseStatus(422, $response);
    }

    public function test_crud_lifecycle_for_print_vendor_connection(): void
    {
        $create = $this->postForSite('/api/print-vendor-connections', $this->payload([
            'code' => 'crud-vendor',
            'name' => 'CRUD Vendor',
        ]));

        $this->assertResponseStatus(201, $create);
        $created = json_decode($create->getContent(), true);
        $this->assertArrayHasKey('connection', $created);
        $id = $created['connection']['id'];
        $this->assertEquals('crud-vendor', $created['connection']['code']);
        $this->assertTrue($created['connection']['has_password']);
        $this->assertArrayNotHasKey('password', $created['connection']);

        $show = $this->getForSite('/api/print-vendor-connections/' . $id);
        $this->assertResponseStatus(200, $show);
        $shown = json_decode($show->getContent(), true);
        $this->assertEquals('CRUD Vendor', $shown['connection']['name']);

        $update = $this->putForSite('/api/print-vendor-connections/' . $id, [
            'name' => 'Updated Vendor',
            'notes' => 'Updated notes',
            'password' => '',
        ]);
        $this->assertResponseStatus(200, $update);
        $updated = json_decode($update->getContent(), true);
        $this->assertEquals('Updated Vendor', $updated['connection']['name']);
        $this->assertEquals('Updated notes', $updated['connection']['notes']);
        $this->assertTrue($updated['connection']['has_password']);

        $delete = $this->deleteForSite('/api/print-vendor-connections/' . $id);
        $this->assertResponseStatus(200, $delete);
        $deleted = json_decode($delete->getContent(), true);
        $this->assertFalse((bool) $deleted['connection']['is_active']);

        $connection = PrintVendorConnection::find($id);
        $this->assertNotNull($connection);
        $this->assertFalse((bool) $connection->is_active);
    }

    public function test_store_rejects_duplicate_code(): void
    {
        PrintVendorConnection::create($this->payload(['code' => 'duplicate-code']));

        $response = $this->postForSite('/api/print-vendor-connections', $this->payload([
            'code' => 'duplicate-code',
        ]));

        $this->assertResponseStatus(422, $response);
    }

    public function test_store_rejects_invalid_payload(): void
    {
        $response = $this->postForSite('/api/print-vendor-connections', [
            'name' => 'Missing required fields',
        ]);

        $this->assertResponseStatus(422, $response);
    }

    public function test_show_returns_404_for_unknown_connection(): void
    {
        $response = $this->getForSite('/api/print-vendor-connections/999999');

        $this->assertResponseStatus(404, $response);
    }

    public function test_test_connection_returns_result_from_service(): void
    {
        $connection = PrintVendorConnection::create($this->payload([
            'code' => 'testable-vendor',
        ]));

        $service = Mockery::mock(PrintVendorConnectionService::class);
        $service->shouldReceive('testConnection')
            ->once()
            ->with((int) $connection->id)
            ->andReturn([
                'success' => true,
                'message' => 'Connected successfully.',
            ]);
        Container::getInstance()->instance(PrintVendorConnectionService::class, $service);

        $response = $this->postForSite('/api/print-vendor-connections/' . $connection->id . '/test');

        $this->assertResponseStatus(200, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Connected successfully.', $data['message']);
    }

    public function test_test_connection_returns_422_when_service_reports_failure(): void
    {
        $connection = PrintVendorConnection::create($this->payload([
            'code' => 'failing-vendor',
        ]));

        $service = Mockery::mock(PrintVendorConnectionService::class);
        $service->shouldReceive('testConnection')
            ->once()
            ->with((int) $connection->id)
            ->andReturn([
                'success' => false,
                'message' => 'Login failed.',
            ]);
        Container::getInstance()->instance(PrintVendorConnectionService::class, $service);

        $response = $this->postForSite('/api/print-vendor-connections/' . $connection->id . '/test');

        $this->assertResponseStatus(422, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Login failed.', $data['message']);
    }

    public function test_test_connection_returns_404_for_unknown_connection(): void
    {
        $response = $this->postForSite('/api/print-vendor-connections/999999/test');

        $this->assertResponseStatus(404, $response);
    }
}
