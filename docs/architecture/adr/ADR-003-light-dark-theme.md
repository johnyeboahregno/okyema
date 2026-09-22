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
Luminous Cyan `#36D7EB`, Cloud `#F5F7FB`, White `#FFFFFF`) is defined as
design tokens.

> Note: the brand identity asset pack shows Luminous Cyan as `#36D7EB`
> (one digit differs from the prompt's `#36D7E8`). The asset pack is
> authoritative; `#36D7EB` is used.

## Consequences

- Contrast is verified per theme.
- `prefers-reduced-motion` is honoured alongside.
