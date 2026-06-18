# Codebase Structure

The codebase is organised by technical responsibility, with feature-specific subdirectories used where a domain is large enough to justify them. The goal is predictable ownership: HTTP concerns stay at the edge, use-case orchestration lives in actions, reusable business behaviour lives in services, and persistence is isolated behind repositories.

## Main application areas

### `src/Controllers`

Controllers are HTTP adapters. They should:

- accept route parameters and framework request objects;
- invoke request validation;
- obtain trusted context such as the current site or authenticated member;
- call one action or a small coordinating service;
- convert known outcomes into HTTP responses.

Controllers should not contain SQL/query-builder code, build complex domain objects, or become the permanent home of business rules.

### `src/Requests`

Request classes define input validation and validation messages. Use them for endpoint-specific input rather than validating arrays manually inside controllers.

A request class owns transport validation, not business eligibility. For example, it can validate that an email has the correct format, while a service decides whether that email is already invited.

### `src/Actions`

Actions represent application use cases such as retrieving a public content document or submitting a contributor request.

An action may coordinate repositories, domain services and DTOs. It should expose a clear method such as `execute()` and remain focused on one user-visible operation.

Use an action when the operation:

- crosses multiple collaborators;
- has a meaningful transactional or authorisation boundary;
- represents a command/query initiated by an endpoint, handler or job;
- would otherwise make a controller too knowledgeable.

### `src/Services`

Services contain reusable business capabilities and policies. They are appropriate for logic shared by multiple actions, controllers, handlers or jobs.

Prefer cohesive, purpose-specific services over broad manager classes. A class named after a precise responsibility is easier to test and replace than one named `Helper`, `Manager` or `Utils`.

Feature subdirectories such as `Services/PublicContent/Composition`, `Paywall`, `Parity` and `Views` group related capabilities without collapsing them into a single large service.

### `src/Repositories`

Repositories own persistence queries and site-scoping rules. They return models, collections, DTOs or purpose-specific result structures needed by the application layer.

Repository methods should describe intent, for example:

```php
findCompletePublishedBySlug(int $siteId, string $slug)
```

Avoid leaking query-builder chains into controllers or services. Avoid generic repository methods that force callers to reconstruct the real query rules repeatedly.

### `src/DTO`

DTOs carry structured data across boundaries. Use them to replace ambiguous arrays when a payload has a stable meaning, especially for commands, resolved context and composed documents.

DTOs should validate or normalise their own construction where practical, but should not acquire repositories or perform I/O.

### `src/Resources`

Resources map internal DTOs/models into public response contracts. They protect API consumers from persistence details and provide one canonical place to evolve output shape.

Do not return models directly from public endpoints.

### `src/Models`

Models represent persisted state and relationships. Keep them focused on state, relationships, casts and small invariants. Large workflows and external integrations belong in actions or services.

### `src/Middleware`

Middleware handles cross-cutting HTTP concerns such as authentication, CSRF, CORS, security headers, rate limiting, rollout checks and latency measurement.

Middleware should be composable and should not duplicate endpoint business logic.

### `src/Events` and listeners

Events announce completed facts, for example that a member liked a page. Listeners perform secondary work that should not obscure the primary use case.

Do not use events to hide a mandatory step required for the operation to be correct. Mandatory work belongs in the action/service or transaction.

### `src/Enums`

Enums define closed sets of meaningful values. Prefer them over scattered string literals where the framework and persistence layer support them cleanly.

### `src/routes`

Route files group endpoints by feature. Keep middleware visible at the route/group boundary so security behaviour can be reviewed without tracing controller internals.

### `src/Database`

Migrations own schema evolution. Seeders create deterministic baseline or development data. Application code must not run seeders as part of normal request handling.

### `src/Tests`

Tests should mirror production responsibilities:

- unit tests for actions, services, policies, resolvers and presenters;
- repository/integration tests for important queries and site scoping;
- functional tests for routes, middleware, validation, status codes and serialised response contracts.

## Dependency direction

The preferred flow is:

```text
Route -> Middleware -> Controller -> Request/Action -> Service/Repository -> Model
                                           |
                                           +-> DTO -> Resource -> Response
```

Dependencies should point inward toward business behaviour. Repositories and infrastructure can be injected into actions/services; domain rules should not depend on controllers or framework response classes.

## Feature placement example

A new public-content reaction would normally involve:

1. a route in `src/routes/public-content-api.php`;
2. request validation under `Requests/PublicContent` when a body is accepted;
3. a thin controller method;
4. an action or focused service for the use case;
5. a repository method for persistence;
6. an event after the state change when secondary processing is useful;
7. a resource/DTO update if the public response contract changes;
8. matching unit and functional tests;
9. an update to `docs/public-content-api.md`.

## Boundaries to protect

- Site context must be passed into repository lookups and mutations.
- Authentication context must come from trusted framework services, never request-supplied member IDs.
- External systems must be wrapped by services/interfaces rather than called throughout controllers.
- Public response contracts must be produced by resources/presenters, not model serialisation.
- Transactions belong around complete business operations, not individual arbitrary repository calls.