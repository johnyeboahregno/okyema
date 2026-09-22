# Okyema Architecture Plan

> **Phase 0 deliverable.** Maps each Okyema domain to the SIKA pattern it
> will reuse, and records the deliberate deviations. SIKA = the `study-fund`
> repository; see `SIKA_ARCHITECTURE_BASELINE.md`.

## 1. Guiding rule

Okyema is a **new Laravel 11 application** built to SIKA's conventions:

- PHP 8.3+, Laravel 11, **Vue 3 via CDN (no build step)**, single CSS file.
- SQLite locally / MySQL on the VPS; session auth (web) + Sanctum (API).
- `courtly/ai-client` shared package for Regno AI; `AIRunLogger` auditing;
  deterministic fallback when AI is disabled.
- Pest Feature/Unit tests on SQLite `:memory:`; Pint; Playwright e2e on demand.
- Config in a single `config/okyema.php`; `Support\Version` + a
  `php artisan okyema:release` command; `deploy.sh` + Docker Compose.

Where Okyema's domain forces a difference, the difference is recorded as an
ADR in `docs/architecture/` and below.

## 2. Proposed Okyema repository structure

```
okyema/
├── app/
│   ├── Console/Commands/            # OkyemaRelease, connector health, sync
│   ├── Enums/                       # WorkspaceContext, ConnectorStatus, EventState, …
│   ├── Http/Controllers/
│   │   ├── Api/                     # Sanctum JSON API, one controller per aggregate
│   │   │   ├── Concerns/            # AuthorizesOwnership, AuthorizesWorkspace
│   │   │   └── Connectors/          # per-provider connection controllers
│   │   ├── Auth/                    # web session + Google/Microsoft OAuth
│   │   └── …
│   ├── Models/                      # canonical domain model (see §4)
│   ├── Providers/                   # AppServiceProvider (+ Horizon/Schedule if added)
│   ├── Services/                    # domain services, one per aggregate
│   │   ├── AI/                      # AIProviderInterface, OpenAiCompatibleProvider, AIRunLogger, extractors
│   │   ├── Calendar/, Meetings/, Actions/, Inbox/, Travel/, Expenses/, Files/, People/, Automations/
│   │   └── Connectors/              # provider adapters (never in controllers/UI)
│   └── Support/                     # Version, CaBundle, OAuthRedirectUri, IdempotencyKey
├── bootstrap/  config/  database/   # migrations, seeders, factories
├── docker/  public/  resources/views/  routes/
├── tests/                           # Pest.php, TestCase.php, Feature/, Unit/, e2e/
├── tools/                           # make-icons.mjs (asset processing only)
├── docs/                            # product, architecture, connectors, security, operations, testing, deployment
├── composer.json  package.json  phpunit.xml  .env.example  deploy.sh
└── README.md
```

## 3. Domain → SIKA pattern mapping

| Okyema domain | SIKA pattern reused |
|---------------|---------------------|
| **Auth / profiles / contexts** | Session + Sanctum; Socialite Google OAuth (`Auth\AuthController`); `User` + 1:1 profile; `AuthorizesOwnership`. Okyema adds `WorkspaceContext` + `Membership` and scopes every resource by `workspace_id` (see ADR-001). |
| **Today / briefing** | `DashboardController::index()` aggregates a dashboard for one user; Okyema's Today controller aggregates across the canonical event/action/inbox models and adds an AI briefing that cites sources. |
| **Timeline / calendar** | No direct SIKA analogue — new `Event` canonical model + provider `Connector` adapters, following SIKA's "services behind controllers" shape and idempotent-sync cursor pattern (new). |
| **Meetings** | `Circle` module is the closest separability precedent (aggregate + member access + AI assistant + suggestions). Meetings reuse the aggregate/authorisation shape. |
| **Actions & commitments** | `Transaction`/`Bucket` show the per-user ledger + enum-driven state + audit-by-append pattern. Actions add an immutable `ActionAudit` trail. |
| **Communications inbox** | New. Reuses the AI summarise/draft pattern (`BudgetAdvisor`, `ReceiptScanner`) with approval-gated send. |
| **People** | `University`/`StudentProfile` identity-linking and `CircleMember` membership inform the `Person` + `ProviderIdentity` reconciliation. |
| **Travel** | `Circle` (trip-type) budget/participants are the closest analogue; trips are a new aggregate. |
| **Expenses & receipts** | `MoneyMath` integer minor-units; `ReceiptScanner` in-memory image read; Okyema **stores** the original (new file pipeline) and files to Drive via a `files.write` connector. |
| **Documents / knowledge** | New retrieval layer over connector `files.read`/`notes.read` with permission-aware search. |
| **Rules & automations** | New deterministic rule engine (trigger/conditions/action/approval), persisted like `CircleAiSuggestion` with explicit apply/dismiss. |
| **Connectors** | No SIKA analogue — new `Connector` abstraction (capabilities, OAuth, cursor sync, retries). One Google + one Microsoft slice first. |
| **Regno AI** | `AIProviderInterface` + `OpenAiCompatibleProvider` + `AIRunLogger` + deterministic fallback, all reused unchanged. |

## 4. Canonical data model (adapted to SIKA style)

Tables (Laravel snake_case, dated migrations, string-backed enums):

`users`, `profiles`, `workspace_contexts`, `memberships`,
`connector_accounts`, `connector_grants`, `sync_cursors`, `sync_runs`,
`calendars`, `events`, `meetings`, `meeting_participants`, `notes`,
`transcripts`, `summaries`, `decisions`, `action_items`, `action_audits`,
`reminders`, `people`, `organisations`, `provider_identities`,
`conversations`, `message_references`, `trips`, `travel_segments`,
`bookings`, `expenses`, `receipts`, `receipt_extractions`,
`document_references`, `tags`, `projects`, `automation_rules`,
`automation_runs`, `approval_requests`, `notifications`, `audit_events`,
`ai_artifacts`, `source_citations`, `ai_runs` (reused).

Rules applied uniformly:

- Internal IDs are provider-neutral; provider IDs, tenant/account, revision
  and external URL live in mapping columns/records.
- Idempotency via unique constraints on (`connector_account_id`, `provider_id`
  [, `provider_revision`]).
- Money = integer minor units + ISO code (`MoneyMath`).
- Immutable append-only history for sensitive changes (`audit_events`,
  `action_audits`); soft deletes only where sync/recovery requires it.

## 5. Key deviations (to be written as ADRs)

- **ADR-001 Workspace contexts.** Every stored object carries a
  `workspace_id` (REGNO / LAUNCHPAD / PERSONAL) unless explicitly global.
  SIKA has no such boundary; this is Okyema's central security invariant.
- **ADR-002 Connector layer.** Provider SDKs live only in
  `Services/Connectors/*`, never in domain services or the UI.
- **ADR-003 Dark theme.** SIKA is light-only; Okyema adds
  `prefers-color-scheme` + `data-theme` tokens (Light/Dark/System).
- **ADR-004 Background sync jobs.** SIKA wires the queue but has no custom
  jobs; Okyema introduces idempotent, per-account-concurrency sync jobs.
- **ADR-005 File storage.** Okyema preserves original receipt images and
  files provider items into deterministic Drive folders — a new pipeline.

## 6. Delivery sequence

See the parent prompt. This plan's first executable slice is **Milestone 0**:
authentication, profile, workspace contexts, the responsive shell with
Okyema branding, CI quality gates, and a deployable empty shell.
