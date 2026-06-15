# Public Directory V2

## Canonical URLs

- `/{site}/authors`
- `/{site}/authors/{slug}`
- `/{site}/categories`
- `/{site}/categories/{slug}`
- `/{site}/tags`
- `/{site}/tags/{slug}`

Legacy singular category, tag and author paths are registered as compatibility aliases and render the same API-first pages.

## APIs

- `/api/v1/{site}/directory/author`
- `/api/v1/{site}/directory/author/{slug}`
- `/api/v1/{site}/directory/category`
- `/api/v1/{site}/directory/category/{slug}`
- `/api/v1/{site}/directory/tag`
- `/api/v1/{site}/directory/tag/{slug}`

## Architecture

Each entity owns a purpose-specific repository:

- `PublicAuthorDirectoryRepository`
- `PublicCategoryDirectoryRepository`
- `PublicTagDirectoryRepository`

`GetPublicDirectoryAction` chooses the repository and builds a shared document through `PublicDirectoryPresenter`. Controllers contain no queries.

The HTML page is a thin API shell. `public-directory.js` uses class-based store, API and view objects to render collection and detail pages.

Page cards contain canonical links to their authors, categories and tags, so navigation between directory pages does not depend on legacy views.

## Verification

Verify:

- author, category and tag indexes;
- detail pages with and without articles;
- category subcategories;
- author metadata and social links;
- anonymous and authenticated page chrome;
- all category, tag and author links from content pages and widgets;
- legacy singular URL aliases;
- mobile layouts and long names/descriptions.
