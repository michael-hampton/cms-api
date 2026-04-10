<?php

namespace App\Tests\Unit\Repositories\OpenCollab;

use App\Enums\OpenCollab\PaymentStatus;
use App\Models\ArticlePayment;
use App\Models\Model;
use App\Repositories\OpenCollab\ArticlePaymentRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class ArticlePaymentRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private ArticlePaymentRepository $repository;

    public function test_find_by_payment_intent_id_returns_correct_record(): void
    {
        $payment = $this->makePayment(['stripe_payment_intent_id' => 'pi_abc123']);

        $found = $this->repository->findByPaymentIntentId('pi_abc123');

        $this->assertNotNull($found);
        $this->assertEquals($payment->id, $found->id);
    }

    private function makePayment(array $overrides = []): Model
    {
        $page = $this->createPage();
        return ArticlePayment::create(array_merge([
            'page_id' => $page->id,
            'amount' => 500,
            'currency' => 'gbp',
            'status' => PaymentStatus::Succeeded->value,
            'stripe_payment_intent_id' => 'pi_' . uniqid(),
            'site_id' => $this->siteId,
            'email' => 'test@test.com'
        ], $overrides));
    }

    public function test_find_by_payment_intent_id_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->findByPaymentIntentId('pi_doesnotexist'));
    }

    public function test_update_status_changes_status(): void
    {
        $payment = $this->makePayment(['status' => PaymentStatus::Pending->value]);

        $this->repository->updateStatus($payment->id, PaymentStatus::Succeeded->value);

        $this->assertDatabaseHas('oc_article_payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::Succeeded->value,
        ]);
    }

    public function test_sum_succeeded_amount_for_contributor_sums_correct_payments(): void
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();

        // Arrange – two pages owned by contributor 10, one by someone else
        $page1 = $this->createPage(['id' => 100, 'contributor_id' => $user1->id]);
        $page2 = $this->createPage(['id' => 101, 'contributor_id' => $user1->id]);
        $page3 = $this->createPage(['id' => 102, 'contributor_id' => $user2->id]);

        $this->makePayment(['page_id' => $page1->id, 'amount' => 300, 'status' => PaymentStatus::Succeeded->value]);
        $this->makePayment(['page_id' => $page2->id, 'amount' => 200, 'status' => PaymentStatus::Succeeded->value]);
        $this->makePayment(['page_id' => $page1->id, 'amount' => 999, 'status' => PaymentStatus::Pending->value]); // excluded
        $this->makePayment(['page_id' => $page3->id, 'amount' => 999, 'status' => PaymentStatus::Succeeded->value]); // other contributor

        // Act
        $total = $this->repository->sumSucceededAmountForContributor($user1->id);

        // Assert
        $this->assertEquals(500, $total);
    }

    public function test_sum_succeeded_amount_returns_zero_when_no_payments(): void
    {
        $this->assertEquals(0, $this->repository->sumSucceededAmountForContributor(999));
    }

    public function test_earnings_breakdown_groups_by_page(): void
    {
        $user = $this->createUser();
        $page1 = $this->createPage(['id' => 10, 'contributor_id' => $user->id, 'title' => 'Article A']);
        $page2 = $this->createPage(['id' => 11, 'contributor_id' => $user->id, 'title' => 'Article B']);

        $this->makePayment(['page_id' => $page1->id, 'amount' => 100]);
        $this->makePayment(['page_id' => $page1->id, 'amount' => 50]);
        $this->makePayment(['page_id' => $page2->id, 'amount' => 200]);

        $breakdown = $this->repository->earningsBreakdownForContributor($user->id);

        $this->assertCount(2, $breakdown);
        // Ordered by total desc – Article B (200) before Article A (150)
        $this->assertEquals(11, $breakdown[0]['page_id']);
        $this->assertEquals(200, $breakdown[0]['total']);
        $this->assertEquals(10, $breakdown[1]['page_id']);
        $this->assertEquals(150, $breakdown[1]['total']);
    }

    public function test_transaction_history_paginates_correctly(): void
    {
        $user = $this->createUser();
        $page = $this->createPage(['contributor_id' => $user->id]);

        for ($i = 0; $i < 5; $i++) {
            $this->makePayment(['page_id' => $page->id]);
        }

        $result = $this->repository->transactionHistoryForContributor($user->id, perPage: 3);

        $this->assertCount(3, $result['data']->all());
        $this->assertEquals(5, $result['pagination']['total']);
    }

    public function test_transaction_history_excludes_pending_payments(): void
    {
        $user = $this->createUser();
        $page = $this->createPage(['id' => 30, 'contributor_id' => $user->id]);

        $this->makePayment(['page_id' => $page->id, 'status' => PaymentStatus::Pending->value]);
        $this->makePayment(['page_id' => $page->id, 'status' => PaymentStatus::Succeeded->value]);

        $result = $this->repository->transactionHistoryForContributor($user->id);

        $this->assertCount(1, $result['data']->all());
        $this->assertEquals(PaymentStatus::Succeeded->value, $result['data']->all()[0]->status);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ArticlePaymentRepository();
    }
}