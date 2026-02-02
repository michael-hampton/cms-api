<?php

namespace App\Tests\Unit\Services\Billing;

use App\Services\Billing\CheckoutSplittingService;
use PHPUnit\Framework\TestCase;

class CheckoutSplittingServiceTest extends TestCase
{
    private CheckoutSplittingService $service;

    protected function setUp(): void
    {
        $this->service = new CheckoutSplittingService();
    }

    // --- splitByMerchant ---

    public function testSplitByMerchantGroupsItemsByMerchantId(): void
    {
        $items = [
            ['product_id' => 1, 'product_name' => 'A', 'quantity' => 1, 'unit_price' => 10.00, 'merchant_id' => 100],
            ['product_id' => 2, 'product_name' => 'B', 'quantity' => 2, 'unit_price' => 20.00, 'merchant_id' => 100],
            ['product_id' => 3, 'product_name' => 'C', 'quantity' => 1, 'unit_price' => 30.00, 'merchant_id' => 200],
        ];

        $result = $this->service->splitByMerchant($items);

        $this->assertCount(2, $result);
        $this->assertArrayHasKey(100, $result);
        $this->assertArrayHasKey(200, $result);
        $this->assertCount(2, $result[100]['items']);
        $this->assertCount(1, $result[200]['items']);
        $this->assertEquals(100, $result[100]['merchant_id']);
        $this->assertEquals(200, $result[200]['merchant_id']);
    }

    public function testSplitByMerchantPutsNullMerchantItemsInSystemBucket(): void
    {
        $items = [
            ['product_id' => 1, 'product_name' => 'A', 'quantity' => 1, 'unit_price' => 10.00, 'merchant_id' => null],
            ['product_id' => 2, 'product_name' => 'B', 'quantity' => 1, 'unit_price' => 15.00],  // no key at all
        ];

        $result = $this->service->splitByMerchant($items);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey(CheckoutSplittingService::SYSTEM_MERCHANT_KEY, $result);
        $this->assertCount(2, $result[CheckoutSplittingService::SYSTEM_MERCHANT_KEY]['items']);
        $this->assertNull($result[CheckoutSplittingService::SYSTEM_MERCHANT_KEY]['merchant_id']);
    }

    public function testSplitByMerchantHandlesMixedMerchantAndSystemItems(): void
    {
        $items = [
            ['product_id' => 1, 'product_name' => 'A', 'quantity' => 1, 'unit_price' => 10.00, 'merchant_id' => 50],
            ['product_id' => 2, 'product_name' => 'B', 'quantity' => 1, 'unit_price' => 20.00, 'merchant_id' => null],
            ['product_id' => 3, 'product_name' => 'C', 'quantity' => 1, 'unit_price' => 30.00, 'merchant_id' => 50],
            ['product_id' => 4, 'product_name' => 'D', 'quantity' => 1, 'unit_price' => 40.00, 'merchant_id' => 75],
        ];

        $result = $this->service->splitByMerchant($items);

        $this->assertCount(3, $result); // merchant 50, merchant 75, __system__
        $this->assertCount(2, $result[50]['items']);
        $this->assertCount(1, $result[75]['items']);
        $this->assertCount(1, $result[CheckoutSplittingService::SYSTEM_MERCHANT_KEY]['items']);
    }

    public function testSplitByMerchantReturnsEmptyArrayForNoItems(): void
    {
        $result = $this->service->splitByMerchant([]);
        $this->assertCount(0, $result);
    }

    public function testSplitByMerchantPreservesBundleIdInMetadata(): void
    {
        $items = [
            [
                'product_id' => 1,
                'product_name' => 'Bundle Item 1',
                'quantity' => 1,
                'unit_price' => 25.00,
                'merchant_id' => 100,
                'bundle_id' => 'bndl-abc',
            ],
            [
                'product_id' => 2,
                'product_name' => 'Bundle Item 2',
                'quantity' => 1,
                'unit_price' => 35.00,
                'merchant_id' => 200,
                'bundle_id' => 'bndl-abc',
            ],
        ];

        $result = $this->service->splitByMerchant($items);

        // Items are in different merchant groups
        $this->assertCount(2, $result);

        // Both items retain bundle_id in metadata
        $item1 = $result[100]['items'][0];
        $item2 = $result[200]['items'][0];

        $this->assertEquals('bndl-abc', $item1['metadata']['bundle_id']);
        $this->assertEquals('bndl-abc', $item2['metadata']['bundle_id']);
    }

    public function testSplitByMerchantDoesNotOverwriteExistingMetadata(): void
    {
        $items = [
            [
                'product_id' => 1,
                'product_name' => 'X',
                'quantity' => 1,
                'unit_price' => 10.00,
                'merchant_id' => 100,
                'bundle_id' => 'bndl-1',
                'metadata' => ['colour' => 'red'],
            ],
        ];

        $result = $this->service->splitByMerchant($items);
        $item = $result[100]['items'][0];

        $this->assertEquals('red', $item['metadata']['colour']);
        $this->assertEquals('bndl-1', $item['metadata']['bundle_id']);
    }

    public function testSplitByMerchantSingleMerchantAllItems(): void
    {
        $items = [
            ['product_id' => 1, 'product_name' => 'A', 'quantity' => 1, 'unit_price' => 10.00, 'merchant_id' => 42],
            ['product_id' => 2, 'product_name' => 'B', 'quantity' => 3, 'unit_price' => 5.00, 'merchant_id' => 42],
        ];

        $result = $this->service->splitByMerchant($items);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey(42, $result);
        $this->assertCount(2, $result[42]['items']);
    }

    // --- getMerchantKeys ---

    public function testGetMerchantKeysExcludesSystemKey(): void
    {
        $groups = [
            100 => ['merchant_id' => 100, 'items' => []],
            200 => ['merchant_id' => 200, 'items' => []],
            CheckoutSplittingService::SYSTEM_MERCHANT_KEY => ['merchant_id' => null, 'items' => []],
        ];

        $keys = $this->service->getMerchantKeys($groups);

        $this->assertContains(100, $keys);
        $this->assertContains(200, $keys);
        $this->assertNotContains(CheckoutSplittingService::SYSTEM_MERCHANT_KEY, $keys);
    }

    public function testGetMerchantKeysReturnsEmptyWhenOnlySystem(): void
    {
        $groups = [
            CheckoutSplittingService::SYSTEM_MERCHANT_KEY => ['merchant_id' => null, 'items' => []],
        ];

        $keys = $this->service->getMerchantKeys($groups);
        $this->assertCount(0, $keys);
    }

    // --- hasSystemOrder ---

    public function testHasSystemOrderReturnsTrueWhenSystemBucketExists(): void
    {
        $groups = [
            CheckoutSplittingService::SYSTEM_MERCHANT_KEY => [
                'merchant_id' => null,
                'items' => [['product_id' => 1, 'unit_price' => 10.00, 'quantity' => 1, 'merchant_id' => null]],
            ],
        ];

        $this->assertTrue($this->service->hasSystemOrder($groups));
    }

    public function testHasSystemOrderReturnsFalseWhenNoSystemBucket(): void
    {
        $groups = [
            100 => ['merchant_id' => 100, 'items' => [['product_id' => 1]]],
        ];

        $this->assertFalse($this->service->hasSystemOrder($groups));
    }

    public function testHasSystemOrderReturnsFalseWhenSystemBucketIsEmpty(): void
    {
        $groups = [
            CheckoutSplittingService::SYSTEM_MERCHANT_KEY => ['merchant_id' => null, 'items' => []],
        ];

        $this->assertFalse($this->service->hasSystemOrder($groups));
    }

    public function testStripeGroupKeyIsMerchantPrefixedForMerchantGroups(): void
    {
        $items = [
            ['product_id' => 1, 'product_name' => 'A', 'quantity' => 1, 'unit_price' => 10.00, 'merchant_id' => 42],
            ['product_id' => 2, 'product_name' => 'B', 'quantity' => 1, 'unit_price' => 20.00, 'merchant_id' => 99],
        ];

        $result = $this->service->splitByMerchant($items);

        $this->assertEquals('merchant_42', $result[42]['stripe_group_key']);
        $this->assertEquals('merchant_99', $result[99]['stripe_group_key']);
    }

    public function testStripeGroupKeyIsSystemForNullMerchant(): void
    {
        $items = [
            ['product_id' => 1, 'product_name' => 'A', 'quantity' => 1, 'unit_price' => 10.00, 'merchant_id' => null],
        ];

        $result = $this->service->splitByMerchant($items);

        $this->assertEquals('system', $result[CheckoutSplittingService::SYSTEM_MERCHANT_KEY]['stripe_group_key']);
    }
}