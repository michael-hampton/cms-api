# Public Content API

The Public Content API exposes published CMS content as a versioned document that can be rendered by the public frontend. It also provides the viewer-specific state and mutation endpoints used for likes, page views, comments and badge-modal acknowledgements.

All endpoints are site-scoped. The `{site}` path segment is resolved into the active `SiteContext`; callers must not pass database site IDs directly.

## Base paths

```text
/api/v1/{site}
```

Content can be requested globally or for an active regional territory:

```text
GET /api/v1/{site}/content/{slug}
GET /api/v1/{site}/regions/{regionSlug}/content/{slug}
```

## Common middleware

Public Content API routes pass through the following shared middleware:

1. security headers;
2. CORS handling;
3. public API rate limiting;
4. query-string validation;
5. API latency measurement.

Mutation endpoints add CSRF protection. Like, unlike and badge-view mutations also require an authenticated member.

## Authentication

Reading published content, viewer state and comments is available anonymously unless the content access rules restrict the document.

Authenticated member state is detected from the existing member session. The API does not accept a member ID from the caller.

Endpoints marked **member required** return `401` when no authenticated member is available.

## Content document

### Get content by slug

```http
GET /api/v1/{site}/content/{slug}
```

### Get regional content by slug

```http
GET /api/v1/{site}/regions/{regionSlug}/content/{slug}
```

The regional route only resolves content for an active territory belonging to the current site. An unknown region or content slug returns `404`.

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
      "canonical": "/example/example-article"
    },
    "access": {
      "can_view": true,
      "reason": null
    },
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

### Restricted content

A content item that exists but cannot be fully viewed still returns a document. The response contains:

- `access.can_view: false`;
- an access `reason`, normally `subscription_required` unless another decision was supplied;
- preview content rather than the protected body;
- paywall-related components composed for the current member and page.

Consumers must use the `access` object rather than attempting to infer access from missing regions or components.

### Geo query handling

Geo-related query values are parsed into a resolved geo object and returned under `meta.geo`. Invalid geo input returns `422`. Consumers should treat the returned object as the canonical interpretation of the request.

### Content errors

| Status | Meaning |
|---|---|
| `404` | The content or requested regional territory was not found for the current site. |
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

The operation is idempotent from the API consumer's perspective. Liking an already-liked page leaves it liked. The response is the same viewer-state document returned by the viewer-state endpoint.

### Unlike content

```http
DELETE /api/v1/{site}/content/{pageId}/like
```

**Member required. CSRF protected.**

Unliking a page that is not currently liked leaves it unliked. The response is the updated viewer-state document.

## Page views

```http
POST /api/v1/{site}/content/{pageId}/views
```

Records a view using server-derived member, IP address, user-agent and referrer information.

### Successful response

Status: `201 Created`

```json
{
  "data": {
    "recorded": true,
    "duplicate": false
  }
}
```

A deduplicated request can return `recorded: false` and `duplicate: true` without being an error.

When the view rate limit is exceeded, the API returns `429` and includes a `Retry-After` header.

## Comments

### List comments

```http
GET /api/v1/{site}/content/{pageId}/comments?page=1&per_page=10
```

Query parameters:

| Parameter | Default | Constraints |
|---|---:|---|
| `page` | `1` | Minimum `1`. |
| `per_page` | `10` | Minimum `1`, maximum `50`. |

### Create a comment

```http
POST /api/v1/{site}/content/{pageId}/comments
Content-Type: application/json
```

**CSRF protected.** Anonymous comments are supported when the request supplies the required identity fields. For authenticated members, the server uses the member's stored name and email rather than trusting equivalent client fields.

Example request:

```json
{
  "name": "Anonymous Reader",
  "email": "reader@example.test",
  "content": "A useful comment.",
  "parent_id": null
}
```

Status: `201 Created`

```json
{
  "success": true,
  "message": "Your comment is awaiting moderation.",
  "comment": {
    "id": 456,
    "name": "Anonymous Reader",
    "member_id": null,
    "content": "A useful comment.",
    "created_at": "...",
    "status": "pending"
  },
  "status": "pending",
  "rate_limit": {
    "remaining": 4
  }
}
```

Comment status can be `approved`, `pending`, `spam` or another server-supported moderation state. Clients must display the returned message and must not assume all submitted comments become public immediately.

Validation failures return `422`. Comment-rate limiting returns `429` with `Retry-After`. Unexpected persistence or processing errors return `500` with a generic public message.

## Badge modal acknowledgement

```http
POST /api/v1/{site}/badge-modals/{memberBadgeId}/viewed
```

**Member required. CSRF protected.**

Marks a member badge modal as viewed. The badge record must belong to the authenticated member and current site.

## Response and consumer conventions

- Follow URLs from the response `links` object where available instead of rebuilding them in frontend code.
- Treat IDs as opaque identifiers even though current responses use integers.
- Use the canonical site slug in paths; never expose internal site IDs in public URLs.
- Do not infer publication, access or moderation state from presentation fields.
- Ignore unknown additive fields to remain forward compatible.
- Respect `Retry-After` on `429` responses.
- Do not retry `422` or `404` responses without changing the request.
- `503` responses may be retried with bounded backoff.

## Implementation map

| Responsibility | Primary implementation |
|---|---|
| Route registration and middleware | `src/routes/public-content-api.php` |
| Content HTTP handling | `Controllers/Api/V1/PublicContentController` |
| Viewer mutations and comments | `Controllers/Api/V1/PublicContentViewerController` |
| Content use case | `Actions/PublicContent/GetPublicContentAction` |
| Content response mapping | `Resources/PublicContent/PublicContentResource` |
| Published-page queries | `Repositories/PublicContent/PublicContentPageRepository` |
| Composition | `Services/PublicContent/Composition` |
| Access and paywall behaviour | `Services/Cms/Pages/ArticleAccessService` and `Services/PublicContent/Paywall` |
| Resilience and parity monitoring | `Services/PublicContent/PublicContentResilience` and `Services/PublicContent/Parity` |

## Change checklist

When changing this API:

1. keep the controller free of persistence queries;
2. preserve site scoping in every lookup and mutation;
3. update the resource or DTO rather than assembling ad-hoc documents in controllers;
4. update this document and examples;
5. add or update unit tests for actions/services and functional tests for routes, middleware, status codes and response shape;
6. consider anonymous, authenticated, restricted, regional, rate-limited and unavailable-service cases.