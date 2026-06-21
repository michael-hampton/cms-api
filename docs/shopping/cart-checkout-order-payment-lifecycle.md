# Shopping: Cart, Checkout, Orders, Payments and Refund Lifecycle

This document describes the shopping subsystem starting from `CartController` and following the real code paths into cart handling, checkout UI routing, order creation, Stripe payments, recurring subscription payments, one-time subscription orders, physical product orders, multi-merchant orders and refunds.

It is intentionally implementation-led. The goal is to document what the code currently does, including rough edges, rather than describe a cleaner imaginary architecture.

## 1. Main entry point

The main HTTP entry point is:

```text
src/Controllers/Shopping/CartController.php
```

`CartController` owns the customer-facing shopping flow:

- cart page rendering;
- cart summary API;
- checkout page rendering;
- subscription checkout page routing;
- product checkout submission;
- one-time subscription checkout submission;
- subscription bundle add-to-cart;
- product offer and bundle add-to-cart;
- order confirmation;
- guest/anonymous checkout identity;
- OTP verification and cart restoration.

The heavy lifting is delegated to services, but the controller and checkout view together decide which checkout path is used.

## 2. Core services and files

The key files in this area are:

```text
src/Controllers/Shopping/CartController.php
src/views/checkout/index.php
src/Services/Shopping/CartService.php
src/Services/Shopping/CheckoutService.php
src/Services/Shopping/OneTimeSubscriptionCheckoutService.php
src/Services/Shopping/CheckoutEligibilityService.php
src/Services/Billing/Order/OrderCreationService.php
src/Services/Billing/Order/OrderDraftService.php
src/Services/Billing/Payments/PaymentIntentService.php
src/Services/Subscriptions/SubscriptionPaymentService.php
src/Services/Subscriptions/SubscriptionCancellationService.php
src/Services/Subscriptions/SubscriptionRefundService.php
```

Supporting services include shipping, fulfilment, tax, discounts, vouchers, rewards, stock, merchant splitting, payment allocation, cart migration, Stripe gateways and subscription state managers.

## 3. Cart ownership

Cart rows are associated with either:

- a session cart ID; or
- an authenticated member/user ID.

`CartService::getSessionId()` stores a generated `cart_session_id` in session if one does not already exist.

When an anonymous checkout later resolves to a member, the controller migrates the session cart to the member through `CartMigrationService`.

Most cart repository lookups use both user ID and session ID. Do not assume the cart is purely session-based or purely member-based.

## 4. Cart item types

A cart can contain several kinds of line:

- normal physical/digital product rows;
- product variant rows;
- product offer rows;
- product bundle rows;
- one-time subscription plan rows;
- recurring subscription plan rows;
- subscription bundle rows;
- free gift rows.

Subscription rows use `subscription_plan_id`. Product rows use `product_id`. Subscription bundle rows are stored as individual plan rows with bundle metadata in `options`.

## 5. Adding physical products

`CartController::add()` receives `product_id`, `quantity` and optional `options`, then delegates to `CartService::addItem()`.

`CartService::addItem()`:

1. loads the product with merchants and variants;
2. rejects inactive or missing products;
3. optionally resolves a variant;
4. prevents adding a normal product row when the same product/variant is already in the cart as an offer or bundle;
5. validates stock before writing;
6. resolves price using `CartPriceResolver`;
7. increments an existing cart row when possible;
8. otherwise creates a new cart row using `CartItemFactory::fromProduct()`.

Stock validation happens before persistence. Quantity updates revalidate stock as well.

## 6. Adding subscriptions to cart

`CartController::addSubscription()` receives:

- `plan_id`;
- `delivery_type`;
- optional `pricing_id` / `pricing_tier_id`;
- optional `duration_months`;
- optional `issues` / `issue_count`;
- optional `voucher_code`.

It passes normalised data into `CartService::addSubscriptionToCart()`.

`CartService::addSubscriptionToCart()`:

1. loads the subscription plan with pricing tiers;
2. rejects inactive or missing plans;
3. validates the delivery type against the plan's delivery options;
4. prevents duplicate subscription plan rows in the current cart;
5. resolves the selected pricing tier or falls back to the lowest effective price;
6. creates either one-time subscription cart data directly or a subscription cart item via `CartItemFactory::fromSubscription()`.

One-time plans do not need a product row. Recurring plans may be represented through an associated product path depending on plan setup.

## 7. Adding subscription bundles

`CartController::addSubscriptionBundle()` delegates to `CartService::addSubscriptionBundleToCart()`.

The service:

1. loads the bundle;
2. verifies it is currently active;
3. verifies it has plan items;
4. rejects the whole bundle if any included plan is already in the cart;
5. allocates the bundle price across plans through `SubscriptionBundlePriceAllocator`;
6. creates one cart row per included plan;
7. stores the bundle ID and subscription bundle type in cart item options.

The operation is transactional. If one plan cannot be added, the whole bundle add rolls back.

Vouchers are intentionally not stackable on top of subscription bundle pricing. Bundle pricing is already a negotiated allocation and should remain cent-perfect.

## 8. Adding offers and product bundles

`CartController::addOffer()` delegates to `CartService::addOfferToCart()`.

Offer rows:

- require an active offer;
- require the underlying product to be active;
- reject duplicate product rows already in cart;
- use sale price when available;
- capture merchant context from the offer.

`CartController::addBundle()` delegates to `CartService::addBundleToCart()` for product bundles. Product bundles are expanded into cart rows from the bundle items and run transactionally.

## 9. Quantity updates and removals

`CartController::update()` calls `CartService::updateQuantity()`.

Rules:

- quantity below 1 removes the item;
- product/variant rows are stock checked;
- subscription rows check plan availability;
- print subscription rows also check next issue availability and stock unless the issue is a preorder;
- subtotal is recalculated as `price * quantity`.

`CartController::remove()` calls `CartService::removeItem()` and then gift rows are recalculated. Gift rows are removed when no paid item remains.

## 10. Shipping requirement

`CartService::requiresShipping()` returns false only when every cart item is digital.

Digital checks include:

- subscription delivery type is `digital`;
- variant `is_digital`;
- product `is_digital`.

Anything not explicitly digital causes the cart to require shipping.

## 11. Cart and checkout page rendering

`CartController::page()` and `CartController::checkoutPage()` enrich cart items for the UI.

They calculate or attach:

- subtotal;
- shipping;
- tax;
- cart total;
- item count;
- start-date options for subscription items;
- estimated delivery ranges;
- trial days for trial plans;
- preorder warnings;
- currency code and symbol;
- saved cards for logged-in members;
- checkout view model disclosures.

The checkout page detects:

- physical/product carts;
- subscription carts;
- one-time subscription carts;
- recurring subscription carts;
- mixed one-time/recurring subscription carts;
- mixed physical/product + subscription carts;
- preorders.

## 12. Checkout identity and OTP flow

Guest checkout can resolve identity before payment.

The controller supports:

- `verifyEmail()`;
- `checkPendingOTP()`;
- `verifyOTP()`;
- `cancelOTP()`;
- `resendOTP()`;
- `getStatus()`.

When OTP is required:

1. the current cart is snapshotted through `CartPersistenceService`;
2. a checkout token is stored;
3. pending OTP email is stored in session;
4. after OTP verification, the member is logged in;
5. the session cart is migrated to the member;
6. the cart snapshot is restored;
7. OTP session state is cleared.

The ordering is important: migrate the session cart first, then restore the OTP snapshot.

## 13. Checkout routing: controller and view together

The controller has a defensive branch in `processCheckout()`:

```text
subscriptionItems = items with subscription_plan_id
productItems      = items without subscription_plan_id
```

At controller level:

- if there are subscription items, the request is routed to `processSubscription()`;
- if there are no subscription items, product checkout goes through `CheckoutService`;
- product-only carts can use single-order or multi-merchant checkout.

The checkout view is the more important customer-facing router. `src/views/checkout/index.php` prevents a physical/product + subscription cart from being submitted together:

- it renders a mixed-cart warning;
- it disables the continue/place-order button for `isMixedCart`;
- `advanceToPayment()` refuses to proceed for `isMixedCart`;
- `process()` refuses to submit for `isMixedCart`.

So, through the normal checkout UI, a subscription checkout should not contain physical product rows. Physical/product and subscription orders are split before submission.

## 14. Subscription flow splitting in the checkout view

The view contains a `CartFlowService` and `CheckoutManager` that split subscription checkout by subscription type.

`CartFlowService` exposes:

```text
oneTimeItems   = subscriptionItems where is_one_time is true
recurringItems = subscriptionItems where is_one_time is false
```

For mixed one-time + recurring subscription carts, `CheckoutManager::handleMixedSubscriptionFlow()` does this:

1. replace the cart with recurring subscription items only;
2. run recurring subscription checkout;
3. replace the cart with one-time subscription items only;
4. run one-time subscription checkout;
5. redirect to subscription confirmation with all completed subscription IDs.

This is why a subscription checkout route receives a clean subset. The cart is deliberately rebuilt for each associated route before submitting.

If the one-time phase fails after recurring subscriptions were created, the view stores the pending one-time state in `sessionStorage`, rebuilds the cart with those one-time items, and surfaces an error telling the customer the one-time payment still needs completing.

## 15. Product checkout path

Product-only checkout goes through `CheckoutService::processCheckout()`.

The flow is:

1. validate checkout data;
2. load cart items;
3. validate and resolve product availability;
4. attach delivery estimates;
5. validate item eligibility;
6. validate voucher if supplied;
7. resolve discounts;
8. calculate shipping;
9. resolve currency;
10. calculate tax;
11. create a Stripe PaymentIntent;
12. allocate stock;
13. prepare order items;
14. create the order;
15. apply voucher usage;
16. handle merchant voucher funding where relevant;
17. claim reward usage where relevant;
18. clear the cart;
19. return order and payment intent context.

## 16. Product checkout validation

`CheckoutService::validateCheckoutData()` only requires address fields when the cart requires shipping.

For shippable carts, required fields are:

- first name;
- last name;
- email;
- address, city, postal code and country unless a saved address is supplied.

Digital-only carts do not require shipping address fields.

## 17. Product availability and stock

`CheckoutService::validateAndResolveAvailability()` locks products or variants for update.

For each physical product row:

1. lock the variant when `variant_id` exists, otherwise lock the product;
2. check the product availability policy;
3. calculate sellable stock;
4. reject insufficient stock unless the item is a preorder;
5. require expected ship date for preorders;
6. resolve order-line availability state;
7. attach `order_line_status`, `expected_ship_date` and `is_preorder` to the cart item.

Later, inside the order creation transaction, `allocateStockForCartItems()` decrements stock for product rows that are ready to ship.

Preorder rows are skipped for immediate stock decrement. Subscription stock is also skipped here because subscription issue stock is handled by the subscription fulfilment action.

## 18. Product order creation

Product order creation uses `OrderCreationService::create()`.

That service:

1. resolves or attaches the member;
2. resolves shipping/billing addresses;
3. prepares order data;
4. generates an order number if missing;
5. creates the order;
6. creates order items;
7. calculates commission and net amount for merchant lines;
8. credits merchants where applicable;
9. logs order history;
10. emits `OrderCreatedEvent`.

Order items are validated for `unit_price` and `quantity`.

Order line status defaults to `ready_to_ship` unless the checkout attached a specific preorder or availability status.

## 19. Product Stripe PaymentIntent timing

For product checkout, `CheckoutService::processCheckout()` creates the Stripe PaymentIntent before order creation inside the checkout flow.

The payment intent DTO includes:

- total amount in cents;
- currency;
- discount metadata;
- voucher metadata.

The service returns the `client_secret` and `payment_intent_id` to the frontend.

There is a separate `confirmRegularCheckoutPayment()` method which confirms the PaymentIntent and, when Stripe reports `succeeded`, marks the order completed and paid, clears the cart and emits `OrderCompletedEvent`.

Current gotcha: product checkout creates the PaymentIntent before the order exists. Metadata therefore contains discount context but not a final order ID at creation time.

## 20. Product payment completion

A product order starts as:

```text
status = pending
payment_status = unpaid
```

After payment confirmation succeeds:

```text
status = completed
payment_status = paid
```

The confirmation path is:

```text
CheckoutService::confirmRegularCheckoutPayment(paymentIntentId, orderId)
```

This method calls the Stripe gateway to confirm the PaymentIntent, then updates the order state inside a transaction.

## 21. Multi-merchant checkout

When the request has `multi_merchant === true`, `CartController::processCheckout()` calls `CheckoutService::processMultiMerchantCheckout()`.

The multi-merchant flow:

1. validates checkout data;
2. loads cart items;
3. validates availability and delivery estimates;
4. runs eligibility filtering;
5. validates voucher data;
6. resolves discounts;
7. splits cart items by merchant through `CheckoutSplittingService`;
8. calculates per-group shipping;
9. calculates order-level tax;
10. allocates totals per merchant group through `PaymentAllocationService`;
11. creates a Stripe PaymentIntent per Stripe-eligible group;
12. creates a checkout ID;
13. allocates stock once for the full cart;
14. creates one merchant order per group;
15. creates a shipment row per order;
16. applies voucher/reward usage against the first created order;
17. clears the cart;
18. emits `MultiMerchantCheckoutCompletedEvent`;
19. returns order numbers and Stripe contexts.

Each merchant order receives metadata including checkout ID, merchant ID and Stripe payment intent context.

## 22. Multi-merchant shipments

For every merchant order, the checkout creates a shipment row with:

- order ID;
- checkout ID;
- merchant ID;
- shipping cost;
- country;
- pending status;
- consolidation metadata;
- site ID.

This gives the fulfilment side a per-merchant unit of shipping work.

## 23. One-time subscription checkout path

One-time subscription checkout goes through:

```text
OneTimeSubscriptionCheckoutService::processCheckout()
```

The customer-facing view sends this route only after the cart has been split to contain the relevant subscription rows. It should not include physical product rows through the normal checkout UI.

The flow is split into three phases.

### Phase 1: transactional subscription and order draft

Inside a database transaction, the service:

1. normalises voucher data;
2. loads subscription cart items;
3. requires member authentication;
4. locks plans;
5. validates plan availability;
6. validates print next issue availability;
7. reserves issue stock for ready-to-ship print subscriptions;
8. attaches delivery estimates and preorder metadata;
9. applies gift recipient fields where applicable;
10. validates subscription eligibility;
11. builds discount context for the first subscription cycle;
12. resolves discounts;
13. creates pending/trialing subscription rows through `SubscriptionBatchFactory`;
14. creates a pending order through `OrderDraftService`.

### Phase 2: external payment call

If `one_time_subscription` is present in the request, the service creates a Stripe PaymentIntent for the order using `PaymentIntentService`.

This payment call happens outside the phase-one database transaction.

On payment failure:

- the order payment status is marked failed;
- created subscriptions are cancelled;
- reserved issue stock is released.

### Phase 3: subscription activation/scheduling and stock confirmation

After payment handling, the service opens another transaction and:

- preserves `trialing` subscriptions;
- sets non-trial subscriptions to `pending` or `scheduled` based on selected start date;
- confirms reserved issue stock through `FulfilSubscriptionAction`.

Finally, when a PaymentIntent was created, the service attaches the payment intent ID and Stripe customer ID to the order.

## 24. One-time subscription order draft

`OrderDraftService::createPendingOrder()` creates an order from the subscription rows.

It:

- creates one order item per subscription;
- uses `SUB-{subscription_id}` as the product SKU;
- stores subscription ID and delivery type in item metadata;
- distributes tax proportionally across order items;
- records one-time subscription ID on the order;
- stores multiple subscription IDs in order metadata when needed;
- attaches shipping address only when any subscription is print delivery.

Current gotcha: the order currency is hardcoded to `USD` in this service. That may be intentional for a specific site or just a legacy wart; do not assume it is globally correct without checking currency requirements.

## 25. Trial subscriptions

When a plan has a trial, `OneTimeSubscriptionService::createOneTimeSubscription()` creates the subscription with:

```text
status = trialing
trial_ends_at = start_date + plan.trial_days
end_date = trial_ends_at
```

The checkout activation phase deliberately does not move trialing subscriptions to pending or scheduled.

Trial conversion is handled later by `TrialConversionService`, which charges the saved Stripe customer off-session after `trial_ends_at` and then activates the subscription if payment succeeds.

## 26. Recurring subscription orders and payments

Recurring subscription payments do not primarily run through `CheckoutService`.

They are handled by:

```text
SubscriptionPaymentService
StripeSubscriptionOrchestrator
SubscriptionStateManager
OrderStateManager
PaymentRecorder
```

`SubscriptionPaymentService::processStripeSubscriptionPayment()`:

1. delegates Stripe subscription creation to `StripeSubscriptionOrchestrator`;
2. resolves the invoice amount to record locally;
3. records a subscription payment through `PaymentRecorder`;
4. maps Stripe status to local payment status;
5. when Stripe returns active and no further action is required:
   - marks the payment completed;
   - marks the subscription active from Stripe period dates;
   - marks the related order paid when an order ID was supplied.

Stripe subscription creation, customer handling, payment method attachment and gateway dispatch are intentionally outside this class.

## 27. Recurring renewal payments

`SubscriptionPaymentService::createRecurringPayment()` creates a pending payment for a subscription renewal cycle when:

- the subscription is due for renewal;
- there is no existing pending payment for that cycle.

`completeSubscriptionPayment()` completes the payment, updates last payment date, calculates next billing date and marks the subscription active.

`handleFailedSubscriptionPayment()` marks the payment failed, marks the subscription past due and cancels the subscription after three failed payments.

There is also a hard-replace renewal model in `SubscriptionRenewalService`, where a renewal replaces the old subscription with a new active subscription after payment success.

## 28. Payment records

Payment records are used heavily for subscriptions.

Subscription payment records capture:

- subscription ID;
- member ID;
- site ID;
- payment method/provider;
- amount;
- currency;
- status;
- transaction/payment intent IDs;
- Stripe subscription/customer/invoice IDs;
- metadata.

Refunds are also stored as payment rows with a negative amount.

## 29. Refunds

Subscription refunds are handled by `SubscriptionRefundService`.

Refund calculation is strategy-based:

- `FullRefundStrategy`;
- `ProRatedRefundStrategy`;
- `ManualRefundStrategy`;
- any custom `RefundStrategy` implementation.

`SubscriptionRefundService` itself orchestrates provider I/O and persistence.

The refund flow:

1. calculate refund amount and metadata through a strategy;
2. resolve a refundable Stripe transaction ID;
3. if the subscription has a Stripe subscription, call `StripeRefundGatewayInterface`;
4. fail if no refundable Stripe payment intent or charge can be found;
5. create a negative payment row;
6. store audit metadata including reason, original payment ID, provider transaction ID, strategy and refund amount;
7. return refund payment and provider result.

Refundable Stripe transactions must start with `pi_` or `ch_`. If only a Stripe invoice ID is available, the gateway may look up a refundable charge/payment intent from the invoice.

## 30. Cancellation and refunds

`SubscriptionCancellationService::cancelSubscription()` can cancel immediately or at period end.

Supported options include:

- `cancel_at_period_end` default true;
- `create_refund` default false;
- `refund_type` as `full` or `pro_rated`;
- `refund_amount` for a manual override;
- cancellation reason/notes.

Rules:

- Stripe cancellation is called first when the subscription has a Stripe subscription ID;
- paid subscription windows are closed;
- `auto_renew` is disabled;
- cancellation metadata is stored;
- immediate cancellation sets status to cancelled and end date to now;
- refunds only run for immediate cancellation, not period-end cancellation;
- `refund_amount` triggers the manual strategy and wins over refund type;
- immediate cancellation revokes premium access;
- lifecycle events are dispatched outside test environment when the subscription still exists.

## 31. Order confirmation

`CartController::orderConfirmation()` supports two lookup modes:

- `order_id` for a single order number;
- `checkout_id` for multi-merchant checkout groups.

When a checkout ID is provided, all orders for that checkout are loaded and displayed together.

## 32. Events

The shopping flow emits several domain events:

- `OrderCreatedByMember` from the controller after successful member-created checkout;
- `OrderCreatedEvent` from `OrderCreationService`;
- `OrderCompletedEvent` when product payment confirmation completes;
- `MultiMerchantCheckoutCompletedEvent` after multi-merchant order creation;
- subscription cancellation/reactivation events from subscription lifecycle services.

Be careful not to emit success events before the operation they describe has actually committed.

## 33. Transactions and external side effects

The code uses transactions for local database consistency, but Stripe and email/event side effects need careful handling.

Important timing differences:

- product checkout currently creates a PaymentIntent inside the checkout flow before order creation;
- one-time subscription checkout creates subscription/order rows first, then creates the PaymentIntent outside that first transaction;
- subscription refunds call the provider inside refund persistence flow;
- subscription Stripe cancellation is called inside the cancellation service before local cancellation update completes;
- recurring subscription creation is delegated to the Stripe subscription orchestrator before local state transitions.

These are not all perfect outbox-style flows. If a provider call succeeds and a later DB update fails, manual reconciliation may be needed.

## 34. Current sharp edges

The current implementation has a few areas engineers should treat carefully:

1. **Controller branch is not the whole story.** The controller branch looks broad, but the checkout view blocks physical + subscription carts and rebuilds subscription-only carts before hitting subscription routes.
2. **OneTimeSubscriptionCheckoutService naming.** The route/service is used for the one-time subscription payment path and also participates in the subscription checkout split; the name can be misleading when reading from the controller only.
3. **OrderDraftService currency.** One-time subscription order drafts currently hardcode currency to `USD`.
4. **Product PaymentIntent metadata.** Product PaymentIntents are created before the final order exists, so order ID metadata is not present at creation time.
5. **Checkout transaction boundaries.** Several provider calls happen close to or inside local transaction flows. This works, but it is not a robust outbox pattern.
6. **Multi-merchant voucher/reward allocation.** Voucher and reward usage are applied against the first created order.
7. **Older status assumptions.** Some services use strings directly while others use enums.
8. **Digital-vs-shipping logic is exclusion-based.** Anything not recognised as digital requires shipping.
9. **Trialing subscription activation is deferred.** Do not mark trial subscriptions active during checkout.
10. **Preorder stock is not decremented like ready-to-ship stock.** It carries expected ship metadata instead.

## 35. Suggested future cleanup

The main future improvements should be:

- keep the mixed physical/subscription cart block explicit in tests so the controller fallback cannot accidentally process mixed carts;
- split `CartController` into smaller controllers or actions;
- rename or generalise `OneTimeSubscriptionCheckoutService` if it remains part of the broader subscription checkout path;
- move external provider calls to an outbox/job model where possible;
- make order draft currency site/currency-resolver based;
- make product PaymentIntent creation happen after an order draft exists, or update metadata after order creation;
- make multi-merchant voucher/reward allocation explicit per order;
- standardise money handling around integer minor units in all checkout services;
- standardise status values through enums.

## 36. End-to-end examples

### Physical product order

```text
CartController::add
-> CartService::addItem
-> checkout view sees non-subscription cart
-> ApiService::processRegularCheckout
-> CartController::processCheckout
-> CheckoutService::processCheckout
-> validate availability and stock
-> resolve discounts/shipping/tax
-> create Stripe PaymentIntent
-> allocate stock
-> OrderCreationService::create
-> return client_secret and order ID
-> confirm payment
-> mark order completed/paid
```

### Product multi-merchant order

```text
checkout view builds payload with multi_merchant=true
-> CartController::processCheckout
-> CheckoutService::processMultiMerchantCheckout
-> split cart by merchant
-> calculate per-merchant shipping and allocations
-> create PaymentIntent per Stripe-eligible group
-> create one order per merchant
-> create shipment per merchant order
-> emit MultiMerchantCheckoutCompletedEvent
```

### Mixed physical product + subscription cart

```text
checkout view detects isMixedCart
-> warning is shown
-> continue/place-order is disabled
-> advanceToPayment/process refuse to proceed
-> customer must return to cart and checkout separately
```

### Mixed recurring + one-time subscription cart

```text
checkout view detects isMixedSubscriptionCart
-> replace cart with recurring subscription rows
-> run recurring subscription checkout route
-> replace cart with one-time subscription rows
-> run one-time subscription checkout route
-> redirect to subscription confirmation with completed IDs
```

### One-time subscription order

```text
CartController::addSubscription
-> CartService::addSubscriptionToCart
-> checkout view sends one-time subscription subset
-> OneTimeSubscriptionCheckoutService::processCheckout
-> reserve print issue stock where needed
-> create pending/trialing subscriptions
-> OrderDraftService::createPendingOrder
-> create PaymentIntent when requested
-> confirm stock reservations
-> attach payment intent to order
```

### Trial subscription

```text
Checkout creates subscription as trialing
-> checkout does not activate it
-> TrialConversionService later charges off-session
-> on success marks subscription active and updates billing dates
-> on failure expires subscription
```

### Recurring subscription payment

```text
SubscriptionPaymentService::processStripeSubscriptionPayment
-> StripeSubscriptionOrchestrator::create
-> PaymentRecorder::recordSubscriptionStripePayment
-> if active/no action: mark payment completed
-> SubscriptionStateManager::markActiveFromStripe
-> OrderStateManager::markPaid when order_id supplied
```

### Subscription cancellation with refund

```text
SubscriptionCancellationService::cancelSubscription(cancel_at_period_end=false, create_refund=true)
-> cancel Stripe subscription
-> close paid window
-> update local subscription cancellation state
-> resolve refund strategy
-> SubscriptionRefundService::executeWithStrategy
-> StripeRefundGatewayInterface::refund
-> create negative payment row
-> revoke premium access
```

## 37. Key implementation locations

```text
src/Controllers/Shopping/CartController.php
src/views/checkout/index.php
src/Services/Shopping/CartService.php
src/Services/Shopping/CheckoutService.php
src/Services/Shopping/OneTimeSubscriptionCheckoutService.php
src/Services/Shopping/CheckoutEligibilityService.php
src/Services/Shopping/CartMigrationService.php
src/Services/Shopping/CartPersistenceService.php
src/Services/Shopping/Factories/CartItemFactory.php
src/Services/Shopping/Resolvers/CartPriceResolver.php
src/Services/Shopping/Resolvers/CartStockResolver.php
src/Services/Billing/Order/OrderCreationService.php
src/Services/Billing/Order/OrderDraftService.php
src/Services/Billing/Payments/PaymentIntentService.php
src/Services/Billing/Payments/PaymentRecorder.php
src/Services/Billing/Stripe/
src/Services/Subscriptions/SubscriptionPaymentService.php
src/Services/Subscriptions/SubscriptionCancellationService.php
src/Services/Subscriptions/SubscriptionRefundService.php
src/Services/Subscriptions/Refunds/
src/Actions/Stock/PurchaseProductAction.php
src/Actions/Stock/FulfilSubscriptionAction.php
src/Repositories/Shopping/CartRepository.php
src/Repositories/Billing/OrderRepository.php
src/Repositories/Billing/OrderItemRepository.php
src/Repositories/Billing/PaymentRepository.php
src/Models/Order.php
src/Models/OrderItem.php
src/Models/Payment.php
src/Models/Subscription.php
src/Models/SubscriptionPlan.php
```

When changing this flow, update the relevant controller/view/service tests and this documentation at the same time. Small checkout changes tend to have large blast radius: cart state, stock, payment, order status, merchant credit and subscription access can all be affected.
