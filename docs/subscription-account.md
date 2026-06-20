# Subscription account

PressStack is a global subscription storefront, not a publication site. The
account resolves an authenticated member and lists every subscription owned by
that member across publication sites. Publication-specific benefits,
newsletters and archive links still use each subscription's own `site_id`.

## Compatibility

`SubscriptionListingService::getGroupedSubscriptions()` keeps the legacy
`active.print`, `active.digital`, `expired.print` and `expired.digital` buckets
alongside the account groups:

- `current`
- `action_required`
- `previous`

Legacy flags including `is_active`, `can_renew` and `should_show_renew` remain
available. `SubscriptionAccountStateResolver` is the only lifecycle-state
resolver; browser JavaScript renders its state and actions without calculating
renewal, expiry, suspension or cancellation from dates.

## Authentication

Normal `validateAccessToken(token, siteId)` behavior remains site-scoped.
PressStack account middleware uses the separate member-only
`validateMemberAccessTokenAcrossSites()` path. It rejects non-member, expired
and inactive-member tokens, and updates token last-used time. Bearer tokens,
the HttpOnly `member_access_token` cookie and the transitional browser session
remain supported.

All PressStack account mutations use member authentication middleware. POST
mutations also use CSRF middleware.

## Account routes

- `GET /press-stack/account`
- `GET /press-stack/account/subscriptions`
- `GET /press-stack/account/orders`
- `GET /press-stack/account/orders/{id}`
- `GET /press-stack/account/billing`
- `POST /press-stack/account/subscriptions/{id}/cancel`
- `POST /press-stack/account/subscriptions/{id}/reactivate`
- `POST /press-stack/account/subscriptions/{id}/pause`
- `POST /press-stack/account/subscriptions/{id}/resume`
- `GET /press-stack/account/subscriptions/{id}/renew`
- `GET /press-stack/account/subscriptions/{id}/resubscribe`
- `GET /press-stack/account/subscriptions/{id}/settle-payment`
- `POST /press-stack/account/orders/{id}/cancel`
- `GET /press-stack/account/billing/payment-methods`
- `POST /press-stack/account/billing/setup-intent`
- `POST /press-stack/account/billing/finalise-setup-intent`
- `POST /press-stack/account/billing/set-default`
- `POST /press-stack/account/billing/remove-card`

## Payment recovery and billing

Listing uses the latest local recoverable payment and does not call Stripe per
subscription render. Stripe is consulted only when settlement is initiated.
Settlement requires member ownership, a valid Stripe invoice ID, an open
invoice, a hosted invoice URL and a positive remaining amount.

Card creation remains customer-bound through SetupIntent finalisation.
Payment-method responses remain limited to safe presentation fields, and the
existing protection against removing a required final payment method remains.

## Pause and resume

`SubscriptionPauseService` retains its existing constructor, methods,
transactions, events, `auto_renew` behavior and billing-date calculations.
The account calls that established contract directly. No new pause duration,
automatic-resume scheduler or Stripe billing-anchor policy is defined here.

## Frontend

Subscription cancellation/actions, order filtering/cancellation and billing
are implemented as class-based controllers with explicit state objects.
Account templates contain no inline event handlers.

Each subscription also links to its publication-specific member management
area. This keeps My Account aligned with the established member subscription
features for email preferences, delivery schedules, delivery addresses,
upgrades, billing-date and auto-renew controls, delivery pause/resume and
digital downloads without duplicating publication-specific rules.

## Unresolved product dependencies

- The authoritative renewal lead-time policy; the current 30-day behavior is
  preserved.
- A renewal-offer endpoint/model.
- An authoritative next-print-issue source.
- A suspension-reason taxonomy.
- Confirmed Stripe pause/resume orchestration and any pause-duration or
  automatic-resume policy.
