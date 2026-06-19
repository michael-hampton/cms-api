# Project Documentation

This directory contains the technical and contributor documentation for the CMS API.

## API documentation

- [Public Content API](public-content-api.md) — public content documents, regional content, viewer state, likes, views, comments, authentication, errors and response conventions.
- [Public Directory V2](public-directory-v2.md) — public author, category and tag directory pages and endpoints.

## Engineering documentation

- [Codebase Structure](codebase-structure.md) — responsibility and dependency boundaries for the main application directories.
- [Coding Standards](coding-standards.md) — project-level PHP, API, persistence, security and testing expectations.
- [Patterns and Conventions](patterns-and-conventions.md) — the architectural patterns used throughout the system and guidance on choosing between them.
- [Subscription account](subscription-account.md) — account display-state contracts, token authentication, payment recovery, and Stripe billing responsibilities.

## Keeping documentation current

Documentation is part of the change, not a follow-up task. Update the relevant files when a change:

- adds, removes or changes an endpoint;
- changes a request or response contract;
- introduces a new architectural convention;
- moves responsibility between controllers, actions, services or repositories;
- changes authentication, authorisation, tenancy, rate limiting or error behaviour.

Examples should describe current behaviour and use stable placeholder values rather than production data.
