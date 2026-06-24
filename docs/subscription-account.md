# Subscription account

The PressStack subscription account is backed by `SubscriptionListingService`.
Controllers pass view-ready display state, facts, benefits, actions and flow
data to the account templates. Views and browser scripts must not derive
lifecycle eligibility from dates or persisted status values.

## Display groups

- `current` — active entitlement, renewing soon, expiring soon, or cancellation scheduled.
- `action_required` — payment recovery, suspended, unpaid, or processing states.
- `previous` — expired, terminally cancelled, renewed/replaced subscriptions.

`SubscriptionAccountStateResolver` owns the precedence and customer-facing
labels. `SubscriptionContinuationResolver` owns Reactivate, Renew and
Resubscribe selection.

## Authentication

Browser login issues an eight-hour `member_access_token` HttpOnly cookie.
Account pages authenticate through `AuthenticateMemberWithToken`; bearer tokens
remain supported for API clients. The legacy session is accepted during the
browser migration.

Token-protected account APIs use:

`/api/{site}/member/account/...`

Cookie-authenticated mutations additionally require the account page CSRF
token. Password changes, password resets and logout revoke member tokens.

## Billing

Stripe remains authoritative for payment credentials and invoice collection.

- Card creation uses a customer-bound, off-session SetupIntent.
- Finalisation verifies SetupIntent ownership and success server-side.
- Payment-method responses expose only ID, brand, last four digits, expiry,
  default status and removal eligibility.
- Removing the only card required by active recurring billing is blocked.
- Payment recovery redirects only to a Stripe-hosted URL from a verified open
  invoice; browser return alone never marks a payment paid.

## Main account endpoints

- `POST /api/{site}/member/account/subscriptions/{id}/cancel`
- `POST /api/{site}/member/account/subscriptions/{id}/reactivate`
- `GET /api/{site}/member/account/subscriptions/{id}/settle-payment`
- `GET /api/{site}/member/account/billing/payment-methods`
- `POST /api/{site}/member/account/billing/setup-intent`
- `POST /api/{site}/member/account/billing/finalise-setup-intent`
- `POST /api/{site}/member/account/billing/set-default`
- `POST /api/{site}/member/account/billing/remove-card`

## PressStack and member account contexts

PressStack is a global subscription storefront rather than a publication site. The PressStack account lists subscriptions owned by the authenticated member across publication sites, while publication-specific benefits, newsletters, archive links, delivery settings and member routes continue to use each subscription's own `site_id` and site slug.

The same account presentation is reused by the site-scoped member subscription page. The backend supplies a complete, context-specific account payload for both PressStack and publication member accounts.

Two endpoint providers define the account context:

- `PressStackSubscriptionAccountEndpointProvider`
- `MemberSubscriptionAccountEndpointProvider`

PressStack endpoints use `/press-stack/account/subscriptions/{id}/...`. Member endpoints use `/{site}/member/subscriptions/unified/{id}/...`.

`SubscriptionAccountPageProvider` contextualises pause, resume, cancellation, reactivation, renewal, payment recovery, billing, history, preference, delivery, address, issue-delivery and upgrade endpoints. Blade renders the completed contract and does not repair service URLs.

## Listing and state

`SubscriptionListingService::getGroupedSubscriptions()` returns `current`, `action_required` and `previous`, while preserving the legacy `active.print`, `active.digital`, `expired.print` and `expired.digital` buckets.

`SubscriptionAccountStateResolver` owns lifecycle presentation. Supported states include active, paused, scheduled cancellation, renewing, expiring, cancelled, expired, suspended, processing, replaced and action-required subscriptions.

`SubscriptionContinuationResolver` remains responsible for choosing between Reactivate, Renew and Resubscribe. It does not own the general customer-facing state precedence.

Grouped listings bulk-load publication sites and cache site slugs per service instance, avoiding the previous site lookup N+1.

## Cross-site authentication and security

Normal member tokens remain site scoped through `validateAccessToken(token, siteId)`. PressStack uses `validateMemberAccessTokenAcrossSites()` so the global account can list subscriptions across publications without weakening publication routes.

Account mutation controllers perform explicit authentication and ownership checks. POST mutations remain CSRF protected, and ownership failures return generic not-found responses. Unexpected failures are logged rather than exposed through `echo`, `die` or raw exception output.

The cross-site PressStack validator supplements the existing browser cookie, bearer-token and legacy-session behaviour documented above; it does not replace it.

## Core PressStack routes

```text
GET  /press-stack/account
GET  /press-stack/account/subscriptions
POST /press-stack/account/subscriptions/{id}/cancel
POST /press-stack/account/subscriptions/{id}/reactivate
POST /press-stack/account/subscriptions/{id}/pause
POST /press-stack/account/subscriptions/{id}/resume
GET  /press-stack/account/subscriptions/{id}/renew
GET  /press-stack/account/subscriptions/{id}/resubscribe
GET  /press-stack/account/subscriptions/{id}/settle-payment
POST /press-stack/account/subscriptions/{id}/auto-renew
POST /press-stack/account/subscriptions/{id}/billing-date/preview
POST /press-stack/account/subscriptions/{id}/billing-date
GET  /press-stack/account/subscriptions/{id}/history
GET  /press-stack/account/subscriptions/{id}/preferences
POST /press-stack/account/subscriptions/{id}/preferences
GET  /press-stack/account/subscriptions/{id}/delivery
POST /press-stack/account/subscriptions/{id}/delivery/pause
POST /press-stack/account/subscriptions/{id}/delivery/resume
GET  /press-stack/account/subscriptions/{id}/delivery-addresses
POST /press-stack/account/subscriptions/{id}/delivery-addresses/{addressId}/default
GET  /press-stack/account/subscriptions/{id}/issue-deliveries
GET  /press-stack/account/subscriptions/{id}/upgrades
POST /press-stack/account/subscriptions/{id}/upgrades/preview
POST /press-stack/account/subscriptions/{id}/upgrades
```

The member area exposes equivalent subscription-management operations beneath `/{site}/member/subscriptions/unified/{id}`. The existing token-protected billing and account endpoints beneath `/api/{site}/member/account/...` remain part of the public contract and are listed above.

## Subscription pause and resume

`SubscriptionPauseService` is the source of truth for subscription-level pause. It remains separate from dated print-delivery pause.

The service validates ownership and lifecycle state, rejects invalid or past dates, caps future pause dates at 90 days, snapshots `auto_renew`, restores the previous renewal preference on resume and performs local writes in transactions.

For Stripe-backed subscriptions, pause sets `pause_collection.behavior = void`. Resume clears `pause_collection` and synchronises local `next_billing_date` from Stripe's `current_period_end`. Stripe remains authoritative for the billing cycle. Compensating Stripe calls are made if the local transaction fails after the remote mutation.

These subscription pause operations do not change the payment-method and invoice-recovery rules in the Billing section.

## Plan issue schedules and subscriber fulfilments

Plan issue schedules and subscriber fulfilments are separate concepts.

### `issue_deliveries`

`issue_deliveries` contains the publication or subscription-plan schedule. Plan issues are selected with `subscription_plan_id`; they are not duplicated or mutated for an individual subscriber.

The account issue drawer loads active plan issues for `subscriptions.plan_id` rather than attempting to find schedule rows by `subscription_id`.

`PlanIssueScheduleRepository` owns plan-level queries, including:

- issues whose delivery date falls inside a dated pause window;
- issues whose `estimated_delivery_date` is absent, using `on_sale_date` as the fallback;
- account issues from the current date;
- past-sale issues that must remain visible because the subscriber has a future deferred fulfilment.

### `subscription_issue_fulfilments`

`subscription_issue_fulfilments` is the subscriber-specific fulfilment table. Each row links one subscription to one plan issue through:

```text
subscription_id
issue_delivery_id
```

Subscriber state belongs here:

```text
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

Supported fulfilment states include `scheduled`, `delivered`, `failed` and `superseded`.

The table was originally created as `issues_delivered` and later renamed. Older migration names still contain `issues_delivered`; runtime code should use `SubscriptionIssueFulfilment` and `SubscriptionIssueFulfilmentRepository`.

## Delivery pause behaviour

Dated delivery pause never changes the plan issue schedule.

`SubscriptionDeliveryService` identifies affected plan issues by `subscription_plan_id` and the delivery window. `IssueFulfilmentPlanner` creates subscriber fulfilment rows where necessary. The service then sets `deferred_until` only on scheduled, undispatched fulfilments belonging to the selected subscription.

Already dispatched rows are immutable for pause purposes. Pausing one subscriber does not affect another subscriber on the same plan.

Resume clears `deferred_until` only for the selected subscription's scheduled, undispatched rows.

## Dispatch and print generation

`IssueFulfilmentPlanner` claims dispatchable fulfilments inside the planning transaction by setting `dispatched_at` on rows that are due and still scheduled.

`IssueFulfilmentDispatchCoordinator` consumes the claimed fulfilment IDs returned by the planner. Digital jobs and the print event are emitted only after the transaction commits. If a downstream handoff throws before ownership is transferred, the coordinator releases the relevant dispatch claims.

Digital and print processing therefore consume the same persisted fulfilment source rather than rebuilding subscriber membership directly from plan schedule rows.

`CreatePrintFulfillmentsJob` loads subscription IDs from already dispatched `subscription_issue_fulfilments`, filters them to print subscriptions, orders them by ID and then chunks deterministically. `PrintRedispatchChunks` uses the same persisted source for controlled replay.

If a print run reaches the zero-recipient branch, `AllFulfilmentsCreated` is still emitted so the Phase 1 → Phase 2 workflow transition remains intact.

## Account issue response

`ShopAccountIssueDeliveryController` combines plan issue information with the current subscriber's fulfilment row.

The response preserves both dates:

- `scheduled_delivery_date` is the original subscriber schedule or plan estimate;
- `deferred_until` is the subscriber-specific deferral;
- `estimated_delivery_date` is the effective date shown by the current drawer frontend;
- `fulfilment_status` is the subscriber fulfilment state.

A deferred issue remains in the drawer even when its plan `on_sale_date` has passed, provided its effective subscriber delivery date is still in the future.

## Edition and publication changes

`SubscriptionIssueDeliveryRebuildService` rebuilds subscriber fulfilments in `subscription_issue_fulfilments`; it does not create subscriber-owned rows in `issue_deliveries`.

For edition changes:

1. resolve the subscriber's first future scheduled fulfilment;
2. count remaining scheduled, undispatched fulfilments;
3. validate that enough plan issues exist from the selected edition;
4. mark the subscriber's current future fulfilments as `superseded`;
5. create or reactivate fulfilments for the replacement plan issues.

For publication or plan changes, the same service validates the replacement schedule before superseding current rows and transferring the remaining issue count.

Rebuild is safe when a subscriber returns to an edition used previously. A matching undispatched `superseded` row is reactivated as `scheduled`, its scheduling fields are refreshed and stale failure or skip metadata is cleared. A dispatched row is never reactivated.

`SubscriptionEditionChangeService` resolves the old edition by subscription ID, not plan ID. `SubscriptionPlanChangeService` continues to call the rebuild service for publication changes and therefore receives the same subscriber-fulfilment behaviour.

## Fulfilment replacements

Replacement eligibility is subscriber scoped.

`FulfilmentReplacementRepository` checks `subscription_issue_fulfilments` for the requested `subscription_id` and `issue_delivery_id`, and requires that subscriber's row to have `dispatched_at` before allowing replacement. A dispatch for a different subscriber does not make the issue eligible.

Legacy reads from subscriber-owned `issue_deliveries` rows remain as a compatibility fallback while old data is being retired. New fulfilment planning, dispatch, edition changes and replacement eligibility use `subscription_issue_fulfilments`.

`FulfilmentReplacementService` itself does not require a workflow change; its eligibility dependency now resolves the correct subscriber fulfilment source.

## Upgrade payment flow

Upgrade payment uses a two-phase flow. The backend creates a PaymentIntent without changing the plan when authentication is required. The browser confirms it with `stripe.confirmCardPayment()`, then posts the PaymentIntent ID back. The backend verifies status, amount and currency before changing the plan or granting benefits.

This upgrade-specific PaymentIntent flow is separate from the SetupIntent flow used to add reusable billing credentials.

## Frontend architecture

Key scripts include:

- `subscription-account.js` — account interaction orchestration;
- `subscription-billing.js` — payment-method and billing actions;
- `subscription-history.js` — communication and lifecycle history;
- `subscription-delivery.js` — issue delivery and dated delivery pause/resume;
- `subscription-upgrade.js` — upgrade preview and payment confirmation.

Scripts should consume the backend-provided endpoint contract and must not hard-code PressStack versus publication member route paths.
