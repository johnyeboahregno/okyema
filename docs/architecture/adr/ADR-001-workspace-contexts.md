# ADR-001 — Workspace contexts (REGNO / LAUNCHPAD / PERSONAL)

- **Status:** Accepted
- **Date:** 2026-09-22

## Context

Okyema spans three information boundaries (Regno, Launchpad, Personal).
SIKA has no such boundary — every resource is scoped to a single user only.

## Decision

Every stored object carries a `workspace_id` unless it is explicitly global
(e.g. `users`, `workspace_contexts` themselves). The three default contexts
are seeded as `REGNO`, `LAUNCHPAD`, `PERSONAL`. Domain queries, connector
actions and AI retrieval must all filter by the active workspace. The UI
shows the active context at all times; an "All contexts" view exists only
for the owner.

## Consequences

- Cross-context leakage becomes a testable invariant (`context-isolation`
  tests).
- Connector actions must state their target workspace; never mix content.
- Multi-user and delegated access can be layered on later via `memberships`.
