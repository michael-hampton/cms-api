# OpenCollab Terms Integration Checklist

## Routes

Register the following authenticated API routes:

- `GET /api/{site}/open-collab/admin/terms`
- `GET /api/{site}/open-collab/admin/terms/latest`
- `POST /api/{site}/open-collab/admin/terms`
- `GET /api/{site}/open-collab/admin/terms/{id}`
- `PUT /api/{site}/open-collab/admin/terms/{id}`
- `POST /api/{site}/open-collab/admin/terms/{id}/publish`
- `POST /api/{site}/open-collab/admin/terms/from-document`
- `GET /api/{site}/open-collab/admin/terms-evidence/{id}`
- `GET /api/{site}/open-collab/onboarding/terms`
- `POST /api/{site}/open-collab/onboarding/terms`

Register the admin page route:

- `GET /{site}/open-collab/admin/terms`

## Controller imports

- `AdminTermsController`
- `AdminTermsEvidenceController`
- `AdminTermsPageController`
- `TermsOnboardingController`

## Onboarding service

Add `terms` as the first canonical onboarding step.

The step is pending when:

```php
$termsRequirementService->requiresAcceptance($userId, $siteId)
```

Suggested pending metadata:

```php
[
    'step' => 'terms',
    'reason' => 'Accept the current OpenCollab Terms and Conditions.',
    'meta' => [
        'required_version_id' => $requiredTerms?->id,
        'semantic_version' => $requiredTerms?->semantic_version,
        'is_material_change' => $requiredTerms?->is_material_change,
    ],
]
```

## Onboarding view

Add this branch before the profile step in `views/open-collab/onboarding/index.php`:

```php
<?php if ($vm->currentStepName() === 'terms'): ?>
    @include('open-collab/onboarding/partials/terms', ['terms' => $terms])
<?php elseif ($vm->currentStepName() === 'profile' && $profileStep): ?>
```

## RBAC

Add permissions:

- `terms.view`
- `terms.create`
- `terms.edit`
- `terms.publish`
- `terms.archive`
- `terms.accept`
- `terms.evidence.view`

Recommended role assignments:

- admin: all permissions
- legal: view, create, edit, publish, archive, evidence
- creator: view and accept

## Verification

Run:

```bash
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/psalm
```

Expected initial failures before integration:

- unregistered routes
- missing RBAC permissions
- terms missing from onboarding pending-step order
- onboarding main view missing terms partial inclusion
