# Okyema — Professional Application Implementation Prompt

Use this prompt with GitHub Copilot/Copilot CLI while the existing SIKA repository and the new Okyema repository are available in the same workspace.

---

## Role and operating mandate

Act as the principal software architect, product engineer, security engineer, UX engineer, QA lead and DevOps engineer responsible for delivering **Okyema**, a production-grade, mobile-first executive operating system.

Okyema is an intelligent digital chief of staff for John Yeboah, Founder/CEO of Regno and CTO of Launchpad. It must unify meetings, communications, commitments, tasks, calendars, travel, expenses, receipts, documents and important events into one secure, date-centred system.

The application must run professionally on phones, tablets and desktop browsers and be installable as a Progressive Web App. It must support automatic light and dark themes.

The product identity is:

- Name: **Okyema**
- Positioning: **Your intelligent chief of staff**
- Brand relationship: independent product, **Powered by Regno AI**
- Personality: intelligent, futuristic, precise, discreet and trustworthy
- Brand assets: use the supplied Okyema asset pack. Do not redraw or reinterpret the approved O/K monogram.

This is not a demo, mock-up or throwaway prototype. Build a maintainable professional product with secure connectors, auditable automation, robust error handling, comprehensive documentation and production deployment.

---

## Non-negotiable architectural rule: model Okyema on SIKA

The existing **SIKA application is the architectural reference implementation**.

Do not guess SIKA's technology stack from this prompt. Do not replace its working patterns with your preferred framework. Do not begin Okyema feature implementation until you have inspected SIKA.

Reuse or faithfully reproduce SIKA's established conventions for:

- repository and package structure;
- runtime, framework and language;
- dependency management;
- authentication and session handling;
- user and profile modelling;
- routing and API conventions;
- database and persistence access;
- migrations and seed data;
- validation and error contracts;
- state management and server-state caching;
- component organisation and design-system conventions;
- responsive layout and PWA implementation;
- notification infrastructure;
- Regno AI integration;
- observability, logging and telemetry;
- environment configuration and secrets;
- test frameworks and test layout;
- CI/CD and deployment;
- local developer workflow;
- documentation conventions.

Preserve the SIKA architecture unless a difference is required by Okyema's domain. Record every deliberate deviation in an architecture decision record.

Do not modify or destabilise SIKA. Shared code may be extracted only when there is a genuine reusable boundary, tests protect both applications and the extraction is explicitly documented. Otherwise, copy the proven pattern into Okyema without creating runtime coupling between the products.

---

## Phase 0 — mandatory repository discovery

Before writing production code:

1. Locate the SIKA repository and read its root instructions, README, contribution guidance and agent instructions.
2. Inspect its package manifests, lockfiles, workspace configuration, application entry points and environment templates.
3. Trace one complete vertical slice through SIKA:
   - route or screen;
   - UI components;
   - client state;
   - API handler;
   - validation;
   - domain service;
   - persistence;
   - permissions;
   - tests;
   - deployment configuration.
4. Inspect authentication, users/profiles, notifications, Regno AI integration, storage, logging and background jobs.
5. Run the documented SIKA install, lint, typecheck, test and production-build commands without changing source code.
6. Produce `docs/architecture/SIKA_ARCHITECTURE_BASELINE.md` containing:
   - detected stack and versions;
   - repository map;
   - architectural layers and dependency direction;
   - request/data flow;
   - authentication and authorisation model;
   - persistence approach;
   - UI/design-system conventions;
   - testing and deployment approach;
   - patterns Okyema will reuse;
   - unresolved questions.
7. Produce `docs/architecture/OKYEMA_ARCHITECTURE_PLAN.md` mapping each Okyema domain to the corresponding SIKA pattern.
8. Stop and report clearly if SIKA cannot be found or inspected. Do not fabricate its architecture.

After Phase 0, proceed autonomously where decisions are reversible and consistent with SIKA. Ask only when a decision is irreversible, security-sensitive, externally billable or impossible to infer from the repository.

---

## Product outcome

Okyema should answer, at any time:

- What is happening today, this week and this month?
- What meeting is next, and what do I need to know before it?
- What was decided in a meeting and who owns each action?
- Which commitments are overdue or at risk?
- What messages require my response?
- What travel is coming up and are the arrangements complete?
- Which expenses and receipts are outstanding?
- Where is the source document or communication behind an item?
- What changed across Regno, Launchpad and personal contexts?
- What should I pay attention to next?

Okyema may recommend, summarise, classify and prepare actions. It must not send messages, alter calendars, make purchases, submit expenses, share documents or perform other consequential external actions without an explicit approval step.

---

## Core design principles

1. **Date-centred:** every meeting, message, decision, action, trip, expense and document can appear on a unified timeline.
2. **Source-grounded:** summaries and recommendations link back to their source items.
3. **Human-controlled:** AI proposes; the user approves consequential actions.
4. **Connector-independent:** domain logic never depends directly on a provider SDK.
5. **Context-separated:** Regno, Launchpad and Personal data are explicitly scoped and never silently mixed.
6. **Privacy-first:** minimum permissions, encryption, transparent retention and revocation.
7. **Idempotent and auditable:** synchronisation and automation can be retried safely and leave an audit trail.
8. **Offline-tolerant:** essential daily views and capture workflows remain usable during weak connectivity.
9. **Mobile-first:** phone use is primary; tablet and desktop progressively reveal more information.
10. **Evidence before automation:** no autonomous behaviour without logs, approval boundaries and tests.

---

## Users, workspaces and information boundaries

Initially support one primary user, but do not hard-code a single-user database model.

Create these default workspace contexts:

- `REGNO`
- `LAUNCHPAD`
- `PERSONAL`

Every stored object must carry a workspace/context identifier unless it is explicitly global. Connections, permissions, storage destinations, retention rules and AI retrieval must respect this boundary.

The UI must always make the active context visible. Provide an `All contexts` view only for the owner. Never expose one workspace's private content inside another workspace's connector actions.

Prepare the permission model for future assistants or delegated users with least-privilege roles such as owner, assistant, viewer and finance reviewer, but do not overbuild multi-tenancy in the first release.

---

## Core domains

Implement domains as separable modules with explicit contracts, following the way SIKA keeps major functionality separable.

### 1. Today and executive briefing

Create a responsive home screen showing:

- current date, location/time zone and active context;
- next meeting with countdown and preparation status;
- today's calendar across connected accounts;
- priority actions and overdue commitments;
- messages needing attention;
- travel alerts;
- unprocessed receipts and expenses;
- recent decisions;
- AI-generated morning briefing;
- end-of-day review.

The briefing must cite the underlying records and clearly distinguish facts from AI inferences.

### 2. Unified timeline and calendar

Create day, week, month and agenda views combining:

- Google Calendar;
- Microsoft/Office 365 calendars;
- travel events;
- meetings and preparation windows;
- deadlines and reminders;
- actions and commitments;
- expenses and receipt dates;
- significant messages and decisions;
- personal events.

Normalise provider events into a canonical event model while retaining provider IDs, deep links, revision tokens and raw metadata needed for synchronisation.

Support conflict detection, travel-time warnings, time zones, recurring events, all-day events and tentative/cancelled states.

### 3. Meetings

For every meeting support:

- calendar event association;
- participants and organisations;
- agenda;
- pre-meeting briefing;
- linked emails, chats, files and previous meetings;
- notes before/during/after;
- transcript and Granola import where available;
- decisions;
- actions, owner and due date;
- follow-ups;
- confidentiality and context classification;
- source citations;
- generated summary in short, standard and detailed forms.

Allow notes to be entered manually, pasted, dictated or imported. Do not assume Granola exposes a public API; implement its connector capability based on verified access, with supported import mechanisms behind the same connector interface.

### 4. Actions and commitments

Actions may originate from meetings, messages, notes, AI suggestions or manual entry.

Each action includes:

- title and description;
- workspace/context;
- owner and stakeholders;
- source and source link;
- status;
- priority;
- due date and reminder policy;
- dependency/blocker;
- related meeting/project/person;
- audit history.

Provide Inbox, Today, Upcoming, Waiting, Delegated, Overdue and Completed views. AI may propose actions but must not silently create or assign them unless the user has explicitly enabled that rule.

### 5. Communications inbox

Unify supported metadata and selected content from:

- Gmail;
- Microsoft Outlook email;
- Slack;
- Microsoft Teams;
- optionally LINE and Linear when configured.

Provide:

- needs-reply detection;
- VIP/person filters;
- workspace classification;
- threads linked to meetings and projects;
- summarisation;
- reply drafting;
- snooze/reminder;
- conversion into actions;
- approval-before-send.

Never send, react, post or mark externally significant items without a visible confirmation step. Avoid duplicating full provider mailboxes unless required; cache the minimum necessary canonical representation according to retention policy.

### 6. People and relationships

Create a canonical person model that can reconcile identities across calendars, Gmail, Outlook, Slack and Teams.

Store names, organisations, roles, contact methods, provider identities, relationship context, last/next interaction, related meetings/actions and private notes. Identity merges must be reversible and logged.

### 7. Travel

Support trips containing:

- itinerary and purpose;
- flights, trains, hotels and ground transport;
- booking references;
- travellers;
- documents;
- check-in windows;
- time zones;
- meeting linkage;
- expected and actual costs;
- receipts and expenses;
- disruption alerts;
- door-to-door schedule.

Allow itinerary extraction from forwarded emails and uploaded documents, with user confirmation before saving inferred details.

### 8. Expenses and receipt capture

Create a fast mobile receipt workflow:

1. Capture a photograph or upload a file.
2. Preserve the original image unchanged.
3. Extract merchant, date, total, currency, tax, payment method and line items when possible.
4. Display confidence and require confirmation for uncertain fields.
5. Classify workspace, project, trip and expense category.
6. Detect possible duplicates using content hash plus merchant/date/amount similarity.
7. Store the receipt in the configured Google Drive destination.
8. Use deterministic folders such as:
   `Business Receipts/{Workspace}/{YYYY}/{YYYY-MM}/`
9. Use a stable filename such as:
   `{YYYY-MM-DD}_{merchant}_{currency}_{amount}_{short-id}.{ext}`
10. Save the Drive file ID, canonical link, content hash and audit record.

Never discard the original. Image enhancement or OCR derivatives must be separate files. Support GBP and multiple currencies. Store money in integer minor units plus ISO currency code. Never use binary floating point for financial values.

Provide monthly expense views, missing-receipt detection, export and reconciliation status. Treat expense submission as a separate approved action.

### 9. Documents, notes and knowledge

Connect to:

- Google Drive;
- OneNote through Microsoft Graph where permitted;
- meeting notes and transcripts;
- uploaded files;
- provider deep links;
- optionally Google Photos for user-selected receipt/document imports.

Build a permission-aware search and retrieval layer. Index only authorised content. Preserve source, ownership, context, timestamps and deletion state. Removing a connector must revoke access and trigger the configured retention/deletion workflow.

### 10. Rules and automations

Create a visible rule engine for user-controlled automations, for example:

- prepare a briefing before selected meetings;
- remind me when an action is approaching its due date;
- identify calendar conflicts;
- prompt me to capture a receipt after business travel;
- create a draft follow-up after a meeting;
- file confirmed receipts into the appropriate Drive folder;
- produce a weekly Regno or Launchpad review.

Rules must include trigger, conditions, proposed actions, approval level, execution history, failure state and pause control. Start with deterministic rules. AI may enrich a rule result but must not become an unbounded workflow engine.

---

## Connector architecture

Treat every external service as a connector implementing canonical capabilities. Provider code must live in connector adapters, not domain services or UI components.

Define capabilities such as:

- `calendar.read`
- `calendar.write`
- `mail.read`
- `mail.draft`
- `mail.send`
- `chat.read`
- `chat.draft`
- `chat.send`
- `files.read`
- `files.write`
- `notes.read`
- `notes.write`
- `photos.read_selected`
- `meetings.import`

Each connector must expose:

- provider metadata and status;
- OAuth connection lifecycle;
- requested and granted scopes;
- token refresh and revocation;
- capability discovery;
- initial sync and incremental sync;
- webhook/subscription support where available;
- polling fallback where appropriate;
- cursor/checkpoint storage;
- normalisation into canonical models;
- rate-limit handling;
- retry with exponential backoff and jitter;
- idempotency keys;
- dead-letter/failure handling;
- health status and last-success timestamp;
- reconnect and disconnect flows;
- structured, redacted logs.

Initial connector targets:

1. Google Calendar
2. Gmail
3. Google Drive
4. Google Photos, limited to explicitly authorised selection/import
5. Microsoft Outlook Calendar
6. Microsoft Outlook Email
7. OneNote
8. Microsoft Teams
9. Slack
10. Granola, based only on supported access/import methods
11. Linear, if intended for task/project linking
12. LINE, if intended for messaging

Do not block the core application on every connector. Build one Google and one Microsoft vertical slice first, prove the interface, then add providers incrementally.

Store secrets and refresh tokens only in the server-side secret mechanism established by SIKA. Never expose provider secrets or refresh tokens to the client. Encrypt sensitive tokens at rest and support key rotation.

---

## Canonical data model

Adapt naming and schema style to SIKA. At minimum account for:

- User
- Profile
- WorkspaceContext
- Membership
- ConnectorAccount
- ConnectorGrant
- SyncCursor
- SyncRun
- Calendar
- Event
- Meeting
- MeetingParticipant
- Note
- Transcript
- Summary
- Decision
- ActionItem
- Reminder
- Person
- Organisation
- ProviderIdentity
- Conversation
- MessageReference
- Trip
- TravelSegment
- Booking
- Expense
- Receipt
- ReceiptExtraction
- DocumentReference
- Tag
- Project
- AutomationRule
- AutomationRun
- ApprovalRequest
- Notification
- AuditEvent
- AIArtifact
- SourceCitation

Use provider-neutral internal IDs. Keep provider ID, tenant/account, revision/version, external URL and sync metadata in mapping records. Apply unique constraints that make repeated syncs idempotent.

Prefer immutable event/audit history for sensitive changes. Use soft deletion only where it is required for sync/recovery, with explicit purge behaviour.

---

## Regno AI integration

Follow SIKA's existing Regno AI client, configuration and error-handling conventions.

Use AI for bounded capabilities:

- meeting summaries;
- agenda and preparation briefs;
- action and decision extraction;
- message/thread summarisation;
- reply drafting;
- receipt field extraction assistance;
- workspace classification suggestions;
- timeline briefing;
- semantic search;
- travel itinerary extraction;
- risk and conflict suggestions.

All machine-readable AI outputs must use versioned schemas, strict validation and explicit confidence. Reject invalid output rather than guessing. Preserve prompt/version/model metadata needed for reproducibility without logging confidential content unnecessarily.

Every generated assertion must retain source references. The UI must label AI-generated content and make it easy to inspect, edit, accept or reject.

Defend against prompt injection in connected emails, messages, files and transcripts. Treat external content as untrusted data, never as instructions. Tools available to AI must be allowlisted, context-scoped and approval-gated.

Do not allow AI to:

- bypass application permissions;
- choose or expand OAuth scopes;
- send communications without approval;
- delete provider data;
- submit financial claims;
- make bookings or purchases;
- silently combine Regno, Launchpad and Personal information;
- execute arbitrary code from connected content.

---

## Security and privacy requirements

Apply the security mechanisms used by SIKA and strengthen them where the connector threat model requires it.

Required controls:

- secure authentication and session management;
- MFA/passkey compatibility if already supported by SIKA;
- server-side authorisation on every resource;
- workspace/context isolation;
- OAuth state and PKCE where applicable;
- least-privilege incremental consent;
- encrypted secrets and sensitive fields;
- CSRF, XSS, SSRF and injection protections;
- strict upload type/size validation and malware-scanning hook;
- signed or access-controlled file delivery;
- webhook signature verification;
- rate limiting and abuse protection;
- security headers and restrictive content security policy;
- structured audit trail;
- redaction of tokens and personal content from logs;
- export, retention and deletion controls;
- connector disconnect and token revocation;
- dependency and secret scanning in CI;
- backup and tested restoration procedures.

Create a threat model covering OAuth compromise, confused-deputy risks, cross-context leakage, prompt injection, malicious attachments, duplicate webhook delivery, stolen devices and excessive data retention.

---

## UX and visual system

Use the supplied approved Okyema assets exactly. Do not recreate the logo with generative tools.

Core palette:

- Obsidian `#0B1020`
- Deep Indigo `#151D3B`
- Intelligence Violet `#7457FF`
- Luminous Cyan `#36D7E8`
- Cloud `#F5F7FB`
- White `#FFFFFF`

Use automatic system theme by default with a manual Light/Dark/System override.

Design language:

- premium executive software;
- calm information hierarchy;
- generous spacing;
- restrained cyan-to-violet accents;
- crisp typography;
- rounded but not playful surfaces;
- accessible contrast;
- minimal motion with reduced-motion support;
- no excessive glow, glass effects or decorative AI imagery.

Responsive navigation:

- Phone: bottom navigation plus a prominent capture action.
- Tablet: navigation rail with split views where useful.
- Desktop: persistent sidebar, command/search bar and multi-panel detail views.

Primary navigation:

- Today
- Timeline
- Meetings
- Actions
- Inbox
- Travel
- Expenses
- Files
- People
- Automations
- Settings

Provide a global command/search interface capable of natural-language queries and direct navigation, while respecting context and permissions.

Meet WCAG 2.2 AA. Support keyboard navigation, screen readers, visible focus, touch targets, semantic markup, dynamic type/zoom and non-colour status indicators.

---

## PWA, mobile and offline behaviour

Use SIKA's established PWA strategy.

Required capabilities:

- installable manifest using supplied icon sizes;
- appropriate Apple touch icons and favicons;
- responsive safe-area handling;
- offline application shell;
- cached read-only Today/Agenda data;
- offline note, task and receipt capture queue;
- background resynchronisation when supported;
- conflict-aware replay;
- push notifications where supported and explicitly enabled;
- share-target support for receipts/documents if compatible with the stack;
- camera capture with graceful file-upload fallback.

Never cache provider tokens or unrestricted sensitive content in insecure browser storage. Follow SIKA's secure client-storage conventions.

---

## Background jobs and synchronisation

Use the background-job mechanism established by SIKA. If SIKA has none, document and introduce the smallest production-ready mechanism compatible with its hosting model.

Jobs must be:

- idempotent;
- observable;
- retryable;
- cancel-safe;
- concurrency-controlled per connector account;
- protected against duplicate webhook delivery;
- able to resume from persisted cursors;
- associated with structured SyncRun records.

Display connector health in Settings without exposing sensitive implementation details.

---

## Observability and operations

Follow SIKA's telemetry conventions and add:

- correlation IDs across UI, API, job and connector calls;
- structured logs with redaction;
- error reporting;
- latency, error-rate and sync-lag metrics;
- connector health and rate-limit metrics;
- queue depth and retry metrics;
- AI usage, latency, schema-failure and cost metrics;
- audit events for consequential actions;
- health and readiness endpoints;
- user-visible incident-safe error messages.

Define service-level objectives for authentication, Today view, calendar sync and receipt capture.

---

## Testing and quality gates

Use SIKA's test frameworks and conventions.

Required test layers:

- unit tests for domain rules, normalisation and financial calculations;
- schema/validation tests;
- connector contract tests using recorded/synthetic fixtures with secrets removed;
- persistence and migration tests;
- API integration tests;
- authorisation and context-isolation tests;
- sync idempotency and duplicate-webhook tests;
- AI structured-output and prompt-injection tests;
- component/accessibility tests;
- responsive UI tests;
- PWA/offline tests;
- end-to-end tests for critical journeys;
- production build and smoke tests.

Critical end-to-end journeys:

1. Sign in and select a context.
2. Connect a Google account with minimum scopes.
3. View merged calendar data.
4. Open a meeting and generate a sourced preparation brief.
5. Convert a meeting decision into an action.
6. Capture a receipt, verify extraction and file it into the correct Drive month folder.
7. Draft a follow-up email and approve sending through the provider.
8. Disconnect a connector and verify revocation/retention behaviour.
9. Work offline, capture a note/receipt and synchronise safely later.
10. Demonstrate that Regno data cannot leak into Launchpad or Personal results.

No phase is complete until lint, formatting, typecheck, tests and the production build pass.

---

## Delivery sequence

Build in production-quality vertical slices, not horizontal piles of unfinished infrastructure.

### Milestone 0 — SIKA baseline and Okyema skeleton

- complete repository discovery;
- architecture documents and ADR structure;
- initialise Okyema using the SIKA pattern;
- integrate Okyema brand assets;
- authentication, profile, contexts and responsive shell;
- CI quality gates;
- deploy a secure empty shell.

### Milestone 1 — Today and calendar

- Google Calendar connector;
- Microsoft Calendar connector;
- canonical event model;
- Today, agenda and timeline views;
- sync health, conflicts and time zones;
- offline cached agenda.

### Milestone 2 — meetings and actions

- meeting workspace;
- manual notes and supported transcript import;
- Regno AI summaries, decisions and action suggestions;
- action management and reminders;
- sourced pre-meeting brief.

### Milestone 3 — receipt and expense workflow

- camera/upload capture;
- original preservation and extraction;
- user verification;
- Google Drive filing by workspace/year/month;
- duplicate detection;
- monthly expense views and exports.

### Milestone 4 — communications

- Gmail and Outlook reading/drafting;
- Slack and Teams integrations;
- needs-reply and follow-up views;
- approval-gated send flow;
- people identity reconciliation.

### Milestone 5 — travel, documents and knowledge

- trips, segments and bookings;
- itinerary extraction with confirmation;
- Drive and OneNote references/search;
- selected Google Photos import;
- source-grounded global search.

### Milestone 6 — rules, hardening and release

- deterministic automation rules;
- morning/weekly briefings;
- complete threat model and security hardening;
- accessibility and performance audit;
- backup/restore test;
- connector runbooks;
- production release checklist.

At the end of each milestone, produce a working demonstration, update documentation, record known limitations and state measurable acceptance evidence.

---

## Documentation-as-work requirement

Documentation is part of the implementation, not a final clean-up task.

Maintain:

- `README.md`
- `docs/product/PRODUCT_BRIEF.md`
- `docs/architecture/SIKA_ARCHITECTURE_BASELINE.md`
- `docs/architecture/OKYEMA_ARCHITECTURE_PLAN.md`
- `docs/architecture/` ADRs
- `docs/connectors/` per-provider setup, scopes, sync and troubleshooting
- `docs/security/THREAT_MODEL.md`
- `docs/security/DATA_CLASSIFICATION_AND_RETENTION.md`
- `docs/operations/RUNBOOK.md`
- `docs/operations/BACKUP_AND_RESTORE.md`
- `docs/testing/TEST_STRATEGY.md`
- `docs/deployment/DEPLOYMENT.md`
- `.env.example` with descriptions and no secrets
- API and canonical model documentation
- progress log and release notes.

Whenever behaviour, schema, configuration or architecture changes, update the associated documentation in the same change.

---

## Agentic development behaviour

Work with concise, Claude-Code-style progress feedback:

- state what you are inspecting;
- state the next concrete action;
- report important findings and risks;
- avoid narrating trivial commands;
- do not repeatedly ask for confirmation when the repository answers the question;
- stop on missing authority, credentials or irreversible choices;
- never fabricate a successful integration.

For each work item:

1. Inspect existing patterns.
2. State the intended vertical slice and acceptance criteria.
3. Implement the smallest complete solution.
4. Add or update migrations.
5. Add tests.
6. Run formatting, lint, typecheck, tests and production build.
7. Exercise the user journey locally.
8. Update documentation.
9. Summarise changed files, evidence, known limitations and next work.

Do not perform broad unrelated refactors. Do not change frameworks merely for preference. Do not leave dead code, mocked production paths, disabled security checks or undocumented environment variables.

---

## Definition of done

Okyema is ready for its first professional release only when:

- it uses the verified SIKA architecture and documented deviations;
- it is installable and usable on phone, tablet and desktop;
- light and dark themes are complete;
- authentication and all connector permissions are secure;
- Google and Microsoft calendar synchronisation is reliable and idempotent;
- meetings, decisions and actions are source-linked;
- receipt capture stores originals and files confirmed receipts correctly;
- consequential external actions require approval;
- workspace/context separation is proven by tests;
- offline capture is safe;
- logs contain no tokens or inappropriate personal content;
- critical journeys pass end-to-end tests;
- accessibility reaches WCAG 2.2 AA;
- lint, typecheck, tests and production build pass;
- deployment, backup, restoration and connector runbooks exist;
- known limitations are explicit;
- the production deployment has monitoring and rollback.

---

## Begin now

Begin with Phase 0. Locate and inspect SIKA, run its validation commands and produce the two architecture documents before implementing Okyema.

Then present:

1. the discovered SIKA stack and architecture;
2. the proposed Okyema repository structure;
3. the first vertical slice and its acceptance criteria;
4. missing credentials or provider registrations, without requesting secrets in chat;
5. material risks or decisions requiring owner input.

After that report, proceed with Milestone 0 unless blocked by a genuine authority, security or repository-access issue.
