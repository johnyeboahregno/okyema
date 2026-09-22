# Okyema — Test Strategy

## Layers

| Layer | Covers | Example |
|-------|--------|---------|
| Unit | Pure domain rules, normalisation, money, conflict detection | `MoneyMathTest`, `GoogleEventNormaliserTest`, `CalendarConflictsTest`, `MeetingExtractorTest`, `ReceiptNamingTest`, `ItineraryExtractorTest` |
| Feature | API contracts, authorisation, context isolation, idempotency | `AuthTest`, `AgendaTest`, `MeetingTest`, `ActionTest`, `ReceiptTest`, `ExpenseTest`, `InboxTest`, `TravelDocumentTest`, `AutomationTest` |
| E2E | Critical journeys | Playwright (`npm run test:e2e`), on demand |

## Conventions

- SQLite `:memory:` for tests (`phpunit.xml`); no database server needed.
- AI is disabled in tests (`AI_ENABLED=false`) — suites assert the
  deterministic fallback, never a live, billable call.
- Connector contracts are tested with fake adapters and recorded fixtures;
  secrets are never present in fixtures.
- Every new feature ships with its tests as part of the implementation.

## Critical journeys (Playwright, on demand)

1. Sign in and select a context.
2. Connect a Google account (mocked) and view merged calendar data.
3. Open a meeting and generate a sourced brief.
4. Convert a decision into an action.
5. Capture a receipt, verify extraction, file it to the correct Drive folder.
6. Draft a follow-up and approve sending.
7. Disconnect a connector and verify revocation.
8. Work offline, capture a note/receipt, sync later.
9. Prove Regno data never leaks into Launchpad or Personal results.

## Verification defaults

`php -l <file>` → `php vendor/bin/pint --test` → focused `php vendor/bin/pest`.
Browser/e2e runs happen only on explicit request or in CI.
