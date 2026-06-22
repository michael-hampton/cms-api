# Subscriptions Domain

The subscriptions domain covers plans, pricing, recurring and one-time subscription payments, upgrades, cancellations, refunds, premium access, print delivery, communications and invoice-driven state changes.

## Main locations

- `src/Services/Subscriptions`
- `src/Services/Subscriptions/Calculators`
- `src/Services/Subscriptions/Refunds`
- `src/Services/Subscriptions/DeliveryChannels`
- `src/Services/Subscriptions/Printing`
- `src/Services/Billing/StripeSubscriptionOrchestrator.php`
- subscription repositories, DTOs, value objects, enums and events

## Architecture rules

Subscription workflows belong in services. Examples include starting, renewing, upgrading, cancelling, refunding, granting premium access and responding to paid/failed invoices.

Actions are only for narrow operations such as exporting subscribers, bulk importing subscriptions, bulk status migration, synchronising one provider subscription or generating one print run.

Repositories persist plans, subscriptions, entitlements, invoices and related records. They do not calculate prices, decide eligibility or orchestrate provider calls.

Independently changing logic belongs in dedicated collaborators:

- pricing and proration calculators;
- upgrade quote builders;
- refund strategies;
- renewal-window and cancellation policies;
- trial eligibility policies;
- delivery-channel strategies;
- premium-access policies;
- billing-cycle resolvers.

Services coordinate these collaborators; they must not absorb their calculations.

## State and entitlement rules

Use enums for subscription status, interval, delivery type, cancellation action, invoice state and refund type. Do not introduce magic strings.

Entitlement changes are critical. A payment must not succeed while local access remains inconsistent without an explicit recoverable state. Multi-write entitlement/subscription updates are transactional.

Trial eligibility is a business rule. A member who has already consumed a plan trial must not receive it again; the policy decides whether checkout proceeds without a trial or is rejected according to the requested behaviour.

`replaced_by_subscription_id` is a lifecycle transition, not just an audit link. When a subscription is replaced by a successful renewal, switch or resubscribe flow, the source subscription must no longer be treated as current or entitled.

## Resubscribe lifecycle

Resubscribe is for expired, cancelled or otherwise non-current subscriptions. It creates a new subscription through the existing subscription modal checkout flow. It is not the same workflow as active subscription renewal and must not be routed through `SubscriptionRenewalService`, which is for active or paused hard-replace renewals.

The account card may open the subscription modal with the original plan preselected. The modal must select plans from `.sub-plan` elements only; account action buttons may also carry plan metadata but are not plan cards. The selected modal plan must provide `delivery_type` to `/cart/subscription` before checkout continues.

The resubscribe source id is carried through checkout as `resubscribe_from_subscription_id`. Pending subscription creation tags the new subscription only after validating member, site and plan ownership:

- new subscription: `renewed_from_subscription_id = source_subscription.id`;
- new subscription: `replacement_reason = 'resubscribe'`.

The source subscription must not be changed while checkout is pending or abandoned. Only after payment succeeds and the new subscription activates should the source subscription be finalised:

- source subscription: `status = SubscriptionStatus::REPLACED->value`;
- source subscription: `replaced_by_subscription_id = new_subscription.id`.

The resulting state is:

- old subscription: `status = replaced`, `replaced_by_subscription_id = new_id`;
- new subscription: `status = active`, `renewed_from_subscription_id = old_id`, `replacement_reason = resubscribe`.

Current subscription listings and entitlement checks must exclude replaced subscriptions. Prefer status/entitlement policies over date-only checks where lifecycle state matters.

## Stripe boundary

Stripe subscription, schedule, customer and payment operations use injected gateway contracts. Raw Stripe objects and event names should be translated at the adapter/parser boundary.

Provider calls must be idempotent and retry-safe. Persist provider IDs and pending states needed to recover from partial external success.

## Communications and print

Emails, notifications, analytics and print fulfilment are side effects and should be triggered by real events/listeners where they are not required to complete the core transaction.

Delivery channels and print drivers are strategies behind interfaces. Subscription workflow services choose or resolve them; they do not contain provider-specific formatting or transport logic.

## Testing

Use Mockery for all dependency mocks. Pricing, proration, refund and eligibility collaborators should have focused deterministic tests. Workflow-service tests cover transaction usage, emitted events, provider failures, rollback, retry behaviour and entitlement consistency. No real Stripe, print, mail or API calls are allowed.

Resubscribe tests should cover source tagging, wrong-member/site/plan guards, abandoned checkout behaviour, payment-success finalisation, and exclusion of replaced subscriptions from current/entitled views.
