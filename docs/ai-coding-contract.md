# AI Coding Contract

This contract applies to AI-generated and AI-modified PHP code in this repository. Cursor loads the enforceable version from `.cursor/rules/00-architecture-contract.mdc`.

## Mandatory architecture

1. Services orchestrate business workflows and rules.
2. Repositories handle persistence only.
3. Calculations and decisions live in dedicated collaborators.
4. Events handle genuine cross-cutting side effects.
5. Transactions wrap all multi-step writes.
6. Infrastructure does not leak into services.

If something can change independently, it must not live inside the workflow service.

## Actions

Actions are reserved for single-purpose operations such as CSV export, bulk import, bulk status updates, external synchronisation and artefact generation.

Actions are not the default use-case layer. Business workflows, state transitions, eligibility rules and multi-step orchestration belong in services.

## Dependency injection

All dependencies are constructor-injected. Services must not use facades, sessions, globals, static model calls or SDK singletons. The only permitted static infrastructure call in service code is `Database::transaction()`.

External integrations must sit behind injected project-owned interfaces or gateways.

## Transactions

Any method performing two or more database writes must use `Database::transaction()`.

- all writes are inside the transaction;
- the callback returns a value;
- critical errors throw and roll back;
- no related writes occur outside the transaction.

## Events

Use events only when a real listener performs a real side effect. Do not add speculative events. Services emit events instead of directly calling other services for cross-cutting side effects.

Service unit tests verify event emission, not framework listener execution.

## Enums

Statuses, types and actions use PHP enums rather than magic strings. Input conversion uses `from()`, explicit `tryFrom()` handling or an enum-aware validation rule.

## Errors

Critical money, reward, order and access flows throw and roll back. Non-critical analytics and tracking failures may be caught and logged. Empty catches and silent continuation after critical failure are forbidden.

## Testing

- All unit-test dependency mocks use Mockery.
- Mock only real classes or interfaces.
- Use `Mockery::mock(ClassName::class)` explicitly.
- Model mocks use `makePartial()` when real model behaviour is required.
- Static mocking is forbidden; refactor static dependencies behind injectable abstractions.
- Every test has at least one meaningful assertion.
- Tests verify behaviour: return values, state/side effects or dependency interactions.
- Tests are isolated, deterministic and make no real external calls.
- Relevant service tests cover transaction usage, event emission and failure rollback.
- Do not test framework internals.

## AI behaviour

AI must suggest relevant improvements, but must not invent dependencies, configuration, requirements or behaviour. Missing context must be requested before code is written. Correctness and clarity take priority over brevity.