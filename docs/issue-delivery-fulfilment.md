# Issue delivery fulfilment

This document describes the complete issue-delivery fulfilment lifecycle. It covers the shared plan schedule, subscriber-specific fulfilment records, eligibility resolution, planning, digital and print dispatch, deferral, retries, rebuilds, replacements and account presentation.

## Domain model

The ownership chain is:

```text
SubscriptionPlan
  -> Subscription
  -> IssueDelivery schedule attached to the plan
  -> SubscriptionIssueFulfilment attached to the subscription and schedule row
```

`issue_deliveries` is the plan schedule. There is one schedule for the plan. A schedule row is never copied, moved or rewritten for an individual subscriber.

`subscription_issue_fulfilments` is the subscriber fulfilment ledger. A row is created for each eligible subscription and issue schedule combination. The row itself begins in `scheduled`; there is no separate entitlement record before fulfilment planning.

The old `issues_delivered` table/model name is historical. New code should use `subscription_issue_fulfilments`, `SubscriptionIssueFulfilment` and `SubscriptionIssueFulfilmentRepository`.

The uniqueness boundary is the pair:

```text
subscription_id
issue_delivery_id
```

This boundary makes planning idempotent and prevents the same subscriber from receiving duplicate fulfilments for the same plan issue.

## Plan schedule

An `IssueDelivery` belongs to a subscription plan through `subscription_plan_id`. Its effective schedule date is:

```text
estimated_delivery_date ?? on_sale_date
```

`PlanIssueScheduleRepository` is the plan-level source of truth. It resolves:

- issues for a plan;
- issues whose effective delivery date falls in a pause window;
- future issues from a starting issue or date;
- issues displayed in the account;
- past-sale issues that remain relevant because a subscriber fulfilment was deferred into the future.

Subscriber actions must not mutate these rows. A delivery pause, edition change or publication change changes only subscriber fulfilment rows.

## Subscriber fulfilment record

`SubscriptionIssueFulfilment` stores the subscriber-specific state:

```text
subscription_id
issue_delivery_id
status
scheduled_for
deferred_until
dispatched_at
delivered_at
failed_at
failure_reason
skip_reason
attempts
```

The lifecycle states are:

- `scheduled` — the fulfilment exists and may be dispatched when due;
- `delivered` — delivery completed successfully;
- `failed` — delivery failed and may be retried while below the attempt limit;
- `superseded` — the undispatched fulfilment was replaced by an edition or plan rebuild.

The timestamps have different meanings:

- `scheduled_for` is the original subscriber delivery date derived from the plan schedule;
- `deferred_until` is an optional subscriber-specific delay;
- `dispatched_at` records the atomic handoff into the downstream digital or print pipeline;
- `delivered_at` records successful completion;
- `failed_at` records the most recent failed attempt.

`SubscriptionIssueFulfilment::canDispatchAt()` returns true only when the row is still `scheduled`, has not already been dispatched, `scheduled_for` is due and `deferred_until` is absent or due.

## End-to-end generation flow

`GenerateIssueDeliveriesJob` is the orchestration entry point for one plan issue.

### 1. Load the issue schedule

The job loads the `IssueDelivery` identified by the job payload. If the schedule cannot be processed, the job does not attempt subscriber fulfilment work.

### 2. Resolve eligible subscriptions

`IssueDeliveryEligibilityService` returns subscriptions eligible for that issue. Eligibility is resolved from the subscription and plan relationship; the issue schedule is not subscriber owned.

If eligibility resolution raises a domain error:

1. the failure is logged;
2. the plan issue is marked dispatch-failed;
3. `IssueDeliveryDispatchFailed` is emitted;
4. no subscriber fulfilments are created or dispatched.

### 3. Plan fulfilments in a transaction

`IssueFulfilmentPlanner::plan()` runs inside the database transaction.

For each eligible subscription it:

1. searches `subscription_issue_fulfilments` by `subscription_id` and `issue_delivery_id`;
2. creates a row when none exists;
3. ensures `status = scheduled` and `attempts = 0` for new/reactivated rows;
4. sets `scheduled_for` from `estimated_delivery_date ?? on_sale_date`;
5. calculates a subscriber deferral when the subscription has a delivery pause covering that date;
6. excludes rows that are not currently dispatchable;
7. separates dispatchable fulfilment IDs into digital and print candidate collections;
8. atomically claims dispatchable candidates by setting `dispatched_at`.

The planner returns:

```text
digital_ids
print_ids
created
deferred
not_due
already_dispatched
non_dispatchable_status
claim_conflicts
```

The transaction contains database planning and claiming only. Downstream jobs and events are dispatched after the transaction returns.

## Idempotency and repeat execution

Planning is safe to rerun.

`SubscriptionIssueFulfilmentRepository::createForSubscription()` first checks the subscription/schedule pair. Existing rows are returned instead of duplicated.

A previously `superseded` row may be reactivated only when it has never been dispatched. Reactivation:

- sets the status back to `scheduled`;
- resets `attempts` to zero;
- refreshes `scheduled_for` and `deferred_until`;
- clears `failed_at`, `failure_reason` and `skip_reason`.

A dispatched row is never reactivated. This prevents a later edition switch from quietly sending the same physical or digital issue twice.

## Dispatch coordination

`IssueFulfilmentDispatchCoordinator` consumes the claimed IDs returned by the planner.

### Digital fulfilments

For every claimed digital fulfilment ID:

1. `dispatched_at` has already been set by the planner's conditional claim;
2. `DeliverIssueDeliveryJob::for($fulfilmentId)` is dispatched.

If queue dispatch throws before ownership is handed off, the coordinator releases the affected claims with `releaseDispatchClaims()`. A later retry can then claim those rows again.

The downstream digital job is responsible for completing the row as `delivered` or `failed`.

### Print fulfilments

Print fulfilments are not sent through the digital delivery job.

When print IDs exist, the coordinator emits `IssueDeliveryDispatched` with:

- the plan issue;
- the number of claimed print fulfilments;
- the number of new fulfilment rows;
- the number deferred or skipped from immediate dispatch.

The print pipeline listens to this event and builds the print work from persisted subscriber fulfilments where `dispatched_at` is present. If the event handoff throws, the coordinator releases the print claims before rethrowing.

### Completing the plan issue dispatch

The plan-level `IssueDelivery` is marked dispatched only when no undispatched `scheduled` subscriber fulfilments remain for that issue.

This matters when some subscribers are deferred or not yet due. The plan issue must not be treated as completely dispatched while subscriber rows still require later processing.

The coordinator logs and returns a summary containing:

```text
issue_delivery_id
created
deferred
not_due
already_dispatched
non_dispatchable_status
claim_conflicts
digital_dispatches
print_dispatches
eligible_subscriptions
```

## Digital completion and failure

A digital fulfilment remains `scheduled` after dispatch until the delivery job completes.

On success, `markAsDelivered()`:

- changes the status to `delivered`;
- records `delivered_at`;
- refreshes the local fulfilment counters on the parent subscription.

On failure, `markAsFailed()`:

- changes the status to `failed`;
- increments `attempts`;
- records `failed_at`;
- appends the failure reason to the failure log;
- refreshes the local fulfilment counters on the parent subscription.

`SubscriptionIssueFulfilmentRepository::getFailedRetriable()` selects failed rows below the configured maximum attempts. A retry operates on the existing subscriber fulfilment row; it does not create a second row.

## Print pipeline

The print pipeline starts from persisted `subscription_issue_fulfilments` rows, not by rerunning broad plan eligibility queries.

`CreatePrintFulfillmentsJob` processes the print fulfilments selected for the plan issue:

1. load dispatched subscription IDs for the issue;
2. filter them to print subscriptions;
3. order by subscription ID;
4. chunk using the configured print chunk size;
5. dispatch one `CreateFulfilmentsChunkJob` per chunk;
6. dispatch `FulfilmentCompletionMonitorJob` when chunks exist.

Chunking must be deterministic. Given the same persisted set and ordering, retries must produce the same chunk boundaries. This prevents subscribers being omitted or included twice when a large run is retried.

If the job runs and produces zero chunks, it still fires `AllFulfilmentsCreated` with `totalFulfilments = 0`. That event is required because `AllFulfilmentsCreatedListener` owns the Phase 1 → Phase 2 transition into print order generation and batching.

`PrintRedispatchChunks` supports controlled replay of print work using the already dispatched subscriber fulfilments.

The subscriber fulfilment is marked dispatched before it enters the print pipeline. Later print workflow stages may record downstream completion, failure or replacement data, but they must continue to reference the same `subscription_id` and `issue_delivery_id` fulfilment.

## Subscription-local fulfilment counters

The `subscriptions` table stores local fulfilment counters for fast account and admin reads:

```text
fulfilments_count
scheduled_fulfilments_count
dispatched_fulfilments_count
delivered_fulfilments_count
failed_fulfilments_count
superseded_fulfilments_count
```

The migration backfills these counters from `subscription_issue_fulfilments`.

After the migration, `SubscriptionIssueFulfilmentRepository` and the fulfilment model keep the counters in sync when rows are created, reactivated, superseded, dispatched, dispatch claims are released, delivered or failed.

## Delivery pause and resume

A dated delivery pause is subscriber specific and is separate from pausing subscription billing.

`SubscriptionDeliveryService`:

1. validates the subscription and pause window;
2. resolves plan issues whose effective dates fall inside the window;
3. ensures subscriber fulfilment rows exist for those plan issues;
4. updates only rows for the selected subscription;
5. sets `deferred_until` only on `scheduled`, undispatched rows.

The deferral date is the day after the pause end date. The original `scheduled_for` value is retained.

Already dispatched rows are immutable for pause purposes. A pause cannot recall an issue that already entered digital delivery or print production.

Resuming delivery clears `deferred_until` only from the selected subscription's `scheduled`, undispatched rows. It does not alter the plan schedule and does not touch another subscriber on the same plan.

## Deferred dispatch

A deferred row remains `scheduled` but is not dispatchable while `deferred_until` is in the future.

When later processing revisits the plan issue, `canDispatchAt()` allows the row once both `scheduled_for` and `deferred_until` are due. The same fulfilment row then enters the normal digital or print branch.

Deferred rows are why plan-level dispatch completion checks `hasUndispatchedForIssue()` rather than marking the plan issue complete immediately after the first generation run.

## Edition changes

An edition change replaces only the subscriber's remaining undispatched fulfilments.

`SubscriptionIssueDeliveryRebuildService` performs the following sequence:

1. resolves the first future `scheduled`, undispatched fulfilment for the subscription;
2. counts all remaining future `scheduled`, undispatched fulfilments;
3. resolves replacement plan issues for the selected edition from the equivalent point;
4. validates that the replacement schedule contains enough issues;
5. marks the current future subscriber fulfilments as `superseded`;
6. creates or reactivates subscriber fulfilments for the replacement schedule.

Dispatched fulfilments are outside the rebuild set and remain historical facts.

`SubscriptionEditionChangeService` resolves the old edition using the subscription, not by confusing the subscription ID with the plan ID.

## Publication and plan changes

Publication or plan changes use the same rebuild rules as edition changes.

The remaining issue count is transferred to the replacement plan only after the replacement schedule is validated. The service must not supersede the current rows first and then discover the destination schedule is too short.

`SubscriptionPlanChangeService` delegates subscriber issue rebuilding to `SubscriptionIssueDeliveryRebuildService`, so edition and publication changes share the same idempotency and dispatched-row protections.

## Returning to a previous edition

A subscription may move back to an edition used earlier.

When a matching subscriber/schedule row already exists as `superseded` and was never dispatched, the repository reactivates it rather than creating a duplicate. Stale failure and skip metadata is cleared.

When the matching row was dispatched, it remains immutable and cannot be reactivated.

## Replacement fulfilments

Replacement eligibility is scoped to the subscriber fulfilment, not merely the plan issue.

`FulfilmentReplacementRepository` requires a `subscription_issue_fulfilments` row matching both:

```text
subscription_id
issue_delivery_id
```

That exact row must have `dispatched_at` before a replacement is allowed. A dispatch belonging to another subscriber does not make the issue replacement-eligible.

Legacy subscriber-owned `issue_deliveries` reads remain only as a compatibility fallback while old data is retired. New planning, dispatch, pause, rebuild and replacement decisions use `subscription_issue_fulfilments`.

## Account projection

`ShopAccountIssueDeliveryController` combines the plan issue with the authenticated subscriber's fulfilment row.

The response exposes:

- `scheduled_delivery_date` — original plan-derived subscriber date;
- `deferred_until` — subscriber-specific delay;
- `estimated_delivery_date` — effective date currently shown to the member;
- `fulfilment_status` — current subscriber state.

A past-sale plan issue remains visible when the subscriber has a future `deferred_until`. Account visibility therefore follows the effective subscriber delivery date rather than hiding the issue solely because `on_sale_date` passed.

## Repository responsibilities

`PlanIssueScheduleRepository` owns plan schedule queries.

`SubscriptionIssueFulfilmentRepository` owns subscriber fulfilment persistence and transitions, including:

- lookup by subscription and schedule;
- idempotent creation;
- superseded-row reactivation;
- future fulfilment lookup and counting;
- local subscription counter syncing;
- deferral and release;
- dispatchable-row selection;
- dispatched-row tracking;
- retry selection;
- superseding future rows.

Keeping these responsibilities separate prevents accidental writes to the shared plan schedule when the intention is to alter one subscriber's fulfilment.

## Required invariants

The fulfilment pipeline must preserve all of these rules:

1. One plan schedule is shared by all subscriptions on the plan.
2. One subscriber fulfilment exists per subscription and issue schedule pair.
3. A fulfilment starts as `scheduled`, then becomes `delivered`, `failed` or `superseded`.
4. Planning is idempotent.
5. Claiming happens inside the planning transaction before downstream handoff.
6. Downstream dispatch happens after the planning transaction.
7. Digital and print delivery consume persisted fulfilment IDs.
8. A deferred subscriber does not alter the plan schedule or another subscriber.
9. A dispatched row is immutable for pause and rebuild purposes.
10. Failed retries reuse the existing row.
11. Replacement eligibility requires dispatch of that subscriber's exact fulfilment.
12. A plan issue is fully dispatched only when no scheduled undispatched fulfilments remain.
13. Account dates distinguish the original schedule from subscriber deferral.
14. A zero print-recipient run still emits `AllFulfilmentsCreated` so the print workflow completes cleanly.

## Test coverage

Focused tests cover:

- planner creation and idempotency;
- deferral calculation;
- digital and print branching;
- coordinator dispatch and plan completion;
- future and dispatchable repository queries;
- failed retry limits;
- pause and resume isolation by subscription;
- deterministic print chunking;
- zero print-recipient transition event;
- edition and publication rebuild counts;
- superseded-row reactivation;
- prevention of dispatched-row reactivation;
- subscriber-scoped replacement eligibility;
- account issue projection and effective dates;
- subscription-local fulfilment counter syncing;
- migration rollback ordering.
