# Coding Standards

These standards describe the expected shape of production code in this repository. They complement automated formatting and static analysis; they do not replace engineering judgement.

## PHP style

- Use `declare(strict_types=1);` in new PHP files where compatible with the surrounding code.
- Follow PSR-12 formatting and the established project namespace layout.
- Use typed parameters, return types and properties. Avoid `mixed` unless the boundary genuinely accepts multiple unrelated shapes.
- Prefer constructor property promotion and `readonly` dependencies for injected collaborators.
- Mark classes `final` unless inheritance is an intentional extension mechanism.
- Prefer early returns over deeply nested conditionals.
- Use named arguments when they make a multi-parameter call materially clearer.
- Remove dead code, commented-out implementations and temporary debug output before merge.

## Naming

Names should expose intent rather than implementation detail.

- Actions: `VerbNounAction`, for example `GetPublicContentAction`.
- Services: name the capability or policy, for example `PublicContentViewerStateService`.
- Repositories: name the owned aggregate/read model, for example `PublicContentPageRepository`.
- Requests: describe the operation, for example `CreatePublicCommentRequest`.
- DTOs: describe the data, not the transport, for example `ResolvedGeo`.
- Boolean methods: use `is`, `has`, `can`, `should` or another question-shaped prefix.
- Repository methods: describe the complete query intent and scope.

Avoid vague names such as `data`, `thing`, `process`, `handleStuff`, `Helper`, `Manager` and `Utils` when a more precise term exists.

## Controllers and HTTP

Controllers must stay thin. They may coordinate request validation, trusted context, an action/service call and response mapping, but business and persistence rules belong elsewhere.

- Return consistent response envelopes through the framework response helpers.
- Use the correct HTTP status code rather than always returning `200`.
- Do not expose exception messages from unexpected/internal failures.
- Do not trust client-supplied user, member, site or ownership identifiers when those values are available from authenticated context.
- Keep authentication, CSRF, CORS, rate limiting and security-header behaviour explicit in routes or middleware.

## Validation and errors

Separate three kinds of failure:

1. **Transport validation** — malformed or missing request values; normally `422`.
2. **Business rejection** — a valid request that cannot be performed; use the project exception/response convention appropriate to the endpoint.
3. **Infrastructure failure** — database, network or timeout failure; log internal detail and return a safe public error.

Do not use exceptions for normal branching when a nullable result or explicit result object communicates the outcome more clearly.

## Site and ownership scoping

Every query or mutation involving tenant-owned data must include the current site scope. A record ID alone is not sufficient.

- Pass `siteId` explicitly into repositories.
- Verify ownership at the repository or application boundary before mutation.
- Never rely on a preceding UI request to guarantee scope.
- Add tests proving a record from another site cannot be read or changed.

## Actions and services

- Give each class one coherent reason to change.
- Inject collaborators; do not instantiate repositories or external clients inside business methods.
- Keep orchestration in actions and reusable rules in focused services/policies.
- Avoid hidden global state except for established framework context adapters at the application edge.
- Make side effects visible in method names and tests.
- Use transactions around a complete consistency boundary.

## Repositories and persistence

- Keep query construction inside repositories.
- Prefer purpose-specific methods over generic `findBy(array $criteria)` calls scattered across the application.
- Avoid N+1 queries by loading relationships required by the use case.
- Return only the data required by the caller, but do not prematurely introduce duplicate query methods for trivial projections.
- Keep schema defaults, model casts and application defaults aligned.
- Never run migrations or seeders from web request code.

## DTOs, resources and arrays

Use DTOs for stable internal contracts and resources/presenters for external contracts.

Associative arrays are acceptable for small, local structures, but introduce a DTO when:

- the same shape crosses more than one class boundary;
- keys are repeatedly accessed as string literals;
- the payload has validation or normalisation rules;
- static analysis cannot describe the shape reliably;
- the structure is part of an important business operation.

Resources must not trigger additional database queries.

## Interfaces and abstractions

Introduce an interface when it creates a real substitution boundary, such as:

- an external provider;
- infrastructure with multiple implementations;
- a domain/application contract consumed independently of implementation;
- a test seam where replacing infrastructure is valuable.

Do not create an interface for every class by reflex. An interface with one implementation is still justified when it protects an important boundary; otherwise it can add ceremony without flexibility.

## Events

Events should be named as completed facts and dispatched after the relevant state change succeeds.

Use events for secondary reactions such as analytics, notifications or projections. Do not hide core consistency requirements in optional listeners.

## Security

- Escape untrusted content at the output boundary appropriate to HTML, attributes, URLs or JSON.
- Use parameterised queries through the persistence layer.
- Apply CSRF protection to session-authenticated mutations.
- Rate-limit public write or abuse-prone endpoints.
- Avoid logging secrets, access tokens, full payment details or unnecessary personal data.
- Return generic messages for unexpected failures while retaining actionable structured logs.

## Logging and observability

Log decisions and failures that help diagnose behaviour, not every method entry.

Useful context usually includes:

- operation name;
- site ID;
- relevant record IDs;
- authenticated actor ID where appropriate;
- provider/request correlation ID;
- exception class and safe diagnostic context.

Never use logs as a substitute for returning or handling an error correctly.

## Tests

Every behavioural change should have tests at the cheapest useful level.

- Unit-test branching, orchestration and domain policies.
- Use repository/integration tests for non-trivial persistence queries.
- Use functional tests for routes, middleware, validation and response contracts.
- Test the failure paths, not only the happy path.
- For site-owned data, include a cross-site rejection test.
- Avoid asserting private implementation details when the observable contract is sufficient.
- Mock external boundaries and slow infrastructure, not simple value objects.

A bug fix should normally include a test that fails before the fix and passes afterward.

## Review checklist

Before requesting review, verify:

- the class is in the correct layer;
- names describe intent;
- tenant and ownership scope is enforced;
- inputs are validated and outputs are mapped deliberately;
- errors are safe and status codes are correct;
- transactions cover the full consistency boundary;
- tests cover success, rejection and failure paths;
- API and architecture documentation has been updated where required.