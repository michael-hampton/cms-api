# Public Content Domain

Public Content exposes published CMS documents, regional variants, access-aware previews, composed components, viewer state, likes, views, comments and public directory data.

## Main locations

- `src/Controllers/Api/V1/PublicContent*`
- `src/Services/PublicContent`
- `src/Repositories/PublicContent`
- `src/DTO/PublicContent`
- `src/Resources/PublicContent`
- `src/Middleware/PublicContent`
- `src/routes/public-content-api.php`

## Architecture rules

Business workflows belong in services. Content access, paywall decisions, viewer-state rules, comment submission, view recording and composition rules must not be placed in controllers.

Single-purpose actions may be used for operations such as exporting parity reports, rebuilding one projection, synchronising one public index or bulk regeneration. Retrieving normal public content is not a reason to create a generic action layer when a service owns the workflow and rules.

Repositories own published-content queries and site/territory scoping only. They do not format response documents or make access decisions.

Independently changing behaviour belongs in collaborators:

- access and paywall policies;
- geo and territory resolvers;
- component providers/composers;
- parity comparators;
- rate limiters;
- view deduplication decisions;
- canonical URL resolvers.

## API boundaries

Controllers resolve HTTP input and trusted site/member context, invoke services, and map known outcomes to responses. Resources own response shape. Services must not return controller responses or format public JSON arrays.

All lookups are explicitly site-scoped. Regional content must also validate territory ownership and active state.

## Mutations

Likes, comments, views and badge acknowledgements must use injected auth/context abstractions rather than static session/global access in new or refactored service code.

Multi-write mutations are transactional. Events are used only for real analytics, notification or projection side effects with active listeners.

## Resilience

Timeout, circuit-breaker, rate-limit and retry behaviour is infrastructure/application-edge behaviour. Critical content writes throw. Non-critical parity, analytics or tracking may catch and log without breaking content delivery.

## Testing

Use Mockery for service dependencies. Cover anonymous/authenticated access, restricted content, site and territory isolation, transaction use for mutations, emitted events, rate limiting, deduplication and safe handling of non-critical failures. Functional tests cover middleware and API contracts.