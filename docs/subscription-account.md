# Subscription account

PressStack is a global subscription storefront, not a publication site. The
PressStack account lists every subscription owned by the authenticated member
across publication sites. Publication-specific benefits, newsletters, archive
links, delivery settings and member routes continue to use each subscription's
own `site_id` and site slug.

The same account presentation is reused by the site-scoped member subscription
page. The backend supplies a complete, context-specific account payload for both
PressStack and publication member accounts.

## Account contexts

Two endpoint providers define the account context:

- `PressStackSubscriptionAccountEndpointProvider`
- `MemberSubscriptionAccountEndpointProvider`

PressStack endpoints use:

```text
/press-stack/account/subscriptions/{id}/...
```

Member endpoints use:

```text
/{site}/member/subscriptions/unified/{id}/...
```

`SubscriptionAccountPageProvider` is responsible for contextualising the full
subscription payload. It rewrites:

- pause and resume endpoints;
- cancellation-flow endpoints;
- reactivation endpoints;
- renew and resubscribe URLs;
- payment-settlement URLs;
- auto-renew, billing-date, history and upgrade endpoints;
- delivery, preference, address and issue-delivery endpoints.

The Blade card renders the contract it receives and does not repair global URLs
at presentation time. This keeps other consumers of the page provider safe from
receiving a mixture of member and PressStack endpoints.

## Compatibility and listing shape

`SubscriptionListingService::getGroupedSubscriptions()` returns:

- `current`
- `action_required`
- `previous`

It also preserves the legacy buckets:

- `active.print`
- `active.digital`
- `expired.print`
- `expired.digital`

Legacy properties including `is_active`, `can_renew` and `should_show_renew`
remain available.

`SubscriptionAccountStateResolver` is the single lifecycle-state resolver. The
browser renders backend-provided states and actions rather than recalculating
renewal, expiry, suspension, cancellation or payment state from dates.

Supported presentation states include active, paused, scheduled cancellation,
renewing, expiring, cancelled, expired, suspended, processing, replaced and
action-required subscriptions.

## Site lookup performance

Grouped listings bulk-load unique publication sites before formatting.
`SubscriptionListingService` caches site slugs for the lifetime of the service,
which removes the previous `Site::find()` query per subscription.

Standalone formatting retains a single cached fallback lookup for controller
mutation responses.

## Authentication and request protection

Normal member token validation remains site scoped through:

```text
validateAccessToken(token, siteId)
```

PressStack uses:

```text
validateMemberAccessTokenAcrossSites()
```

The cross-site path rejects non-member, expired and inactive-member tokens and
updates token last-used time. Bearer tokens, the HttpOnly
`member_access_token` cookie and the transitional browser session remain
supported.

All account mutation controllers perform explicit authentication and ownership
checks. POST mutations use CSRF middleware. Ownership failures return generic
not-found responses to prevent subscription enumeration.

Unexpected controller failures are logged server-side. Account controllers do
not expose exceptions through `echo`/`die`; JSON response contracts are
preserved.

## Routes

### PressStack pages

```text
GET /press-stack/account
GET /press-stack/account/subscriptions
GET /press-stack/account/orders
GET /press-stack/account/orders/{id}
GET /press-stack/account/billing
```

### Subscription lifecycle

```text
POST /press-stack/account/subscriptions/{id}/cancel
POST /press-stack/account/subscriptions/{id}/reactivate
POST /press-stack/account/subscriptions/{id}/pause
POST /press-stack/account/subscriptions/{id}/resume
GET  /press-stack/account/subscriptions/{id}/renew
GET  /press-stack/account/subscriptions/{id}/resubscribe
GET  /press-stack/account/subscriptions/{id}/settle-payment
```

### Account management

```text
POST /press-stack/account/subscriptions/{id}/auto-renew
POST /press-stack/account/subscriptions/{id}/billing-date/preview
POST /press-stack/account/subscriptions/{id}/billing-date
GET  /press-stack/account/subscriptions/{id}/history
GET  /press-stack/account/subscriptions/{id}/preferences
POST /press-stack/account/subscriptions/{id}/preferences
```

### Delivery

```text
GET  /press-stack/account/subscriptions/{id}/delivery
POST /press-stack/account/subscriptions/{id}/delivery/pause
POST /press-stack/account/subscriptions/{id}/delivery/resume
GET  /press-stack/account/subscriptions/{id}/delivery-addresses
POST /press-stack/account/subscriptions/{id}/delivery-addresses/{addressId}/default
GET  /press-stack/account/subscriptions/{id}/issue-deliveries
```

### Upgrades

```text
GET  /press-stack/account/subscriptions/{id}/upgrades
POST /press-stack/account/subscriptions/{id}/upgrades/preview
POST /press-stack/account/subscriptions/{id}/upgrades
```

### Billing methods

```text
GET  /press-stack/account/billing/payment-methods
POST /press-stack/account/billing/setup-intent
POST /press-stack/account/billing/finalise-setup-intent
POST /press-stack/account/billing/set-default
POST /press-stack/account/billing/remove-card
```

### Orders

```text
POST /press-stack/account/orders/{id}/cancel
```

The member area exposes equivalent subscription operations beneath:

```text
/{site}/member/subscriptions/unified/{id}
```

## Cancellation and continuation

Cancellation copy, effective dates, reasons, consequences and benefits are
provided by the backend. `SubscriptionAccountPageProvider` places the correct
contextual endpoint directly into `cancellation_flow`.

Scheduled-cancellation subscriptions expose a contextual reactivation action.
Expired or ended subscriptions may expose renew or resubscribe actions according
to `SubscriptionContinuationResolver`.

Member actions never depend on PressStack fallback URLs.

## Pause and resume

`SubscriptionPauseService` is the source of truth for subscription-level pause.
It is separate from print-delivery pause.

The service:

- validates ownership and lifecycle status;
- rejects invalid, same-day and past `pause_until` dates;
- caps future dated pauses at 90 days;
- allows an indefinite pause when no date is supplied;
- snapshots `auto_renew` in `auto_renew_before_pause`;
- sets `auto_renew` to `false` while paused;
- restores the exact previous renewal preference on resume;
- stores and clears `paused_at`, `pause_until` and `resumed_at`;
- recalculates the local next billing date from the paused duration;
- dispatches `SubscriptionPaused` and `SubscriptionResumed` events;
- performs local state changes inside database transactions.

The `Subscription` model explicitly includes the following pause fields in its
fillable and cast contracts:

```text
auto_renew_before_pause  boolean
paused_at                datetime
pause_until              datetime
resumed_at               datetime
```

### Stripe billing synchronisation

Automated renewal is Stripe-led, so changing only local `auto_renew` would not
be sufficient to stop collection.

For subscriptions with a Stripe subscription ID:

- pause calls `StripeSubscriptionGateway::pauseCollection()`;
- Stripe receives `pause_collection.behavior = void`;
- resume calls `StripeSubscriptionGateway::resumeCollection()`;
- Stripe receives an empty `pause_collection` value to restore collection.

Stripe is updated before the local transaction. If the local pause transaction
fails, the service compensates by resuming Stripe collection. If the local
resume transaction fails, it compensates by reapplying the Stripe pause.
Compensation failures are logged with the local and Stripe subscription IDs.

Stripe schedule handling and moving the remote billing-cycle anchor are not
part of this implementation. The local next billing date is recalculated on
resume, while the remote Stripe cycle remains governed by Stripe's subscription
configuration.

### Access and fulfilment semantics

Subscription-level pause:

- stops local entitlement states that depend on active subscription status;
- disables renewal and pauses Stripe collection;
- has no automatic end date in the card flow;
- requires the member to resume manually;
- does not remove print issues already queued for fulfilment;
- does not replace the dated print-delivery pause feature;
- prevents upgrade and manual-renewal actions until resumed;
- keeps cancellation available.

Print delivery pause remains a separate dated fulfilment feature in the
management drawer.

## Payment recovery and payment methods

Subscription listing uses the latest local recoverable payment and does not call
Stripe for every rendered card.

Stripe is consulted when settlement is initiated. Settlement requires:

- member ownership;
- a valid Stripe invoice ID;
- an open invoice;
- a hosted invoice URL;
- a positive remaining amount.

Card creation uses customer-bound SetupIntent finalisation. Payment-method
responses expose only safe presentation fields. Existing safeguards preventing
removal of a required final payment method remain in place.

## Upgrade payment flow

Upgrade payment is a two-phase flow.

### Phase 1: prepare

The upgrade endpoint validates ownership, plan eligibility and the proration
quote. When an immediate charge is required, it creates a Stripe PaymentIntent.

If customer authentication is required, the service returns:

- `requires_confirmation`;
- `payment_intent_id`;
- `client_secret`;
- the quoted amount;

without changing the subscription plan or granting benefits.

### Phase 2: confirm and finalise

The browser calls:

```javascript
stripe.confirmCardPayment(clientSecret)
```

After successful confirmation, it posts `payment_intent_id` back to the same
upgrade endpoint. The backend retrieves the PaymentIntent and verifies:

- completed status;
- expected amount;
- expected currency.

Only then does the transaction:

- change the plan;
- record upgrade metadata and price difference;
- grant premium access;
- grant lower-tier access.

The browser handles declined payments, authentication failure, incomplete
responses and duplicate submissions. It exposes separate submitting,
confirming and finalising states and cannot report success before server-side
verification and plan mutation complete.

Both account wrappers load Stripe.js and expose the publishable key through:

```javascript
window.SubscriptionAccountStripeKey
```

## Frontend architecture

The account frontend uses class-based controllers and shared state. Templates do
not use inline event handlers.

Key scripts include:

- `subscription-account-runtime.js`
- `subscription-account.js`
- `subscription-account-drawer-bootstrap.js`
- `subscription-account-management.js`
- `subscription-account-pause-controller.js`
- `subscription-account-upgrade.js`
- `subscription-account-history-delivery.js`
- `subscription-account-preferences.js`
- `subscription-account-delivery-address.js`
- `subscription-account-digital-access.js`
- `subscription-account-issue-deliveries.js`
- `subscription-account-acquisition.js` in member context.

The removed duplicate `subscription-account-pause.js` is not loaded or tested.
`SubscriptionPauseJavascriptContractTest` reads the production
`subscription-account-pause-controller.js` file.

The manage-drawer partial contains markup only. Each outer wrapper loads
`subscription-account-drawer-bootstrap.js` once, preventing duplicate listeners,
observers and nested accordion wrappers.

Subscription cards use valid `<article>` markup.

## Database changes

Subscription metadata includes:

- `billing_day_of_month`;
- `consent_given`;
- `auto_renew_before_pause`;
- `paused_at`;
- `resumed_at`.

The billing-day and consent migrations implement rollback by removing the column
they add.

## Test coverage

Coverage includes:

- unauthenticated PressStack and member pages;
- global multi-site listing;
- member and site filtering;
- acquisition-plan scoping;
- complete endpoint-provider contracts;
- contextual cancellation and reactivation payloads;
- continuation routes;
- state resolution;
- cancellation-flow data;
- pause confirmation and persistence;
- past and same-day pause-date rejection;
- renewal-preference preservation;
- pause metadata fillable/cast contracts;
- Stripe pause and resume SDK payloads;
- Stripe/local compensation paths;
- wrong-member and wrong-site access;
- single drawer-bootstrap loading;
- production pause-controller contract;
- Stripe upgrade confirmation and server finalisation;
- failed or unverified upgrade payments;
- duplicate upgrade submission prevention;
- migration rollback contracts;
- payment recovery and listing output.

## Remaining product dependencies

The following remain outside this implementation:

- authoritative renewal lead-time policy; the existing 30-day behaviour is
  preserved;
- a renewal-offer endpoint/model;
- an authoritative next-print-issue source;
- a suspension-reason taxonomy;
- Stripe schedule-specific pause semantics and remote billing-anchor movement;
- an automatic-resume scheduler and final dated-pause product policy.
