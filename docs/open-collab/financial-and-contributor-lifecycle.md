# Open Collab: Financial and Contributor Lifecycle

This document describes how the Open Collab subsystem currently handles contributor access, onboarding, earnings, balances, adjustments, payouts, content risk, moderation queues and contributor violations.

It is intended as an implementation guide for engineers, reviewers and operational users. It documents the current code paths and business rules rather than an idealised future design.

## 1. Core principles

Open Collab is site-scoped. A user account may exist globally, but contributor access, onboarding state, earnings, moderation and enforcement are evaluated in the context of a site.

The main architectural rules are:

- financial amounts are stored and calculated as integer minor units, currently pence for GBP;
- earnings are represented by immutable-style ledger records whose lifecycle is changed through explicit status transitions;
- a displayed balance is a derived view of ledger entries, liabilities and payouts, not a separately editable cash total;
- payouts attach to specific settled ledger entries so the system can prove what was paid;
- withdrawn earnings are not silently edited when corrected later: they create liabilities which can be recovered from future payouts;
- invitations grant site access and start onboarding, but do not by themselves make a contributor fully operational;
- onboarding completion is revalidated against live domain state, so a previously completed step can become stale;
- risks describe concerns attached to content or images, while violations describe enforcement against a contributor;
- moderation queues coordinate review work and use risk information to influence priority;
- service-layer operations perform business validation and transactions, repositories perform persistence, and events/notifications communicate completed outcomes.

## 2. Contributor invitations

### 2.1 Invitation creation

`InvitationService::create()` is the main entry point for an administrator inviting a contributor.

Before creating an invitation, the service:

1. trims and lowercases the email address;
2. checks whether the email belongs to an existing user who already has contributor access to the target site;
3. checks whether a pending invitation already exists for the same email and site;
4. creates a cryptographically random token;
5. stores the invitation as `pending` with an expiry time, defaulting to 72 hours;
6. dispatches the invitation notification after the transaction commits where after-commit support is available.

The access check is site-specific. An existing account may therefore be invited to another site without creating a second user account.

### 2.2 Invitation states

An invitation resolves to one of the lifecycle states represented by `InvitationStatus`, including:

- `pending`: valid and available for acceptance;
- `used`: already accepted;
- `expired`: no longer valid because its expiry time has passed;
- `revoked`: explicitly withdrawn by an administrator.

Only a pending invitation can be accepted or directly resent by `InvitationService::send()`.

A revoked invitation is intentionally permanent. The resend flow must not silently regenerate it. An administrator must make a deliberate decision to create a new invitation.

### 2.3 Accepting an invitation

There are two acceptance paths:

- `accept()` for the invitee;
- `acceptOnBehalf()` for an administrator.

Both paths run transactionally and perform the same core work:

1. resolve the token and require a pending invitation;
2. ensure a contributor account exists for the invited email;
3. grant contributor access to the invitation's site;
4. mark the invitation as used;
5. ensure a global contributor profile exists;
6. start site-specific onboarding if it has not already started;
7. dispatch acceptance events and notifications after commit where supported.

For an existing user, acceptance must not overwrite the user's current name or password. The user keeps their existing account credentials and receives only the missing site access.

The authorisation layer is responsible for granting access. This keeps invitation handling separate from the persistence details of user/site membership.

### 2.4 Resending and revocation

Pending invitations may be resent. Expired, used and revoked invitations are not resent by the basic send method. The dedicated resend service is responsible for lifecycle-aware handling and throttling.

An invitation which has already been used cannot be revoked. Revocation is for outstanding invitations only.

## 3. Contributor onboarding

`ContributorOnboardingService` is the single authority for onboarding state.

### 3.1 Site-specific onboarding

Onboarding belongs to a contributor and a site. Completing onboarding for one site does not automatically complete it for another, because each site may have different contracts, guidelines, required fields and compliance settings.

Invitation acceptance starts onboarding, but onboarding can also be started independently where another access path grants contributor access.

### 3.2 Applicable steps

The service builds the required step list from the site's configuration and current domain requirements. Possible steps are:

- `terms`, when a current required terms version exists;
- `profile`, always applicable;
- `payment_setup`, when the site requires payment details;
- `kyc_verification`, when the site requires KYC;
- `contract`, when contracts are required;
- `guidelines`, when guidelines acknowledgement is required;
- `age_verification`, when age verification is required.

Typical defaults are payment setup, contracts, guidelines and age verification enabled, minimum age 18, and KYC disabled unless explicitly required.

### 3.3 The three-part completion rule

A step is considered complete only when all three conditions hold:

1. the step is applicable to the site;
2. the contributor onboarding step row is marked `completed`;
3. the underlying domain validation still passes at runtime.

This is deliberately stronger than trusting a stored completion flag. Examples:

- the profile step becomes incomplete when newly required profile fields are introduced or required data is removed;
- terms, contracts or guidelines can become stale when a newer required version is published;
- payment setup becomes incomplete if valid payment details are no longer present;
- KYC requires the Stripe Connect account to remain enabled;
- age verification remains subject to the configured minimum age and current evidence.

When a previously completed step fails current validation, it is treated as pending and may be marked invalidated.

### 3.4 Completing a step

`completeStep()`:

1. normalises and validates the step key;
2. verifies that the step applies to the site;
3. runs step-specific domain validation;
4. marks the step completed, optionally storing metadata;
5. recalculates and synchronises overall onboarding status.

A step can also be marked in progress or invalidated. Site-wide invalidation is supported for completed steps when a policy change affects every contributor, such as a new guidelines version.

### 3.5 Profile, payment and KYC helpers

The profile step delegates completeness to `ContributorProfileCompletionService`, which evaluates active required custom fields for the site.

The payment helper requires the contributor profile repository to report payment setup as complete before the onboarding step can be completed.

The KYC helper uses the generic completion flow but domain validation also requires the Stripe Connect account status to be enabled. Webhook handling may invalidate the step later if Stripe restricts the account.

### 3.6 Operational gates

Other services should not duplicate onboarding logic. They should call either:

- `isComplete()` when a boolean is sufficient;
- `pendingSteps()` when the UI or API needs reasons;
- `requireComplete()` when an incomplete contributor must be rejected;
- the contributor policy, where a higher-level capability such as withdrawal is being checked.

Payout withdrawal is one of the operations gated by onboarding completion, including valid payment details.

## 4. Earnings ledger

The earnings ledger is the financial source of truth for contributor earnings.

A ledger entry has an accrual status represented by `AccrualStatus`:

- `estimated`: an expected earning which is not final;
- `confirmed`: the earning has been confirmed but is not yet withdrawable;
- `settled`: the earning is available for payout, subject to liabilities and in-flight payouts;
- `withdrawn`: the earning has been included in a completed payout;
- `reversed`: the earning has been cancelled or offset by a correction.

The exact timing of transitions depends on the earning source and payment terms. Code which changes accrual state should use `AccrualTransitionService`, rather than updating `accrual_status` directly.

### 4.1 Why statuses matter

The status model separates four questions which are often incorrectly collapsed into one balance:

- what might the contributor earn?
- what has been confirmed?
- what is legally/operationally settled and available?
- what has already been paid?

Only settled entries are eligible for attachment to a payout.

### 4.2 Ledger integrity

Financial corrections should preserve history. Existing entries are transitioned or linked to reversal records rather than being deleted. Payouts link to specific earnings ledger rows through payout ledger entries.

This provides traceability from:

`earning source -> earnings ledger entry -> payout ledger attachment -> payout -> provider/admin completion`

## 5. Earnings and balances

`CreatorBalanceService` calculates contributor balances from repositories. It does not maintain a mutable balance column.

For a contributor and site, it returns:

- `estimated_balance`: sum of estimated ledger entries;
- `confirmed_balance`: sum of confirmed ledger entries;
- `settled_balance`: sum of settled ledger entries;
- `withdrawn_balance`: sum of withdrawn ledger entries;
- `reversed_balance`: sum of reversed ledger entries;
- `open_liabilities`: unresolved amounts owed by the contributor;
- `in_flight_payouts`: payouts which reserve money but are not complete;
- `available_to_withdraw`: the final amount currently available.

The current formula is:

```text
available_to_withdraw = max(0, settled_balance - open_liabilities - in_flight_payouts)
```

All values are integer minor units. For GBP, `5000` means £50.00.

### 5.1 Settled is not the same as available

A contributor can have a positive settled balance but no withdrawable balance when:

- an approved or pending payout already reserves the funds;
- an open liability must be recovered;
- both conditions apply.

Interfaces should therefore label each figure accurately. Do not present settled balance as cash available for immediate withdrawal.

## 6. Earnings adjustments

`EarningsAdjustmentService::reverse()` handles corrections according to the current state of the original ledger entry.

Every adjustment requires both a non-empty source and a non-empty reason.

### 6.1 Estimated or confirmed earnings

For an `estimated` or `confirmed` entry, the original entry is transitioned to `reversed` through `AccrualTransitionService`.

No contributor liability is needed because the money was not yet settled or withdrawn.

### 6.2 Settled earnings

For a `settled` entry:

1. a reversal ledger record is created, linked to the source ledger entry;
2. the original entry is transitioned to `reversed`;
3. the reversal carries a reference, reason and source information.

This preserves a visible accounting history instead of mutating the settled amount out of existence.

### 6.3 Withdrawn earnings

Once an entry is `withdrawn`, the system cannot simply reverse money which has already left the platform.

Instead, it creates a creator liability for the absolute amount. That liability is recovered from future payouts through set-off.

This is a key distinction:

- before withdrawal, correct the ledger;
- after withdrawal, record money owed and recover it later.

### 6.4 Current site-resolution limitation

The adjustment service currently uses the ledger entry's `site_id` when available. If it is absent, the implementation falls back to site ID `1` with a code comment stating that this should be replaced by a page/article-to-site lookup.

This fallback is a known implementation limitation and should not be treated as a general multi-site rule.

## 7. Liabilities and set-off

A creator liability represents money the contributor owes back to the platform, commonly because an already-withdrawn earning was later reversed.

At payout request time, `SetOffService` applies open liabilities against the contributor's gross available settled balance.

The service returns:

- the net amount which may be paid;
- the deductions applied;
- source and reason data for those deductions where available.

Each applied deduction is recorded as a payout liability recovery linked to both the payout and the creator liability. This produces an audit trail showing exactly how the payout's gross settled amount became its net paid amount.

## 8. Payouts

`PayoutService` manages contributor payout requests and administrative/provider processing.

### 8.1 Supported methods

The current accepted methods are:

- `bank_transfer`;
- `paypal`;
- `other`;
- `stripe`.

`stripe` and `bank_transfer` are currently treated as Stripe-backed processing paths after approval.

### 8.2 Requesting a payout

A contributor requests a payout for their full available balance. Arbitrary partial payout requests are not currently supported by the service.

The request flow:

1. validates the payout method;
2. verifies that the site exists;
3. checks the contributor policy and onboarding withdrawal eligibility;
4. starts a database transaction;
5. reads the settled balance and existing in-flight payouts;
6. refuses the request when another payout is already in progress;
7. calculates gross available settled funds;
8. enforces the minimum payout of 5000 pence (£50.00);
9. builds an idempotency key from the contributor, site and available settled ledger entry IDs;
10. returns an existing non-rejected payout when the same state has already produced one;
11. applies liability set-off;
12. enforces the £50 minimum again against the net amount;
13. creates the pending payout;
14. records liability recoveries;
15. attaches the gross settled ledger entries to the payout;
16. commits, then dispatches events and notifications.

The payout amount is the net amount after set-off. The attached ledger total is the gross settled amount being consumed. The difference is explained by recorded liability recoveries.

### 8.3 Idempotency

The manual payout idempotency key contains a hash of the currently available settled ledger entry IDs.

This means repeated requests against the same financial state return the existing payout instead of creating duplicates. Once the available ledger state changes, a different key is produced.

### 8.4 Payout ledger attachment

`PayoutLedgerService::attachSettledEntriesToPayout()` loads settled entries which are available for payout and attaches whole entries until the requested gross amount is covered.

Current constraints:

- non-positive entries are skipped;
- partial attachment of one earnings entry is not supported;
- the operation fails if whole eligible entries cannot exactly cover the requested attachment amount.

This is important for future work on partial payouts: supporting a user-entered payout amount would require either ledger splitting or partial attachment semantics.

### 8.5 Approval

An administrator may approve only a pending payout.

Approval stores the approving administrator and timestamp, records a payout audit action, notifies the contributor and dispatches the payout processed event.

For Stripe-backed methods, approval dispatches `ProcessStripePayoutJob` on the `payouts` queue.

Approval does not itself mark the associated earnings as withdrawn.

### 8.6 Completing non-Stripe payouts

For methods not backed by Stripe, an administrator may mark an approved payout as paid and optionally store a reference and notes.

The operation:

1. changes the payout to `paid`;
2. records who completed it and when;
3. writes a payout audit entry;
4. transitions all attached earnings ledger entries to `withdrawn`;
5. dispatches events and the paid notification.

Stripe-backed payouts cannot be manually finalised through this method. Their final state must come from provider webhook handling.

### 8.7 Stripe-backed processing and retries

After approval, Stripe-backed payouts are processed asynchronously. Provider identifiers, provider status and processing attempts are stored on the payout.

Webhook handling is responsible for the authoritative provider outcome. Successful completion marks attached ledger entries withdrawn. Failure leaves the payout recoverable through the retry flow.

Only failed Stripe-backed payouts may be retried. A paid payout cannot be retried, and a non-Stripe payout cannot use the Stripe retry path.

### 8.8 Payout state and balance reservation

Pending and approved/in-processing payouts count as in-flight according to repository rules. They reduce `available_to_withdraw` so the same settled earnings cannot fund a second payout.

The ledger entries are attached when the payout is created, but they are transitioned to `withdrawn` only when payment completion is confirmed.

## 9. Risks

A risk marker describes a concern attached to content. It is not itself a contributor disciplinary action.

`RiskMarkerService` can associate a marker with:

- a site;
- a page;
- a page version;
- an image;
- a risk type;
- a source;
- a severity;
- structured details;
- the user who created it.

New markers begin with status `open`.

### 9.1 Risk sources and types

Enums define the supported risk types, sources, severities and statuses. Services such as image metadata and creator declaration analysis create markers from different evidence sources while sharing the same marker lifecycle.

Code consuming risks should use the enums rather than comparing arbitrary strings.

### 9.2 Creating a marker

Creation is transactional. When an actor is known, the service records a moderation audit action with marker metadata.

When the marker is associated with a moderation queue entry, queue priority is recalculated after creation.

A risk status change event is dispatched after the marker has been created.

### 9.3 Clearing and dismissing

A reviewer may:

- resolve a marker, transitioning it to `cleared`;
- dismiss a marker, transitioning it to `dismissed`.

The service verifies that the marker belongs to the current site.

High and critical risks require resolution notes when being cleared. The transition stores reviewer, review time, resolver, resolution time and notes, records a moderation audit action, and dispatches a status-changed event.

Outstanding/open markers continue to influence the queue's risk score. Closed markers should no longer be counted by the repository's outstanding query.

## 10. Moderation queues

`ModerationQueueService` coordinates the review lifecycle for submitted content.

### 10.1 Submission and resubmission

When an article is submitted or resubmitted:

- an existing open queue entry for the site/page is refreshed, or a new entry is created;
- status becomes `queued`;
- submission time is updated;
- previous assignment and claim time are cleared;
- initial risk and priority scores are set when creating a new entry;
- risk and priority are recalculated;
- a submitted or resubmitted moderation audit action is written.

The article approval service is expected to call this while updating page status, normally within the same broader transaction.

### 10.2 Queue statuses

The queue uses `ModerationQueueStatus`. Important lifecycle states include:

- `queued`;
- a claimed/in-review state represented through assignment and repository behaviour;
- `changes_requested`;
- `approved`;
- `rejected`.

Approved and rejected entries are closed. Priority recalculation returns the existing entry unchanged for a closed status.

### 10.3 Claiming work

Claims are atomic through `claimIfUnassigned()`.

When two reviewers attempt to claim the same entry, only one succeeds. The other receives `ModerationQueueClaimConflictException`, allowing the controller to return HTTP 409 rather than silently overwriting the assignment.

A successful claim is audited.

### 10.4 Releasing work

Only the currently assigned reviewer may release a queue entry.

Release clears the assigned user and claim time, returns the entry to `queued`, and records a release audit action.

### 10.5 Risk and priority scoring

Priority recalculation:

1. loads all outstanding risk markers for the page;
2. computes a risk score through `RiskScoreCalculator`;
3. computes priority through `ModerationPriorityCalculator`, using the entry, risk score and any manual boost;
4. persists both scores.

Recalculation occurs after submission and when risk marker changes are connected to a queue entry. A listener also supports recalculation from risk status change events.

Administrators can apply a manual priority boost. The resulting recalculation and boost value are written to the moderation audit trail.

### 10.6 Outcome handling

Approval and rejection close the current open queue entry with the corresponding status.

Requesting changes leaves the review cycle open but marks it `changes_requested`. A later resubmission refreshes the entry and returns it to `queued` with assignment cleared.

## 11. Violations

A contributor violation is an enforcement record against a contributor. It is distinct from a content risk marker:

- a risk marker says content may require review;
- a violation says a contributor breached a rule and records the resulting action.

`ViolationService` stores the type, severity, reason, action taken, administrator, optional page and site.

### 11.1 Automatic thresholds

When an administrator does not supply an action override, the service calculates the action from unresolved violations of the same severity for the contributor and site.

Current thresholds, including the newly recorded violation, are:

- one high-severity violation: ban;
- three medium-severity violations: suspension;
- five low-severity violations: suspension;
- below the threshold: warning.

An administrator-supplied action overrides the calculated action.

### 11.2 Enforcement

Warnings create the violation record without deactivating the account.

Suspensions and bans immediately deactivate the contributor through `UserLifecycleServiceInterface`. The operation is transactional with the violation record and is logged.

The service then dispatches the contributor notification and a `ViolationRecordedEvent`.

### 11.3 Resolving a violation

Resolution records:

- resolution time;
- resolving administrator;
- optional resolution notes.

After resolution, the service checks whether the contributor still has any active ban or suspension for the site. The account is reactivated only when no blocking enforcement remains.

`isBlocked()` reports whether an active ban or suspension remains.

### 11.4 Scope warning

The violation repository checks are site-scoped, but deactivation is performed through the user lifecycle service and may affect the global user account depending on that implementation. Engineers changing multi-site enforcement should verify whether suspension is intended to block one site or every site before altering this flow.

## 12. Risks versus violations

These concepts must not be merged:

| Concern | Risk marker | Contributor violation |
|---|---|---|
| Primary subject | Page, version or image | Contributor |
| Purpose | Review and prioritisation | Enforcement and disciplinary history |
| Typical origin | Automated analysis, metadata, declaration or reviewer | Administrator/moderation decision |
| Main statuses | Open, cleared, dismissed | Unresolved or resolved, with action taken |
| Direct account effect | None | Warning, suspension or ban |
| Queue effect | Changes risk and priority score | Not directly part of queue scoring |

A high-risk article does not automatically mean the contributor should be banned. A reviewer must determine whether the issue is an actual violation and record one explicitly when appropriate.

## 13. Audit, events and notifications

The subsystem uses several related but different mechanisms:

- domain records provide the durable business state;
- payout audit records explain administrative payout actions;
- moderation audit records explain queue and risk actions;
- events allow other parts of the application to react without embedding those concerns in the core service;
- notifications inform contributors and administrators;
- logs provide operational diagnostics but are not a substitute for business audit records.

Where supported, notifications and events which represent successful completion should be scheduled after the surrounding transaction commits. This prevents emails or event consumers observing a result which was later rolled back.

## 14. Transaction boundaries and concurrency

Transactions protect multi-record operations such as:

- accepting an invitation, granting access and starting onboarding;
- creating a payout, applying set-off and attaching ledger entries;
- recording a violation and deactivating the contributor;
- changing risk status and writing moderation audit records;
- completing a payout and withdrawing attached earnings.

Concurrency is addressed through several mechanisms:

- invitation duplicate and access checks are repeated inside transactions;
- payout idempotency keys prevent duplicate requests against the same ledger state;
- in-flight totals prevent the same balance funding concurrent payouts;
- queue claims are atomic and conflict explicitly;
- repository constraints should back service checks wherever duplicate records would be financially or operationally dangerous.

## 15. Common implementation mistakes

Avoid the following:

1. **Updating a balance column directly.** Balance is derived from ledger statuses, liabilities and payouts.
2. **Marking earnings withdrawn on payout approval.** Withdrawal occurs only after confirmed payment.
3. **Deleting or editing old earnings to correct them.** Use reversal transitions, reversal entries or liabilities based on status.
4. **Ignoring site scope.** Access, onboarding, balances, queues, risks and violations all require the correct site.
5. **Treating invitation acceptance as onboarding completion.** Acceptance only grants access and starts onboarding.
6. **Trusting a completed onboarding row forever.** Runtime domain validation can make the step stale.
7. **Using risk markers as disciplinary records.** Create a violation explicitly when enforcement is justified.
8. **Allowing two reviewers to overwrite a claim.** Use the atomic queue claim path and handle conflicts.
9. **Manually marking Stripe payouts paid.** Stripe-backed payouts must be finalised through provider webhook handling.
10. **Assuming partial payouts work.** Current ledger attachment requires whole entries and exact coverage.
11. **Sending success notifications before commit.** Prefer after-commit dispatch for transaction-backed outcomes.
12. **Using floating-point currency.** Keep all calculations in integer minor units.

## 16. End-to-end lifecycle example

A typical contributor lifecycle is:

1. An administrator creates an invitation for a site.
2. The invitee accepts; an account is created or reused.
3. Contributor access is granted to the site.
4. The invitation becomes used and onboarding starts.
5. The contributor completes terms, profile, payment, optional KYC, contract, guidelines and age steps as required.
6. The contributor submits content.
7. A moderation queue entry is created or refreshed.
8. Automated or manual checks create content risk markers.
9. Risk score and queue priority are recalculated.
10. A reviewer atomically claims the entry.
11. The reviewer approves, rejects or requests changes.
12. Approved payable work creates earnings ledger entries.
13. Earnings progress through estimated, confirmed and settled states according to payment terms.
14. The contributor's available balance is settled earnings minus liabilities and in-flight payouts.
15. The contributor requests a payout once onboarding permits withdrawal and the net amount is at least £50.
16. The payout attaches specific settled ledger entries and applies liability set-off.
17. An administrator approves the payout.
18. Stripe processing or a manual non-Stripe payment completes it.
19. Attached earnings transition to withdrawn only after payment is confirmed.
20. A later correction to withdrawn earnings creates a liability recovered from a future payout.
21. A confirmed contributor breach may be recorded as a violation, potentially causing suspension or a ban independently of the content risk marker lifecycle.

## 17. Key implementation locations

The main code is located under:

```text
src/Services/OpenCollab/
src/Services/OpenCollab/Moderation/
src/Services/OpenCollab/Risk/
src/Repositories/OpenCollab/
src/Controllers/OpenCollab/
src/Controllers/OpenCollab/Admin/
src/Enums/OpenCollab/
src/Models/
src/Jobs/OpenCollab/
src/Events/OpenCollab/
src/Listeners/OpenCollab/
src/Tests/Unit/Services/OpenCollab/
src/Tests/Functional/Controllers/OpenCollab/
```

Particularly important services are:

```text
InvitationService
ContributorOnboardingService
CreatorBalanceService
AccrualTransitionService
EarningsAdjustmentService
CreatorLiabilityService
SetOffService
PayoutLedgerService
PayoutService
StripeConnectWebhookHandler
ModerationQueueService
ModerationAuditService
RiskMarkerService
ViolationService
```

When changing a business rule, update the service, repository constraints where applicable, unit tests, functional controller tests and this documentation together.
