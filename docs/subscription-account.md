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
