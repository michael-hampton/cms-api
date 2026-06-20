# Subscription account

PressStack is a global subscription storefront, not a publication site. The
PressStack account therefore lists every subscription owned by the authenticated
member across publication sites. Publication-specific benefits, newsletters,
archive links, delivery settings and member routes continue to use each
subscription's own `site_id` and site slug.

The same account presentation is also reused by the site-scoped member
subscription page. The backend supplies different endpoint sets for the global
PressStack context and the publication member context.

## Account contexts

Two endpoint providers define the account context:

- `PressStackSubscriptionAccountEndpointProvider`
- `MemberSubscriptionAccountEndpointProvider`

PressStack endpoints use the global account namespace:

```text
/press-stack/account/subscriptions/{id}/...
```

Member endpoints remain publication scoped:

```text
/{site}/member/subscriptions/unified/{id}/...
```

The page provider replaces action, cancellation, pause, billing, delivery,
preference, history and upgrade endpoints with the endpoints belonging to the
current context. Views also use the contextual endpoints from
`account_management` when rendering cancellation and reactivation controls.
This prevents member pages from posting through the global PressStack routes.

## Compatibility and listing shape

`SubscriptionListingService::getGroupedSubscriptions()` returns the account
groups:

- `current`
- `action_required`
- `previous`

It also retains the legacy buckets:

- `active.print`
- `active.digital`
- `expired.print`
- `expired.digital`

Legacy properties including `is_active`, `can_renew` and `should_show_renew`
remain available.

`SubscriptionAccountStateResolver` is the single lifecycle-state resolver. The
browser renders the backend-provided state and actions rather than recalculating
renewal, expiry, suspension, cancellation or payment state from dates.

Supported presentation states include active, paused, scheduled cancellation,
renewing, expiring, cancelled, expired, suspended, payment/action required,
processing and replaced subscriptions.

## Site lookup performance

Grouped listings bulk-load unique publication sites before subscription
formatting. `SubscriptionListingService` stores site slugs in a per-service
cache, avoiding a `Site::find()` query for every subscription.

Standalone subscription formatting still supports a single cached fallback
lookup for controller mutation responses.

## Authentication and request protection

Normal member token validation remains site scoped through:

```text
validateAccessToken(token, siteId)
```

PressStack uses the separate cross-site member validation path:

```text
validateMemberAccessTokenAcrossSites()
```

It rejects non-member, expired and inactive-member tokens and updates token
last-used time. Bearer tokens, the HttpOnly `member_access_token` cookie and the
transitional browser session remain supported.

All account mutation controllers perform explicit authentication and ownership
checks. POST mutations use CSRF middleware. Ownership failures return a generic
not-found response so another member's subscription cannot be enumerated.

Unexpected pause failures are logged server-side. Controllers never print raw
exceptions or terminate with `echo`/`die`; JSON error contracts are preserved.

## PressStack account routes

Core pages:

```text
GET /press-stack/account
GET /press-stack/account/subscriptions
GET /press-stack/account/orders
GET /press-stack/account/orders/{id}
GET /press-stack/account/billing
```

Subscription lifecycle:

```text
POST /press-stack/account/subscriptions/{id}/cancel
POST /press-stack/account/subscriptions/{id}/reactivate
POST /press-stack/account/subscriptions/{id}/pause
POST /press-stack/account/subscriptions/{id}/resume
GET  /press-stack/account/subscriptions/{id}/renew
GET  /press-stack/account/subscriptions/{id}/resubscribe
GET  /press-stack/account/subscriptions/{id}/settle-payment
```

Account management:

```text
POST /press-stack/account/subscriptions/{id}/auto-renew
POST /press-stack/account/subscriptions/{id}/billing-date/preview
POST /press-stack/account/subscriptions/{id}/billing-date
GET  /press-stack/account/subscriptions/{id}/history
GET  /press-stack/account/subscriptions/{id}/preferences
POST /press-stack/account/subscriptions/{id}/preferences
```

Delivery:

```text
GET  /press-stack/account/subscriptions/{id}/delivery
POST /press-stack/account/subscriptions/{id}/delivery/pause
POST /press-stack/account/subscriptions/{id}/delivery/resume
GET  /press-stack/account/subscriptions/{id}/delivery-addresses
POST /press-stack/account/subscriptions/{id}/delivery-addresses/{addressId}/default
GET  /press-stack/account/subscriptions/{id}/issue-deliveries
```

Upgrades:

```text
GET  /press-stack/account/subscriptions/{id}/upgrades
POST /press-stack/account/subscriptions/{id}/upgrades/preview
POST /press-stack/account/subscriptions/{id}/upgrades
```

Billing methods:

```text
GET  /press-stack/account/billing/payment-methods
POST /press-stack/account/billing/setup-intent
POST /press-stack/account/billing/finalise-setup-intent
POST /press-stack/account/billing/set-default
POST /press-stack/account/billing/remove-card
```

Order cancellation:

```text
POST /press-stack/account/orders/{id}/cancel
```

The site-scoped member account exposes equivalent subscription operations under
`/{site}/member/subscriptions/unified/{id}`.

## Cancellation and continuation actions

Cancellation copy, effective dates, reasons, consequences and benefits are
provided by the backend. The browser submits the endpoint embedded in the
contextual cancellation flow.

Scheduled-cancellation subscriptions expose reactivation. Expired or ended
subscriptions may expose renewal or resubscription according to
`SubscriptionContinuationResolver`.

Member cancellation and reactivation controls always use site-scoped endpoints;
PressStack controls use global endpoints.

## Pause and resume

`SubscriptionPauseService` remains the source of truth for local pause state.
It:

- validates ownership and eligible status;
- persists `paused` and `active` lifecycle states;
- snapshots `auto_renew` in `auto_renew_before_pause`;
- disables automatic renewal while paused;
- restores the previous renewal preference when resumed;
- stores `paused_at`, `pause_until` and `resumed_at`;
- recalculates the next billing date from the time paused;
- dispatches `SubscriptionPaused` and `SubscriptionResumed` events;
- performs local changes inside database transactions.

The pause button is available for eligible active subscriptions, including
subscriptions with a Stripe subscription ID. It is hidden for unsupported
states and subscriptions with cancellation already scheduled.

The current implementation does **not** call Stripe `pause_collection`, update a
Stripe schedule or move the Stripe billing anchor. The UI copy therefore only
promises that automatic renewal in the local account is disabled. Stripe remote
pause/resume orchestration remains a separate product and billing integration
requirement.

Print subscription pause is distinct from delivery pause. Billing-level pause
does not remove already queued print fulfilment. Delivery pause/resume remains a
separate feature with its own dates and endpoints.

## Payment recovery and payment methods

Subscription listing uses the latest local recoverable payment and does not call
Stripe while rendering every card.

Stripe is consulted only when settlement is initiated. Settlement requires:

- member ownership;
- a valid Stripe invoice ID;
- an open invoice;
- a hosted invoice URL;
- a positive remaining amount.

Card creation uses customer-bound SetupIntent finalisation. Payment-method
responses expose only safe presentation fields. Existing safeguards preventing
removal of a required final payment method remain in place.

## Upgrade payment flow

Subscription upgrade payment is a two-phase flow.

### Phase 1: prepare payment

The upgrade endpoint validates ownership, plan eligibility and the calculated
proration quote. When an immediate charge is required it creates a Stripe
PaymentIntent.

If the intent requires customer authentication, the service returns:

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

After successful confirmation it posts the `payment_intent_id` back to the same
upgrade endpoint. The backend retrieves the PaymentIntent and verifies:

- successful/completed status;
- expected amount;
- expected currency.

Only then does the database transaction:

- change the plan;
- record upgrade metadata and price difference;
- grant premium access;
- grant lower-tier access.

The Stripe subscription plan synchronisation runs after the local transaction
and logs external provider failures.

The browser handles authentication failure, declined/incomplete payment,
invalid confirmation responses and duplicate submissions. It shows separate
submitting, confirming and finalising states and never reports success before
the server has verified the PaymentIntent and applied the upgrade.

Both account page wrappers load Stripe.js and expose the configured publishable
key through `window.SubscriptionAccountStripeKey`.

## Frontend architecture

The account frontend uses class-based controllers and shared state rather than
inline event handlers.

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
- `subscription-account-acquisition.js` for the member context.

The unused duplicate `subscription-account-pause.js` was removed. Tests inspect
the same pause controller loaded by the page.

The shared manage-drawer partial contains markup only. Each outer page wrapper
loads `subscription-account-drawer-bootstrap.js` exactly once, preventing
nested `<details>` elements, duplicate listeners and duplicate observers.

Subscription cards use valid `<article>` markup and render contextual mutation
endpoints from backend data.

## Database changes

The subscription metadata migrations add:

- `billing_day_of_month`;
- `consent_given`.

Both migrations implement rollback by removing the column they added. Rollback
does not drop an unrelated table and does not leave an empty `down()` method.

## Test coverage

Coverage includes:

- unauthenticated PressStack and member pages;
- global multi-site listing;
- member and site filtering;
- acquisition-plan scoping;
- endpoint-provider completeness;
- contextual cancellation and reactivation endpoints;
- continuation routes;
- state resolution;
- cancellation-flow data;
- pause confirmation and persistence;
- renewal-preference preservation;
- pause availability for Stripe-backed subscriptions;
- wrong-member and wrong-site access;
- single drawer-bootstrap loading;
- active pause-controller file usage;
- Stripe client-secret handling;
- payment confirmation and server finalisation;
- failed or unverified upgrade payment handling;
- duplicate upgrade submission prevention;
- migration rollback contracts;
- payment recovery and listing output.

## Remaining product dependencies

The following are intentionally not defined by this implementation:

- authoritative renewal lead-time policy; the existing 30-day behaviour is
  preserved;
- a renewal-offer endpoint/model;
- an authoritative next-print-issue source;
- a suspension-reason taxonomy;
- Stripe pause/resume orchestration, Stripe schedule behaviour and billing-anchor
  policy;
- an automatic-resume scheduler or definitive pause-duration product policy.
