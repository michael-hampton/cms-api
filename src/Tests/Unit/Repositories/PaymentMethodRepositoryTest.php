<?php

namespace App\Tests\Unit\Repositories;

use App\Models\PaymentMethod;
use App\Repositories\PaymentMethodRepository;

class PaymentMethodRepositoryTest extends RepositoryTestCase
{
    private PaymentMethodRepository $repository;

    public function test_find_by_code_returns_payment_method(): void
    {
        $paymentMethod = $this->createPaymentMethod(['code' => 'stripe_test']);

        $found = $this->repository->findByCode('stripe_test');

        $this->assertNotNull($found);
        $this->assertEquals($paymentMethod->id, $found->id);
    }

    protected function createPaymentMethod(array $overrides = []): PaymentMethod
    {
        return PaymentMethod::create(array_merge([
            'site_id' => $this->siteId,
            'name' => 'Test Payment ' . uniqid(),
            'code' => 'test_' . uniqid(),
            'provider' => 'test',
            'is_active' => true,
            'requires_processing' => false,
            'sort_order' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    public function test_find_by_code_returns_null_when_not_found(): void
    {
        $found = $this->repository->findByCode('non_existent');

        $this->assertNull($found);
    }

    public function test_get_active_returns_only_active_methods(): void
    {
        $this->createPaymentMethod(['is_active' => true, 'sort_order' => 1]);
        $this->createPaymentMethod(['is_active' => true, 'sort_order' => 2]);
        $this->createPaymentMethod(['is_active' => false, 'sort_order' => 3]);

        $active = $this->repository->getActive();

        $this->assertGreaterThanOrEqual(2, $active->count());
        foreach ($active as $method) {
            $this->assertTrue($method->is_active);
        }
    }

    public function test_get_active_returns_ordered_by_sort_order(): void
    {
        $method1 = $this->createPaymentMethod(['is_active' => true, 'sort_order' => 3]);
        $method2 = $this->createPaymentMethod(['is_active' => true, 'sort_order' => 1]);
        $method3 = $this->createPaymentMethod(['is_active' => true, 'sort_order' => 2]);

        $active = $this->repository->getActive();

        $ids = $active->pluck('id')->toArray();
        $this->assertLessThan(array_search($method1->id, $ids), array_search($method2->id, $ids));
        $this->assertLessThan(array_search($method1->id, $ids), array_search($method3->id, $ids));
    }

    public function test_get_by_provider_returns_filtered_methods(): void
    {
        $this->createPaymentMethod(['provider' => 'stripe']);
        $this->createPaymentMethod(['provider' => 'stripe']);
        $this->createPaymentMethod(['provider' => 'paypal']);

        $methods = $this->repository->getByProvider('stripe');

        $this->assertGreaterThanOrEqual(2, $methods->count());
        foreach ($methods as $method) {
            $this->assertEquals('stripe', $method->provider);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PaymentMethodRepository();
    }
}