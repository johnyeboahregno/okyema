# ADR-005 — Receipt originals and file storage

- **Status:** Accepted
- **Date:** 2026-09-22

## Context

Okyema must keep the original receipt image unchanged, store derivatives
separately, and file confirmed receipts into deterministic Google Drive
folders. SIKA reads a receipt photo in memory and stores nothing.

## Decision

Receipt capture stores the original bytes in Okyema storage (signed or
access-controlled delivery), then a `files.write` connector files it to
`Business Receipts/{Workspace}/{YYYY}/{YYYY-MM}/` using a stable filename
`{YYYY-MM-DD}_{merchant}_{currency}_{amount}_{short-id}.{ext}`. The Drive
file ID, canonical link, content hash and audit record are persisted. OCR /
enhancement derivatives are separate files.

## Consequences

- Originals are never discarded.
- Duplicate detection uses content hash + merchant/date/amount similarity.
- File storage and connector write are separable, testable steps.
