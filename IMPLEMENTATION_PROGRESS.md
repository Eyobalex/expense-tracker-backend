# Next-Gen Financial Tracker — Backend Implementation Progress

Status: BLOCKED

Last reconciled with implementation plan: 2026-08-12; plan phases 0–15 reconciled with the feature manifest, local `dev`, `origin/dev`, remote branches, and GitHub PR API (no existing PRs targeting `dev`).

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

Total planned features: 18

Merged: 0

In progress: 0

PR open: 0

Blocked: 1

Pending: 17

Overall status: BLOCKED

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
| 001 | Project governance and baseline                                 | Phase 0                               | None               | `feat/001-project-baseline`       | BLOCKED | —  | —            | Local validation complete | GitHub PR creation requires CLI or API authentication                         |
| 002 | PostgreSQL, Redis, MinIO and local runtime                      | Phase 1                               | 001                | `feat/002-runtime-infrastructure` | PENDING | —  | —            | —             | Production-shaped local/CI infrastructure                                      |
| 003 | Laravel API shell, authentication and single-device enforcement | Phase 2                               | 002                | `feat/003-api-auth`               | PENDING | —  | —            | —             | May be split if final plan separates API foundation and auth                   |
| 004 | DDD foundation and shared primitives                            | Phase 3                               | 003                | `feat/004-shared-primitives`      | PENDING | —  | —            | —             | Money, time, errors, audit, IDs                                                |
| 005 | Identity, financial accounts, categories and onboarding backend | Phase 4                               | 004                | `feat/005-accounts-categories`    | PENDING | —  | —            | —             | Includes immutable user/account currency rules                                 |
| 006 | Currency Core                                                   | Phase 5                               | 005                | `feat/006-currency-core`          | PENDING | —  | —            | —             | Must precede ledger                                                            |
| 007 | Transaction aggregate and double-entry ledger                   | Phase 6                               | 006                | `feat/007-double-entry-ledger`    | PENDING | —  | —            | —             | Includes functional-currency invariant and inclusion matrix                    |
| 008 | Budget engine                                                   | Phase 7                               | 007                | `feat/008-budget-engine`          | PENDING | —  | —            | —             | Includes lazy initialization, compensation, refunds, atomic borrowing          |
| 009 | Receipt storage and OCR infrastructure                          | Phase 8                               | 008, 002, 006      | `feat/009-receipt-ocr`            | PENDING | —  | —            | —             | MinIO, preprocessing, PP-OCRv6, parser/security/lifecycle                     |
| 010 | Merchant and item normalization                                 | Phase 9                               | 009                | `feat/010-normalization`          | PENDING | —  | —            | —             | Raw evidence remains immutable                                                  |
| 011 | Duplicate detection                                             | Phase 9                               | 009, 010           | `feat/011-duplicate-detection`    | PENDING | —  | —            | —             | User-controlled candidate resolution                                           |
| 012 | FX provider operations and rate lifecycle                       | Phase 10                              | 006, 007, 011      | `feat/012-fx-operations`          | PENDING | —  | —            | —             | Provider jobs, stale policies, overrides                                       |
| 013 | Offline synchronization API contract                            | Phase 11                              | 003, 007, 009, 011, 012 | `feat/013-sync-contract` | PENDING | —  | —            | —             | Includes cursor expiry/full resync                                             |
| 014 | Dashboard and forecasting                                       | Phase 12                              | 008, 012, 013      | `feat/014-dashboard-forecasting`  | PENDING | —  | —            | —             | Product formulas must be approved before implementation                        |
| 015 | Notifications backend                                           | Phase 12                              | 014                | `feat/015-notifications`          | PENDING | —  | —            | —             | Approved notification channels only                                             |
| 016 | Reports and exports                                             | Phase 13                              | 007, 008, 012, 014 | `feat/016-reports-exports`        | PENDING | —  | —            | —             | PDF, XLSX, CSV, Full JSON Data Export, full-account ZIP                        |
| 017 | Security, performance and operations hardening                  | Phase 14                              | 001-016            | `feat/017-release-hardening`      | PENDING | —  | —            | —             | RPO/RTO, retention, constraints, correlation, restore drills                  |
| 018 | MVP release validation                                          | Phase 15                              | 017                | `feat/018-mvp-release-validation` | PENDING | —  | —            | —             | No product features; final gates/runbooks                                      |
| 999 | Progress finalization                                           | Administrative                        | 001-018            | `feat/999-progress-finalization`  | PENDING | —  | —            | —             | Use only after final feature merge if needed                                   |

---

# Current Feature

Feature ID: 001

Feature: Project governance and baseline

Branch: `feat/001-project-baseline`

Status: BLOCKED

Started: 2026-08-12

PR: —

Blocker: GitHub CLI is unavailable and no GitHub API token is configured to create the required PR.

---

# Completed Features

None.

---

# Blockers

None.

---

# Decision / Dependency Notes

## Product decisions required before relevant phases

Record unresolved decisions here when encountered.

Examples may include:

* exact deterministic forecasting formulas;
* approved RPO/RTO targets;
* production retention periods;
* supported OCR locale/language matrix;

only if they are not already resolved in the approved source documents.

Do not invent business-approved values.

---

# Verification Ledger

Use this section for final per-feature records when useful.

## Feature 001

Status: BLOCKED

Branch: `feat/001-project-baseline`

Implementation plan coverage: TBD after final reconciliation

PR: —

Merge commit: —

Tests executed: —

Checklist: Local Phase 0 baseline complete; branch pushed; PR creation blocked by unavailable GitHub API authentication.

Notes: Local `dev` equals `origin/dev` at `2d08caa54ac494d47c3915dced4bd6eda500bd0f`; GitHub PR API reported no existing PRs targeting `dev`. GitHub CLI is unavailable; use the GitHub API for PR verification in this session. Branch is pushed to origin. BLOCKED: GitHub CLI is unavailable and no GH_TOKEN or GITHUB_TOKEN is configured for GitHub PR creation.

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

Final notes: —
