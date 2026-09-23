# ADR-003 — Automatic light / dark theme

- **Status:** Accepted
- **Date:** 2026-09-22

## Context

The Okyema prompt requires automatic system theme with a manual
Light/Dark/System override and WCAG 2.2 AA contrast. SIKA ships a light-only
theme set once via `data-theme="light"`.

## Decision

Okyema keeps SIKA's single-CSS-file approach but defines two token sets
(light + dark) keyed off `data-theme`, defaults to `prefers-color-scheme`,
and stores a manual override in `localStorage`. The Okyema palette
(Obsidian `#0B1020`, Deep Indigo `#151D3B`, Intelligence Violet `#7457FF`,
Luminous Cyan `#36D7E8`, Cloud `#F5F7FB`, White `#FFFFFF`) is defined as
design tokens.

## Consequences

- Contrast is verified per theme.
- `prefers-reduced-motion` is honoured alongside.

## Update — Notion theme, light + dark (2026-09-23)

Both token sets now follow Notion's palette. Light: white surfaces,
`#37352F` text, `#787774` muted, `#E9E9E7` strokes, Notion blue (`#2383E2`)
accent and Notion status colours (`#EB5757` / `#F2994A` / `#219653`). Dark:
`#191919` background, `#202020` surfaces, `#D4D4D4` / `#9B9B9B` text, same
blue accent. The hero card, capture button, auth background and badge tints
are flat (no gradients). All border radius is removed system-wide (a global
`border-radius: 0` reset), and shadows are off. PWA `theme-color` and the
manifest colours follow.
