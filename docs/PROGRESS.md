# Okyema — Progress log

## Milestone 6 — Rules, briefing and hardening (2026-09-22)

**State:** in progress.

### Completed

- **Rules engine:** `automation_rules` + `automation_runs`; `RuleEngine` runs
  deterministic rules (`action_due_soon`, `morning_briefing`,
  `weekly_review`), records every run and honours the pause control.
- **Briefings:** `BriefingService` morning and weekly briefings that report
  facts (meetings, overdue actions, replies, receipts) and a factual summary.
- **UI complete:** every primary tab is now wired — Today, Timeline, Meetings,
  Actions, Inbox, Travel, Expenses, Files, People, Automations and Settings
  (profile + timezone editing, connector health and app version).
- **E2E specs:** `playwright.config.ts` (offline, self-contained) and
  `tests/e2e/critical-journeys.spec.ts` covering the runnable critical
  journeys; provider-dependent journeys are skipped until credentials exist.
- **Documentation:** threat model, data classification/retention, runbook,
  backup/restore, test strategy, deployment, release checklist and per-provider
  connector runbooks (Google, Microsoft, Slack, plus a plan for the rest).
- **Today briefing + accessibility:** the dashboard now returns a factual
  briefing, recent decisions and travel alerts; the shell gained visible
  focus-visible styles and `aria-current` on the active nav item.
- **Notion theme (light + dark):** both themes now match Notion — white/neutral
  surfaces, warm gray text, Notion blue (`#2383E2`) accent and Notion status
  colours — with flat components and no border radius or shadows. PWA chrome
  colour follows the active theme.
- **Tests:** 74 passing (238 assertions).

### Known limitations

- Live provider calls (OAuth flows, sync, send, upload) are guarded adapters;
  they need real credentials to be proven end-to-end.
- Playwright specs are written but not run here (on-demand via
  `npm run test:e2e`); a browser pass will be needed at release.

### Next work

Run the Playwright suite, exercise backup/restore against the VPS, and follow
the release checklist for the first tagged release.

---

## Milestone 5 — Travel, documents and knowledge (2026-09-22)

**State:** complete.

### Completed

- **Trips:** `trips` + `travel_segments`; `ItineraryExtractor` parses
  `Trip:` / `Dates:` / `Flight|Train|Hotel|Ground:` markers into a proposal
  the user confirms before saving (`TripService::saveItinerary`).
- **Documents:** `document_references` (soft-deleted, provider deep links) and
  permission-aware search scoped to the workspace.
- **Global search:** `SearchService` returns source-grounded results across
  meetings, actions, documents, people, conversations and trips.
- **API:** `/api/trips`, `/api/trips/extract`, `/api/trips/itinerary`,
  `/api/documents`, `/api/search`.
- **Tests:** 68 passing (215 assertions).

---

## Milestone 4 — Communications inbox (2026-09-22)

**State:** complete.

### Completed

- **People:** `people` + `provider_identities` with idempotent, reversible
  identity linking (`PeopleService::link`).
- **Inbox:** `conversations` + `message_references`; needs-reply detection
  (latest message inbound), VIP filter and per-person filtering.
- **Approval-gated send:** `drafts` + `approval_requests`; drafting never
  sends — the user explicitly approves, and only then does the connector
  (`MailConnector` / `ChatConnector`) attempt the send. Without a connected
  account the draft stays approved-but-unsent and the reason is reported.
- **API:** `/api/inbox`, `/api/inbox/{id}`, `/api/inbox/{id}/draft`,
  `/api/people`, `/api/approvals/{id}/approve|reject`; the dashboard reports
  messages needing a reply.
- **Tests:** 63 passing (194 assertions).

---

## Milestone 3 — Receipt and expense workflow (2026-09-22)

**State:** in progress.

### Completed

- **Models:** `expenses` (integer minor units + ISO currency), `receipts`
  (original path, content hash, Drive filing columns), `receipt_extractions`.
- **Original preservation:** `ReceiptStorage` stores the uploaded file
  byte-for-byte under the private `local` disk; derivatives are never mixed in.
- **Extraction:** `ReceiptScanner` (vision, OpenAI-compatible, best-effort)
  returns structured fields with confidence and never breaks capture — when
  unconfigured or it fails, the user enters fields by hand.
- **Confirmation:** `ReceiptService::confirm` validates merchant/total/date
  (money parsed with `MoneyMath`), creates the linked expense and files the
  receipt.
- **Duplicate detection:** content hash + merchant/date/amount similarity.
- **Drive filing (ADR-005):** `ReceiptNaming` produces the deterministic
  `Business Receipts/{Workspace}/{YYYY}/{YYYY-MM}/` folder and stable
  filename; `DriveFilesConnector` is the guarded `files.write` boundary —
  without a token it reports "not connected" and never fakes success.
- **Monthly views:** `ExpenseService` monthly totals, missing-receipt
  detection and CSV export.
- **API:** `/api/receipts` (capture/list/confirm), `/api/expenses` (monthly)
  and `/api/expenses/export`; the dashboard reports unprocessed receipts.
- **UI:** Expenses tab — camera/file capture, confirm form and monthly view.
- **Tests:** 55 passing (168 assertions).

### Known limitations

- Live OCR/Drive upload are not exercised (no credentials); both are guarded
  adapters with deterministic fallbacks.

### Next work

Milestone 4 — communications inbox.

---

## Milestone 2 — Meetings and actions (2026-09-22)

**State:** in progress.

### Completed

- **Meeting workspace model:** `meetings`, `meeting_participants`, `notes`
  (source_type: manual/transcript/import), `summaries`, `decisions`,
  `action_items`, `action_audits` (append-only status history) and
  `source_citations` (polymorphic, links decisions/actions to the note that
  produced them).
- **Deterministic extraction:** `MeetingExtractor` parses `Decision:` and
  `Action:` markers from notes (with `@owner`, `by YYYY-MM-DD` and
  `#priority`), and produces a factual, non-fabricated summary.
- **Regno AI path:** `MeetingAIService` calls the shared AI provider with a
  versioned JSON schema, sanitises strictly, and falls back to the
  deterministic extractor on any failure. AI runs are logged via
  `AIRunLogger`.
- **Human-controlled actions (rule respected):** generating a summary records
  the summary + decisions but only *proposes* actions; the user explicitly
  accepts a proposal or converts a decision into an action
  (`POST /decisions/{id}/convert`), which carries the source citations across.
- **Action management:** `ActionService` with Inbox/Today/Upcoming/Overdue/
  Waiting/Delegated/Completed views, derived overdue flag, and an audit row on
  every status change.
- **API:** meetings CRUD + notes + participants + generate; actions CRUD +
  transition + decision convert; the dashboard now reports the real overdue
  action count.
- **UI:** Meetings tab (list → detail with notes, generate, decisions and
  proposed actions) and Actions tab (view chips, create, complete).
- **Demo data:** `DemoSeeder` now adds a Regno product-kickoff meeting with a
  note, a participant and an overdue action.
- **Tests:** 44 passing (139 assertions) — extraction, meetings, generate +
  decision conversion, action views/audit and workspace isolation.

### Known limitations

- The AI path is contract-tested only through the deterministic fallback; a
  live provider call is not exercised (no credentials).
- Transcript import is modelled (`source_type=transcript`) but there is no
  Granola connector yet.
- Proposed actions are transient in the response; there is no persistent
  "proposal" store.

### Next work

Milestone 3 — receipt and expense workflow (camera/upload capture, original
preservation, extraction, Google Drive filing, duplicate detection and
monthly expense views).

---

## Milestone 1 — Today and calendar (2026-09-22)

**State:** in progress.

### Completed

- **Canonical calendar model:** `Calendar` + `Event` tables with
  provider-neutral IDs, provider ID/revision mapping columns, and a unique
  `(provider, provider_event_id)` constraint for idempotent sync.
- **Connector layer (ADR-002):** `CalendarConnector` contract, `SyncResult`,
  `GoogleCalendarConnector` (Google Calendar API via HTTPS) and
  `MicrosoftCalendarConnector` (Microsoft Graph delta query), each guarded to
  refuse loudly without stored credentials. `GoogleEventNormaliser` maps raw
  provider events to canonical payloads (pure, fixture-tested).
- **Sync records:** `ConnectorAccount` (tokens encrypted-at-rest column,
  hidden from JSON), `SyncCursor`, `SyncRun`; `CalendarSyncService` upserts
  idempotently, persists the cursor and records each run (success or error).
- **Calendar reads:** `CalendarService` (agenda, timeline, next meeting)
  scoped to the active workspace and presented in the user's timezone;
  `CalendarConflicts` flags overlapping events.
- **API:** `GET /api/agenda`, `GET /api/timeline`, `GET /api/connectors`;
  the dashboard now reports the next meeting and today's meeting count.
- **UI:** Today shows the next meeting and today's events (with conflict
  flags); Timeline lists events across a range; the agenda is cached in
  `localStorage` and shown when offline.
- **Demo data:** `php artisan db:seed --class=Database\\Seeders\\DemoSeeder`
  creates `john@okyema.test` / `password` with three calendars and sample
  events across the three contexts.
- **Tests:** 34 passing (92 assertions) — normalisation, conflict detection,
  agenda context-isolation, sync idempotency and error runs.

### Known limitations

- Live OAuth connect flows and real provider sync are not exercised (no
  credentials); the connectors are contract-tested with a fake adapter and
  fixtures.
- Recurrence expansion (repeating events) is stored but not yet expanded into
  individual occurrences on the timeline.
- Webhook/subscription sync is not implemented; polling (cursor) is the model.

### Next work

Milestone 2 — meetings workspace, manual notes + transcript import, Regno AI
summaries/decisions/actions, action management and sourced pre-meeting briefs.

---

## Milestone 0 — SIKA baseline and Okyema skeleton (2026-09-22)

**State:** in progress.

### Completed

- **Phase 0 discovery** of SIKA (`study-fund`): stack, repository map,
  architecture, auth model, AI integration and deployment recorded in
  `docs/architecture/SIKA_ARCHITECTURE_BASELINE.md`.
- **Architecture plan** mapping every Okyema domain to its SIKA pattern, in
  `docs/architecture/OKYEMA_ARCHITECTURE_PLAN.md`.
- **Five ADRs** for the deliberate deviations (workspace contexts, connector
  layer, dark theme, background sync jobs, receipt file storage).
- **Okyema skeleton** on the SIKA pattern:
  - Laravel 11 app bootstrapped and migrating cleanly.
  - Authentication: email/password (web session + Sanctum API) and Google
    OAuth (Socialite), with the `GoogleRedirectUri` / `CaBundle` hardening.
  - Profile + `WorkspaceContext` + `Membership` model with the three default
    contexts seeded per user and an active-context switcher.
  - Responsive shell (`resources/views/app.php`) with the primary navigation,
    context switcher, theme toggle (Light/Dark/System) and PWA head.
  - `config/okyema.php`, `MoneyMath`, `Version`, AI provider shims +
    `AIRunLogger` for the Regno AI integration.
- **Tests:** 16 passing (43 assertions) across `tests/Feature` and
  `tests/Unit`, covering registration, login, context isolation (404 for
  non-members) and money/version pure logic.
- **Style:** `php vendor/bin/pint` clean on `app`, `bootstrap`, `config`,
  `database`, `routes` and `tests`.

### Known limitations

- Brand assets (O/K monogram, app icon, splash, PWA icons) are not in the
  repository; the in-code monogram is a temporary placeholder. Drop the
  approved asset pack into `public/assets/` before release.
- No dark-theme proof screenshots / visual QA has been run (no browser runs
  per the working agreement).
- CI workflow exists but has not been exercised on GitHub; it needs a GitHub
  token if the `courtly/ai-client` VCS repository is private.
- Dashboard counts are placeholders — filled by Milestone 1+.

### Next work

Milestone 1 — Google + Microsoft Calendar connectors, the canonical event
model, Today/agenda/timeline views, sync health and offline cached agenda.
