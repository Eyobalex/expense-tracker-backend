# Next-Gen Financial Tracker — Implementation Guidelines

Status: Development handoff draft based on Next-Gen_Financial_Tracker_Development_Ready_PRD_v1.0.docx (v1.0, 11 August 2026)

This document turns the PRD into an executable engineering workflow. The PRD remains the product authority.

## 1. Non-negotiable engineering principles

1. Laravel owns all authoritative accounting, budgeting, FX conversion, duplicate decisions, normalization, forecasting, reporting, and authorization.
2. Every posted money movement is an immutable, balanced double-entry journal entry. Corrections are reversal plus replacement entries.
3. Money uses exact arithmetic. Never use binary floating point for money, rates, balances, budget limits, or report totals.
4. OCR creates a stored, reviewable draft. It never posts directly.
5. Preserve the original receipt image, raw OCR response, parsed fields, normalized entities, user changes, and posted journal.
6. All mutating mobile requests are retry-safe through idempotency keys.
7. Offline records are visibly different from posted records. Local drafts never affect authoritative balances.
8. Historical meaning is immutable: lock used FX rates, snapshot budget periods, preserve raw labels, and retain reversal links.
9. Authorization is enforced on every resource and action, including UUID-addressed resources and private files.
10. Build vertical slices with tests and observability before adding breadth.

## 2. Recommended Laravel structure

Use Laravel conventions with domain boundaries independent of controllers and transport details.

    app/
      Domain/
        Accounting/       journals, posting, reversal, money/value objects
        Budgeting/        periods, reset, rollover, reallocation, borrowing
        Currency/         currencies, rates, conversion, rounding
        Transactions/     lifecycle, splits, duplicate detection
        Receipts/         attachments, OCR orchestration, reconciliation
        Catalog/          merchant/item normalization and suggestions
        Insights/         forecasts, concentration, price intelligence
        Reporting/        filters, read models, export generation
        Sync/             idempotency, versions, conflicts, tombstones
      Application/        use-case services/commands and DTOs
      Http/Controllers/Api/V1/
      Http/Requests/Api/V1/
      Http/Resources/Api/V1/
      Jobs/
      Policies/
      Models/
      Support/
    database/migrations/
    database/factories/
    database/seeders/
    routes/api.php
    tests/Unit/Domain/
    tests/Feature/Api/V1/

Controllers authenticate, validate, authorize, invoke one application service, and serialize. They must not calculate balances, compose journal lines, or implement budget formulas. This is a modular monolith, not a distributed microservice system.

## 3. Delivery sequence

### Phase 0 — decisions and foundation (P0)

- Confirm the open architectural decisions in TECHNICAL_SPECIFICATION.md.
- Configure PostgreSQL for local development, CI, testing, staging, and production. Automated tests use isolated PostgreSQL databases; do not use SQLite as a test substitute.
- Use Redis for queues, cache, rate limiting, idempotency locks, and distributed job coordination.
- Use MinIO through Laravel's S3-compatible filesystem adapter for development and MVP deployment. Keep storage access behind Laravel's filesystem abstraction so migration to S3 is configuration-only.
- Add API versioning, authentication, request IDs, structured errors, policies, rate limits, and idempotency middleware.
- Add UUID generation, exact money/rate value objects, currency metadata, timezone handling, and audit conventions.
- Configure private object storage, queue workers, failed-job handling, and secrets management.
- Run free, self-hosted PaddleOCR PP-OCRv6 on the VPS behind an internal-only network boundary. Laravel communicates through an OCR adapter so the engine can later be replaced without changing the receipt domain.
- Establish CI for formatting, static analysis, unit/feature tests, migrations, frontend checks, and security scans.

### Phase 1 — ledger vertical slice (P0)

Deliver online manual expense, income, transfer, refund, and opening balance end to end:

1. User/base currency/timezone and financial accounts.
2. Categories and account mappings.
3. Transaction drafts and splits.
4. One PostTransaction service that validates, balances, and commits a journal atomically.
5. Idempotent posting and optimistic version checks.
6. Transaction history and account balance projections.
7. Reversal/correction workflow.

Do not start OCR or offline posting until this slice has invariant, authorization, idempotency, and correction tests.

### Phase 2 — budget engine (P0/P1)

- Create category configuration and monthly budget_period snapshots.
- Implement the authoritative order: base reset, borrowing deduction, positive rollover, negative carry, reallocations, effective limit.
- Store adjustment records rather than deriving closed history from current settings.
- Add reallocation and one-immediate-next-month full-limit borrowing.
- Add dashboard budget totals and month-boundary/timezone tests.

### Phase 3 — receipt and OCR pipeline (P1)

- Validate MIME, size, checksum, ownership, and image integrity before storage.
- Persist the original image privately before dispatching OCR.
- Queue OCR; record attempts, provider status, raw response, parsed values, confidence, and failure reason.
- Create Pending Review, never Posted, output.
- Provide purchase and transfer/payment review, reconciliation, and explicit Unitemized / Other lines.

### Phase 4 — normalization and duplicates (P1)

- Build user-scoped merchant suggestions.
- Preserve raw OCR strings while linking canonical merchants/items.
- Store compatible unit and pack-size dimensions.
- Implement weighted duplicate candidates and explicit user resolution.

### Phase 5 — currency and FX (P1)

- Add currency registry, daily reference rates, provider retries, stale-rate policy, and audit metadata.
- Lock used_rate, rate date/source, original amount, and base amount at posting.
- Add cross-currency transfer amounts and explicit FX/fee lines.

### Phase 6 — offline synchronization (P0/P1)

- Define the Flutter local schema from server DTOs; use client UUIDs and operation IDs.
- Add sync push/pull, operation status, tombstones, and conflicts.
- Require dependency ordering or temporary client IDs.
- Return conflicts rather than last-write-wins for financial records.
- Test offline draft → reconnect → idempotent post and stale-version conflict resolution.

### Phase 7 — insights and reports (P1/P2)

- Build server-side read models for dashboard, safe-to-spend, projections, income concentration, and item prices.
- Use queue jobs for large PDF/XLSX/JSON exports; stream small CSV exports.
- Secure report files, expire them, and record filter/base-currency context.

### Phase 8 — hardening and release (P0)

- Run large-user/100,000-transaction performance tests and EXPLAIN critical queries.
- Verify authorization for every resource and private attachment.
- Test backups/restores, queue retries, stale FX, OCR failure, month boundaries, leap days, DST/timezone behavior, and deletion.
- Complete all PRD release gates before production traffic.

## 4. Domain implementation rules

### Accounting

- Construct journal lines in a domain service; never accept arbitrary debit/credit lines from the client.
- Validate line currencies and conversion/rounding before persistence.
- Enforce total debits equal total credits in code and, where practical, with database consistency checks.
- Commit transaction aggregate, journal header, lines, splits, and audit event in one database transaction.
- Use a unique user-scoped idempotency operation record.
- Posted journal rows are append-only. Restrict update/delete in policies and database roles.

### Budgets

- A budget is not an account and never changes a ledger balance.
- Snapshot base limit, borrow deduction, rollover, negative carry, reallocations, effective limit, actual, remaining, and status.
- Make monthly initialization idempotent on category and period and serialize competing initializers.
- Never use current category configuration to rewrite a closed period.

### Money and currency

- Store currency code and amount together.
- Recommended representation: integer minor units for amounts, currency exponent from the currency registry, and high-precision decimal for rates/intermediates.
- Store original amount, locked used rate, reference rate if different, rate date/source, override reason, and base amount.
- Amounts are positive; transaction type and debit/credit direction determine accounting meaning.

### Receipts and OCR

- Private object keys are unguessable and user-scoped. Use authorized streaming or short-lived signed URLs.
- Check file bytes, not only extension. Enforce configured size and image-dimension limits.
- OCR jobs are idempotent and safe to retry.
- Keep raw provider output separate from normalized extraction. Redact provider secrets and payloads in logs.
- Reconciliation accounts for subtotal, discount, tax, fees, tip, and rounding. Mismatch blocks posting until fixed or explicitly represented.

### Normalization and duplicate detection

- Normalize for search only; never overwrite original OCR/import text.
- Scope suggestions to the user unless a governed global catalog is approved.
- Store normalization algorithm/version on derived records.
- Duplicate detection warns and never discards. Exact API retries are the only silent deduplication.

### Sync

- Every client mutation carries operation ID/idempotency key, client timestamp, device ID, and expected server version where applicable.
- Server responses include authoritative IDs, versions, timestamps, status, and reconciliation results.
- Use tombstones or archived records for deletions that clients must observe.
- Never count local_draft, pending_sync, or conflict as posted financial truth.

## 5. API conventions

- Prefix mobile APIs with /api/v1.
- Use resource routes for CRUD and action routes for transitions: /post, /reverse, /retry-ocr, /borrow-next-month.
- Success envelope: {data, meta, links}. Error envelope: {error: {code, message, fields, request_id}}.
- Require Idempotency-Key on create/post/reverse/correction, upload, budget adjustment, and sync push.
- Use HTTP 409 for stale versions and domain conflicts; 422 for validation/reconciliation; 202 for queued work.
- Paginate all collections; cap page size; use cursor pagination for large histories.
- Return ISO-8601 dates with timezone/offset and server timestamps.

## 6. Testing strategy

### Unit and property tests

- Money arithmetic, currency exponents, rounding, and FX conversion.
- Journal balancing and canonical postings.
- Reversal/correction links and immutability.
- Budget formula, reset order, rollover, negative carry, reallocation, borrowing.
- Merchant/item normalization and compatible unit comparison.
- Duplicate scoring, forecast, and concentration formulas.

Generate randomized journal inputs to assert the balancing invariant and randomized month settings to assert reset-order reproducibility.

### Feature and integration tests

- Authentication, token/session revocation, policies, ownership scopes, and rate limits.
- Idempotent retries and stale-version conflicts.
- Receipt authorization, queue retry, OCR failure/manual fallback, and private download.
- Exchange provider success/failure/stale fallback and locked historical rates.
- Report filter parity and secure asynchronous downloads.
- Rollback when any journal line or adjustment fails.

### Client and contract tests

- API schema/DTO fixtures shared with Flutter.
- Offline queue states, dependency ordering, retry, tombstone convergence, and conflict UX.
- Camera/gallery permission, image replacement, pending review, and receipt-type correction.

## 7. Observability and operations

- Include request ID and non-sensitive user/device identifiers in structured logs.
- Track API latency/error rate, posting, idempotency hits, conflicts, OCR latency/retry, queue depth/age, FX freshness, report duration, and storage failures.
- Alert on failed ledger posts, failed budget initialization, stale FX, failed jobs, and OCR/report backlog.
- Trace request → job → provider/storage/database where supported.
- Prohibit tokens, receipt bytes, raw OCR, and full exports in logs/telemetry.

## 8. Release checklist

- [ ] PostgreSQL migrations and indexes applied to a clean database.
- [ ] Test suite runs against an isolated PostgreSQL database and passes without SQLite-specific behavior.
- [ ] Redis queue, cache, rate-limit, and lock behavior verified.
- [ ] MinIO private bucket policy and S3-compatible storage abstraction verified.
- [ ] Auth, ownership policies, private file delivery, and rate limiting verified.
- [ ] Ledger invariant, idempotency, reversal, and correction gates pass.
- [ ] Budget reset/borrowing boundary tests pass in supported timezones.
- [ ] FX rate lock and stale provider policy verified.
- [ ] OCR retention, retry, reconciliation, and manual fallback verified.
- [ ] Offline sync and conflict tests pass; unsynced data excluded from totals.
- [ ] CSV/XLSX/PDF/JSON exports reconcile and expire securely.
- [ ] Backup restore, queue retry, failed-job, and deletion runbooks exist.
- [ ] 100,000-transaction/user baseline measured.
- [ ] Product analytics and audit events are documented and privacy-reviewed.


## 9. Approved infrastructure and product decisions

- Use Laravel Sanctum bearer tokens. MVP enforces one active device session: a new-device login revokes all previous tokens and emits forced-logout state to those clients.
- Use PostgreSQL for local development, CI, isolated automated test databases, staging, and production.
- Use Redis for queues, cache, rate limits, idempotency, and distributed locks.
- Use MinIO through the S3-compatible filesystem abstraction; migration to S3 must require configuration changes only.
- Use free self-hosted PaddleOCR PP-OCRv6 behind a private VPS network boundary. Keep OCR behind an adapter and persist model/version metadata.
- Deploy to a VPS with supervised Laravel workers/scheduler, PostgreSQL, Redis, MinIO, PaddleOCR, TLS, encrypted backups, monitoring, and firewall isolation.
- Implement in-app, Android local, Firebase push, and email notifications behind retryable adapters.
- Seed retention/deletion/report/OCR/audit/backup settings in MVP; make them configurable through the post-MVP admin dashboard.
- Defer the admin dashboard and multi-device concurrency until after MVP.
