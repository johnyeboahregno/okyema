# Remaining connectors — status and plan

These connectors are declared in the product scope but are planned rather than
implemented. Each will follow the same convention as the implemented adapters:
a capability interface, guarded adapter, cursor sync and approval-gated
consequential actions.

| Connector | Capabilities | Status |
|-----------|--------------|--------|
| OneNote (Graph) | `notes.read`, `notes.write` | planned |
| Google Photos | `photos.read_selected` | planned — only user-selected receipt/document imports |
| Granola | `meetings.import` | planned — only verified import mechanisms; Granola may not expose a public API |
| Linear | `chat.read` (task/project linking) | planned |
| LINE | `chat.read` | planned |

## OneNote / Photos notes

- OneNote uses Microsoft Graph scopes `Notes.Read` / `Notes.ReadWrite`; the
  Microsoft runbook (`docs/connectors/microsoft.md`) covers the OAuth setup.
- Photos uses the *read-only app-created data* scope so Okyema can only see
  what it was explicitly asked to import, never the user's library.

## Granola caveat

Granola is imported behind the same connector interface as everything else.
If Granola exposes no public API, the supported path is file/export import
(transcripts uploaded by the user), which lands in `notes` with
`source_type=transcript`.
