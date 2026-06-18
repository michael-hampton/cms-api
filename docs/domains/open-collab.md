# OpenCollab Domain

OpenCollab covers contributor access, invitations, onboarding, profiles, briefs, submissions, moderation, risk, contracts, payments, disputes and contributor communications.

## Main locations

- `src/Controllers/OpenCollab`
- `src/Services/OpenCollab`
- `src/Repositories/OpenCollab`
- `src/Requests/OpenCollab`
- `src/Resources/OpenCollab`
- `src/ViewModels/OpenCollab`
- `src/Events/OpenCollab` and related member events

## Architecture rules

Business workflows belong in services: invitation acceptance/resend, contributor requests, onboarding progression, brief assignment, moderation transitions, disputes, approvals and payouts.

Actions are appropriate only for narrow jobs such as bulk imports, exports, batch status changes or synchronising one OpenCollab resource.

Repositories own site-scoped persistence only. They must not decide invitation eligibility, onboarding completion, moderation outcomes or payment rules.

Extract independently changing rules into collaborators, for example:

- authorisation and access policies;
- invitation/onboarding state machines;
- profile completion calculators;
- moderation governance checks;
- risk scoring and priority calculations;
- deadline/SLA calculators;
- payout and reward calculations.

## Site and contributor access

Every lookup must be scoped to the current site where the record is site-owned. Contributor membership and site access are different concepts; do not infer one from the other.

Controllers and services must not accept a user ID from the request when authenticated identity is available through an injected auth abstraction.

## Workflow writes

Approval, rejection, invitation acceptance, onboarding completion, assignment, moderation and payout flows normally perform multiple writes and must be transactional. The transaction must return the resulting entity or DTO.

Critical workflow failures throw and roll back. Notifications, analytics and audit projections should use real events with listeners.

## Dynamic fields

Dynamic profile, onboarding and contributor-request fields must use shared definitions, field view models and validation behaviour. Do not duplicate field markup or reimplement field interpretation in controllers.

Database columns may coexist with dynamic fields, but ownership must be explicit: canonical system fields remain model columns; configurable extension fields use custom-field definitions and values. Do not maintain the same value independently in both places.

## Moderation and statuses

Use enums and state machines for invitation, request, submission, moderation, escalation, dispute and payout statuses/actions. Controllers must not assign status strings directly.

## Testing

Service unit tests use Mockery and cover allowed/rejected transitions, site isolation, transaction usage, emitted events and rollback. Mocked models use `makePartial()` where model behaviour is required. Functional tests cover routes, permissions, validation and response resources.