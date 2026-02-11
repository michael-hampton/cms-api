<?php

namespace App\Tests\Unit\Repositories\Product;

use App\Models\MerchantUrl;
use App\Models\ProductMerchant;
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

    public function test_get_statistics_returns_correct_structure(): void
    {
        $merchant1 = $this->createMerchant(['is_active' => true]);
        $merchant2 = $this->createMerchant(['is_active' => true]);
        $merchant3 = $this->createMerchant(['is_active' => false]);

        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        ProductMerchant::create([
            'merchant_id' => $merchant1->id,
            'product_id' => $product1->id,
            'price' => 100.00,
            'sale_price' => 80.00,
            'is_available' => true,
            'url' => 'https://url1.com',
        ]);

        ProductMerchant::create([
            'merchant_id' => $merchant2->id,
            'product_id' => $product2->id,
            'price' => 200.00,
            'sale_price' => null,
            'is_available' => true,
            'url' => 'https://url2.com',
        ]);

        $stats = $this->repository->getStatistics();

        $this->assertArrayHasKey('total_merchants', $stats);
        $this->assertArrayHasKey('active_merchants', $stats);
        $this->assertArrayHasKey('total_products', $stats);
        $this->assertArrayHasKey('products_on_sale', $stats);
        $this->assertArrayHasKey('avg_discount_percentage', $stats);
        $this->assertArrayHasKey('total_revenue_estimate', $stats);
        $this->assertArrayHasKey('top_merchants_by_products', $stats);

        $this->assertEquals(3, $stats['total_merchants']);
        $this->assertEquals(2, $stats['active_merchants']);
        $this->assertEquals(2, $stats['total_products']);
        $this->assertEquals(1, $stats['products_on_sale']);
    }


    public function test_statistics_calculates_average_discount_correctly(): void
    {
        $merchant = $this->createMerchant(['is_active' => true]);

        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        // 20% discount
        ProductMerchant::create([
            'merchant_id' => $merchant->id,
            'product_id' => $product1->id,
            'price' => 100.00,
            'sale_price' => 80.00,
            'is_available' => true,
            'url' => 'https://url1.com',
        ]);

        // 50% discount
        ProductMerchant::create([
            'merchant_id' => $merchant->id,
            'product_id' => $product2->id,
            'price' => 100.00,
            'sale_price' => 50.00,
            'is_available' => true,
            'url' => 'https://url2.com',
        ]);

        $stats = $this->repository->getStatistics();

        // (20 + 50) / 2 = 35
        $this->assertEquals(35.00, $stats['avg_discount_percentage']);
    }


    public function test_statistics_excludes_inactive_merchants(): void
    {
        $activeMerchant = $this->createMerchant(['is_active' => true]);
        $inactiveMerchant = $this->createMerchant(['is_active' => false]);

        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        ProductMerchant::create([
            'merchant_id' => $activeMerchant->id,
            'product_id' => $product1->id,
            'price' => 100.00,
            'sale_price' => null,
            'is_available' => true,
            'url' => 'https://url1.com',
        ]);

        ProductMerchant::create([
            'merchant_id' => $inactiveMerchant->id,
            'product_id' => $product2->id,
            'price' => 200.00,
            'sale_price' => null,
            'is_available' => true,
            'url' => 'https://url2.com',
        ]);

        $stats = $this->repository->getStatistics();

        $this->assertEquals(1, $stats['total_products']);
        $this->assertEquals(100.00, $stats['total_revenue_estimate']);
    }

    public function test_get_statistics_can_filter_by_merchant(): void
    {
        $merchant1 = $this->createMerchant(['is_active' => true]);
        $merchant2 = $this->createMerchant(['is_active' => true]);

        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        ProductMerchant::create([
            'merchant_id' => $merchant1->id,
            'product_id' => $product1->id,
            'price' => 100.00,
            'sale_price' => 80.00,
            'is_available' => true,
            'url' => 'https://url1.com',
        ]);

        ProductMerchant::create([
            'merchant_id' => $merchant2->id,
            'product_id' => $product2->id,
            'price' => 200.00,
            'sale_price' => null,
            'is_available' => true,
            'url' => 'https://url2.com',
        ]);

        $stats = $this->repository->getStatistics($merchant1->id);

        $this->assertEquals(1, $stats['total_merchants']);
        $this->assertEquals(1, $stats['active_merchants']);
        $this->assertEquals(1, $stats['total_products']);
        $this->assertEquals(1, $stats['products_on_sale']);
        $this->assertEquals($merchant1->id, $stats['filtered_merchant_id']);
    }

    public function test_create_note_stores_note_correctly(): void
    {
        $merchant = $this->createMerchant();
        $user = $this->createUser();

        $note = $this->repository->createNote(
            $merchant->id,
            $user->id,
            'Test note content'
        );

        $this->assertNotNull($note);
        $this->assertEquals($merchant->id, $note->merchant_id);
        $this->assertEquals($user->id, $note->user_id);
        $this->assertEquals('Test note content', $note->content);
    }

    public function test_get_notes_returns_notes_with_user(): void
    {
        $merchant = $this->createMerchant();
        $user = $this->createUser();

        $this->repository->createNote($merchant->id, $user->id, 'Note 1');
        $this->repository->createNote($merchant->id, $user->id, 'Note 2');

        $notes = $this->repository->getNotes($merchant->id);

        $this->assertCount(2, $notes);
        $this->assertNotNull($notes->first()->user);
        $this->assertEquals($user->name, $notes->first()->user['name']);
    }

    public function test_update_note_modifies_content(): void
    {
        $merchant = $this->createMerchant();
        $user = $this->createUser();

        $note = $this->repository->createNote($merchant->id, $user->id, 'Original');

        $updated = $this->repository->updateNote($note->id, 'Updated content');

        $this->assertNotNull($updated);
        $this->assertEquals('Updated content', $updated->content);
    }

    public function test_delete_note_removes_note(): void
    {
        $merchant = $this->createMerchant();
        $user = $this->createUser();

        $note = $this->repository->createNote($merchant->id, $user->id, 'Test');

        $result = $this->repository->deleteNote($note->id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('merchant_notes', ['id' => $note->id]);
    }

    public function test_update_balance_updates_merchant_balance(): void
    {
        $merchant = $this->createMerchant(['balance' => 100.00]);

        $result = $this->repository->updateBalance($merchant->id, 250.50);

        $this->assertTrue($result);
        $this->assertEquals(250.50, $merchant->fresh()->balance);
    }

    public function test_update_balance_returns_false_for_nonexistent_merchant(): void
    {
        $result = $this->repository->updateBalance(99999, 100.00);

        $this->assertFalse($result);
    }

    public function test_create_transaction_stores_transaction(): void
    {
        $merchant = $this->createMerchant();
        $order = $this->createOrder();

        $transaction = $this->repository->createTransaction([
            'merchant_id' => $merchant->id,
            'order_id' => $order->id,
            'type' => 'credit',
            'amount' => 50.00,
            'status' => 'completed',
            'description' => 'Order payment',
            'balance_before' => 0,
            'balance_after' => 50,
        ]);

        $this->assertNotNull($transaction);
        $this->assertEquals($merchant->id, $transaction->merchant_id);
        $this->assertEquals('credit', $transaction->type);
        $this->assertEquals(50.00, $transaction->amount);
    }

    public function test_get_transactions_returns_all_transactions_for_merchant(): void
    {
        $merchant1 = $this->createMerchant();
        $merchant2 = $this->createMerchant();

        $this->repository->createTransaction([
            'merchant_id' => $merchant1->id,
            'type' => 'credit',
            'amount' => 100.00,
            'status' => 'completed',
            'balance_before' => 0,
            'balance_after' => 50,
        ]);

        $this->repository->createTransaction([
            'merchant_id' => $merchant1->id,
            'type' => 'debit',
            'amount' => 50.00,
            'status' => 'completed',
            'balance_before' => 50.00,
            'balance_after' => 50,
        ]);

        $this->repository->createTransaction([
            'merchant_id' => $merchant2->id,
            'type' => 'credit',
            'amount' => 75.00,
            'status' => 'completed',
            'balance_before' => 75.00,
            'balance_after' => 50,
        ]);

        $transactions = $this->repository->getTransactions($merchant1->id);

        $this->assertCount(2, $transactions);
    }

    public function test_get_transactions_filters_by_type(): void
    {
        $merchant = $this->createMerchant();

        $this->repository->createTransaction([
            'merchant_id' => $merchant->id,
            'type' => 'credit',
            'amount' => 100.00,
            'status' => 'completed',
            'balance_before' => 0,
            'balance_after' => 50,
        ]);

        $this->repository->createTransaction([
            'merchant_id' => $merchant->id,
            'type' => 'debit',
            'amount' => 50.00,
            'status' => 'completed',
            'balance_before' => 50.00,
            'balance_after' => 50,
        ]);

        $transactions = $this->repository->getTransactions($merchant->id, ['type' => 'credit']);

        $this->assertCount(1, $transactions);
        $this->assertEquals('credit', $transactions->first()->type);
    }

    public function test_get_transactions_filters_by_status(): void
    {
        $merchant = $this->createMerchant();

        $this->repository->createTransaction([
            'merchant_id' => $merchant->id,
            'type' => 'credit',
            'amount' => 100.00,
            'status' => 'completed',
            'balance_before' => 0,
            'balance_after' => 50,
        ]);

        $this->repository->createTransaction([
            'merchant_id' => $merchant->id,
            'type' => 'credit',
            'amount' => 50.00,
            'status' => 'pending',
            'balance_before' => 50.00,
            'balance_after' => 50,
        ]);

        $transactions = $this->repository->getTransactions($merchant->id, ['status' => 'pending']);

        $this->assertCount(1, $transactions);
        $this->assertEquals('pending', $transactions->first()->status);
    }

    public function test_get_transactions_filters_by_date_range(): void
    {
        $merchant = $this->createMerchant();

        // Create transaction with older date
        $old = $this->repository->createTransaction([
            'merchant_id' => $merchant->id,
            'type' => 'credit',
            'amount' => 100.00,
            'status' => 'completed',
            'balance_before' => 0,
            'balance_after' => 50,
        ]);
        $old->update(['created_at' => '2024-01-01']);

        // Create recent transaction
        $this->repository->createTransaction([
            'merchant_id' => $merchant->id,
            'type' => 'credit',
            'amount' => 50.00,
            'status' => 'completed',
            'balance_before' => 50.00,
            'balance_after' => 50,
        ]);

        $transactions = $this->repository->getTransactions($merchant->id, [
            'from_date' => '2024-06-01'
        ]);

        $this->assertCount(1, $transactions);
    }

    public function test_get_pending_review_transactions_returns_only_pending(): void
    {
        $merchant = $this->createMerchant();

        $this->repository->createTransaction([
            'merchant_id' => $merchant->id,
            'type' => 'credit',
            'amount' => 100.00,
            'status' => 'pending_review',
            'balance_before' => 0,
            'balance_after' => 50,
        ]);

        $this->repository->createTransaction([
            'merchant_id' => $merchant->id,
            'type' => 'credit',
            'amount' => 50.00,
            'status' => 'completed',
            'balance_before' => 50.00,
            'balance_after' => 50,
        ]);

        $pending = $this->repository->getPendingReviewTransactions();

        $this->assertCount(1, $pending);
        $this->assertEquals('pending_review', $pending->first()->status);
    }

    public function test_get_pending_review_transactions_filters_by_merchant(): void
    {
        $merchant1 = $this->createMerchant();
        $merchant2 = $this->createMerchant();

        $this->repository->createTransaction([
            'merchant_id' => $merchant1->id,
            'type' => 'credit',
            'amount' => 100.00,
            'status' => 'pending_review',
            'balance_before' => 0,
            'balance_after' => 50,
        ]);

        $this->repository->createTransaction([
            'merchant_id' => $merchant2->id,
            'type' => 'credit',
            'amount' => 50.00,
            'status' => 'pending_review',
            'balance_before' => 50.00,
            'balance_after' => 50,
        ]);

        $pending = $this->repository->getPendingReviewTransactions($merchant1->id);

        $this->assertCount(1, $pending);
        $this->assertEquals($merchant1->id, $pending->first()->merchant_id);
    }

    public function test_update_transaction_status_updates_status(): void
    {
        $merchant = $this->createMerchant();
        $transaction = $this->repository->createTransaction([
            'merchant_id' => $merchant->id,
            'type' => 'credit',
            'amount' => 100.00,
            'status' => 'pending_review',
            'balance_before' => 0,
            'balance_after' => 50,
        ]);

        $result = $this->repository->updateTransactionStatus($transaction->id, 'completed');

        $this->assertTrue($result);
        $this->assertEquals('completed', $transaction->fresh()->status);
    }

    public function test_update_transaction_status_updates_notes(): void
    {
        $merchant = $this->createMerchant();
        $transaction = $this->repository->createTransaction([
            'merchant_id' => $merchant->id,
            'type' => 'credit',
            'amount' => 100.00,
            'status' => 'pending_review',
            'balance_before' => 0,
            'balance_after' => 50,
        ]);

        $result = $this->repository->updateTransactionStatus(
            $transaction->id,
            'completed',
            'Approved by admin'
        );

        $this->assertTrue($result);
        $this->assertEquals('Approved by admin', $transaction->fresh()->notes);
    }

    public function test_update_transaction_status_returns_false_for_nonexistent(): void
    {
        $result = $this->repository->updateTransactionStatus(99999, 'completed');

        $this->assertFalse($result);
    }

}