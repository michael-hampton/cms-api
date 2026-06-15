# Public Content V2

## Status

Implemented beside the legacy public content controller and views. The legacy route remains unchanged.

## Routes

- `GET /{site}/content-v2/{slug}` — V2 preview page
- `GET /api/v1/{site}/content/{slug}` — public content composition document
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

`PublicContentComposer` owns component registration, visibility, region and ordering. Adding a component does not require changing the V2 page template or browser composer.

## Visual parity

The composition layer renders the existing production partials through `ViewRenderer`. It therefore preserves their existing:

- HTML structure
- CSS classes
- inline styles
- responsive rules
- JavaScript hooks
- asset directives

Registered legacy components include page title, taxonomy, page actions, category widgets, activity feed, trending, products, newsletter, comments, social links, category pages, deals, guest contributors, authors, gift notice and all page modals.

The V2 shell uses the legacy `mt-20 > .container`, `.page-header`, `.page-layout`, `.main-content` and `.sidebar` hierarchy. Neutral composition wrappers use `display: contents` so they do not alter legacy selectors or layout.

## Query ownership

Controllers, actions, services and views do not define database queries for the V2 path. Query responsibilities are held by purpose-specific repositories under:

```text
src/Repositories/PublicContent/
```

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
PUBLIC_CONTENT_V2_PAGE_TYPES=page,article,landing-page
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
