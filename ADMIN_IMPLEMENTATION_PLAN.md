# Admin Dashboard Implementation Plan

Status: Approved execution plan for Admin Dashboard PRD v1.1 (TALL)
Source: `Next_Gen_Financial_Tracker_Admin_Dashboard_PRD_v1.1_TALL.md`
Scope: Laravel-native staff console; the existing Flutter `/api/v1` remains unchanged.

## Reconciliation baseline

- Current base: Laravel 13.25, Livewire 4.4, Flux 2.16, Tailwind 4.1, Fortify 1.38, Sanctum 4.3, Pest 4.7.
- `origin/dev` contains the customer API, Fortify customer web auth/MFA, private receipt/OCR, sync, FX, reports, notifications, and existing customer audit events.
- No staff guard, staff identity model, admin routes, permission catalogue, support cases, purpose-bound grants, or admin Livewire shell exists on `origin/dev`.
- The historical `FLUTTER_BACKEND_INTEGRATION_GUIDE.md`, original expense-tracker PRD, and source DOCX/PDF are not present in this checkout. The admin PRD and current source are authoritative for this work; missing historical documents are recorded as verification gaps.
- Existing customer authentication and Sanctum ownership policies must not be reused as staff authorization.

## Ordered feature manifest

All features start from the latest synchronized `dev`, use their own `feat/...` branch, and are initially `PENDING` unless marked otherwise in the progress file.

| ID | Feature | Phase | Dependencies | Status |
| --- | --- | --- | --- | --- |
| 021 | Staff authentication, mandatory MFA, RBAC, audit and TALL shell | 1 | None | IN_PROGRESS |
| 022 | Overview, redacted user directory/User 360, support cases, staff management and audit explorer | 2 | 021 | PENDING |
| 023 | OCR, sync, FX, queue, report and notification operational views | 3 | 021, 022 | PENDING |
| 024 | Governed retries, finance/budget diagnostics, privacy workflows, catalog and incidents | 4 | 022, 023 | PENDING |
| 025 | Security, accessibility, performance, threat-model retest and production hardening | 021-024 | PENDING |
| 026 | P2 aggregate analytics and safe platform settings | Later | 025, separate scope approval | PENDING |

## Feature specifications

### 021 — Staff authentication, mandatory MFA, RBAC, audit and TALL shell

Dependencies: none. Affected modules: dedicated `staff` auth guard/provider, staff models/roles, admin audit, `/admin` web routes, Livewire pages, Blade layout, Tailwind/Vite assets, rate limiting and middleware.

Screens: `ADM-001` staff sign-in/MFA/recovery foundation and protected shell placeholder. Services: staff login/MFA verifier, permission checker, session revocation, immutable audit writer. Security: staff identities are segregated from customers; password plus TOTP is mandatory; explicit permission keys are required; every Livewire request is protected by the staff guard and action middleware; authentication failures are generic and rate limited. Migration: staff users, roles, role assignments and append-only admin audit events. Tests: migration/model factories, login/MFA success and failure, customer rejection, disabled/revoked sessions, permission denial, audit attribution and mobile route regression. Acceptance: AC-01, AC-18, AC-23, AC-24 and P0 gates 1-3, 8.

Completion: all routes/actions require staff authentication, MFA and explicit permission; no customer API route changes; Pint, PHPStan, focused Pest tests and Vite build pass.

### 022 — Core support and overview

Dependencies: 021. Screens: `ADM-002` through `ADM-005`, `ADM-017`, `ADM-018`. Add bounded read services, redacted user queries, support cases/events, access grants and staff-role administration. Metrics show source, freshness and coverage, with unavailable states. All sensitive reads and mutations audit. Add pagination, indexed filters, forbidden/empty/loading/error states and Livewire tests.

### 023 — P0 operations

Dependencies: 021, 022. Screens: `ADM-006` through `ADM-010`. Add permissioned operational read models for OCR, sync, FX, queues, reports and notifications. Do not expose receipt bytes, raw OCR, report contents or production logs. Preserve server-observed versus unavailable telemetry.

### 024 — P1 operational actions and diagnostics

Dependencies: 022, 023. Screens: `ADM-011` through `ADM-016`, `ADM-021`. Add governed, reasoned, idempotent retries; read-only financial and budget diagnostics; privacy requests with dual control; catalog diagnostics and incident timelines. No direct journal, locked-FX or budget mutation.

### 025 — Hardening and launch

Dependencies: 021-024. Execute threat-model retest, authorization/redaction/concurrency tests, accessibility and responsive checks, representative load/query-plan checks, rollback/runbook and staged-release gates.

### 026 — P2 deferred scope

Dependencies: 025 and separate approved scope. Aggregate analytics and platform settings only after privacy, metric provenance and configuration safety review.
