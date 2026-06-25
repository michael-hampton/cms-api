# Public Content API

The Public Content API exposes published CMS content as a versioned document that can be rendered by the public frontend. It also provides viewer-specific state and mutation endpoints used for likes, page views, comments, public image delivery and badge-modal acknowledgements.

All API endpoints are site-scoped through the `{site}` path segment. The site slug is resolved into the active `SiteContext`; callers must not pass database site IDs directly.

For configurable public URL patterns, see `docs/configurable-public-content-slugs.md`.

## Base paths

```text
/api/v1/{site}
```

Content can be requested globally or for an active regional territory. The `{contentPath}` segment is a catch-all path, not only a single slug:

```text
GET /api/v1/{site}/content/{contentPath}
GET /api/v1/{site}/regions/{regionSlug}/content/{contentPath}
```

Examples:

```text
GET /api/v1/golf-monthly/content/ping-g430-driver-review
GET /api/v1/guitar-world/content/news/gear/clapton-lost-strat-discovered
```

Public image assets are token-addressed outside the versioned site API path:

```text
GET /public/images/{token}
```

## Common middleware

Public Content API routes pass through the following shared middleware:

1. `SecurityHeadersMiddleware`;
2. `PublicApiCorsMiddleware`;
3. `PublicApiRateLimitMiddleware`;
4. `ValidatePublicApiQueryMiddleware`;
5. `MeasurePublicApiLatencyMiddleware`.

Mutation endpoints add CSRF protection where appropriate. Like, unlike and badge-view mutations also require an authenticated member.

The route definitions live in:

```text
src/routes/public-content-api.php
```

The content path route is deliberately constrained so page-ID sub-resources such as `/viewer-state`, `/comments`, `/like` and `/views` continue to resolve to their dedicated routes.

## Authentication

Reading published content, viewer state, comments and public image assets is available anonymously unless content access rules restrict the document.

Authenticated member state is detected from the existing member session. The API does not accept a member ID from the caller.

Endpoints marked **member required** return `401` when no authenticated member is available.

## Content document

### Get content by path

```http
GET /api/v1/{site}/content/{contentPath}
```

`{contentPath}` may be a flat slug or a configured nested path:

```text
about-us
category/my-article
news/my-article
news/local/my-article
```

### Get regional content by path

```http
GET /api/v1/{site}/regions/{regionSlug}/content/{contentPath}
```

The regional route only resolves content for an active territory belonging to the current site. An unknown region or content path returns `404`.

### Controller flow

`PublicContentController`:

1. resolves the current member from `MemberAuth` when present;
2. parses supported geo query parameters through `ResolvedGeoQueryParser`;
3. resolves `{contentPath}` through `GetPublicContentByPathAction`;
4. executes the underlying content document build inside `PublicContentResilience`;
5. returns `422` for invalid geo/query values;
6. returns `503` when the operation times out or the resilience circuit is open;
7. returns `404` when no published content document can be resolved;
8. runs parity comparison through `PublicContentParityMonitor`;
9. maps the document through `PublicContentResource`.

### Successful response

```json
{
  "data": {
    "id": 123,
    "site_id": 4,
    "slug": "example-article",
    "type": "article",
    "title": "Example article",
    "summary": "A short summary.",
    "seo": {},
    "taxonomy": {
      "categories": [
        {"id": 10, "name": "News", "slug": "news"}
      ],
      "tags": [
        {"id": 20, "name": "Example", "slug": "example"}
      ]
    },
    "authors": [
      {
        "id": 7,
        "name": "Example Author",
        "slug": "example-author",
        "bio": null,
        "image": null
      }
    ],
    "links": {
      "viewer_state": "/api/v1/example/content/123/viewer-state",
      "comments": "/api/v1/example/content/123/comments",
      "like": "/api/v1/example/content/123/like",
      "view": "/api/v1/example/content/123/views",
      "canonical": "/example/news/example-article"
    },
    "access": {
      "can_view": true,
      "reason": null
    },
    "design_tokens": [],
    "content": {
      "schema_version": "...",
      "regions": {},
      "components": {}
    }
  },
  "meta": {
    "schema_version": "...",
    "generated_at": "2026-06-18T12:00:00+00:00",
    "region": null,
    "geo": {}
  }
}
```

The exact component payload is intentionally extensible. Consumers should dispatch on component type and tolerate unknown optional fields. `meta.schema_version` and `data.content.schema_version` identify the document contract used for the response.

## Published content resolution

`GetPublicContentByPathAction` resolves the configured content path first, then delegates document construction to `GetPublicContentAction`.

The path-aware action:

1. asks `PublicContentPathResolver` for ordered candidates;
2. loads each candidate by final page slug;
3. validates optional `page_type`, category and subcategory constraints;
4. returns the first matching published content document;
5. keeps flat `/api/v1/{site}/content/{slug}` behaviour backwards compatible.

`GetPublicContentAction` owns document construction:

1. resolve optional active territory;
2. load a complete published page by site, slug and optional territory;
3. reject site-scope mismatches;
4. derive canonical API and public links using configured slug patterns;
5. check access through `ArticleAccessService`;
6. build composition data;
7. render main content regions through `PublicContentRenderer`;
8. compose surrounding widgets through `PublicContentComposer`;
9. rewrite internal public-content links in rendered regions/components to canonical configured paths;
10. map taxonomy, authors, territory widgets and geo widgets into the document.

For regional pages, canonical URLs differ depending on whether the page slug is the territory slug:

```text
/{site}/{territory}
/{site}/{territory}/{contentPath}
```

## Configurable content paths

Default path patterns are configured in `src/config/public_content.php` and may be overridden per site through the `settings` JSON.

Preferred site setting:

```json
{
  "public_content": {
    "slug_patterns": {
      "article": {
        "pattern": "{category}/{subcategory}/{slug}",
        "page_type": "article",
        "priority": 100
      },
      "content": {
        "pattern": "{slug}",
        "page_type": "content",
        "priority": 90
      }
    }
  }
}
```

Supported placeholders are `{slug}`, `{category}` and `{subcategory}`. Static path segments such as `category/{slug}` are also supported.

Sites with no setting continue to support flat URLs such as:

```text
/{site}/{slug}
/api/v1/{site}/content/{slug}
```

## Restricted content

A content item that exists but cannot be fully viewed still returns a document. The response contains:

- `access.can_view: false`;
- an access `reason`, normally `subscription_required` unless another decision was supplied;
- preview content rather than the protected body;
- paywall-related components composed for the current member and page.

Consumers must use the `access` object rather than attempting to infer access from missing regions or components.

Restricted documents currently use a preview region based on `listing_synopsis`, `meta_description` or `description`. The composer filters restricted widgets so only allowed restricted-content widgets render.

## Geo query handling

Geo-related query values are parsed into a resolved geo object and returned under `meta.geo`. Invalid geo input returns `422`. Consumers should treat the returned object as the canonical interpretation of the request.

The public rendering shell also carries geo values through to the API URL so the API and rendered page agree about the request context.

## Content errors

| Status | Meaning |
|---|---|
| `404` | The content path or requested regional territory was not found for the current site. |
| `422` | A supported query value was invalid. |
| `503` | The content operation timed out or the resilience circuit was open. |

## Viewer state

```http
GET /api/v1/{site}/content/{pageId}/viewer-state
```

Returns public engagement state for the page, enriched with member-specific state when a member is authenticated.

```json
{
  "data": {}
}
```

The endpoint is site-scoped and only accepts published content. A missing page returns `404`.

## Likes

### Like content

```http
PUT /api/v1/{site}/content/{pageId}/like
```

**Member required. CSRF protected.**

The operation is idempotent from the API consumer's perspective. Liking an already-liked page leaves it liked. When a new like is created, activity tracking runs and `PageLikedByMember` is emitted. The response is the same viewer-state document returned by the viewer-state endpoint.

### Unlike content

```http
DELETE /api/v1/{site}/content/{pageId}/like
```

**Member required. CSRF protected.**

Unliking an unliked page is also idempotent. The response is the same viewer-state document returned by the viewer-state endpoint.
