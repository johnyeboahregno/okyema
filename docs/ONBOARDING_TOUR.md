# First-use tour & onboarding

A spotlight walkthrough of the whole app that opens by itself the first time an
owner signs in. It dims the screen, rings the thing it is talking about, and
explains it in one short card with Back / Next — then gets out of the way for
good.

```
┌────────────────────────────────────────────┐
│  2 / 16   Your workspaces               ×  │   ← the card floats next to
│  Everything lives in one of three          │     the highlighted element
│  boundaries — Regno, Launchpad, Personal…  │     (above it when there is
│  ● ○ ○ ○ ○ ○ ○ ○ ○ ○ ○ ○ ○ ○ ○ ○           │      no room below)
│  [ Back ]            [    Next    ]        │
│  Never show me this again   Skip for now   │
└────────────────────────────────────────────┘
        ┆
        ┆   the spotlight is one element with
        ┆   a 9999px box-shadow + a pulsing
        ▼   green ring (no SVG masks needed)
╭────────────────────────────────────────────╮
│  ▪ Regno                                   │  ← the real, live UI
╰────────────────────────────────────────────╯
```

## The steps

| # | Highlights | Says |
|---|-----------|------|
| 1 | *(centred)* | Welcome to Okyema |
| 2 | `.context-chip` | Your workspaces — Regno / Launchpad / Personal |
| 3 | `.nav__capture` | Capture anything — the green + takes a receipt |
| 4 | `.hero` (Today) | Today, at a glance |
| 5 | `.card` (Today) | What needs you — the counts and the briefing |
| 6 | `.date-line` (Timeline) | The days ahead, conflicts flagged |
| 7 | `.btn--ghost.btn--block` (Meetings) | Create a meeting, notes become decisions and actions |
| 8 | `.chips` (Actions) | Everything you owe someone, by view |
| 9 | `.greet` (Inbox) | Replies are drafted, never sent without approval |
| 10 | `.btn--ghost.btn--block` (Travel) | Trips, segments and their documents |
| 11 | `.card` (Expenses) | Confirm receipts into expenses; the month at a glance |
| 12 | `.inline-form` (Files) | Search drives by what a document is about |
| 13 | `.greet` (People) | Everyone, with the identities that tie them together |
| 14 | `.btn--ghost.btn--block` (Automations) | Rules that report facts, never act for you |
| 15 | `.inline-form` (Settings) | Name, timezone and connections — moving on after Save |
| 16 | *(centred)* | You're set — replay any time from the avatar menu |

A step can carry a `tab`, so the tour switches screens itself
(`this.tab = …`, `$nextTick`, measure) before drawing the spotlight. If a target
does not exist (an empty list, say) the step still works: the card centres
itself.

Steps 2-5 and the final step return to **Today**, so the tour ends where the
user starts work.

### Setup steps advance themselves

The Settings step carries `waitsForSave`. Pressing **Save profile** moves the
tour on by itself after a 900ms beat, so the point is to get the setup done
rather than to hunt for Next.

## Dismissal

- **Never show me this again** → `PATCH /api/profile { onboarding_dismissed: true }`
  sets `profiles.onboarding_dismissed_at`. It follows the user to any device and
  the tour never auto-opens again.
- **Start using Okyema** (the final button) does the same — seeing it through
  counts as done.
- **Skip for now** / ✕ / Escape close it for this visit only; it returns next
  time. Skipping is deliberately not persisted, so nobody can lose the tour by
  tapping the wrong thing.
- **Replay**: **Show me the tour** in the avatar menu, just under *Install on
  device*, clears the flag and starts again from step 1.
- Keyboard: `→` next, `←` back, `Esc` skip.

## Where it lives

| Piece | File |
|-------|------|
| Steps, spotlight maths, persistence | `resources/views/app.php` — `tourSteps`, `showTourStep`, `startTour`, `endTour`, `skipTour`, `replayTour`, `onTourKey` |
| Overlay + card markup | `resources/views/app.php` — the `.tour` block below the nav |
| Styles | `public/css/okyema.css` — "First-use tour" block |
| Storage | `profiles.onboarding_dismissed_at` (already in `create_profiles_table`) |
| API | `PATCH /api/profile` → `onboarding_dismissed`; `GET /api/me` exposes `profile.onboarding_dismissed_at` |
| Tests | `tests/Feature/OnboardingTourTest.php` |

## Adding a step

Add an object to `tourSteps` with a `key`, a `title`, a `body`, and optionally a
`target` (CSS selector), a `tab` (screen to switch to first) and `waitsForSave`.
No target = a centred card. Keep the copy short — the card is ~340px wide.
