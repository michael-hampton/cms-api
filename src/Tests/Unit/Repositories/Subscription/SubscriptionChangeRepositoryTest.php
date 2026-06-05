<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repositories\Subscription;

use App\Models\SubscriptionChange;
use App\Repositories\Subscriptions\SubscriptionChangeRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class SubscriptionChangeRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private SubscriptionChangeRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new SubscriptionChangeRepository();
    }

    // ── recordEditionChange ───────────────────────────────────────────────────

    public function test_record_edition_change_persists_correct_fields(): void
    {
        $subscription = $this->createSubscription();

        $record = $this->repository->recordEditionChange(
            subscriptionId: $subscription->id,
            oldEditionId:   10,
            newEditionId:   20,
            createdBy:      99,
            reason:         'Customer requested upgrade',
        );

        $this->assertNotNull($record->id);
        $this->assertEquals($subscription->id, $record->subscription_id);
        $this->assertEquals('edition_change',             $record->change_type);
        $this->assertEquals(10,                           $record->old_edition_id);
        $this->assertEquals(20,                           $record->new_edition_id);
        $this->assertEquals(99,                           $record->created_by);
        $this->assertEquals('Customer requested upgrade', $record->reason);
    }

    public function test_record_edition_change_accepts_null_reason(): void
    {
        $subscription = $this->createSubscription();

        $record = $this->repository->recordEditionChange(
            subscriptionId: $subscription->id,
            oldEditionId:   10,
            newEditionId:   20,
            createdBy:      1,
            reason:         null,
        );

        $this->assertNull($record->reason);
    }

    public function test_record_edition_change_sets_change_type_to_edition_change(): void
    {
        $subscription = $this->createSubscription();

        $record = $this->repository->recordEditionChange(
            subscriptionId: $subscription->id,
            oldEditionId:   1,
            newEditionId:   2,
            createdBy:      1,
        );

        $this->assertEquals('edition_change', $record->change_type);
    }

    public function test_record_edition_change_is_persisted_to_database(): void
    {
        $subscription = $this->createSubscription();

        $record = $this->repository->recordEditionChange(
            subscriptionId: $subscription->id,
            oldEditionId:   5,
            newEditionId:   6,
            createdBy:      1,
        );

        $fromDb = SubscriptionChange::find($record->id);

        $this->assertNotNull($fromDb);
        $this->assertEquals(5, $fromDb->old_edition_id);
        $this->assertEquals(6, $fromDb->new_edition_id);
    }

    public function test_record_edition_change_does_not_populate_publication_fields(): void
    {
        $subscription = $this->createSubscription();

        $record = $this->repository->recordEditionChange(
            subscriptionId: $subscription->id,
            oldEditionId:   1,
            newEditionId:   2,
            createdBy:      1,
        );

        $fromDb = SubscriptionChange::find($record->id);

        $this->assertNull($fromDb->old_publication_id);
        $this->assertNull($fromDb->new_publication_id);
        $this->assertNull($fromDb->remaining_issues_transferred);
    }

    // ── recordPublicationChange ───────────────────────────────────────────────

    public function test_record_publication_change_persists_correct_fields(): void
    {
        $subscription = $this->createSubscription();

        $record = $this->repository->recordPublicationChange(
            subscriptionId:            $subscription->id,
            oldPublicationId:          100,
            newPublicationId:          200,
            oldEditionId:              10,
            newEditionId:              20,
            remainingIssuesTransferred: 5,
            createdBy:                 99,
            reason:                    'Publication discontinued',
        );

        $this->assertNotNull($record->id);
        $this->assertEquals($subscription->id,      $record->subscription_id);
        $this->assertEquals('publication_change',    $record->change_type);
        $this->assertEquals(100,                     $record->old_publication_id);
        $this->assertEquals(200,                     $record->new_publication_id);
        $this->assertEquals(10,                      $record->old_edition_id);
        $this->assertEquals(20,                      $record->new_edition_id);
        $this->assertEquals(5,                       $record->remaining_issues_transferred);
        $this->assertEquals(99,                      $record->created_by);
        $this->assertEquals('Publication discontinued', $record->reason);
    }

    public function test_record_publication_change_accepts_null_reason(): void
    {
        $subscription = $this->createSubscription();

        $record = $this->repository->recordPublicationChange(
            subscriptionId:            $subscription->id,
            oldPublicationId:          100,
            newPublicationId:          200,
            oldEditionId:              10,
            newEditionId:              20,
            remainingIssuesTransferred: 3,
            createdBy:                 1,
            reason:                    null,
        );

        $this->assertNull($record->reason);
    }

    public function test_record_publication_change_sets_change_type_to_publication_change(): void
    {
        $subscription = $this->createSubscription();

        $record = $this->repository->recordPublicationChange(
            subscriptionId:            $subscription->id,
            oldPublicationId:          1,
            newPublicationId:          2,
            oldEditionId:              10,
            newEditionId:              20,
            remainingIssuesTransferred: 0,
            createdBy:                 1,
        );

        $this->assertEquals('publication_change', $record->change_type);
    }

    public function test_record_publication_change_is_persisted_to_database(): void
    {
        $subscription = $this->createSubscription();

        $record = $this->repository->recordPublicationChange(
            subscriptionId:            $subscription->id,
            oldPublicationId:          100,
            newPublicationId:          200,
            oldEditionId:              10,
            newEditionId:              20,
            remainingIssuesTransferred: 7,
            createdBy:                 1,
        );

        $fromDb = SubscriptionChange::find($record->id);

        $this->assertNotNull($fromDb);
        $this->assertEquals(100, $fromDb->old_publication_id);
        $this->assertEquals(200, $fromDb->new_publication_id);
        $this->assertEquals(7,   $fromDb->remaining_issues_transferred);
    }

    public function test_record_publication_change_with_zero_remaining_issues(): void
    {
        $subscription = $this->createSubscription();

        $record = $this->repository->recordPublicationChange(
            subscriptionId:            $subscription->id,
            oldPublicationId:          1,
            newPublicationId:          2,
            oldEditionId:              10,
            newEditionId:              20,
            remainingIssuesTransferred: 0,
            createdBy:                 1,
        );

        $this->assertEquals(0, $record->remaining_issues_transferred);
    }

    public function test_multiple_changes_can_be_recorded_for_same_subscription(): void
    {
        $subscription = $this->createSubscription();

        $this->repository->recordEditionChange($subscription->id, 1, 2, 1);
        $this->repository->recordEditionChange($subscription->id, 2, 3, 1);

        $count = SubscriptionChange::where('subscription_id', $subscription->id)->count();

        $this->assertEquals(2, $count);
    }
}