# Public Content V2

## Status

Implemented beside the legacy public content controller and views. The legacy route remains unchanged and is used only when V2 is disabled for the request.

## Supported page types

Public Content V2 currently supports three active page types:

| Page type | Intended use | Typical examples | Default widget behaviour |
|---|---|---|---|
| `landing-page` | Homepages and section/brand landing pages whose primary purpose is discovery and navigation. | Home, topic landing pages, campaign landing pages | Landing widgets are eligible, including category sections, activity feed, newsletter signup and guest contributors. Article title, taxonomy, actions and comments are not shown by default. |
| `content` | Static informational pages that are not editorial stories. | About, Contact, Privacy, Terms, Accessibility | Article title, taxonomy, actions and comments are not shown by default. Shared site header/member controls remain available. |
| `article` | Editorial stories and publishable article content. | News stories, features, guides and editorial posts | Page title, categories, tags, page actions, comments and author widgets are eligible by default. |

The legacy `page` type is not used by current content data and is not included in the V2 rollout defaults.

### Source of truth

The active V2 page types are configured in:

```text
src/config/public-content.php
```

and may be restricted at runtime using:

```text
PUBLIC_CONTENT_V2_PAGE_TYPES=content,article,landing-page
```

Widget visibility by page type is configured in:

```text
src/config/public_content.php
```

Page templates should not contain page-type checks for widget visibility. Eligibility belongs in the widget catalogue/configuration layer so page-type behaviour remains centrally configurable.

## Routes

- `GET /{site}/content-v2/{slug}` — V2 preview page
- `GET /api/v1/{site}/content/{slug}` — public content composition document
- `GET /api/v1/{site}/regions/{regionSlug}/content/{slug}` — regional public content document
- `GET /api/v1/{site}/content/{pageId}/viewer-state` — member-specific state
- `PUT /api/v1/{site}/content/{pageId}/like` — like page
- `DELETE /api/v1/{site}/content/{pageId}/like` — unlike page
- `POST /api/v1/{site}/content/{pageId}/views` — record page view
- `GET /api/v1/{site}/content/{pageId}/comments` — comments and statistics
- `POST /api/v1/{site}/content/{pageId}/comments` — create comment

## Rendering ownership

Structured CMS blocks are the canonical content representation. Final main and sidebar HTML is rendered by the existing backend `PageRenderService`.

This preserves existing handling for:

- standard blocks
- sidebar blocks
- page grids
- zones
- offers
- deals
- rewards
- boosts
- advert spacing and overflow

The browser does not render CMS blocks.

## Component composition contract

Surrounding page features are returned as ordered components grouped into semantic regions:

- `notices`
- `header`
- `after-content`
- `below-content`
- `modals`

Each component includes:

```json
{
  "id": "comments",
  "type": "comments",
  "region": "after-content",
  "priority": 150,
  "html": "<section class=\"comments-wrapper\">...</section>",
  "assets": {
    "styles": [],
    "scripts": []
  },
  "endpoints": {
    "list": "/api/v1/site/content/123/comments",
    "create": "/api/v1/site/content/123/comments"
  },
  "stateful": true
}
```

`PublicContentComposer` resolves registered widget definitions through `PublicContentWidgetRegistry` and page placements through `PageWidgetLayoutResolver`. Adding a component does not require changing the V2 page template or browser composer.

## Widget definitions and page overrides

Widget behaviour is developer-owned through:

```text
src/Services/PublicContent/Widgets/PublicContentWidgetDefinition.php
```

A definition provides:

- a stable widget key
- a default region and priority
- an eligibility rule
- backend-rendered HTML
- assets and API endpoints where needed

Page-specific layout overrides are stored in `page_widgets`:

```text
page_id
widget_key
region
priority
is_enabled
configuration
```

No `page_widgets` records are required for existing pages. In the absence of overrides, all current widgets retain their existing eligibility, region and order.

Example override:

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

Disable a widget for one page:

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

Database configuration cannot bypass a widget definition's `supports()` rule. A page may move, configure or disable an eligible widget, but it cannot force an invalid widget onto unsupported content.

## Registering a new widget

Create a class implementing:

```php
App\Services\PublicContent\Widgets\PublicContentWidgetDefinition
```

Register its class in:

```php
// config/public-content.php
'widgets' => [
    App\Services\PublicContent\Widgets\RelatedProductsWidget::class,
],
```

The widget service provider resolves each configured definition through the container and adds it to the singleton registry. Existing keys are preserved; a provider may call `replace()` explicitly when intentional replacement is required.

Widget-specific page configuration is available to backend partials as:

```php
$widgetConfiguration
```

and to custom definitions through:

```php
$placement->config('key', $default)
```

## Visual parity

The composition layer renders the existing production partials through `ViewRenderer`. It therefore preserves their existing:

- HTML structure
- CSS classes
- inline styles
- responsive rules
- JavaScript hooks
- asset directives

Registered legacy components include page title, taxonomy, page actions, category widgets, activity feed, trending, products, newsletter, comments, social links, category pages, deals, guest contributors, authors, regional context, gift notice and all page modals.

The V2 shell uses the legacy `mt-20 > .container`, `.page-header`, `.page-layout`, `.main-content` and `.sidebar` hierarchy. Neutral composition wrappers use `display: contents` so they do not alter legacy selectors or layout.

## Query ownership

Controllers, actions, services and views do not define database queries for the V2 path. Query responsibilities are held by purpose-specific repositories under:

```text
src/Repositories/PublicContent/
```

`PageWidgetLayoutResolver` depends on `PageWidgetRepositoryInterface`; page-widget persistence remains outside the composer.

Composition providers may filter or order repository results, but they do not construct database queries.

## Frontend responsibilities

`public-content-v2.js` owns:

- loading the API document
- composing arbitrary regions
- inserting backend-rendered HTML
- loading component-declared assets
- dispatching component lifecycle events
- loading and error state
- view recording

`public-content-v2-hydrators.js` owns stateful behaviour such as API comment submission and newsletter triggers. Stateful components receive their endpoints through the API contract.

## Rollout

Environment controls:

```text
PUBLIC_CONTENT_V2_PREVIEW_ENABLED=true
PUBLIC_CONTENT_V2_ENABLED=false
PUBLIC_CONTENT_V2_SHADOW_ENABLED=false
PUBLIC_CONTENT_V2_SITE_IDS=
PUBLIC_CONTENT_V2_PAGE_TYPES=content,article,landing-page
```

The preview route can be disabled independently. Production cutover remains disabled by default.

## Rollback

Set:

```text
PUBLIC_CONTENT_V2_ENABLED=false
```

The legacy controller and route remain available and unchanged.

## Verification

Run:

```bash
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/psalm
```

Manual parity checks should cover anonymous and authenticated visitors, paid content, gifted content, landing pages, comments, likes, adverts, grids, zones, sidebar pages, every registered component and all modal interactions.
