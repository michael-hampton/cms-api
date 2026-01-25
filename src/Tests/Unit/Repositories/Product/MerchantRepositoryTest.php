<?php

namespace App\Tests\Unit\Repositories\Product;

use App\Models\MerchantUrl;
use App\Repositories\Product\MerchantRepository;
use App\Search\SearchCriteria;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class MerchantRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private MerchantRepository $repository;

    public function test_it_can_search_merchants_with_relationships(): void
    {
        $merchant = $this->createMerchant();
        $this->createMerchantUrl(['merchant_id' => $merchant->id, 'is_primary' => true]);

        $criteria = new SearchCriteria();
        $result = $this->repository->search($criteria);

        $this->assertGreaterThan(0, count($result->getData()));
        $foundMerchant = $result->getData()[0];

        $this->assertNotEmpty($foundMerchant['urls']);
    }

    public function test_find_by_slug_returns_correct_merchant(): void
    {
        $merchant = $this->createMerchant(['slug' => 'test-merchant']);

        $found = $this->repository->findBySlug('test-merchant');

        $this->assertNotNull($found);
        $this->assertEquals($merchant->id, $found->id);
    }

    public function test_find_by_slug_returns_null_when_not_found(): void
    {
        $found = $this->repository->findBySlug('non-existent');

        $this->assertNull($found);
    }

    public function test_get_active_returns_only_active_merchants(): void
    {
        $this->createMerchant(['is_active' => true]);
        $this->createMerchant(['is_active' => false]);
        $this->createMerchant(['is_active' => true]);

        $active = $this->repository->getActive();

        $this->assertCount(2, $active);
        foreach ($active as $merchant) {
            $this->assertTrue($merchant->is_active);
        }
    }

    public function test_sync_urls_removes_old_and_adds_new(): void
    {
        $merchant = $this->createMerchant();

        $this->createMerchantUrl(['merchant_id' => $merchant->id, 'url' => 'https://old1.com']);
        $this->createMerchantUrl(['merchant_id' => $merchant->id, 'url' => 'https://old2.com']);

        $newUrls = [
            ['url' => 'https://new1.com', 'is_primary' => true, 'label' => 'Primary'],
            ['url' => 'https://new2.com', 'is_primary' => false, 'label' => 'Secondary'],
        ];

        $this->repository->syncUrls($merchant->id, $newUrls);

        $urls = MerchantUrl::where('merchant_id', $merchant->id)->get()->toArray();

        $this->assertCount(2, $urls);
        $this->assertEquals('https://new1.com', $urls[0]['url']);
        $this->assertEquals('https://new2.com', $urls[1]['url']);

        $this->assertDatabaseMissing('merchant_urls', [
            'merchant_id' => $merchant->id,
            'url' => 'https://old1.com'
        ]);
    }

    public function test_sync_urls_ensures_only_one_primary(): void
    {
        $merchant = $this->createMerchant();

        $urls = [
            ['url' => 'https://url1.com', 'is_primary' => true],
            ['url' => 'https://url2.com', 'is_primary' => true],
            ['url' => 'https://url3.com', 'is_primary' => true],
        ];

        $this->repository->syncUrls($merchant->id, $urls);

        $primaryCount = MerchantUrl::where('merchant_id', $merchant->id)
            ->where('is_primary', true)
            ->count();

        $this->assertEquals(1, $primaryCount);
    }

    public function test_sync_urls_sets_first_as_primary_if_none_specified(): void
    {
        $merchant = $this->createMerchant();

        $urls = [
            ['url' => 'https://url1.com', 'is_primary' => false],
            ['url' => 'https://url2.com', 'is_primary' => false],
        ];

        $this->repository->syncUrls($merchant->id, $urls);

        $primary = MerchantUrl::where('merchant_id', $merchant->id)
            ->where('is_primary', true)
            ->first();

        $this->assertNotNull($primary);
        $this->assertEquals('https://url1.com', $primary->url);
    }

    public function test_sync_sites_updates_associations(): void
    {
        $merchant = $this->createMerchant();
        $site1 = $this->createSite();
        $site2 = $this->createSite();

        $this->repository->syncSites($merchant->id, [$site1->id, $site2->id]);

        $merchant = $this->repository->find($merchant->id, ['sites']);
        $this->assertCount(2, $merchant->sites);

        $this->repository->syncSites($merchant->id, [$site1->id]);

        $merchant = $this->repository->find($merchant->id, ['sites']);
        $this->assertCount(1, $merchant->sites);
    }

    public function test_get_urls_returns_sorted_by_primary(): void
    {
        $merchant = $this->createMerchant();

        $this->createMerchantUrl(['merchant_id' => $merchant->id, 'url' => 'https://secondary.com', 'is_primary' => false]);
        $this->createMerchantUrl(['merchant_id' => $merchant->id, 'url' => 'https://primary.com', 'is_primary' => true]);

        $urls = $this->repository->getUrls($merchant->id)->toArray();

        $this->assertCount(2, $urls);
        $this->assertEquals('https://primary.com', $urls[0]['url']);
        $this->assertTrue((bool)$urls[0]['is_primary']);
    }

    public function test_delete_urls_removes_all_urls(): void
    {
        $merchant = $this->createMerchant();
        $this->createMerchantUrl(['merchant_id' => $merchant->id]);
        $this->createMerchantUrl(['merchant_id' => $merchant->id]);

        $this->repository->deleteUrls($merchant->id);

        $count = $this->countRecords('merchant_urls', ['merchant_id' => $merchant->id]);
        $this->assertEquals(0, $count);
    }

    public function test_bulk_update_status_updates_multiple_merchants(): void
    {
        $merchant1 = $this->createMerchant(['is_active' => true]);
        $merchant2 = $this->createMerchant(['is_active' => true]);
        $merchant3 = $this->createMerchant(['is_active' => true]);

        $updated = $this->repository->bulkUpdateStatus(
            [$merchant1->id, $merchant2->id],
            0
        );

        $this->assertEquals(2, $updated);

        $merchant1 = $merchant1->fresh();
        $merchant2 = $merchant2->fresh();
        $merchant3 = $merchant3->fresh();

        $this->assertFalse($merchant1->is_active);
        $this->assertFalse($merchant2->is_active);
        $this->assertTrue($merchant3->is_active);
    }

    public function test_bulk_delete_removes_multiple_merchants_and_urls(): void
    {
        $merchant1 = $this->createMerchant();
        $merchant2 = $this->createMerchant();

        $this->createMerchantUrl(['merchant_id' => $merchant1->id]);
        $this->createMerchantUrl(['merchant_id' => $merchant2->id]);

        $deleted = $this->repository->bulkDelete([$merchant1->id, $merchant2->id]);

        $this->assertEquals(2, $deleted);
        $this->assertDatabaseMissing('merchants', ['id' => $merchant1->id]);
        $this->assertDatabaseMissing('merchants', ['id' => $merchant2->id]);
        $this->assertEquals(0, MerchantUrl::where('merchant_id', $merchant1->id)->count());
        $this->assertEquals(0, MerchantUrl::where('merchant_id', $merchant2->id)->count());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new MerchantRepository();
    }

//    public function test_find_by_site_returns_merchants_for_site(): void
//    {
//        $site1 = $this->createSite();
//        $site2 = $this->createSite();
//
//        $merchant1 = $this->createMerchant();
//        $merchant2 = $this->createMerchant();
//        $merchant3 = $this->createMerchant();
//
//        $this->repository->syncSites($merchant1->id, [$site1->id]);
//        $this->repository->syncSites($merchant2->id, [$site1->id, $site2->id]);
//        $this->repository->syncSites($merchant3->id, [$site2->id]);
//
//        $site1Merchants = $this->repository->findBySite($site1->id);
//
//        $this->assertCount(2, $site1Merchants);
//        $ids = $site1Merchants->pluck('id')->toArray();
//        $this->assertContains($merchant1->id, $ids);
//        $this->assertContains($merchant2->id, $ids);
//    }
}