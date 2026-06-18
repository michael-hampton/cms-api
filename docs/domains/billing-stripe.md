# Billing and Stripe Domain

This domain covers orders, payments, payment allocation, invoices, refunds, Stripe customers, payment intents, subscriptions, schedules and webhook processing.

## Main locations

- `src/Services/Billing`
- `src/Services/Billing/Stripe`
- `src/Services/Billing/PaymentProviders`
- `src/Services/Billing/Order`
- `src/Services/Billing/Refund`
- billing repositories, DTOs, enums and events

## Architecture rules

Billing workflows belong in services. Examples include placing an order, allocating payment, charging off-session, creating/refunding a payment, synchronising a Stripe customer and processing a webhook.

Actions are reserved for narrow operations such as exporting invoices, bulk reconciliation, synchronising one Stripe resource or retrying one failed artefact generation.

Repositories persist local billing state only. Stripe calls belong behind injected project-owned gateway interfaces. Workflow services must not depend directly on Stripe SDK clients.

Independently changing behaviour belongs in dedicated collaborators:

- tax, totals and allocation calculators;
- refund and proration strategies;
- order state machines;
- payment eligibility policies;
- event parsers;
- idempotency-key generators;
- provider mappers;
- invoice number generators.

## Critical-flow rules

Money flows are critical. Failures must throw; silent continuation is forbidden.

Any workflow with two or more local writes uses `Database::transaction()` and returns a value. All related local writes occur inside the transaction.

A database transaction cannot roll back Stripe. Design provider calls using idempotency keys and a recoverable ordering of operations. Persist provider identifiers and statuses deliberately so retries are safe.

## Webhooks

Webhook controllers authenticate/verify input and pass a parsed event to a service. Parsing and signature verification are separate from business handling.

Webhook handling must be idempotent. Store/process provider event IDs so duplicate delivery does not duplicate charges, refunds, orders or subscription transitions.

Use enums for internal event types, payment status, order status, refund status and actions. Do not spread raw Stripe strings beyond the adapter/parser boundary.

## Side effects

After a successful billing state change, events may trigger email, analytics, fulfilment or projections. Every event must have a real listener. A billing service must not directly call unrelated services just to send notifications or tracking.

## Error handling

Translate provider exceptions into project-level exceptions or result DTOs at the gateway boundary. Public responses must not expose Stripe internals, secrets or raw exception messages.

Non-critical analytics may be caught and logged. Payment, refund, order and entitlement failures must throw and leave the operation recoverable.

## Testing

All unit mocks use Mockery. Mock gateway interfaces rather than Stripe SDK internals. No real provider calls are allowed.

Service tests cover calculations delegated to collaborators, transaction usage, idempotency, event emission, provider failure, rollback and retry-safe behaviour. Do not static-mock Stripe or framework classes.