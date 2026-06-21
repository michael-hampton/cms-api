# Public Content V2

## Status

Implemented beside the legacy public content controller and views. The legacy route remains available. V2 is used only when preview routes are hit directly or rollout middleware decides the current request/page is eligible.

## Island architecture

Public Content V2 uses an island architecture rather than a full single-page application.

The server owns the page shell, content access decisions, CMS rendering and island composition. The browser progressively hydrates only the interactive islands that need client-side behaviour.

At a high level:

```text
server-rendered shell
+ API-delivered content document
+ backend-rendered island HTML
+ island-declared assets/endpoints
+ targeted frontend hydration
```

An island is a backend-composed public-content component with:

- a stable widget/component key;
- rendered HTML;
- a semantic region;
- priority/order;
- optional styles;
- optional scripts;
- optional API endpoints;
- a `stateful` flag;
- optional frontend hydration behaviour.

Static islands are inserted into the page and left alone. Stateful islands are hydrated by `public-content-v2-hydrators.js` or island-specific scripts.

Examples of public-content islands include:

- `page-actions`;
- `comments`;
- `newsletter`;
- `products`;
- `deals`;
- `paywall-overlay`;
- `subscription-modal`;
- `badge-earned-modal`;
- `region-context`.

Backend ownership:

- decides whether an island exists;
- decides where it appears;
- enforces access restrictions;
- renders initial HTML;
- declares assets;
- declares endpoint URLs;
- provides initial server-side data.

Frontend ownership:

- loads the API document;
- inserts islands into semantic regions;
- loads declared assets;
- hydrates stateful islands;
- handles browser events;
- calls API endpoints declared by the island;
- handles island-level loading/error states.

The frontend must not rebuild business rules that already exist on the server. Access control, page-type eligibility, widget visibility and restricted-content island filtering belong to the backend.

In implementation terms, widgets are the server-side representation of public-content islands.

## Supported page types

Public Content V2 currently supports three active page types:

| Page type | Intended use | Typical examples | Default widget behaviour |
|---|---|---|---|
| `landing-page` | Homepages and section/brand landing pages whose primary purpose is discovery and navigation. | Home, topic landing pages, campaign landing pages | Landing widgets are eligible, including category sections, activity feed, newsletter signup, guest contributors and configured landing widgets. Article title, taxonomy, actions and comments are not shown by default. |
| `content` | Static informational pages that are not editorial stories. | About, Contact, Privacy, Terms, Accessibility | Article title, taxonomy, actions and comments are not shown by default. Shared site header/member controls remain available. |
| `article` | Editorial stories and publishable article content. | News stories, features, guides and editorial posts | Page title, categories, tags, page actions, comments and author widgets are eligible by default. |

The legacy `page` type is not used by current content data and is not included in the V2 rollout defaults.

## Source of truth

The current config file is:

```text
src/config/public_content.php
```

It contains:

- rollout defaults;
- page types;
- config-registered widget definitions;
- widget page-type visibility;
- cache hints.

Runtime rollout is controlled by environment variables and `PublicContentRollout`:

```text
PUBLIC_CONTENT_V2_PREVIEW_ENABLED=true
PUBLIC_CONTENT_V2_ENABLED=false
PUBLIC_CONTENT_V2_SHADOW_ENABLED=false
PUBLIC_CONTENT_V2_SITE_IDS=
PUBLIC_CONTENT_V2_PAGE_TYPES=content,article,landing-page
```

`PublicContentRollout::enabledFor()` first checks `PUBLIC_CONTENT_V2_ENABLED`, then optional site IDs, then allowed page types.

Page templates should not contain page-type checks for widget visibility. Eligibility belongs in the widget catalogue/configuration layer so page-type behaviour remains centrally configurable.

## Routes and entry points

### API routes

```text
GET    /api/v1/{site}/content/{slug}
GET    /api/v1/{site}/regions/{regionSlug}/content/{slug}
GET    /api/v1/{site}/content/{pageId}/viewer-state
PUT    /api/v1/{site}/content/{pageId}/like
DELETE /api/v1/{site}/content/{pageId}/like
POST   /api/v1/{site}/content/{pageId}/views
GET    /api/v1/{site}/content/{pageId}/comments
POST   /api/v1/{site}/content/{pageId}/comments
POST   /api/v1/{site}/badge-modals/{memberBadgeId}/viewed
GET    /public/images/{token}
```

### Public shell/rendering entry points

```text
Controllers/Front/ApiFirstPublicContentController
Actions/PublicContent/RenderPublicContentPageAction
Middleware/PublicContent/PublicContentRolloutMiddleware
Middleware/PublicContent/RegionalPublicContentRolloutMiddleware
```

The shell renders `public-content-v2/page` with SEO, navigation, territory context, API URL and security headers. The browser then fetches the API document and injects backend-rendered regions/islands.

## Rollout middleware

`PublicContentRolloutMiddleware` targets requests heading to the legacy `ContentController`. It resolves the page from route attributes or slug, skips custom handlers, checks rollout eligibility, optionally resolves an active territory for the page and renders the V2 shell instead of continuing to the legacy controller.

`RegionalPublicContentRolloutMiddleware` handles regional page URLs. It resolves the `regionSlug` and `pageSlug`, verifies the active territory and published page for that territory, skips custom handlers and renders the V2 shell when rollout allows it.

Both middleware paths pass resolved geo data into `RenderPublicContentPageAction` so the shell API URL carries the same geo context as the page request.

## Rendering ownership

Structured CMS blocks are the canonical content representation. Final main/sidebar/body HTML is still rendered by backend services, not by the browser.

This preserves existing handling for:

- standard blocks;
- sidebar blocks;
- page grids;
- zones;
- offers;
- deals;
- rewards;
- boosts;
- advert spacing and overflow;
- image URL transformation.

The browser does not render CMS blocks. It consumes backend-rendered HTML from the API document.

## API document ownership

`PublicContentController` handles content API requests. It:

1. resolves the current member from `MemberAuth`;
2. parses geo query parameters;
3. executes `GetPublicContentAction` through the resilience wrapper;
4. returns `422`, `503` or `404` for invalid/unavailable/missing content cases;
5. runs parity monitoring;
6. maps the document through `PublicContentResource`.

`GetPublicContentAction` owns document construction:

1. resolve optional active territory;
2. load complete published page by site/slug/territory;
3. reject site-scope mismatches;
4. build canonical links;
5. check article access;
6. build restricted preview document when access is denied;
7. build composition data;
8. render content regions;
9. compose widgets/islands;
10. map taxonomy, authors, territory and geo widgets.

## Component and island composition contract

Surrounding page features are returned as ordered components grouped into semantic regions. These components are the API representation of public-content islands:

- `notices`;
- `header`;
- `after-content`;
- `below-content`;
- `modals`.

Each island component includes:

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

`PublicContentComposer` resolves registered widget definitions through `PublicContentWidgetRegistry` and page placements through `PageWidgetLayoutResolver`. Adding an island does not require changing the V2 page template or browser composer when the island follows the component contract.

## Widget definitions and page overrides

Widget behaviour is developer-owned through:

```text
App\Services\PublicContent\Widgets\PublicContentWidgetDefinition
```

A definition provides:

- a stable widget key;
- a default region and priority;
- an eligibility rule;
- backend-rendered HTML;
- assets and API endpoints where needed.

Built-in widgets are registered by `BuiltInPublicContentWidgetCatalog`. Additional widgets are registered from `public_content.widget_definitions` by `PublicContentWidgetServiceProvider`.

Page-specific layout overrides are stored in `page_widgets`:

```text
page_id
widget_key
region
priority
is_enabled
configuration
```

No `page_widgets` records are required for existing pages. In the absence of overrides, all registered widgets retain their default eligibility, region and order.

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

## Restricted content and island allow-list

Restricted content is handled through the island model.

If `ArticleAccessService::canView()` denies access, the API still returns a document. That document contains:

- `access.can_view: false`;
- an access reason;
- a preview region using available synopsis/description fields;
- restricted-content islands.

`PublicContentComposer` currently allows only these islands on restricted content:

```text
page-title
paywall-overlay
subscription-modal
```

This restricted island allow-list is a security boundary. New islands must not be added to restricted content unless they are safe to render without exposing protected body content, comments, products, related content or other derived private data.

The paywall overlay itself is a config-registered widget, not a built-in catalog widget.

## Visual parity

The composition layer renders existing production partials through `ViewRenderer`. It therefore preserves their existing:

- HTML structure;
- CSS classes;
- inline styles;
- responsive rules;
- JavaScript hooks;
- asset directives.

Registered legacy components include page title, taxonomy, page actions, category widgets, activity feed, trending, products, newsletter, comments, social links, category pages, deals, guest contributors, authors, regional context, gift notice, paywall overlay, most-popular articles and modals.

The V2 shell uses the legacy `mt-20 > .container`, `.page-header`, `.page-layout`, `.main-content` and `.sidebar` hierarchy. Neutral composition wrappers use `display: contents` so they do not alter legacy selectors or layout.

## Query ownership

Controllers, actions, services and views should not define ad-hoc database queries for the V2 path. Query responsibilities are held by purpose-specific repositories, especially under:

```text
src/Repositories/PublicContent/
```

`PageWidgetLayoutResolver` depends on `PageWidgetRepositoryInterface`; page-widget persistence remains outside the composer.

Composition providers may filter or order repository results, but they should not construct persistence queries.

## Frontend responsibilities

`public-content-v2.js` owns:

- loading the API document;
- composing arbitrary regions;
- inserting backend-rendered HTML;
- loading component-declared assets;
- dispatching component lifecycle events;
- loading and error state;
- view recording.

`public-content-v2-hydrators.js` owns stateful behaviour such as API comment submission and newsletter triggers. Stateful components receive their endpoints through the API contract.

The frontend should follow API-provided links/endpoints rather than rebuilding them.

## Public images

Content image URLs may be transformed into tokenised public image URLs. The public image endpoint is:

```text
GET /public/images/{token}
```

It supports long-lived immutable caching for resolved assets, ETag/304 handling and SVG fallback behaviour for missing assets. Unsupported image types return `415`.

## Rollback

Set:

```text
PUBLIC_CONTENT_V2_ENABLED=false
```

The legacy controller and route remain available. Preview routes can also be disabled independently with:

```text
PUBLIC_CONTENT_V2_PREVIEW_ENABLED=false
```

## Verification

Run:

```bash
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/psalm
```

Manual parity checks should cover anonymous and authenticated visitors, paid content, gifted content, landing pages, regional pages, comments, likes, page views, adverts, grids, zones, sidebar pages, tokenised images, every registered component and all modal interactions.

## Change checklist

When changing Public Content V2:

1. update `docs/public-content-api.md` if endpoint shape/status/middleware changes;
2. update `docs/public-content-v2-widgets.md` if widget registration, placement or eligibility changes;
3. preserve site scoping in every lookup;
4. keep query ownership in repositories;
5. keep protected-content behaviour explicit;
6. test legacy fallback, preview, rollout enabled/disabled, regional rollout and custom-handler bypass;
7. test anonymous/authenticated/restricted/geo/error cases;
8. test stateful island hydration and ensure static islands do not require JavaScript;
9. treat restricted-content island allow-list changes as security-sensitive.
