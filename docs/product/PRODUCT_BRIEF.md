# Okyema — Product Brief

- **Name:** Okyema
- **Positioning:** Your intelligent chief of staff
- **Brand relationship:** independent product, **Powered by Regno AI**
- **Personality:** intelligent, futuristic, precise, discreet and trustworthy

## What Okyema answers

At any time, Okyema answers:

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

## Core design principles

1. **Date-centred** — everything can appear on a unified timeline.
2. **Source-grounded** — summaries and recommendations link to their sources.
3. **Human-controlled** — AI proposes; the user approves consequential actions.
4. **Connector-independent** — domain logic never depends on a provider SDK.
5. **Context-separated** — Regno, Launchpad and Personal are never silently mixed.
6. **Privacy-first** — minimum permissions, encryption, retention and revocation.
7. **Idempotent and auditable** — sync and automation retry safely.
8. **Offline-tolerant** — essential daily views work during weak connectivity.
9. **Mobile-first** — phone first; tablet and desktop reveal more.
10. **Evidence before automation** — no autonomy without logs and tests.

## Primary navigation

Today · Timeline · Meetings · Actions · Inbox · Travel · Expenses · Files ·
People · Automations · Settings.

## Information boundaries

Three default workspace contexts, seeded for the first user:

- `REGNO`
- `LAUNCHPAD`
- `PERSONAL`

Every stored object carries its workspace unless explicitly global. The UI
always shows the active context; an "All contexts" view exists only for the
owner.

## Consequential actions require approval

Okyema may recommend, summarise, classify and prepare actions. It must **not**
send messages, alter calendars, make purchases, submit expenses, share
documents or perform other consequential external actions without an explicit
approval step.

## Visual identity

Palette (from the approved asset pack):

| Swatch | Name | Hex |
|--------|------|-----|
| ■ | Obsidian | `#0B1020` |
| ■ | Deep Indigo | `#151D3B` |
| ■ | Intelligence Violet | `#7457FF` |
| ■ | Luminous Cyan | `#36D7EB` |
| ■ | Cloud | `#F5F7FB` |
| ■ | White | `#FFFFFF` |

Typography: **Inter**. Automatic system theme by default with a manual
Light/Dark/System override.

## Status

Milestone 0 (skeleton) — see `docs/PROGRESS.md`.
