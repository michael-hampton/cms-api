# Public Directory V2

The public directory exposes canonical author, category and tag index/detail pages. The HTML pages are API-first shells backed by purpose-specific directory repositories and a shared presenter.

## Canonical URLs

- `/{site}/authors`
- `/{site}/authors/{slug}`
- `/{site}/categories`
- `/{site}/categories/{slug}`
- `/{site}/tags`
- `/{site}/tags/{slug}`

Legacy singular category, tag and author paths are registered as compatibility aliases and render the same API-first pages. New links must use the canonical plural paths.

## API endpoints

- `GET /api/v1/{site}/directory/author`
- `GET /api/v1/{site}/directory/author/{slug}`
- `GET /api/v1/{site}/directory/category`
- `GET /api/v1/{site}/directory/category/{slug}`
- `GET /api/v1/{site}/directory/tag`
- `GET /api/v1/{site}/directory/tag/{slug}`

All requests are site-scoped through the `{site}` path segment. Callers must not supply an internal site ID.

Collection endpoints return the available directory entries for the current site. Detail endpoints return the selected entry plus its public content listing and relevant metadata.

Consumers should follow canonical links returned by the API/presenter when available and tolerate additive response fields.

## Architecture

Each entity owns a purpose-specific repository:

- `PublicAuthorDirectoryRepository`
- `PublicCategoryDirectoryRepository`
- `PublicTagDirectoryRepository`

`GetPublicDirectoryAction` selects the repository and builds a shared document through `PublicDirectoryPresenter`. Controllers contain no persistence queries.

The HTML page is a thin API shell. `public-directory.js` uses class-based store, API and view objects to render collection and detail pages.

Page cards contain canonical links to their authors, categories and tags, so navigation between directory pages does not depend on legacy views.

Directory content links should resolve to the canonical public page URL. The content document and engagement endpoints are documented separately in [Public Content API](public-content-api.md).

## Change rules

When extending a directory type or response:

1. keep lookup and site scoping in the relevant repository;
2. keep use-case selection/orchestration in `GetPublicDirectoryAction`;
3. keep output mapping in `PublicDirectoryPresenter`;
4. do not add entity-specific query logic to the controller or JavaScript view;
5. update this document and the functional response-contract tests.

## Verification

Verify:

- author, category and tag indexes;
- detail pages with and without articles;
- category subcategories;
- author metadata and social links;
- anonymous and authenticated page chrome;
- all category, tag and author links from content pages and widgets;
- legacy singular URL aliases;
- cross-site records are not exposed;
- missing slugs return the expected not-found response;
- mobile layouts and long names/descriptions.