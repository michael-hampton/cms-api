# Public Content V2 Widget Reference

This document describes the widgets currently registered by Public Content V2, their default placement, eligibility rules and runtime behaviour.

The main sources of truth are:

```text
src/Services/PublicContent/Widgets/BuiltInPublicContentWidgetCatalog.php
src/Services/PublicContent/Widgets/PublicContentWidgetEligibility.php
src/Services/PublicContent/Composition/RegionalPublicContentComponentFactory.php
src/Services/PublicContent/Widgets/PaywallOverlayWidget.php
src/config/public_content.php
```

## Regions

Widgets are composed into the following semantic regions:

| Region | Purpose |
|---|---|
| `notices` | Contextual notices shown before the page header/content. |
| `header` | Page-level title, taxonomy and actions. |
| `after-content` | Widgets rendered immediately after the main page body. |
| `below-content` | Supporting sections rendered below the main content area. |
| `modals` | Hidden overlays and dialogs hydrated by the browser when required. |

## Page-type visibility

Page-type visibility is configured centrally in:

```text
src/config/public_content.php
```

The current defaults are:

| Widget key | Allowed page types |
|---|---|
| `page-title` | `article` |
| `category-pills` | `article` |
| `tags` | `article` |
| `page-actions` | `article` |
| `comments` | `article` |

Templates must not contain their own page-type visibility checks. Visibility belongs in widget eligibility/configuration so it remains centrally configurable.

## Built-in widgets

### Notices

| Widget key | Component type | Default priority | Eligibility | Stateful | Notes |
|---|---|---:|---|---|---|
| `region-context` | `region-context` | 1 | A territory is present in the composition context. | Yes | Renders regional context and loads `public-content-v2-region-context.js`. |
| `claimed-gift` | `claimed-gift` | 5 | A claimed gift exists in `viewData`. | No | Displays a gift-claim notice. |

### Header

| Widget key | Component type | Default priority | Eligibility | Stateful | Notes |
|---|---|---:|---|---|---|
| `breadcrumbs` | `breadcrumbs` | 5 | Page is not a landing page and has at least one category. | No | Uses the shared breadcrumbs partial. |
| `page-title` | `page-title` | 10 | Configured page types; currently `article`. | No | Renders editorial title, subtitle and publish date. |
| `category-pills` | `category-pills` | 20 | Configured page types; currently `article`. | No | Displays assigned categories as pills. |
| `tags` | `tags` | 30 | Configured page types; currently `article`. | No | Displays assigned tags. |
| `page-actions` | `page-actions` | 40 | Configured page types; currently `article`. | Yes | Uses viewer-state, like and view endpoints. |

### After content

| Widget key | Component type | Default priority | Eligibility | Stateful | Assets/endpoints |
|---|---|---:|---|---|---|
| `categories-widget` | `categories-widget` | 100 | Landing page with homepage categories available. | No | Receives categories with carousel layout. |
| `activity-feed` | `activity-feed-widget` | 110 | Landing pages only. | No | Receives recent feed pages. |
| `trending` | `trending-widget` | 120 | Always eligible; empty HTML may still cause it to be skipped. | No | Receives trending pages. |
| `products` | `product-section` | 130 | Page has products. | No | Loads `products.css` and `product-interactions.js`. |
| `newsletter` | `newsletter-signup-widget` | 140 | Landing pages only. | Yes | Renders the newsletter teaser and signup modal behaviour. |
| `comments` | `comments` | 150 | Configured page types; currently `article`. | Yes | Uses comments list/create endpoints and comment badge data. |
| `links` | `social-links` | 160 | Always eligible; empty HTML may still cause it to be skipped. | No | Renders configured social/external links. |

### Below content

| Widget key | Component type | Default priority | Eligibility | Stateful | Notes |
|---|---|---:|---|---|---|
| `category-pages` | `category-pages` | 200 | Landing page with category sections available. | No | Renders grouped pages for landing-page categories. |
| `deals` | `deals-carousel` | 210 | Deals are available in `viewData`. | No | Loads `deals-carousel.css` and `deals-carousel.js`. |
| `guest-contributors` | `guest-contributors` | 220 | Landing pages only. | No | Displays guest contributor content. |
| `authors` | `authors` | 230 | Non-landing page with an author relationship or author reference. | No | Renders page author information. |

### Modals

| Widget key | Component type | Default priority | Eligibility | Stateful | Notes |
|---|---|---:|---|---|---|
| `paywall-overlay` | `paywall-overlay` | 1 | Viewer cannot access the page. | Yes | Loads `paywall-overlay.css` and `paywall-overlay.js`. |
| `subscription-modal` | `subscription-modal` | 300 | Subscription modal data is available. | No | Uses subscription modal data from the composition context. |
| `newsletter-account-modal` | `newsletter-account-modal` | 310 | Always eligible; empty HTML may still cause it to be skipped. | No | Account-creation modal used by newsletter flows. |
| `newsletter-modal` | `newsletter-modal` | 320 | Always eligible; empty HTML may still cause it to be skipped. | No | Newsletter modal shell. |
| `comment-modal` | `comment-modal` | 330 | Always eligible; empty HTML may still cause it to be skipped. | No | Comment interaction modal. |
| `badge-earned-modal` | `badge-earned-modal` | 340 | An authenticated member and badge modal data are present. | No | Displays newly earned badge information. |

## Eligibility and skipping

A widget may be omitted for several reasons:

1. Its placement is disabled by a `page_widgets` record.
2. Its `supports()` rule returns `false`.
3. The rendered partial returns empty HTML.
4. The page is restricted and the widget is not allowed for restricted content.

For restricted content, the composer currently permits only:

```text
page-title
paywall-overlay
subscription-modal
```

Skipped widgets are recorded by `PublicContentWidgetDiagnostics` with the widget key, reason, page ID and site ID.

## Per-page overrides

The `page_widgets` table can move, reprioritise, configure or disable an eligible widget:

```text
page_id
widget_key
region
priority
is_enabled
configuration
```

A page-level override cannot force a widget to render when its `supports()` rule rejects the page.

Example: disable the newsletter widget for a specific landing page.

```sql
INSERT INTO page_widgets (
    page_id,
    widget_key,
    region,
    priority,
    is_enabled
) VALUES (
    42,
    'newsletter',
    'after-content',
    140,
    0
);
```

## Adding a widget

A custom widget should implement:

```php
App\Services\PublicContent\Widgets\PublicContentWidgetDefinition
```

It must define:

- a stable widget key;
- default region and priority;
- an eligibility rule;
- rendered HTML;
- optional assets and endpoints;
- whether it is stateful.

Register custom widget classes in:

```text
src/config/public-content.php
```

Built-in widgets remain registered by `BuiltInPublicContentWidgetCatalog`.
