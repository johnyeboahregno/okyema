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

Both token sets follow Notion's neutral surfaces. Light: white surfaces,
`#37352F` text, `#787774` muted, `#E9E9E7` strokes. Dark: `#191919`
background, `#202020` surfaces, `#D4D4D4` / `#9B9B9B` text. Status colours are
Notion's (`#EB5757` / `#F2994A` / `#219653`). The hero card, capture button and
auth background are flat (no gradients). All border radius is removed
system-wide (a global `border-radius: 0` reset), and shadows are off.

## Update — Okyema Green brand (2026-09-23)

The accent is the brand green. `--accent` / `--accent-2` are Okyema Green
`#43C7A7`; `--accent-strong` is a deeper green (`#1F7A62`) on light surfaces
and the brand green on dark, so accent text meets AA; `--accent-fill`
(`#1F7A62`) fills primary buttons and the capture button so white labels stay
≥4.5:1. Brand tokens (`--green`, `--green-ink`, `--obsidian`, `--indigo`,
`--cloud`) are declared in `:root`. The auth screens use the brand background
artwork, the appbar/auth logos and favicons point at the new
`assets/app-icon-*` and `assets/favicon/*` files, and the PWA `theme-color`
plus manifest colours are Obsidian `#0B1020`. Reference packs live under
`docs/brand/`.
