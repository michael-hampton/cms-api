# Engineering Handbook

This document defines how application code is structured, reviewed and tested. It is written for engineers and AI coding tools working in this repository.

## 1. Architectural model

The application uses a service-led architecture with explicit boundaries:

```text
HTTP / CLI / Queue
        |
        v
Controller / Command / Handler
        |
        v
Request validation + DTO construction
        |
        v
Service (workflow + rules + transaction boundary)
   |          |           |            |
   v          v           v            v
Repository  Policy     Calculator    Gateway
   |                      /Resolver      |
   v                                     v
Database                           External provider
        
Service result -> Resource / ViewModel -> Response
Service event  -> Listener -> cross-cutting side effect
```

The service owns the business workflow. Everything that can vary independently is extracted behind an injected collaborator.

## 2. Controllers

Controllers are adapters, not workflow owners.

A controller may:

- receive route parameters and a request object;
- call request validation;
- obtain trusted authenticated/site context;
- construct a DTO;
- call one service or one narrow action;
- map known exceptions to HTTP responses;
- return a resource or view model.

A controller must not:

- perform repository queries;
- call models statically;
- open transactions;
- decide business eligibility;
- calculate totals, discounts, deadlines or status transitions;
- dispatch side effects directly;
- build large response arrays.

## 3. Services

Services own business workflows and rules.

A service may:

- validate business invariants;
- coordinate repositories;
- invoke policies, calculators, resolvers, state machines and gateways;
- own the transaction boundary;
- emit domain events;
- return a model, DTO or value object representing the result.

A service must not:

- format API or template output;
- build complex persistence queries;
- access sessions, request globals, facades or container singletons;
- call models statically;
- use static database access;
- absorb calculations that can evolve separately;
- call another service merely to send mail, analytics or notifications.

### Service split test

Extract a collaborator when at least one is true:

- the rule has a separate reason to change;
- the logic has its own failure mode;
- it can be tested independently with meaningful inputs and outputs;
- more than one workflow needs it;
- replacing the implementation should not require rewriting the workflow;
- the code answers a distinct question such as “can this happen?”, “how much?”, “which strategy?”, or “what is the next state?”.

Do not split code simply to reduce line count.

## 4. Actions

Actions are not the general business-workflow layer.

Use an action for a narrow single-purpose operation such as:

- export CSV;
- bulk import;
- bulk status update;
- sync one external resource;
- clone one record tree;
- rebuild one projection;
- generate one file or artefact;
- run one maintenance command.

An action may coordinate repositories and services, but it must remain narrow and task-shaped. If the operation contains reusable business rules or lifecycle transitions, those rules belong in services or dedicated collaborators.

## 5. Repositories

Repositories own persistence only.

They may:

- query;
- insert/update/delete;
- eager-load required relations;
- enforce tenant/site scope at the persistence boundary;
- return models, collections or persistence DTOs.

They must not:

- decide business policy;
- emit events;
- call external providers;
- format presentation data;
- open workflow transactions;
- orchestrate multiple business steps.

Repository methods should expose intent rather than implementation detail:

```php
findPendingForSite(int $siteId, int $requestId): ?ContributorRequest
findActiveByMemberAndPlan(int $memberId, int $planId): ?Subscription
markApproved(ContributorRequest $request, int $actorId): ContributorRequest
```

Avoid generic methods that force every caller to reconstruct site scope and status rules.

## 6. Database and transactions

`App\Framework\Database\Database` is injected through the constructor and stored as `$this->database`.

Never use static database access in services, actions, controllers, handlers or jobs.

A method performing two or more related database writes must use:

```php
return $this->database->transaction(function () use ($input): ResultType {
    // all related writes
    return $result;
});
```

Rules:

- inject `Database` via the constructor;
- keep every related write inside the callback;
- return the workflow result from the callback;
- return the transaction result from the service method;
- let critical exceptions escape;
- do not manually begin, commit or roll back;
- do not open the transaction inside a repository;
- do not split one workflow across multiple independent transactions unless the workflow is intentionally resumable and documented.

### Events and commit timing

An event describing committed state must be dispatched only when listeners can safely observe that state. Use the injected database instance’s commit hooks where required by the workflow.

Do not use post-commit hooks to hide a critical write that belongs in the transaction.

## 7. Decisions and calculations

Heavy or independently changing logic belongs in dedicated collaborators.

Use:

- `*Policy` for allow/deny or eligibility decisions;
- `*Calculator` for numerical results;
- `*Resolver` for selecting a mode, strategy or implementation;
- `*StateMachine` for legal transitions;
- `*Strategy` for interchangeable algorithms;
- `*Allocator` for distributing amounts or resources;
- `*QuoteBuilder` for producing a structured commercial result;
- `*Generator` for deterministic identifiers or artefacts.

A workflow service should read like orchestration, not like a spreadsheet implemented in PHP.

## 8. DTOs and value objects

Use DTOs at workflow boundaries and value objects for meaningful immutable concepts.

Introduce one when:

- an array shape crosses class boundaries;
- keys are repeated in several places;
- validation or normalisation exists;
- the data has domain meaning;
- static analysis cannot reliably describe the structure.

DTOs do not query repositories, read sessions or call providers.

## 9. Enums and states

Statuses, types and actions use PHP enums.

Do not introduce new magic strings for:

- lifecycle status;
- payment/refund state;
- moderation action;
- invitation state;
- page type;
- subscription interval/type;
- provider event category after the adapter boundary.

Convert input through `from()`, explicit `tryFrom()` handling or enum-aware validation. Do not compare raw request strings throughout the workflow.

## 10. Events and listeners

Use events only for real cross-cutting side effects.

Valid examples:

- notifications;
- analytics;
- audit projections;
- cache invalidation;
- search indexing;
- asynchronous fulfilment;
- downstream synchronisation.

Rules:

- every event has at least one working listener;
- event names describe completed facts;
- do not create events for possible future use;
- the core workflow must remain correct if a non-critical listener fails;
- services do not directly call another service merely to perform a side effect;
- critical state changes remain in the service transaction.

## 11. External integrations

Stripe, email, storage, queues, print providers and APIs sit behind injected project-owned interfaces or gateways.

Workflow services do not depend on raw provider SDK clients.

Adapters are responsible for:

- request/response translation;
- provider-specific identifiers;
- exception translation;
- idempotency support;
- provider event parsing;
- shielding the rest of the application from SDK changes.

## 12. Error handling

Critical flows throw and roll back:

- money;
- refunds;
- rewards;
- orders;
- subscription access;
- publishing state;
- moderation decisions;
- contributor access.

Non-critical flows may catch and log:

- analytics;
- tracking;
- parity comparison;
- optional telemetry.

Empty catches are forbidden. Never silently continue after a critical failure.

Public responses must not expose raw SQL, provider messages, stack traces, secrets or sensitive identifiers.

## 13. Site and ownership scope

Every site-owned read and write must include site scope.

An ID alone is not authorisation.

Tests must include cross-site rejection for important reads and mutations. Authenticated identity comes from an injected abstraction at the application edge, not from client-supplied member/user IDs.

## 14. Resources and presentation

Resources, presenters and view models own output formatting.

Services return domain results. They do not build HTTP response arrays, HTML snippets or template-specific structures.

Resources must not trigger hidden queries.

## 15. Unit testing with Mockery

All unit-test mocks use Mockery and explicit real class/interface names:

```php
$repository = Mockery::mock(ContributorRequestRepository::class);
$database = Mockery::mock(Database::class);
```

Rules:

- no `stdClass` mocks;
- no arrays or anonymous objects as fake collaborators;
- model mocks use `makePartial()` when real model behaviour is required;
- partial mocks are used only when orchestration genuinely needs real behaviour;
- static mocking is forbidden;
- external integrations are mocked at gateway boundaries;
- tests are deterministic and isolated;
- every test has at least one meaningful assertion.

A meaningful test verifies one or more of:

- return value;
- thrown exception;
- repository write/read interaction;
- event emission;
- transaction use;
- collaborator decision;
- resulting model/value-object state.

### Transaction tests

A service test must expect `$database->transaction()` and execute the supplied callback. It then asserts the workflow result and all relevant writes/events.

Do not test the database class internals in a service unit test.

### Event tests

Assert that the service emits the event. Test listeners separately. Do not boot the framework merely to prove listener execution from a service test.

### Rollback tests

For critical workflows, make a repository or gateway throw inside the transaction callback and assert that the exception escapes. The service test proves that no later write/event occurs. The database component’s own tests prove rollback mechanics.

## 16. Review checklist

Before merge:

- Is the workflow in a service rather than a controller/action/repository?
- Is the action genuinely single-purpose?
- Are queries and writes confined to repositories?
- Is `Database` injected and used through `$this->database`?
- Are all multi-write operations transactional?
- Are independent calculations/decisions extracted?
- Are statuses/types/actions enums?
- Are side effects events with real listeners?
- Are critical failures allowed to throw?
- Is site/ownership scope explicit?
- Are API/template formats handled by resources/view models?
- Do unit tests use Mockery and meaningful assertions?
- Are external integrations mocked at project-owned boundaries?
- Has domain documentation been updated?

## 17. AI coding behaviour

AI tools must inspect the existing code before adding dependencies, events, enums, configuration or provider behaviour.

AI tools should suggest genuine improvements discovered while working, but must not invent missing requirements. When a required rule cannot be established from code or documentation, ask before writing behaviour that changes the domain.