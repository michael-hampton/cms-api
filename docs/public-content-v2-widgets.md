# Public Content V2 Widget Reference

This document describes the widgets currently available to Public Content V2, their placement, eligibility rules and runtime behaviour.

The main sources of truth are:

```text
src/Services/PublicContent/Widgets/BuiltInPublicContentWidgetCatalog.php
src/Services/PublicContent/Widgets/PublicContentWidgetEligibility.php
src/Services/PublicContent/Widgets/PageWidgetLayoutResolver.php
src/Services/PublicContent/Composition/PublicContentComposer.php
src/Providers/PublicContentWidgetServiceProvider.php
src/config/public_content.php
```

## Registration model

Widgets can be registered in two ways.

Built-in widgets are registered on every compose call by:

```text
BuiltInPublicContentWidgetCatalog
```

Config-registered widgets are registered during provider boot from:

```php
config('public_content.widget_definitions')
```

Current config-registered widgets are:

```text
App\Services\PublicContent\Widgets\PaywallOverlayWidget
App\Services\PublicContent\Widgets\MostPopularArticlesWidget
```

`PublicContentWidgetRegistry` stores widget definitions by stable key. `PageWidgetLayoutResolver` resolves placement with this precedence:

1. catalog `defaultPlacement()`;
2. site public-content config (`widgets.{key}.region` / `priority`);
3. article-type overlay (`widgets.{key}.page_type_placements.{pageType}`);
4. per-page `page_widgets` rows.

Editor aliases `top`, `middle` and `bottom` canonicalise to `header`, `after-content` and `below-content` so the composed API keeps its existing region keys.

## Regions

Widgets are composed into the following semantic regions:

| Region | Editor label | Purpose |
|---|---|---|
| `notices` | Notices | Contextual notices shown before the page header/content. |
| `header` | Top | Page-level title, taxonomy and actions. Config may store `top`. |
| `after-content` | Middle | Widgets rendered immediately after the main page body. Config may store `middle`. |
| `sidebar` | Sidebar | Widgets rendered in the page aside, alongside CMS sidebar blocks. |
| `below-content` | Bottom | Supporting sections rendered below the main content area. Config may store `bottom`. |
| `modals` | Modals | Hidden overlays and dialogs hydrated by the browser when required. |

Site article-type defaults are edited in `src/views/public-content-v2/config-editor.php`. Individual pages override those defaults through the public-content page widget API:

```http
GET  /api/v1/{site}/content/{pageId}/widgets
PUT  /api/v1/{site}/content/{pageId}/widgets
```

Every widget build receives the site design tokens (`designTokens` / `cssVariables`) so markup and CSS use that site's theme rather than hardcoded colours.

## Page-type visibility

Page-type visibility is configured centrally in:

```text
src/config/public_content.php
```

Current defaults include:

| Widget key | Allowed page types |
|---|---|
| `page-title` | `article`, `review` |
| `hero-block` | `article`, `landing-page`, `review` |
| `breadcrumbs` | `article`, `review`, `buying-guide`, `content` |
| `category-pills` | `article` |
| `tags` | `article` |
| `page-actions` | `article` |
| `social-links` | `article`, `review`, `buying-guide` |
| `categories-widget` | `landing-page` |
| `activity-feed` | `landing-page` |
| `most-popular-articles` | `landing-page` |
| `trending` | `article`, `landing-page` |
| `recirculation` | `article`, `review`, `buying-guide` |
| `products` | `buying-guide`, `review`, `article` |
| `newsletter` | `landing-page` |
| `comments` | `article` |
| `category-pages` | `landing-page` |
| `deals` | `article`, `landing-page` |
| `vouchers` | `landing-page` |
| `guest-contributors` | `landing-page` |
| `authors` | `article`, `review`, `buying-guide`, `content` |
| `adverts` | `article`, `landing-page` |

When a widget key has no page-type config, `PublicContentWidgetEligibility::supportsWidget()` falls back to `['*']`, so that widget is not blocked by page type unless its own `supports()` rule rejects the context.

Templates must not contain their own page-type visibility checks. Visibility belongs in widget eligibility/configuration so it remains centrally configurable.

## Built-in widgets

### Notices

| Widget key | Component type | Default priority | Eligibility | Stateful | Notes |
|---|---|---:|---|---|---|
| `region-context` | `region-context` | Factory-defined | A territory is present in the composition context. | Yes | Registered by `RegionalPublicContentComponentFactory`, not by `BuiltInPublicContentWidgetCatalog`. |
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
| `categories-widget` | `categories-widget` | 100 | Configured page types with homepage categories available. | No | Receives categories with carousel layout. |
| `most-popular-articles` | `most-popular-articles` | 105 | Configured page types; currently `landing-page`. | No | Config-registered widget. Uses `PageViewRepository::getMostPopularArticles()`. Loads `most-popular-articles.css`. |
| `activity-feed` | `activity-feed-widget` | 110 | Configured page types; currently `landing-page`. Limit via `widgets.activity-feed.limit`. | No | Receives recent feed pages. |
| `trending` | `trending-widget` | 120 | Configured page types; currently `article`, `landing-page`. | No | Receives trending pages. |
| `products` | `product-section` | 130 | Configured page types plus products/buying-guide data. | No | Loads `products.css` and `product-interactions.js`. |
| `newsletter` | `newsletter-signup-widget` | 140 | Configured page types; currently `landing-page`. | Yes | Renders the newsletter teaser and signup modal behaviour. |
| `comments` | `comments` | 150 | Configured page types; currently `article`. | Yes | Uses comments list/create endpoints and comment badge data. |
| `social-links` | `social-links` | 35 (header) | Supports when sharing enabled with non-empty platforms; site `region`/`priority` + page_widgets override catalog. | No | Share links at top of page; hidden when empty. |

### Below content

| Widget key | Component type | Default priority | Eligibility | Stateful | Notes |
|---|---|---:|---|---|---|
| `category-pages` | `category-pages` | 200 | Configured page types with category sections available. | No | Renders grouped pages for landing-page categories. |
| `deals` | `deals-carousel` | 210 | Configured page types; deals island from featured deals. | No | Loads `deals-carousel.css` and `deals-carousel.js`. |
| `guest-contributors` | `guest-contributors` | 220 | Configured page types; currently `landing-page`. | No | Displays guest contributor content. |
| `authors` | `authors` | 230 | Configured page types with an author relationship or author reference. | No | Renders page author information. |

### Modals

| Widget key | Component type | Default priority | Eligibility | Stateful | Notes |
|---|---|---:|---|---|---|
| `paywall-overlay` | `paywall-overlay` | 1 | Viewer cannot access the page. | Yes | Config-registered widget. Loads `paywall-overlay.css` and `paywall-overlay.js`. |
| `subscription-modal` | `subscription-modal` | 300 | Subscription modal data is available. | No | Uses subscription modal data from the composition context. |
| `newsletter-account-modal` | `newsletter-account-modal` | 310 | No explicit supports callback; empty HTML may still cause it to be skipped. | No | Account-creation modal used by newsletter flows. |
| `newsletter-modal` | `newsletter-modal` | 320 | No explicit supports callback; empty HTML may still cause it to be skipped. | No | Newsletter modal shell. |
| `comment-modal` | `comment-modal` | 330 | No explicit supports callback; empty HTML may still cause it to be skipped. | No | Comment interaction modal. |
| `badge-earned-modal` | `badge-earned-modal` | 340 | An authenticated member and badge modal data are present. | No | Displays newly earned badge information. |

## Restricted-content composition

When `access.can_view` is false, `PublicContentComposer` only allows the following widgets to render:

```text
page-title
paywall-overlay
subscription-modal
```

All other widget placements are skipped with reason `restricted_content`.

This is separate from each widget's own `supports()` rule. A widget must first pass the restricted-content allow-list and then pass its own supports rule.

## Eligibility and skipping

A widget may be omitted for several reasons:

1. Its placement is disabled by a `page_widgets` record.
2. Its `supports()` rule returns `false`.
3. The rendered partial returns empty HTML.
4. The page is restricted and the widget is not allowed for restricted content.
5. The widget is not registered.

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

`PageWidgetLayoutResolver` starts with default placements for every registered widget, applies site article-type config, then applies override records for the current page. Unknown widget keys are ignored. Disabled placements are removed before composition.

Use `PageWidgetOverrideService` (not CMS page services) to persist those rows. A page-level override cannot force a widget to render when its `supports()` rule rejects the page.

Example: comments at the bottom of articles, sidebar on reviews.

```php
'comments' => [
    'page_types' => ['article', 'review'],
    'region' => 'bottom',
    'priority' => 150,
    'page_type_placements' => [
        'review' => [
            'region' => 'sidebar',
            'priority' => 20,
        ],
    ],
],
```

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

Example: move comments below content.

```sql
INSERT INTO page_widgets (
    page_id,
    widget_key,
    region,
    priority,
    is_enabled,
    configuration
) VALUES (
    42,
    'comments',
    'below-content',
    20,
    1,
    '{"title":"Discussion"}'
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
src/config/public_content.php
```

using:

```php
'widget_definitions' => [
    App\Services\PublicContent\Widgets\RelatedProductsWidget::class,
],
```

Built-in widgets remain registered by `BuiltInPublicContentWidgetCatalog`. Config widgets are registered by `PublicContentWidgetServiceProvider`.

## Change checklist

When adding or changing widgets:

1. update `src/config/public_content.php` when adding page-type config or config-registered definitions;
2. update this reference;
3. add or update composer, registry and page-widget override tests;
4. test restricted-content behaviour;
5. check anonymous and authenticated member contexts;
6. verify asset paths and frontend hydrators;
7. make sure empty partials degrade by skipping rather than breaking the document.
