# Next-Gen Financial Tracker — Backend Implementation Progress

Status: IN PROGRESS

Last reconciled with implementation plan: 2026-08-17; Feature 016 is verified merged into `dev` via PR #20 (`b8bfeee024dd6e242f1a7996c0072c32bf3a1124`). Feature 017 has local committed work but remains blocked on VPS-only release validation. Documentation-only Feature 020 is in progress from synchronized `dev`.

Integration branch: `dev`

Production branch: `main`

Branch cleanup owner: User

Implementation scope: Laravel backend API and backend infrastructure only

---

# Purpose

This file is the durable execution ledger for the backend implementation.

Codex MUST read this file before selecting implementation work.

This file determines:

* what has already been implemented;
* what is currently being implemented;
* what remains;
* dependencies;
* feature branch names;
* PR numbers;
* merge commits;
* blockers;
* implementation-plan coverage.

Conversation history must not be used as the authoritative implementation-progress record.

---

# Status Definitions

## PENDING

Feature implementation has not started.

## IN_PROGRESS

The feature branch has been created and implementation is underway.

## PR_OPEN

The implementation PR exists and has not yet been verified as merged.

## MERGED

The PR has been verified as merged into `dev`.

## BLOCKED

The feature cannot safely continue until an external issue, dependency, requirement, infrastructure problem, failing required check, or human decision is resolved.

---

# Progress Rules

1. Features are implemented in dependency order.

2. A feature may begin only when every required dependency is `MERGED`.

3. Every feature uses a dedicated:

   `feat/...`

   branch.

4. Every feature PR targets:

   `dev`.

5. `main` is not part of the Codex implementation workflow.

6. Feature branches remain locally and remotely after merge.

7. The user handles all branch cleanup.

8. Codex must verify GitHub/Git state instead of trusting stale status fields blindly.

9. A feature must not be marked `MERGED` until GitHub confirms the PR merged into `dev`.

10. If a previous feature merged after its progress update was already part of the PR, the next feature branch should reconcile that previous row to `MERGED`.

11. After the final feature, use `feat/999-progress-finalization` if necessary to reconcile final merge metadata without committing directly to `dev`.

---

# Overall Progress

Total planned features: 20

Merged: 17

In progress: 1

PR open: 0

Blocked: 1

Pending: 1

Administrative Feature 999 is excluded from the 20 planned delivery features.

Overall status: IN PROGRESS

---

# Feature Manifest

IMPORTANT:

Before the first implementation feature begins, Codex must compare this manifest against the FINAL approved implementation plan.

Codex must:

1. read the complete approved implementation plan;
2. determine independently testable/reviewable backend features;
3. preserve implementation-plan dependency order;
4. update this table;
5. assign deterministic `feat/...` branch names;
6. update Total planned features;
7. update Last reconciled with implementation plan;
8. only then begin Feature 001.

The initial expected feature families are listed below.

They may be split into additional independently reviewable features if required by the final approved implementation plan.

| ID  | Feature                                                         | Implementation Plan Source            | Dependencies       | Branch                            | Status  | PR | Merge Commit | Tests / Gates | Notes                                                                          |
| --- | --------------------------------------------------------------- | ------------------------------------- | ------------------ | --------------------------------- | ------- | -- | ------------ | ------------- | ------------------------------------------------------------------------------ |
| 001 | Project governance and baseline | Phase 0 | None | feat/001-project-baseline | MERGED | 1 | 45b0e935b6de5cee44c0f2d57cfa3054884866f4 | CI passed | Merged into dev after PHP 8.5 CI correction |
| 002 | PostgreSQL, Redis, MinIO and local runtime | Phase 1 | 001 | feat/002-runtime-infrastructure | MERGED | 2 | 9142db0f5aecb950cf8d06861d492239036ba7fd | CI passed | Merged into dev after PostgreSQL/Redis/MinIO CI passed |
| 003 | Laravel API shell, authentication and single-device enforcement | Phase 2 | 002 | feat/003-api-auth | MERGED | 3 | 0cbe65679e67d694f99733437a541c1a64a569af | CI passed | Merged into dev after PostgreSQL/Redis/MinIO-backed CI passed |
| 004 | DDD foundation and shared primitives | Phase 3 | 003 | `feat/004-shared-primitives` | MERGED | 4 | 9a5aaa71d5d1323fe198203866b5fd7fe3484588 | CI passed | Merged into dev after PostgreSQL/Redis/MinIO-backed CI passed |
| 005 | Identity, financial accounts, categories and onboarding backend | Phase 4 | 004 | `feat/005-accounts-categories` | MERGED | 5 | 1c3bba74d4486921c7cc41cc88308613aa47005b | CI passed | Merged into dev after PostgreSQL/Redis/MinIO-backed CI passed |
| 006 | Currency Core | Phase 5 | 005 | `feat/006-currency-core` | MERGED | 6 | 17df610cfa89da3cd8cb97f82786b1c4dc5270b3 | CI passed | Merged into dev after PostgreSQL/Redis/MinIO-backed CI passed |
| 007 | Transaction aggregate and double-entry ledger | Phase 6 | 006 | `feat/007-double-entry-ledger` | MERGED | 8 | 746c17072232df723b8778c89ab8926ca0c6edfa | CI passed | Merged into dev after PostgreSQL-backed CI passed |
| 008 | Budget engine | Phase 7 | 007 | `feat/008-budget-engine` | MERGED | 9 | ae1ace1f4c5f9b16d1b9bb3b31ee93ab4429faae | CI passed | Merged into dev after PostgreSQL-backed CI passed |
| 009 | Receipt storage and OCR infrastructure | Phase 8 | 008, 002, 006 | `feat/009-receipt-ocr` | MERGED | 10 | d409a68a7d1f84b9947e9563a689b816938399f2 | CI passed | Merged into dev after PostgreSQL/Redis/MinIO-backed CI passed |
| 010 | Merchant and item normalization | Phase 9 | 009 | `feat/010-normalization` | MERGED | 11 | 73f7fa742d1ed4497c6ab395b4e860cf7ccd4298 | CI passed | Merged into dev after PostgreSQL/Redis/MinIO-backed CI passed; OCR locale/parser matrix approved 2026-08-14 |
| 011 | Duplicate detection                                             | Phase 9                               | 009, 010           | `feat/011-duplicate-detection`    | MERGED | 13 | b06fa689b5cea5696a5a8dbf36f8efef7b581692 | CI passed | Merged into dev after PostgreSQL/Redis/MinIO-backed CI passed |
| 012 | FX provider operations and rate lifecycle                       | Phase 10                              | 006, 007, 011      | `feat/012-fx-operations`          | MERGED | 14 | 8b3eb9f87607d14b04c7d23081b70bee89a10397 | CI passed | Open Exchange Rates integration merged into dev after conflict-resolution CI passed |
| 013 | Offline synchronization API contract                            | Phase 11                              | 003, 007, 009, 011, 012 | `feat/013-sync-contract` | MERGED | 16 | af015b6bc8a01f952fb22b5eab2ff33215974b28 | CI passed | Authoritative cursor/full-resync contract merged into dev                      |
| 014 | Dashboard and forecasting                                       | Phase 12                              | 008, 012, 013      | `feat/014-dashboard-forecasting`  | MERGED | 17 | 315bb79ca3065a1644aa3b10d374108225a8eb6d | CI passed | Merged into dev after PostgreSQL/Redis/MinIO-backed CI passed                  |
| 015 | Notifications backend                                           | Phase 12                              | 014                | `feat/015-notifications`          | MERGED | 19  | 7e7ed53d74ca56e1621e3c712c4fcc1c9bf1f605 | CI passed | In-app, Android local payload, FCM, email; item-price notification threshold remains a product decision |
| 016 | Reports and exports                                             | Phase 13                              | 007, 008, 012, 014 | `feat/016-reports-exports`        | MERGED | 20 | b8bfeee024dd6e242f1a7996c0072c32bf3a1124 | CI passed | PDF, XLSX, CSV, Full JSON Data Export, full-account ZIP                        |
| 017 | Security, performance and operations hardening                  | Phase 14                              | 001-016            | `feat/017-release-hardening`      | BLOCKED | —  | — | Local gates passed; VPS gates pending | RPO/RTO, retention, constraints, correlation, restore drills                  |
| 018 | MVP release validation                                          | Phase 15                              | 017                | `feat/018-mvp-release-validation` | PENDING | —  | —            | —             | No product features; final gates/runbooks                                      |
| 019 | OpenAPI/Swagger documentation and REST endpoint scenarios      | API contract support                  | 003                | `feat/019-api-documentation`       | MERGED | 15 | db2c8f9775710dda79d9881f0037d17f885b3bab | CI passed | OpenAPI documentation, docs gate, and REST scenarios merged into dev |
| 020 | Flutter backend integration guide                              | Documentation audit                   | 003-016, 019        | `feat/020-flutter-integration-guide` | IN_PROGRESS | — | — | Pending | Actual-state guide for the separate Flutter client repository |
| 999 | Progress finalization                                           | Administrative                        | 001-018            | `feat/999-progress-finalization`  | PENDING | —  | —            | —             | Use only after final feature merge if needed                                   |

---

# Current Feature

Feature ID: 020

Feature: Flutter backend integration guide

Branch: `feat/020-flutter-integration-guide`

Status: IN_PROGRESS

Started: 2026-08-17

PR: —

Blocker: None for Feature 020. Feature 017 remains blocked on a provisioned VPS/production-like environment, approved retention/RPO/RTO values, backup/restore, performance/query-plan, and outage drills.

---

# Completed Features

Feature 001 — Project governance and baseline — merged into dev via PR #1 (45b0e935b6de5cee44c0f2d57cfa3054884866f4).

Feature 002 — PostgreSQL, Redis, MinIO and local runtime — merged into dev via PR #2 (9142db0f5aecb950cf8d06861d492239036ba7fd).
Feature 003 — Laravel API shell, authentication and single-device enforcement — merged into dev via PR #3 (0cbe65679e67d694f99733437a541c1a64a569af).

Feature 004 — DDD foundation and shared primitives — merged into dev via PR #4 (9a5aaa71d5d1323fe198203866b5fd7fe3484588).

Feature 005 — Identity, financial accounts, categories and onboarding backend — merged into dev via PR #5 (1c3bba74d4486921c7cc41cc88308613aa47005b).

Feature 006 — Currency Core — merged into dev via PR #6 (17df610cfa89da3cd8cb97f82786b1c4dc5270b3).

Feature 007 — Transaction aggregate and double-entry ledger — merged into dev via PR #8 (746c17072232df723b8778c89ab8926ca0c6edfa).

---

# Blockers

## Feature 017 — VPS / production operations validation required

Feature 017 has local committed implementation work on `feat/017-release-hardening`, but its remaining release gates require a provisioned VPS/production-like environment and approved operations values. It cannot be merged until approved retention/RPO/RTO values, backup destinations and restore drill, production-like performance/query-plan checks, and outage validation are completed.

---

# Decision / Dependency Notes

## Product decisions required before relevant phases

Record unresolved decisions here when encountered.

Examples may include:

* exact deterministic forecasting formulas;
* approved RPO/RTO targets;
* production retention periods;
* supported OCR locale/language matrix;

### Duplicate-resolution lifecycle — APPROVED 2026-08-14

For `Replace pending draft with extracted version` and `Cancel current import`, retain the affected unposted transaction as a terminal, non-postable, auditable `cancelled` record. Preserve attached receipt originals and OCR evidence, never delete a receipt solely because an import is cancelled, and permit replacement only when the selected candidate is unposted. Record links between the duplicate decision, its source/candidate transactions, and audit event.

### OCR locale/parser matrix — APPROVED 2026-08-14

MVP supports English (Latin) and Amharic (Ethiopic) scripts; ETB and USD only; the explicit number forms `1,250.50`, `1.250,50`, and `1 250,50`; ISO and unambiguous English textual dates; and date-only preservation pending user confirmation of timezone/time. Bare or ambiguous symbols (including `$` and `Br`), numeric dates, unsupported scripts/formats, and ambiguous numbers remain `needs_review`; Laravel does not guess. The persisted parser version is `locale-matrix-v1`.

### FX provider operations — APPROVED 2026-08-14

Use Open Exchange Rates Free with the user-provided `OPEN_EXCHANGE_RATES_APP_ID`. Store provider USD→ETB daily rates and derive ETB→USD exactly from the reciprocal. Refresh daily at 00:30 UTC with three exponential-backoff retries. Alert at 24 hours; accept a latest-valid rate through 48 hours. On weekends/holidays, use the latest valid prior daily rate inside that 48-hour window. Beyond 48 hours, block automatic foreign-currency posting and require an explicit audited manual rate override. Credentials remain server-only and never appear in API responses or logs.

only if they are not already resolved in the approved source documents.

Do not invent business-approved values.

---

# Verification Ledger

Use this section for final per-feature records when useful.

## Feature 001

Status: MERGED

Branch: `feat/001-project-baseline`

Implementation plan coverage: Phase 0, steps 1-8; checklist and exit criteria.

PR: #1 (merged into dev)

Merge commit: 45b0e935b6de5cee44c0f2d57cfa3054884866f4

Tests executed: GitHub CI passed on PHP 8.5.

Checklist: Complete.

Notes: PR #1 merged after CI passed; feature branch retained locally and remotely.

## Feature 002

Status: MERGED

Branch: `feat/002-runtime-infrastructure`

Implementation plan coverage: Phase 1, steps 1-9; checklist and exit criteria verified by GitHub Actions service-backed tests.

PR: #2 (targets `dev`)

Merge commit: 9142db0f5aecb950cf8d06861d492239036ba7fd

Tests executed: local focused PHPUnit configuration/command tests; complete local suite; Pint; PHPStan debug mode; GitHub Actions PostgreSQL/Redis/MinIO deep check, service-backed smoke test, and full CI checks passed.

Checklist: Complete.

Notes: private MinIO, isolated PostgreSQL testing, Redis cache/queue/locks, Sail worker/scheduler, health command, CI services, and runbook are included.

---


## Feature 003

Status: MERGED

Branch: `feat/003-api-auth`

Implementation plan coverage: Phase 2, steps 1-10; checklist and exit criteria complete.

PR: #3 (merged into `dev`)

Merge commit: 0cbe65679e67d694f99733437a541c1a64a569af

Tests executed: GitHub Actions CI passed with PostgreSQL, Redis, MinIO, Pint, PHPStan, and PHPUnit.

Checklist: Complete.

Notes: Sanctum 4.3.3, device/token revocation, API v1 envelopes, request IDs, Redis throttling/idempotency, encrypted replay storage, policies, and profile optimistic concurrency included.
---

## Feature 004

Status: MERGED

Branch: `feat/004-shared-primitives`

Implementation plan coverage: Phase 3, steps 1-9; checklist and exit criteria complete.

PR: #4 (merged into `dev`)

Merge commit: 9a5aaa71d5d1323fe198203866b5fd7fe3484588

Tests executed: GitHub Actions CI passed with PostgreSQL, Redis, MinIO, Pint, PHPStan, and PHPUnit.

Checklist: Complete.

Notes: Exact money/rate arithmetic, deterministic time, domain errors, audit redaction, contracts, enums, and event conventions.

---

## Feature 005

Status: IN_PROGRESS

Branch: `feat/005-accounts-categories`

Implementation plan coverage: Phase 4, steps 1-11.

PR: #5 (targets `dev`)

Merge commit: —

Tests executed: Pending.

Checklist: In progress.

Notes: User financial settings, accounts, categories, onboarding, seed data, ownership, and currency locks.

---

# Release Gates

These remain incomplete until explicitly verified.

* [ ] All implementation-plan features merged into `dev`
* [ ] All PRs target `dev`
* [ ] No Codex implementation PR targeted `main`
* [ ] PostgreSQL test suite passes
* [ ] Accounting invariant tests pass
* [ ] Budget boundary/correction tests pass
* [ ] FX historical-locking tests pass
* [ ] OCR/storage failure tests pass
* [ ] Sync retry/conflict/full-resync tests pass
* [ ] Authorization tests pass
* [ ] Export authorization/integrity tests pass
* [ ] Static analysis passes
* [ ] Pint passes
* [ ] Performance baseline passes
* [ ] Backup restore drill passes
* [ ] Approved retention policies configured
* [ ] Approved RPO/RTO objectives validated
* [ ] Production monitoring/alerts configured
* [ ] No known posting/sync/correction data-loss bug
* [ ] `IMPLEMENTATION_PROGRESS.md` reconciled
* [ ] Local current branch is `dev`
* [ ] Local `dev` matches `origin/dev`
* [ ] `main` untouched by Codex

---

# Final Completion Record

Overall status: NOT STARTED

Final `dev` commit: —

Progress finalization PR: —

Progress finalization merge commit: —

Completed date: —

Post-MVP items remaining:

* SMS capture / transaction-code parsing
* E2EE / zero-knowledge architecture
* multi-device concurrent editing
* bank/open-banking integration
* other explicitly approved post-MVP features
