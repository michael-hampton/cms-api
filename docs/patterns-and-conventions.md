# Patterns and Conventions

The project uses a pragmatic layered architecture. Patterns exist to make responsibilities and change boundaries obvious; they are not targets to maximise. Use the smallest pattern that keeps the use case clear, testable and correctly scoped.

## Action pattern

An action represents one application use case and usually exposes a single public method such as `execute()`.

Use an action when an endpoint, message handler or job must coordinate several collaborators or enforce an operation-level boundary.

```php
final class PublishPageAction
{
    public function __construct(
        private readonly PageRepository $pages,
        private readonly PublicationPolicy $policy,
        private readonly TransactionManager $transactions,
    ) {
    }

    public function execute(int $siteId, int $pageId, int $actorId): Page
    {
        return $this->transactions->run(function () use ($siteId, $pageId, $actorId): Page {
            $page = $this->pages->findForUpdate($siteId, $pageId);
            $this->policy->assertCanPublish($page, $actorId);

            $page->publish();
            $this->pages->save($page);

            return $page;
        });
    }
}
```

Actions may orchestrate, but should not become miscellaneous containers for every rule in the feature. Extract reusable policies, resolvers or services when behaviour has an independent meaning.

## Service pattern

A service provides a focused capability used by one or more application flows.

Good service names describe the responsibility: `ArticleAccessService`, `PublicPageViewRecorder`, `ResolvedGeoQueryParser`.

Split a service when it has unrelated reasons to change, not merely because it has reached an arbitrary line count.

## Repository pattern

Repositories isolate persistence and encode meaningful query rules.

```php
interface PublishedPageRepository
{
    public function findBySlug(int $siteId, string $slug): ?Page;
}
```

A repository method should make tenant, publication and ownership constraints visible in its signature or name. Callers should not need to remember a hidden extra `where` clause.

Repositories are not generic CRUD wrappers. A precise repository API is preferable to exposing an unrestricted query builder across the application.

## Request -> DTO -> Action pattern

For write operations, the preferred flow is:

```text
HTTP request -> Request validation -> DTO -> Action -> Repository/Service
```

The request validates transport shape. The DTO gives the operation a typed input. The action enforces business rules and coordinates side effects.

This keeps HTTP-specific field handling out of reusable business code.

## Resource/presenter pattern

Resources and presenters convert internal data into stable external documents.

Use a resource when serialising a model or DTO for an API. Use a presenter when composing a richer read model from several sources or when the same presentation is shared by HTML/API consumers.

The mapping layer should be deterministic and must not perform hidden database queries.

## Policy and resolver patterns

Use a policy for a business decision such as whether a member can access, approve or mutate a record. Use a resolver when selecting one value or strategy from context, such as paywall mode, region or implementation.

Prefer explicit result data when callers need the reason for a decision:

```php
[
    'can_view' => false,
    'reason' => 'subscription_required',
]
```

A boolean alone is insufficient when the API or UI must explain the outcome.

## Strategy pattern

Use strategies when behaviour varies by type and each variation has meaningful logic. Register strategies behind a shared interface and resolve them by enum/type rather than building a growing conditional chain.

Do not use a strategy hierarchy for two trivial branches that are unlikely to grow.

## Composition pattern

Public content uses composition to assemble components and regions from focused providers. This allows new components to be added without turning the main content action into a switchboard for every page type.

A composition provider should:

- declare when it applies;
- consume an explicit context/DTO;
- return a predictable component contract;
- avoid mutating unrelated state;
- remain independently testable.

## Middleware pipeline

Cross-cutting HTTP behaviour belongs in middleware. Route groups make the pipeline reviewable and consistent.

Examples include:

- authentication and member requirements;
- CSRF;
- CORS;
- security headers;
- public rate limiting;
- rollout gates;
- query validation;
- latency measurement.

Do not move endpoint-specific business eligibility into middleware merely to make a controller shorter.

## Event/listener pattern

Events describe completed facts. Listeners react to those facts for secondary concerns.

```text
State change succeeds -> event dispatched -> analytics/notification listener reacts
```

Dispatch after successful persistence, and after commit where listener visibility of committed data matters.

Avoid event chains where the primary use case only succeeds if an undocumented listener happens to run.

## State machine pattern

Use a state machine for workflows with explicit statuses and controlled transitions, such as invitations, moderation, disputes or onboarding.

A state machine should centralise:

- allowed transitions;
- transition guards;
- resulting status;
- transition-specific side effects that are mandatory for consistency.

Do not scatter status assignments across controllers and repositories.

## Adapter pattern for external systems

Wrap external SDKs and providers behind project-owned interfaces/services. Application code should depend on the capability required, not the vendor client.

```php
interface PaymentGateway
{
    public function refund(RefundRequest $request): RefundResult;
}
```

Adapters translate provider errors and data into project-level exceptions and DTOs. This prevents vendor contracts leaking throughout the codebase and keeps tests deterministic.

## Transaction boundary pattern

Transactions wrap complete business operations. The action/service that understands the consistency boundary should own the transaction.

Avoid opening separate transactions in several repositories for one workflow. That produces individually successful writes without an atomic business result.

External network calls require deliberate handling because database transactions cannot roll them back. Prefer idempotency keys, outbox/jobs, or a clearly recoverable workflow.

## Null object and explicit result objects

Use `null` for a simple absence such as “published page not found.” Use an explicit result object/DTO when the caller needs several outcome values, for example `recorded`, `duplicate`, `limited` and `retryAfter`.

Avoid undocumented arrays for complex results. If a result shape is shared or growing, promote it to a DTO.

## Dependency inversion

High-level business behaviour should depend on project-owned contracts where substitution matters. Infrastructure implements those contracts.

Good interface boundaries include payment providers, mail delivery, file storage, clocks, queues and third-party APIs.

Do not add an interface solely to satisfy a rule that every class needs one. The interface should protect a meaningful boundary.

## SOLID application

- **Single Responsibility:** organise classes around one coherent capability or use case.
- **Open/Closed:** add providers/strategies for genuine variation rather than repeatedly editing a central conditional.
- **Liskov Substitution:** implementations must honour the semantics of their interfaces, not merely their method signatures.
- **Interface Segregation:** expose small capability-specific contracts instead of broad service interfaces.
- **Dependency Inversion:** inject abstractions around volatile infrastructure and important boundaries.

SOLID is a design aid, not a reason to turn a three-line operation into twelve files.

## Choosing the right layer

Ask these questions in order:

1. Is it purely HTTP input/output behaviour? Put it in a request, controller or middleware.
2. Is it one application operation? Use an action.
3. Is it reusable business behaviour or a policy? Use a focused service/policy/resolver.
4. Is it persistence? Use a repository.
5. Is it a stable data contract? Use a DTO.
6. Is it public serialisation? Use a resource/presenter.
7. Is it a completed fact with secondary reactions? Use an event.
8. Is it provider-specific infrastructure? Use an adapter behind a project-owned contract.

When code appears to fit several layers, choose the layer that owns the decision, then keep transport and persistence details at the edges.