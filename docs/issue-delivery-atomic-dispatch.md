# Atomic issue fulfilment dispatch

This document supplements `issue-delivery-fulfilment.md` and defines the concurrency, handoff and retry contract used by the subscriber fulfilment pipeline.

## Persistence boundary

`issue_deliveries` remains the shared plan schedule. Subscriber-specific state belongs to `issues_delivered`.

Every subscriber fulfilment is uniquely identified by:

```text
subscription_id
issue_delivery_id
```

The database enforces a unique constraint on that pair. Repository-level checks improve the common path, but the database remains authoritative when workers race.

When concurrent inserts collide, `IssuesDeliveredRepository::createForSubscription()` refetches and returns the winning row. Unrelated database failures are rethrown.

## Legacy scheduling repair

The fulfilment scheduling migration backfills missing `scheduled_for` values from:

```text
estimated_delivery_date ?? on_sale_date
```

The repository performs the same repair when it encounters an older row without `scheduled_for`. Existing subscriber rows therefore do not become immediately dispatchable simply because they pre-date the scheduling columns.

## Planning transaction

`GenerateIssueDeliveriesJob` resolves eligible subscriptions and runs `IssueFulfilmentPlanner::plan()` inside the database transaction.

For each subscription, the planner:

1. creates or reuses the subscription/issue fulfilment;
2. repairs missing schedule information;
3. resolves any delivery-pause deferral;
4. classifies the row;
5. groups due candidates into digital and print collections;
6. atomically claims those candidates.

The classification counters are separate:

```text
deferred
not_due
already_dispatched
non_dispatchable_status
claim_conflicts
```

`deferred` therefore means an actual future `deferred_until`, rather than every reason a row could not dispatch.

## Conditional claim

`IssuesDeliveredRepository::claimForDispatch()` sets `dispatched_at` only when the row still satisfies all of these conditions:

```text
status = scheduled
dispatched_at IS NULL
scheduled_for IS NULL OR scheduled_for <= claim time
deferred_until IS NULL OR deferred_until <= claim time
```

The update returns the number of affected rows. Only IDs whose update affected one row are returned to the planner.

If another worker wins the claim first, the losing worker records a claim conflict and does not hand the fulfilment downstream.

## Transaction and side-effect boundary

Planning, creation, repair and claiming happen inside the transaction.

Queue jobs and events are emitted only after that transaction commits. Downstream consumers therefore never receive an ID for an uncommitted fulfilment.

`IssueFulfilmentDispatchCoordinator` consumes only the claimed IDs returned by the planner. It does not perform another claim or unconditionally overwrite `dispatched_at`.

## Digital handoff

For each claimed digital fulfilment, the coordinator dispatches:

```text
DeliverIssueDeliveryJob::for($fulfilmentId)
```

If queue push throws, the coordinator calls:

```text
releaseDispatchClaims([$fulfilmentId])
```

and rethrows the original exception. A retry may then claim the row again.

The downstream delivery job updates the same fulfilment row to `delivered` or `failed`. It does not create another subscription/issue row.

## Print handoff

Print rows are claimed before `IssueDeliveryDispatched` is emitted.

This ordering is required because `CreatePrintFulfillmentsJob` discovers print recipients from persisted subscriber fulfilments where `dispatched_at` is present. A synchronous listener or immediately available queue worker therefore sees the complete claimed recipient set.

If event handoff throws, the coordinator releases the print claims and rethrows.

The existing event keeps an aggregate `skippedCount` for compatibility. The coordinator result retains the separate skip counters for diagnostics.

## Print recipient selection

`CreatePrintFulfillmentsJob`:

1. loads dispatched subscription IDs for the plan issue;
2. filters them to print subscriptions;
3. orders by subscription ID;
4. chunks using the configured print chunk size;
5. queues one `CreateFulfilmentsChunkJob` for each chunk;
6. queues one `FulfilmentCompletionMonitorJob`.

The ordering makes chunk boundaries deterministic. With a chunk size of 200, 450 recipients always produce chunks of 200, 200 and 50.

`CreateFulfilmentsChunkJob` exposes its immutable subscription IDs and chunk index through read-only accessors for diagnostics and contract tests.

## Plan issue completion

The plan-level issue is marked dispatched only when no subscriber rows remain with:

```text
status = scheduled
dispatched_at IS NULL
```

Deferred and not-yet-due rows therefore keep the plan issue open for later processing.

## Claim release limitations

Claim release is restricted to rows still in `scheduled` status. It does not reopen delivered, failed or superseded history.

A successful queue push followed by an unrelated later failure does not release the claim. At that point downstream ownership has already been transferred.

## Pause and resume interaction

Delivery pause changes only scheduled, undispatched rows for the selected subscription.

A row already claimed through `dispatched_at` is outside the pause set. Pause cannot recall digital or print work that has entered downstream processing.

Resume clears `deferred_until` only from scheduled, undispatched rows for the selected subscription.

## Edition and plan changes

Edition and publication rebuilds supersede only scheduled, undispatched rows.

A dispatched row remains immutable. An undispatched matching `superseded` row may be reactivated, with stale failure and skip metadata cleared.

The unique subscription/issue constraint prevents the rebuild from creating a duplicate row when a subscriber returns to an edition used previously.

## Replacement eligibility

Modern replacement eligibility requires an `issues_delivered` row matching the exact subscription and issue with `dispatched_at` present.

Legacy subscriber-owned `issue_deliveries` rows use a consistent subscription-scoped fallback for both ownership and dispatched status. A dispatch for another subscription is never sufficient.

## Required tests

The focused test set covers:

- unique and idempotent subscriber fulfilment creation;
- repair of missing legacy schedule dates;
- conditional claim success and repeat-claim failure;
- separate deferred and not-due classification;
- claim conflicts between workers;
- coordinator digital and print side effects;
- persisted end-to-end issue generation;
- exact print recipient IDs;
- deterministic print chunk boundaries;
- superseded-row reactivation;
- dispatched-row immutability;
- subscriber-scoped replacement eligibility.

The GitHub connector cannot execute PHPUnit. The affected suites must be run locally before merge.
