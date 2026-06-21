# Newsletters: Creation, Content, Recipients and Send Lifecycle

This document describes how the newsletter subsystem works in the CMS API.

It is deliberately about **newsletters**, not the subscription product lifecycle. Subscriptions only appear here where they are used to decide whether a member is allowed to receive or access a premium newsletter.

## 1. What a newsletter is

A newsletter is represented by `App\Models\Newsletter` and belongs to a site.

Important newsletter fields include:

- `site_id`: the owning site;
- `title`: used as the email subject for real sends;
- `slug`: used by premium access checks;
- `interval`: `daily`, `weekly` or `monthly`;
- `active`: whether the newsletter is eligible for interval-based sending;
- `paused`: whether scheduled sends should skip it;
- `last_sent`: used for interval and duplicate-send protection;
- `content_type`: `manual`, `auto_pages` or `custom_blocks`;
- `content` / `legacy_content`: legacy manual text content;
- `content_blocks`: block-based newsletter content;
- `layout_id`: optional newsletter layout;
- `design_config`: optional design data;
- `is_default`: default newsletter marker for the site;
- `is_premium`: whether recipient and archive access require paid entitlement;
- `allowed_regions`, `blocked_regions`, `has_geographic_restrictions`: region gating;
- `access_window_start`, `access_window_end`, `has_time_window`: time-window gating;
- `bundle_id`, `requires_bundle`: bundle-gated newsletter access.

The model also exposes interval constants and content-type constants. Code should prefer those constants/enums over arbitrary strings where possible.

## 2. Site scope

Newsletters are site-scoped. Recipient resolution, schedules, access checks, default newsletter selection, branding and layouts must all use the current site.

Common rule: never send or resolve a newsletter without a reliable `site_id`. If the caller omits one, services generally fall back to `SiteContext::getId()`, but explicit site IDs are safer in jobs and admin tooling.

## 3. Newsletter content types

The content system supports three source types.

### 3.1 Manual / legacy content

`manual` content is resolved from `legacy_content` first, falling back to `content`.

The resolver converts the text into a simple text block and routes it through the normal block rendering path. Empty manual content resolves to empty HTML rather than trying to fetch pages.

### 3.2 Auto-pages content

`auto_pages` newsletters pull pages dynamically using newsletter filters. The page builder returns a page collection, which is mapped into the send response and stored as the send's content snapshot.

For v2 layouts, the pages are converted into blocks and rendered through the region layout pipeline. For v1/no-layout newsletters, the older page template path is used.

If no matching pages exist, the builder preserves the existing error contract and returns a failed result such as `No pages match newsletter criteria`.

### 3.3 Custom blocks content

`custom_blocks` newsletters render `content_blocks` directly.

If there are no blocks, the resolver logs a warning and returns empty HTML. This is intentional: a custom-block newsletter can exist before editorial content is complete.

## 4. Rendering pipeline

Rendering is split into a few clear services:

- `NewsletterContentBuilder` is the high-level builder used by send and preview flows;
- `NewsletterContentResolver` decides which content source path to use;
- `NewsletterPageBuilderService` owns existing page/template rendering paths;
- `LayoutRenderPipeline` renders v2 region/slot/block layouts;
- `NewsletterBrandingRepository` provides newsletter-specific branding;
- `NewsletterLayoutRepository` provides the active/latest layout version.

`NewsletterContentResolver` does not assemble HTML directly. It routes the work and returns a `NewsletterResolveResult` containing rendered HTML and, where relevant, the pages that were fetched internally.

This matters because callers should not fetch the same pages again just to build API responses or snapshots.

## 5. Layout versions

A newsletter may have a `layout_id`.

The content builder asks the layout repository for the layout version history and currently uses the last version.

Rendering has two modes:

- v2 layout: schema version >= 2, rendered by the region layout pipeline;
- v1/no layout: implicit single `content` slot rendered by the page builder.

For v2 layouts, newsletter content is injected into the center region as an implicit slot named `center_content`. Top and bottom regions are left as configured by the layout designer.

The page builder remains responsible for the outer email chrome: doctype, header, footer, branding wrapper and unsubscribe placeholder.

## 6. Branding

Branding is loaded per newsletter through `NewsletterBrandingRepository`.

The rendered output should use the newsletter's branding at the time of rendering. Snapshots then freeze the resulting HTML so later branding changes do not rewrite history.

The snapshot service can also store branding snapshots and layout version references for audit and view-in-browser rendering.

## 7. Unsubscribe and view-in-browser placeholders

The content builder ensures the rendered HTML contains the unsubscribe placeholder:

```text
{{UNSUBSCRIBE_LINK}}
```

If the renderer did not include it, the builder appends it.

For real sends, the send service creates a newsletter snapshot and generates a snapshot view token. The generic view placeholder is replaced with a tokenised placeholder:

```text
{{VIEW_IN_BROWSER_URL:{snapshotToken}}}
```

The dispatcher later expands this per recipient as:

```text
/newsletter/view/{snapshotToken}?r={recipientToken}
```

The snapshot token identifies the frozen send snapshot. The recipient token provides per-recipient attribution.

Preview sends do not need view-in-browser links or unsubscribe links, so preview HTML strips those placeholders before dispatch.

## 8. Recipient resolution

`NewsletterRecipientResolver` is the authority for deciding who should receive a newsletter.

Resolution happens in two phases:

1. **Eligibility** — who is allowed to receive this newsletter?
2. **Preferences** — who has opted in and not opted out?

Preferences can only remove recipients. They must not add someone who failed entitlement or eligibility checks.

## 9. Free newsletter recipients

For non-premium newsletters, the eligible recipient set is built from:

- confirmed legacy subscribers from `SubscriberRepository`;
- member subscription preferences from `MemberSubscriptionPreferenceRepository`.

Member preferences are filtered by:

- matching `newsletter_frequency` against the newsletter interval;
- `newsletter_opt_out` not being set.

Legacy subscriber emails and member emails are merged and deduplicated.

## 10. Premium newsletter recipients

For premium newsletters, legacy subscribers are not enough. The resolver uses member preferences and then verifies paid access.

The premium flow:

1. load active member newsletter preferences for the site;
2. keep only preferences matching the newsletter interval and not newsletter-opted-out;
3. for each member, load their active subscription for the site;
4. call `Subscription::canAccessNewsletter($newsletter, $member)`;
5. include the email only when the access result is allowed;
6. log exclusions with the concrete denial reason.

A paid newsletter with zero eligible recipients logs an error. This is useful operationally because it may mean access rules, slugs, bundles or subscription grants are misconfigured.

## 11. Preference filtering

After eligibility is resolved, `applyPreferences()` performs final preference filtering.

For member emails, it checks:

- global newsletter communication preference;
- global marketing communication preference;
- newsletter-specific opt-out.

For legacy subscribers which are not matched to a member account, the email is allowed through after eligibility. That only applies to free newsletters because premium resolution starts from member preferences and subscriptions.

The return shape is:

```php
[
    'valid' => [...],
    'skipped' => [
        'person@example.com' => 'reason',
    ],
]
```

`NewsletterSendService` formats skipped recipients as arrays of `email` and `reason` for responses.

## 12. Premium access rules

The newsletter subsystem delegates paid entitlement checks to the subscription model, but the business concept remains newsletter-specific.

`Subscription::canAccessNewsletter()` denies access when:

- the subscription is not eligible for paid newsletter access;
- the newsletter slug is not granted directly, through the plan, or through a bundle;
- the newsletter requires a bundle that the subscription does not have;
- the newsletter has geographic restrictions and the member's region is not allowed;
- the newsletter has a time window and the current time is outside that window.

A newsletter slug is important because premium grants are usually checked as:

```text
premium_type = newsletter
premium_identifier = {newsletter.slug}
```

Do not rename premium newsletter slugs casually. It can break entitlement checks.

## 13. Subscription involvement is access-only

Subscriptions are not the newsletter lifecycle.

Subscriptions are consulted only to answer questions like:

- does this member have access to this premium newsletter?
- does this member have the required bundle?
- is this member currently entitled according to subscription status?

The actual newsletter lifecycle remains:

```text
newsletter definition -> content render -> recipient resolution -> send record -> recipient rows -> email dispatch -> snapshot/archive/tracking
```

## 14. Sending due newsletters

`NewsletterSendService::sendDueNewsletters()` loads due newsletters for the site from the newsletter repository and calls `sendNewsletter()` for each one.

The model-level due logic is based on:

- `active = true`;
- matching site;
- `last_sent` being empty, or old enough for the configured interval:
  - daily: at least 1 day;
  - weekly: at least 7 days;
  - monthly: at least 30 days.

This due-newsletter path is separate from explicit send schedules, which are managed by `NewsletterScheduleService` and run by `NewsletterSendScheduleRunner`.

## 15. Duplicate-send protection

`NewsletterSendService::sendNewsletter()` prevents a newsletter being sent again within one hour of `last_sent`.

This is a simple safety net. It protects against accidental repeated sends, but it is not a full distributed lock. If multiple workers can process the same newsletter concurrently, repository-level locking or a send-state guard should be added.

## 16. Real send flow

The real send flow is:

1. resolve site ID;
2. reject duplicate sends within the one-hour guard window;
3. build rendered HTML and resolved pages through `NewsletterContentBuilder`;
4. resolve valid recipients and skipped recipients;
5. reject when there are no valid recipients;
6. create a frozen newsletter snapshot;
7. generate a snapshot view token;
8. inject the snapshot token into the HTML;
9. create a `NewsletterSend` record;
10. create per-recipient `NewsletterSendRecipient` rows;
11. dispatch emails through `NewsletterDispatcher`;
12. update `last_sent` on the newsletter;
13. calculate recipient statistics;
14. return success, failure, skipped, pending, send ID and snapshot ID.

The send operation is wrapped in a database transaction, including snapshot creation, send record creation, recipient creation and dispatch invocation.

Because email delivery is an external side effect, this means an exception after some email sends can still leave the classic problem of external mail already sent while the DB transaction may roll back. That is worth remembering if this is later moved to a queue/outbox model.

## 17. Preview sends

Preview sends are intentionally constrained:

- at least one preview email is required;
- every preview email must be valid;
- maximum recipients: 10;
- the subject is prefixed with `[PREVIEW]`;
- deterministic preview unsubscribe tokens are generated;
- view-in-browser and unsubscribe placeholders are stripped;
- preview sends cannot be retried.

Preview sends create a send record marked `is_preview = true` and create normal recipient rows, but they are operationally distinct from real sends.

## 18. Custom email sends

`sendToCustomEmails()` allows sending a newsletter to an explicit list of email addresses.

It validates the email list, builds content, creates a snapshot, creates a send record, creates recipient rows and dispatches the same way as normal sends.

This path bypasses normal recipient resolution, so it should be treated as an admin/tooling operation. It is useful for targeted sends but can also bypass opt-in logic if used carelessly.

## 19. Dispatching emails

`NewsletterDispatcher` performs per-recipient delivery.

The dispatcher:

- exits successfully when recipient list is empty;
- processes recipients in batches of 100;
- resolves unsubscribe tokens in bulk;
- stores unsubscribe tokens on recipient rows for real sends when missing;
- replaces view-in-browser placeholders with per-recipient URLs;
- replaces tracking placeholders for automated newsletters;
- injects unsubscribe footer HTML;
- sends through `EmailService`;
- marks each recipient as sent or failed;
- updates send counts at the end.

Recipient failures do not stop the whole send. They are recorded per recipient.

## 20. Recipient rows and retry

Each recipient is represented by `NewsletterSendRecipient`.

Statuses are:

- `pending`;
- `sent`;
- `failed`;
- `bounced`.

`markAsSent()` sets status to `sent`, records `sent_at` and `last_attempt_at`, and increments attempts.

`markAsFailed()` sets status to `failed`, stores the error message, records `last_attempt_at`, and increments attempts.

A failed recipient can be retried while:

```text
status = failed AND attempts < maxAttempts
```

`NewsletterSendService::retrySend()` refuses preview sends, loads retryable recipients, redispatches using the original send HTML snapshot, and returns updated statistics.

## 21. Snapshots and view-in-browser

Newsletter snapshots freeze what was sent.

A snapshot may include:

- rendered HTML;
- branding snapshot;
- layout version ID;
- branding version ID.

The snapshot service is explicit that snapshots are the source of truth for `what was sent`. View-in-browser should render from the snapshot, not from the live newsletter layout or live branding.

This prevents old newsletter editions changing when an editor later changes branding, layout or content.

## 22. Archives

`NewsletterArchiveService` reads sent editions from `NewsletterSend` rows.

Archive behaviour:

- non-premium newsletter archives are public;
- premium archives require a logged-in member with active access;
- anonymous users get an auth-required response;
- members without access get subscriber/lapsed/non-subscriber messaging;
- editions are grouped by sent year descending;
- latest edition and total edition count are returned.

Current archive access checks use active subscriptions and direct newsletter premium access. This is slightly narrower than `Subscription::canAccessNewsletter()`, which also knows about plan grants, bundle access, geography and time windows. Be careful before assuming archive access and send-recipient access are identical.

## 23. Scheduling

There are two schedule types:

- creation schedules;
- send schedules.

Both are managed by `NewsletterScheduleService`.

A newsletter can have only one active creation schedule and one active send schedule. Attempting to create another active schedule for the same newsletter throws a domain exception.

Schedule fields include:

- `frequency`;
- `day_of_week`;
- `day_of_month`;
- `time`;
- `status`;
- `next_run_at`;
- optional `creation_schedule_id` for send schedules.

`ScheduleNextRunCalculator` calculates `next_run_at` from the schedule parameters.

Updating schedule timing parameters recalculates `next_run_at`. Resuming a paused schedule also recalculates `next_run_at`. Cancelling a schedule sets status to cancelled and clears `next_run_at`.

## 24. Send schedule runner

`NewsletterSendScheduleRunner` is the bridge between schedule records and actual delivery.

The runner:

1. loads due send schedules;
2. fetches the newsletter;
3. skips and advances the schedule if the newsletter no longer exists;
4. skips and advances the schedule if the newsletter is paused;
5. calls `NewsletterSendService::sendNewsletter()`;
6. advances `next_run_at` after success or partial failure;
7. does not advance `next_run_at` after total failure.

That last rule is important: total failure will retry on the next cron tick, while partial success advances to avoid repeatedly emailing successful recipients.

## 25. Default newsletter

`Newsletter::getDefault($siteId)` returns the active default newsletter for a site.

`setAsDefault()` clears any other default newsletters for the same site before setting the current newsletter as default.

The default flag is site-local. Do not clear defaults globally.

## 26. Geographic restrictions

Newsletters can restrict access by region.

The rule is:

1. if restrictions are disabled, everyone is allowed;
2. if restrictions are enabled and no region is available, deny;
3. blocked regions take precedence;
4. if allowed regions are configured, the member's region must be in the allowed list;
5. otherwise, use the blocklist only.

This affects premium newsletter access through `Subscription::canAccessNewsletter()`.

## 27. Time-window restrictions

A newsletter with `has_time_window` enabled may define start and end datetimes.

Access is denied when the current time is before `access_window_start` or after `access_window_end`.

This is checked during premium newsletter access evaluation.

## 28. Bundle restrictions

A newsletter may require a bundle.

When `requires_bundle` is true, the member's subscription must have the required bundle. Bundle access may also grant newsletter access when the bundle includes the newsletter slug.

This allows a newsletter to be sold or granted as part of a bundle without hardcoding every newsletter into every plan.

## 29. Pausing

`NewsletterSendScheduleRunner` checks the newsletter's `paused` flag.

A paused newsletter is skipped and the schedule is advanced. This avoids hammering the same paused newsletter on every cron tick.

`active` and `paused` are different concepts:

- `active` controls whether a newsletter is generally eligible/due;
- `paused` tells the schedule runner to skip scheduled sends temporarily.

## 30. Tracking

For automated newsletters, dispatch replaces:

```text
{{TRACKING_EMAIL}}
{{SEND_ID}}
```

`{{TRACKING_EMAIL}}` becomes a SHA-256 hash of the recipient email. `{{SEND_ID}}` becomes the send ID.

This avoids embedding the raw email address into tracking markup while still allowing attribution.

## 31. Operational gotchas

Avoid these mistakes:

1. **Confusing subscriptions with newsletters.** Subscriptions grant premium access; newsletters own content, schedules, sends and archives.
2. **Bypassing `NewsletterRecipientResolver`.** You will miss opt-outs, frequency preferences or premium access checks.
3. **Treating preferences as eligibility.** Preferences can remove recipients only; they must not grant paid access.
4. **Renaming premium newsletter slugs casually.** Slugs are used as premium identifiers.
5. **Rendering archive editions from live content.** Archives and view-in-browser should use snapshots.
6. **Retrying preview sends.** Preview sends are explicitly non-retryable.
7. **Advancing failed schedules incorrectly.** Total send failure should not advance `next_run_at`; skipped and partial-success cases do.
8. **Assuming archive access equals send access.** Archive access currently uses a narrower access check.
9. **Sending custom emails without caution.** Custom sends bypass normal recipient resolution.
10. **Forgetting the one-hour duplicate-send guard is not a lock.** Add stronger locking before running multiple workers.
11. **Dropping the unsubscribe placeholder.** The builder appends it if missing, and dispatcher replaces it with the footer.
12. **Using live branding for old sends.** Snapshot rendering exists to avoid that.

## 32. End-to-end lifecycle

A typical newsletter lifecycle is:

1. An administrator creates or updates a site-scoped newsletter.
2. The newsletter is configured as manual, auto-pages or custom-blocks content.
3. Optional branding and layout are attached.
4. Optional premium, geographic, time-window or bundle access rules are configured.
5. A creation or send schedule may be created.
6. The schedule runner detects a due send schedule, or an admin manually sends the newsletter.
7. The content builder renders HTML and returns any pages included.
8. The recipient resolver determines eligible recipients and applies preferences.
9. The send service creates a frozen snapshot.
10. The send service creates a send record and per-recipient rows.
11. The dispatcher sends emails, personalising view links, tracking placeholders and unsubscribe footer.
12. Each recipient row becomes sent or failed.
13. Send counts are updated.
14. `last_sent` is updated.
15. The schedule advances when appropriate.
16. The archive displays editions from send records and snapshots, subject to access checks.

## 33. Key implementation locations

```text
src/Models/Newsletter.php
src/Models/NewsletterSend.php
src/Models/NewsletterSendRecipient.php
src/Models/NewsletterSendSchedule.php
src/Models/NewsletterSnapshot.php
src/Services/Newsletter/NewsletterSendService.php
src/Services/Newsletter/NewsletterDispatcher.php
src/Services/Newsletter/NewsletterRecipientResolver.php
src/Services/Newsletter/NewsletterContentBuilder.php
src/Services/Newsletter/NewsletterContentResolver.php
src/Services/Newsletter/NewsletterScheduleService.php
src/Services/Newsletter/NewsletterSendScheduleRunner.php
src/Services/Newsletter/NewsletterSnapshotService.php
src/Services/Newsletter/NewsletterArchiveService.php
src/Services/Newsletter/Layout/
src/Repositories/Newsletters/
src/Controllers/Newsletter/
src/Requests/Newsletter/
src/views/emails/newsletter/
```

When changing newsletter behaviour, update the service, repository, request validation, relevant tests and this document together.
