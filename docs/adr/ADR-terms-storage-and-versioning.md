# ADR: OpenCollab Terms Storage and Versioning

## Status

Accepted

## Context

OpenCollab must prove which Terms and Conditions a creator accepted before submitting content. Published terms must remain retrievable after later edits, and material changes must trigger re-acceptance while editorial changes must not.

Candidate storage options considered:

- Git-versioned Markdown
- Database-backed versions
- Content-addressed blob storage
- Confluence pinned versions

## Decision

Use immutable database-backed terms versions as the application source of truth.

Each published version stores:

- semantic version
- source content and format
- rendered content and format
- SHA-256 hash of the rendered content
- publication metadata
- material-change flag
- change summary
- optional source document references from `open_collab_documents`

Published and archived records are immutable. Any content change requires a new draft version.

## Version identity

A terms version is identified by:

1. database monotonic ID for internal references
2. semantic version for human-readable communication
3. SHA-256 hash of the exact rendered snapshot shown to creators

The semantic version and rendered hash are unique per site.

## Material versus editorial changes

Material changes affect contractual rights or obligations, including:

- ownership
- licence grant
- revenue share
- right of set-off
- moderation powers
- termination rights

Material versions require creator re-acceptance.

Editorial changes include spelling, formatting, broken links, and non-substantive clarification. They create a new immutable version but do not replace the latest material version as the acceptance requirement.

## Publication workflow

1. Create or import a draft.
2. Review source content and change summary.
3. Classify the change as material or editorial.
4. Render the final creator-facing snapshot.
5. Generate the SHA-256 hash from the rendered snapshot.
6. Publish the new version.
7. Archive the previous published version.
8. Prevent all later mutation of published or archived versions.

## Acceptance evidence

Each creator acceptance records:

- site ID
- user ID
- terms version ID
- rendered hash
- acceptance timestamp
- IP address
- user agent
- acceptance source

Historical evidence is reconstructed from the acceptance and immutable terms snapshot. Hash verification confirms that the stored rendered content matches what was accepted.

## Consequences

### Positive

- no runtime dependency on Git or Confluence
- exact accepted content remains retrievable
- simple site-scoped queries
- material re-acceptance is explicit
- uploaded documents reuse the existing OpenCollab documents infrastructure

### Negative

- application-level immutability must be enforced
- legal/admin users require publication tooling
- rendered output changes must create a new version even when source wording is unchanged

## Rejected alternatives

### Git-versioned Markdown

Useful for developer ownership but awkward for legal users and runtime retrieval.

### Content-addressed blob store

Strong immutability but unnecessary operational complexity for the current scale.

### Confluence pinned versions

Convenient for drafting but unsuitable as the authoritative runtime and dispute-resolution store.
