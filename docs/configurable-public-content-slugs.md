# Configurable public content slug paths

Public content URLs are site-scoped and can be generated from configurable slug patterns. This allows one site to use flat URLs while another uses category/subcategory article URLs.

## Default behaviour

Sites with no custom setting use the global defaults from `src/config/public_content.php`:

```php
'slug_patterns' => [
    'flat' => [
        'pattern' => '{slug}',
        'priority' => 100,
    ],
    'category_prefix' => [
        'pattern' => 'category/{slug}',
        'priority' => 90,
    ],
    'category_slug' => [
        'pattern' => '{category}/{slug}',
        'priority' => 80,
    ],
    'category_subcategory_slug' => [
        'pattern' => '{category}/{subcategory}/{slug}',
        'priority' => 70,
    ],
],
```

The flat pattern keeps existing URLs working:

```text
/{site}/{slug}
```

Example:

```text
/golf-monthly/ping-g430-driver-review
```

## Per-site configuration

Configure a site by storing slug patterns in the site `settings` JSON.

Preferred nested form:

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

Legacy flat settings key is also supported:

```json
{
  "public_content_slug_patterns": {
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
```

## Supported placeholders

| Placeholder | Meaning |
|---|---|
| `{slug}` | The page slug. |
| `{category}` | A root category slug assigned to the page. |
| `{subcategory}` | A child category slug assigned to the page. |

Static segments are allowed too:

```json
{
  "pattern": "category/{slug}",
  "priority": 90
}
```

## Resolution rules

`PublicContentPathResolver` resolves incoming paths into candidate page lookups and generates canonical outbound paths.

For incoming requests:

1. the path is normalised;
2. configured patterns are matched by segment count and static segments;
3. the final `{slug}` value is used to load the page;
4. optional `page_type`, `{category}` and `{subcategory}` values are validated against the loaded page;
5. if no custom configuration exists, the default flat URL remains valid.

For outgoing links:

1. the resolver picks the first configured pattern that matches the page type and can be filled from the page data;
2. page grid links and public-content region/component HTML are rewritten to the configured canonical path;
3. custom/external links are preserved.

## Frontend public page URLs

The frontend `DynamicUrlResolver` supports both old flat URLs and configured nested paths.

Examples:

```text
/golf-monthly/ping-g430-driver-review
/guitar-world/news/gear/clapton-lost-strat-discovered
/guitar-world/about-us
```

Flat lookup is tried first for one-segment paths so legacy sites are not broken.

## Public Content API URLs

The API accepts a full content path after `/content/`:

```text
GET /api/v1/{site}/content/{contentPath}
GET /api/v1/{site}/regions/{regionSlug}/content/{contentPath}
```

Examples:

```text
/api/v1/golf-monthly/content/ping-g430-driver-review
/api/v1/guitar-world/content/news/gear/clapton-lost-strat-discovered
```

Viewer state, comments, likes and views still use the page ID based routes, so the content path catch-all route excludes those sub-resources.

## Notes and caveats

- Page slugs should still be unique within a site where possible.
- Nested path validation uses the loaded page taxonomy, so a mismatched category/subcategory path returns not found.
- If a configured pattern cannot be filled because the page has no matching category/subcategory, the resolver falls through to the next pattern.
- Existing sites with no settings continue using the default flat pattern.
