# Public Content V2

## Status

Implemented beside the legacy public content controller and views. The legacy route remains unchanged.

## Routes

- `GET /{site}/content-v2/{slug}` — V2 preview page
- `GET /api/v1/{site}/content/{slug}` — public content document
- `GET /api/v1/{site}/content/{pageId}/viewer-state` — member-specific state
- `PUT /api/v1/{site}/content/{pageId}/like` — like page
- `DELETE /api/v1/{site}/content/{pageId}/like` — unlike page
- `POST /api/v1/{site}/content/{pageId}/views` — record page view
- `GET /api/v1/{site}/content/{pageId}/comments` — comments and statistics
- `POST /api/v1/{site}/content/{pageId}/comments` — create comment

## Rendering ownership

Structured blocks are the canonical API representation. Final page-region HTML is rendered by the existing backend `PageRenderService`.

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

The browser does not render CMS blocks. JavaScript manages request, interaction, and component state only.

## Frontend state

`public-content-v2.js` owns:

- document loading
- viewer state
- likes
- views
- comments
- errors and retry

`public-content-v2-supplementary.js` owns:

- subscription, gift, and badge notices
- activity feed
- trending content
- products
- deals
- guest contributors
- newsletter trigger

Supplementary failures do not prevent the primary article from rendering.

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

Manual parity checks should cover anonymous and authenticated visitors, paid content, gifted content, landing pages, comments, likes, adverts, grids, zones, sidebar pages, and supplementary widgets.
