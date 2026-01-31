<?php

namespace App\Tests\Functional\Controllers\Billing;

use App\Models\PaymentMethod;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class PaymentMethodControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexReturnsPaymentMethodsList(): void
    {
        $this->createPaymentMethod(['name' => 'Stripe', 'sort_order' => 2]);
        $this->createPaymentMethod(['name' => 'PayPal', 'sort_order' => 1]);

        $response = $this->getForSite('/api/payment-methods');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('payment_methods', $data);
        $this->assertCount(2, $data['payment_methods']);

        // Verify ordering
        $this->assertEquals('PayPal', $data['payment_methods'][0]['name']);
        $this->assertEquals('Stripe', $data['payment_methods'][1]['name']);
    }

    protected function createPaymentMethod(array $overrides = []): PaymentMethod
    {
        return PaymentMethod::create(array_merge([
            'site_id' => $this->siteId,
            'name' => 'Test Payment Method',
            'code' => 'test_' . uniqid(),
            'provider' => 'test',
            'is_active' => true,
            'requires_processing' => false,
            'sort_order' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    public function testActiveReturnsOnlyActiveMethods(): void
    {
        $this->createPaymentMethod(['name' => 'Stripe', 'is_active' => true]);
        $this->createPaymentMethod(['name' => 'PayPal', 'is_active' => true]);
        $this->createPaymentMethod(['name' => 'Bitcoin', 'is_active' => false]);

        $response = $this->getForSite('/api/payment-methods/active');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['payment_methods']);

        foreach ($data['payment_methods'] as $method) {
            $this->assertTrue((bool)$method['is_active']);
        }
    }

    public function testShowReturnsPaymentMethodById(): void
    {
        $method = $this->createPaymentMethod([
            'name' => 'Stripe',
            'code' => 'stripe'
        ]);

        $response = $this->getForSite("/api/payment-methods/{$method->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals($method->id, $data['payment_method']['id']);
        $this->assertEquals('Stripe', $data['payment_method']['name']);
    }

    public function testShowReturns404ForNonExistent(): void
    {
        $response = $this->getForSite('/api/payment-methods/99999');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testStoreCreatesPaymentMethod(): void
    {
        $data = [
            'name' => 'New Payment Method',
            'code' => 'new_method',
            'provider' => 'provider',
            'is_active' => true,
            'requires_processing' => true,
            'instructions' => 'Test instructions',
            'sort_order' => 5
        ];

        $response = $this->postForSite('/api/payment-methods', $data);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals('New Payment Method', $data['payment_method']['name']);
        $this->assertEquals('new_method', $data['payment_method']['code']);
    }

    public function testStoreValidatesRequiredFields(): void
    {
        $response = $this->postForSite('/api/payment-methods', []);

        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
    }

    public function testStoreValidatesUniqueCode(): void
    {
        $this->createPaymentMethod(['code' => 'existing_code']);

        $response = $this->postForSite('/api/payment-methods', [
            'name' => 'Test',
            'code' => 'existing_code'
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdateModifiesPaymentMethod(): void
    {
        $method = $this->createPaymentMethod([
            'name' => 'Old Name',
            'is_active' => true
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'is_active' => false
        ];

        $response = $this->putForSite("/api/payment-methods/{$method->id}", $updateData);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals('Updated Name', $data['payment_method']['name']);
        $this->assertFalse((bool)$data['payment_method']['is_active']);
    }

    public function testUpdateReturns404ForNonExistent(): void
    {
        $response = $this->putForSite('/api/payment-methods/99999', [
            'name' => 'Test'
        ]);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDestroyDeletesPaymentMethod(): void
    {
        $method = $this->createPaymentMethod();

        $response = $this->deleteForSite("/api/payment-methods/{$method->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull(PaymentMethod::find($method->id));
    }

    public function testDestroyReturns404ForNonExistent(): void
    {
        $response = $this->deleteForSite('/api/payment-methods/99999');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testCreatePaymentMethodWithConfiguration(): void
    {
        $data = [
            'name' => 'Stripe',
            'code' => 'stripe_config',
            'provider' => 'stripe',
            'configuration' => [
                'api_key' => 'test_key',
                'secret' => 'test_secret'
            ]
        ];

        $response = $this->postForSite('/api/payment-methods', $data);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        // Configuration should not be exposed in API response
        $this->assertArrayNotHasKey('configuration', $data['payment_method']);
    }

    public function testGetActiveMethodsExcludesInactive(): void
    {
        $this->createPaymentMethod(['name' => 'Active 1', 'is_active' => true]);
        $this->createPaymentMethod(['name' => 'Active 2', 'is_active' => true]);
        $this->createPaymentMethod(['name' => 'Inactive 1', 'is_active' => false]);
        $this->createPaymentMethod(['name' => 'Inactive 2', 'is_active' => false]);

        $response = $this->getForSite('/api/payment-methods/active');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['payment_methods']);

        $names = array_column($data['payment_methods'], 'name');
        $this->assertContains('Active 1', $names);
        $this->assertContains('Active 2', $names);
        $this->assertNotContains('Inactive 1', $names);
        $this->assertNotContains('Inactive 2', $names);
    }

    public function testPaymentMethodsOrderedBySortOrder(): void
    {
        $this->createPaymentMethod(['name' => 'Third', 'sort_order' => 3]);
        $this->createPaymentMethod(['name' => 'First', 'sort_order' => 1]);
        $this->createPaymentMethod(['name' => 'Second', 'sort_order' => 2]);

        $response = $this->getForSite('/api/payment-methods');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('First', $data['payment_methods'][0]['name']);
        $this->assertEquals('Second', $data['payment_methods'][1]['name']);
        $this->assertEquals('Third', $data['payment_methods'][2]['name']);
    }

    public function testUpdatePaymentMethodInstructions(): void
    {
        $method = $this->createPaymentMethod([
            'name' => 'Bank Transfer',
            'instructions' => 'Old instructions'
        ]);

        $updateData = [
            'instructions' => 'New bank transfer instructions with account details'
        ];

        $response = $this->putForSite("/api/payment-methods/{$method->id}", $updateData);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('New bank transfer instructions with account details', $data['payment_method']['instructions']);
    }
}