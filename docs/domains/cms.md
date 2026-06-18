# CMS Domain

The CMS domain covers pages, versions, blocks, grids, taxonomies, authors, media, publishing, scheduling, SEO and public rendering inputs.

## Main locations

- `src/Controllers/Cms`
- `src/Services/Cms`
- `src/Repositories/Cms`
- `src/Models/Page*` and related CMS models
- `src/Parsers`
- `src/Resources`
- `src/ViewModels`

## Architecture rules

Services own workflows such as create/update page, publish, unpublish, schedule, version, restore, assign taxonomy and manage editorial state.

Actions are for single-purpose operations such as export pages, import content, bulk status updates, backfills, one-off regeneration or synchronisation.

Repositories own persistence and complex CMS queries. Controllers and services must not construct query-builder chains.

Extract independently changing behaviour into dedicated collaborators:

- publication and access policies;
- status state machines;
- SEO resolvers;
- slug generators;
- scheduling/deadline calculators;
- block parsers and renderers;
- page composition providers;
- validation and governance checks.

## Versioning and publication

Publishing and version promotion are critical multi-write workflows. They must be transactional and must not leave the current version, published version, status and audit data out of sync.

Status, page type, block type and moderation action values use enums. Direct magic-string assignments are not allowed.

## Rendering boundaries

CMS services produce domain data, not presentation HTML or API response arrays. Parsers/renderers, resources and view models own output transformation.

Public-content composition may consume CMS data, but public delivery concerns must not leak back into CMS persistence services.

## Site scoping

Every page, taxonomy, media and configuration lookup must be explicitly site-scoped. Tests must prove that IDs or slugs from another site cannot be read or mutated.

## Events

Use events after successful state changes for genuine side effects such as cache invalidation, search indexing, notifications, analytics or downstream publication work. Every event must have an active listener.

## Testing

Use Mockery for service tests. Cover business-rule rejection, version and status transitions, transaction usage, event emission and rollback. Repository tests cover publication filters, eager loading and site isolation. Functional tests cover permissions, validation and response contracts.