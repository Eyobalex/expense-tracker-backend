# Next-Gen Financial Tracker — Backend Implementation Progress

Status: NOT STARTED

Last reconciled with implementation plan: NOT YET

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

Total planned features: TBD

Merged: 0

In progress: 0

PR open: 0

Blocked: 0

Pending: TBD

Overall status: NOT STARTED

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
| 001 | Project governance and baseline                                 | Phase 0                               | None               | `feat/001-project-baseline`       | PENDING | —  | —            | —             | Confirm final implementation-plan numbering before start                       |
| 002 | PostgreSQL, Redis, MinIO and local runtime                      | Phase 1                               | 001                | `feat/002-runtime-infrastructure` | PENDING | —  | —            | —             | Production-shaped local/CI infrastructure                                      |
| 003 | Laravel API shell, authentication and single-device enforcement | Phase 2                               | 002                | `feat/003-api-auth`               | PENDING | —  | —            | —             | May be split if final plan separates API foundation and auth                   |
| 004 | DDD foundation and shared primitives                            | Phase 3                               | 003                | `feat/004-shared-primitives`      | PENDING | —  | —            | —             | Money, time, errors, audit, IDs                                                |
| 005 | Identity, financial accounts, categories and onboarding backend | Phase 4                               | 004                | `feat/005-accounts-categories`    | PENDING | —  | —            | —             | Reconcile exact final phase name                                               |
| 006 | Currency Core                                                   | Revised Currency Core Phase           | 005                | `feat/006-currency-core`          | PENDING | —  | —            | —             | Must precede ledger                                                            |
| 007 | Transaction aggregate and double-entry ledger                   | Revised Ledger Phase                  | 006                | `feat/007-double-entry-ledger`    | PENDING | —  | —            | —             | Includes approved FX-aware accounting invariant                                |
| 008 | Budget engine                                                   | Revised Budget Phase                  | 007                | `feat/008-budget-engine`          | PENDING | —  | —            | —             | Includes rollover, underflow, reallocation, borrowing, historical compensation |
| 009 | Receipt storage and OCR infrastructure                          | Revised OCR Phase                     | 007, 002           | `feat/009-receipt-ocr`            | PENDING | —  | —            | —             | Exact split may change after final plan reconciliation                         |
| 010 | Merchant and item normalization                                 | Revised Normalization Phase           | 009                | `feat/010-normalization`          | PENDING | —  | —            | —             | May split merchant and item normalization if large                             |
| 011 | Duplicate detection                                             | Revised Normalization/Duplicate Phase | 009, 010           | `feat/011-duplicate-detection`    | PENDING | —  | —            | —             | User-controlled candidate resolution                                           |
| 012 | FX provider operations and rate lifecycle                       | Revised FX Operations Phase           | 006, 007           | `feat/012-fx-operations`          | PENDING | —  | —            | —             | Provider jobs, stale policies, overrides                                       |
| 013 | Offline synchronization API contract                            | Revised Sync Phase                    | 003, 007, 009      | `feat/013-sync-contract`          | PENDING | —  | —            | —             | Includes cursor expiry/full resync                                             |
| 014 | Dashboard and forecasting                                       | Revised Insights Phase                | 007, 008, 012      | `feat/014-dashboard-forecasting`  | PENDING | —  | —            | —             | Product formulas must be approved before implementation                        |
| 015 | Notifications backend                                           | Revised Insights/Notification Phase   | 014                | `feat/015-notifications`          | PENDING | —  | —            | —             | Exact MVP notification channels must match approved plan                       |
| 016 | Reports and exports                                             | Revised Reporting Phase               | 007, 008, 012, 014 | `feat/016-reports-exports`        | PENDING | —  | —            | —             | PDF, XLSX, CSV, JSON/full export                                               |
| 017 | Security, performance and operations hardening                  | Revised Hardening Phase               | 001-016            | `feat/017-release-hardening`      | PENDING | —  | —            | —             | RPO/RTO, retention, performance, restore drills                                |
| 018 | MVP release validation                                          | Final Release Phase                   | 017                | `feat/018-mvp-release-validation` | PENDING | —  | —            | —             | No product features; final gates/runbooks                                      |
| 999 | Progress finalization                                           | Administrative                        | 001-018            | `feat/999-progress-finalization`  | PENDING | —  | —            | —             | Use only after final feature merge if needed                                   |

---

# Current Feature

Feature ID: NONE

Feature: NONE

Branch: `dev`

Status: NOT STARTED

Started: —

PR: —

Blocker: —

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

Status: PENDING

Branch: `feat/001-project-baseline`

Implementation plan coverage: TBD after final reconciliation

PR: —

Merge commit: —

Tests executed: —

Checklist: —

Notes: —

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
