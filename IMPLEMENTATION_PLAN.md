# Next-Gen Financial Tracker — Implementation Plan

Status: Approved implementation sequence
Source of truth: IMPLEMENTATION_GUIDELINES.md, TECHNICAL_SPECIFICATION.md, and the PRD
Architecture: Laravel modular monolith, DDD boundaries, PostgreSQL, Redis, MinIO, self-hosted PaddleOCR PP-OCRv6, backend API

## How to use this plan

Implement phases in order. A phase is complete only when its checklist is satisfied, its affected tests pass against PostgreSQL, its authorization and failure paths are covered, and its documentation/observability requirements are complete.

Do not duplicate business logic in API consumers. Laravel remains authoritative for ledger posting, budget calculations, FX conversion, duplicate decisions, normalization, forecasting, reports, and authorization.

Every implementation task must follow the generated Laravel Boost AGENTS.md:

- Inspect installed package versions before relying on package APIs.
- Search version-specific Laravel documentation before code changes.
- Use Artisan generators with --no-interaction for Laravel files.
- Use Pest closure tests and factories.
- Run affected tests and Pint after PHP changes.
- Inspect database schema before creating migrations/models.
- Use the approved DDD directories instead of placing domain logic in controllers/models.

## Target structure

Use the approved DDD structure while retaining Laravel conventions:

    app/
      Domain/
        Accounting/
        Budgeting/
        Currency/
        Transactions/
        Receipts/
        Catalog/
        Insights/
        Reporting/
        Sync/
      Application/
      Actions/
      Http/
        Controllers/Api/V1/
        Requests/Api/V1/
        Resources/Api/V1/
      Jobs/
      Models/
      Policies/
      Providers/
      Support/
    database/
      factories/
      migrations/
      seeders/
    routes/api.php
    tests/
      Unit/Domain/
      Feature/Api/V1/
      Integration/
    services/ocr/              # only if the OCR service is kept in this repository

Eloquent models stay in app/Models. Controllers validate/authorize/delegate. Domain services and value objects own business rules. Application services orchestrate use cases. Jobs invoke application services and are retry-safe.

## Global definition of done

A change is not complete until all applicable items are true:

- [ ] Business behavior is implemented authoritatively in Laravel, not duplicated in API consumers.
- [ ] Input validation, ownership policy, authorization, and rate limits are present.
- [ ] Database migration, indexes, constraints, factories, and seed data are present where needed.
- [ ] Idempotency and optimistic concurrency behavior is defined for mutations.
- [ ] Happy path, validation failure, authorization failure, retry, conflict, and boundary tests exist.
- [ ] Pest tests run successfully against an isolated PostgreSQL database.
- [ ] Pint, static analysis, and relevant API contract checks pass.
- [ ] Logs and telemetry contain request/job correlation but no tokens, raw images, raw OCR, or full financial payloads.
- [ ] API resources, error codes, and documentation are updated.
- [ ] The phase checklist and release notes are updated.

---

## Blocking decision gates

The source documents do not define values for the following items. They must be approved before the listed phase exits; implementation must not invent semantics.

- **Before Phase 8:** OCR languages/scripts, currency symbols, numeric separators, date/time formats, and timezone locale strategy.
- **Before Phase 10:** exchange-rate provider, supported pairs, quotas, and stale-rate threshold.
- **Before Phase 12:** safe-to-spend, month-end projection, income concentration, and item inflation/deflation formulas.
- **Before Phase 14:** concrete retention values for reports, full exports, OCR artifacts, derivatives, abandoned receipts, audits, deleted accounts, backups, tombstones, and failed jobs; approved PostgreSQL/MinIO/API/queue/OCR RPO/RTO values.

Each unresolved item must be labeled PRODUCT/OPERATIONS DECISION REQUIRED BEFORE PHASE N in its checklist.

---

## Phase 0 — project governance and baseline

Goal: establish the development contract before adding business code.

Dependencies: none.

### Steps

1. Confirm that the approved DDD directory introduction is recorded as a durable project rule using the Boost rule-recording mechanism.
2. Confirm the approved decisions: Sanctum, PostgreSQL everywhere, Redis, MinIO, self-hosted PaddleOCR PP-OCRv6, VPS deployment, all notification channels, configurable seeded settings, and single-device login.
3. Inspect the installed package versions with Composer and package.json. Do not assume APIs from another Laravel version.
4. Use Boost search-docs for authentication, API resources, queues, filesystem, PostgreSQL testing, notifications, and rate limiting.
5. Establish coding conventions from sibling files in app, tests, routes, and config.
6. Define the initial API version as /api/v1 and the stable response/error envelope.
7. Define request correlation IDs, error codes, status values, enum naming, timezone format, and server timestamp conventions.
8. Create a decision log entry for any implementation choice that does not alter PRD behavior.

### Checklist

- [ ] DDD directory approval is recorded.
- [ ] No unresolved architecture decision remains for MVP.
- [ ] Installed package versions are recorded.
- [ ] API v1 conventions and error envelope are documented.
- [ ] Naming, enum, money, timezone, and UUID conventions are documented.
- [ ] Boost documentation searches were performed before implementation begins.
- [ ] CI commands and required local services are documented.

Exit criteria: a new engineer can create a correctly structured class, migration, endpoint, and Pest test without making architectural decisions.

---

## Phase 1 — PostgreSQL, Redis, MinIO, and local runtime

Goal: make the production-shaped infrastructure reproducible locally and in CI.

Dependencies: Phase 0.

### Steps

1. Configure PostgreSQL as the default connection for local development, CI, staging, and production.
2. Define isolated test database/schema strategy. Tests must not share state with development databases.
3. Configure Redis for cache, queues, rate limits, idempotency, and distributed locks.
4. Configure MinIO using Laravel's S3-compatible filesystem disk:
   - private bucket;
   - separate prefixes for receipts, OCR artifacts, and report files;
   - no public object access;
   - short-lived authorized download mechanism;
   - checksums and ownership metadata.
5. Add environment validation for required database, Redis, storage, queue, and app secrets.
6. Configure queue worker, scheduler, failed-job storage, retry/backoff, and health checks.
7. Add local service documentation and a VPS service topology.
8. Configure CI service containers or managed test services for PostgreSQL and Redis.
9. Add encrypted backup/restore commands or runbook placeholders for PostgreSQL and MinIO.

### Checklist

- [ ] Application boots using PostgreSQL without SQLite-specific assumptions.
- [ ] Tests create/use an isolated PostgreSQL database or schema.
- [ ] Redis cache, queue, rate limiting, and locks work.
- [ ] MinIO private upload, read, and delete behavior works.
- [ ] Unauthorized object access is rejected.
- [ ] Queue failure and retry behavior is observable.
- [ ] Required environment variables fail fast with actionable errors.
- [ ] CI starts PostgreSQL and Redis and runs a smoke test.
- [ ] Backup and restore procedures are documented.

Exit criteria: infrastructure can run the application and a representative queued test locally and in CI.

---

## Phase 2 — Laravel foundation, API shell, and authentication

Goal: secure API access and establish shared backend infrastructure.

Dependencies: Phase 1.

### Steps

1. Add Laravel Sanctum using the approved dependency change and verify its installed version.
2. Implement registration, login, logout, token revocation, password recovery, and authenticated user profile endpoints.
3. Implement device registration with device ID, platform, app version, last-seen timestamp, and revoked state.
4. Enforce single-device login:
   - on successful login, revoke all previous tokens for the user;
   - create the new device/session record;
   - return the new token and device state;
   - ensure old clients receive an authenticated failure/forced-logout response.
5. Add request ID middleware and a consistent JSON error renderer.
6. Add API v1 route grouping, authentication middleware, throttling, and content negotiation.
7. Add ownership policies and a shared user-scoped query scope pattern.
8. Add version/ETag or expected-version handling for mutable resources.
9. Add idempotency middleware/storage for mutating operations, including response replay.
10. Add API Resources and request DTO conventions.

### Checklist

- [ ] Unauthenticated requests cannot access protected routes.
- [ ] A new-device login invalidates all previous device tokens.
- [ ] Logout and explicit device revocation work.
- [ ] Password recovery does not bypass authorization.
- [ ] User A cannot access User B resources by UUID.
- [ ] Validation errors use the agreed envelope and field map.
- [ ] Rate limits cover auth and expensive endpoints.
- [ ] Idempotent retries replay the original response without duplicate side effects.
- [ ] Stale mutable-resource versions return HTTP 409.
- [ ] Pest feature tests cover auth success/failure, token revocation, ownership, throttling, and idempotency.

Exit criteria: a protected, versioned, retry-safe API shell exists and is tested.

---

## Phase 3 — DDD foundation and shared primitives

Goal: create reusable domain infrastructure before financial features.

Dependencies: Phase 2.

### Steps

1. Create approved DDD namespaces/directories with Artisan generators where applicable.
2. Implement typed value objects and services for:
   - money minor units;
   - currency code and exponent;
   - exact decimal exchange rates;
   - rounding modes;
   - UUID/operation IDs;
   - timezone-aware period boundaries.
3. Define PHP enums with TitleCase keys and explicit serialization values for transaction states, source types, account types, journal types, budget adjustments, job states, and sync statuses.
4. Define domain exceptions and map them to stable API error codes.
5. Define audit event contracts and redaction rules.
6. Define application command/query conventions and dependency injection boundaries.
7. Define clock/time provider abstraction for deterministic month-boundary tests.
8. Define event/listener conventions for projections and notifications without making events the source of truth.
9. Add static-analysis configuration for the DDD namespaces.

### Checklist

- [ ] Money operations use exact arithmetic and never float.
- [ ] Currency exponent and rounding rules are centralized.
- [ ] Period/timezone calculations are deterministic under injected clocks.
- [ ] Domain exceptions serialize to stable error codes.
- [ ] Enums follow project naming rules.
- [ ] Audit payloads redact sensitive values.
- [ ] Unit tests cover money, rate, rounding, UUID, and timezone primitives.
- [ ] Static analysis recognizes all new namespaces.

Exit criteria: all later domains can use shared primitives without reimplementing money, time, error, or audit behavior.

---

## Phase 4 — identity, accounts, categories, and seed data

Goal: establish the financial structure required by every transaction.

Dependencies: Phase 3.

### Steps

1. Create users/settings extensions for base currency, timezone, budget timezone, onboarding state, and notification preferences.
2. Create devices if not completed in Phase 2.
3. Create financial accounts with user-facing types, native currency, internal accounting mapping, archive state, and version.
4. Create categories with hierarchy, expense/income kind, active state, and budget configuration.
5. Define stable internal ledger-account mappings for financial accounts, category expense accounts, income/revenue accounts, equity/opening balance, fees, and refund/reversal accounts.
6. Add starter account/category seeders.
7. Create factories and useful factory states for all models.
8. Implement account/category APIs, archive/restore behavior, and authorization.
9. Prevent hard deletion of accounts/categories referenced by posted history.
10. Add onboarding endpoints for base currency, timezone, starter accounts, and starter categories.
11. Enforce immutable currency rules: user base currency may change only before any posted transaction; after posted history it returns a stable domain error. Account native currency cannot change after posted journal history; a different-currency account is required.

### Checklist

- [ ] Account currencies and category ownership are enforced.
- [ ] Archived accounts/categories remain visible historically but cannot receive new allocations.
- [ ] Starter data is idempotently seeded.
- [ ] Opening-balance configuration is represented.
- [ ] Parent/category hierarchy rules are validated.
- [ ] Factories support all transaction/budget test scenarios.
- [ ] API resources include version and archive state.
- [ ] Cross-user access tests pass.
- [ ] Base-currency mutation is rejected after posted history.
- [ ] Account-currency mutation is rejected after posted history.
- [ ] Currency-lock validation and authorization tests pass.

Exit criteria: a user can complete onboarding and create valid accounts/categories ready for posting.

---

## Phase 5 — Currency Core

Goal: establish all currency and conversion concepts before any transaction or journal posting.

Dependencies: Phase 4.

### Steps

1. Create the supported-currency registry with ISO codes, display names, minor-unit exponents, symbols, and active state.
2. Define user base/functional currency, account native currency, and transaction currency.
3. Define exact decimal exchange-rate representation, precision, scale, rounding mode, and tolerance.
4. Define reference_rate, used_rate, historical rate date, provider/source, override reason, and immutable base_amount.
5. Implement a provider-independent historical rate lookup interface supporting exact pair/date lookup, latest-valid fallback, stale/unavailable results, and manual used-rate overrides.
6. Define the rate-locking contract: used rate and base amount are immutable after posting; later provider updates never rewrite history.
7. Define cross-currency journal requirements: native/account amount and currency, functional/base amount and currency, applicable rate/date/source, and functional balancing.
8. Define dedicated fee, realized FX gain/loss, and FX rounding ledger accounts. Differences within tolerance post to FX rounding; differences beyond tolerance post to explicit realized FX gain/loss. No hidden imbalance or silent user-amount mutation.
9. Define settings and validation for supported pairs, exponents, and rounding.

### Checklist

- [ ] Currency registry and exponents are seeded.
- [ ] Base, account, transaction, reference, used, historical-date, and base-amount concepts are modeled.
- [ ] Rate precision, rounding, and tolerance are centralized.
- [ ] Provider-independent historical lookup exists.
- [ ] Used rates and base amounts are lockable and immutable after posting.
- [ ] Native and functional journal amount requirements are documented.
- [ ] Realized FX gain/loss, FX rounding, and fee accounts are defined.
- [ ] Account/base currency mutation rules are tested.
- [ ] Conversion, precision, rounding, and rate-lock unit/property tests pass.

Exit criteria: the ledger can depend on a complete Currency Core without waiting for an external rate provider.

---

## Phase 6 — transaction aggregate and double-entry ledger

Goal: deliver the first complete financial vertical slice with exact functional-currency balancing and durable transaction semantics.

Dependencies: Phase 5.

### Functional-currency accounting invariant

Every posted journal entry must satisfy:

    SUM(functional/base debits) = SUM(functional/base credits)

Every journal line retains its native/account amount and currency when it affects an account, plus a functional/base amount and currency. Rate, date, source, used rate, and rounding metadata are retained for converted lines. Native amounts drive native account balances; functional amounts drive the balancing invariant and base reporting.

For cross-currency transfers, retain sent/source and received/destination native amounts, convert both using one locked effective rate, add explicit fee lines, and post any difference within tolerance to a dedicated FX rounding account. A difference beyond tolerance posts to an explicit realized FX gain/loss account. MVP does not perform periodic account revaluation. No hidden imbalance or user-amount mutation is allowed.

### Transaction-type inclusion matrix

| Type | Account balance | Expense | Income | Budget spending | Cash flow | Forecast |
|---|---|---|---|---|---|---|
| Expense | asset decreases or liability increases | yes | no | yes | outflow | expense |
| Income | asset increases or liability decreases | no | yes | no | inflow | income |
| Same/cross-currency transfer | source down, destination up | no | no | no | internal transfer/net zero | no expense/income |
| Credit-card purchase | liability increases | yes | no | yes | no bank outflow at purchase | expense |
| Credit-card repayment | liability and bank decrease | no | no | no | cash outflow/debt service | cash/debt only |
| Refund | receiving account increases | reduces linked expense | no | reduces linked budget | inflow | expense reduction |
| Fee | account decreases or liability increases | yes when expense fee | no | yes when categorized | outflow | expense |
| Opening balance | account/equity changes | no | no | no | excluded from operating flow | no |
| Adjustment | explicit subtype only | no generic default | no generic default | never; use budget adjustment | subtype-defined | subtype-defined |

A credit-card purchase counts once as expense. Repayment is a liability/account transfer, not another expense. Transfers do not inflate income/expense. Opening balances do not count as income.

### Adjustment semantics

Only an authorized account owner may create a user-facing balance correction; privileged system services may create financial-system adjustments through an explicit audited command. Every adjustment requires an explicit subtype and mandatory reason. A balance_correction may correct an account balance and affects balances/cash-flow classification but not expense/income/budget by default. A financial_system_adjustment is system-generated and requires audit evidence. Expense/income corrections must use their normal transaction/correction workflows. Budget adjustments are separate records and never journal lines. A generic adjustment endpoint cannot accept arbitrary debit/credit lines or bypass authorization, currency, balancing, or immutability.

### Database defense-in-depth

Add constraints for positive line amounts, exactly one debit/credit side, valid currency references, required functional fields for posted lines, conditional non-null posted fields, reversal/correction uniqueness, ownership-consistent relationships where feasible, and posted-journal immutability protections. Keep complex orchestration in Laravel rather than triggers.

### Steps

1. Create transaction, transaction split, journal entry, journal line, and idempotency tables with constraints and indexes.
2. Create transaction states: draft, pending_review, posted, reversed, sync_conflict.
3. Implement manual transaction creation for expense, income, transfer, refund, adjustment, and opening balance.
4. Validate required fields: UUID, type, date/time/timezone, original amount/currency, account(s), splits/category, optional merchant/notes, source, version.
5. Implement split reconciliation, including tax, fee, discount, and rounding lines.
6. Implement canonical journal builders:
   - expense;
   - income;
   - same-currency transfer;
   - cross-currency transfer;
   - credit-card purchase;
   - credit-card repayment;
   - refund;
   - opening balance.
7. Implement PostTransaction as one database transaction:
   - authorize;
   - resolve idempotency;
   - lock and version-check;
   - validate;
   - build native and functional journal;
   - assert functional debit equals functional credit;
   - persist aggregate/journal/audit;
   - update projections;
   - commit.
8. Make posted journals append-only through policies and database permissions.
9. Implement reversal and correction as linked immutable entries.
10. Implement transaction history with cursor pagination and server-side filtering.
11. Add account balance projections/read queries from posted journals only.
12. Add API resources and action routes.

### Checklist

- [ ] Every posted journal balances exactly.
- [ ] Unbalanced journals cannot persist.
- [ ] Posting is atomic; partial journal writes roll back.
- [ ] Same idempotency key cannot create a second posting.
- [ ] Draft/pending records can be edited/deleted only before posting.
- [ ] Posted records cannot be directly edited/deleted.
- [ ] Reversal creates equal/opposite linked journal.
- [ ] Correction preserves original, reversal, replacement, actor, and reason.
- [ ] Account balances exclude local/pending/conflict records.
- [ ] Expense, income, transfer, refund, adjustment, and opening balance scenarios pass.
- [ ] Authorization, stale version, invalid split, invalid account, and retry tests pass.
- [ ] Property tests exercise randomized balanced/unbalanced journal inputs.
- [ ] Functional-currency cross-currency balancing and rounding tests pass.
- [ ] Native account amounts remain available for balances.
- [ ] Transaction inclusion matrix prevents report/budget double counting, verified by report and budget integration tests.
- [ ] Adjustment subtype, reason, authorization, and audit tests pass.
- [ ] Database constraint and migration integration tests pass.
- [ ] API filters and pagination are index-backed.

Exit criteria: the online manual ledger vertical slice is production-safe. Do not begin OCR or offline posting before this checklist is complete.

---

## Phase 7 — budget engine, lazy initialization, and historical compensation

Goal: implement deterministic monthly budgets, lazy initialization, borrowing reservations, refunds, and auditable historical compensation.

Dependencies: Phase 6.

### Shared initialization service

EnsureBudgetPeriodExists is the single application service used by both the monthly scheduler and first-use API reads. It uses the same locks, deterministic reset logic, snapshots base_limit and budget timezone, persists period_start_at and period_end_at, and is idempotent under concurrent calls. Scheduled initialization remains required, but API reads initialize a missing current period safely.

### Late and backdated budget compensation

A backdated expense, income allocation, refund, reversal, correction, or split/category change affecting an initialized or closed period must not silently rewrite later budget history. RecalculateBudgetChain locks the affected historical period and all propagated periods, recomputes actual/rollover/carry effects, compares previous propagated adjustments with corrected values, and creates auditable compensating adjustments. Original snapshots and adjustments remain immutable and each correction links to its source transaction, revision, and prior adjustment. If August originally propagated +2,000 to September but a late expense changes the correct value to +500, preserve +2,000 and create a -1,500 rollover correction in September, continuing through later propagated periods as necessary. The operation is idempotent.

### Borrowing snapshot and atomic reservation

Borrowing freezes the full base_limit at confirmation time. Persist category, source period, target period, borrow amount, base_limit snapshot, actor, confirmation timestamp, operation/idempotency key, and linked adjustment IDs. The action locks both periods and creates two linked adjustments in one PostgreSQL transaction: BORROWING_IN positive in the current period and BORROWING_RESERVED negative in the immediate target period. A database uniqueness constraint on the category, target period, and reservation type prevents duplicate or concurrent borrowing. Partial borrowing is impossible; later category changes never change the stored amount.

### Refund budget behavior

Refunds should link to the original transaction/category. A same-period full or partial refund reduces actual spending in that period. A later-period refund invokes RecalculateBudgetChain and creates compensating adjustments rather than rewriting closed snapshots. Preserve the original transaction, refund, category, affected period, and correction chain.

### Budget timezone changes

Persist budget timezone, period_start_at, and period_end_at on each initialized period. A timezone change applies only to the next period that is not initialized; historical/initialized periods never change boundaries.

### Steps

1. Create budget_periods and budget_adjustments tables with uniqueness and audit fields.
2. Add category budget configuration: base limit, rollover, overspend carry, borrowing, currency, active state.
3. Implement idempotent monthly initialization with locking and user budget timezone.
4. Apply the mandatory order:
   1. reset to base_limit snapshot;
   2. apply borrowing deduction;
   3. apply positive rollover;
   4. apply negative carry;
   5. apply reallocations;
   6. persist effective_limit.
5. Persist all period inputs/outputs needed to reproduce historical reports.
6. Calculate actual spent from posted ledger allocations only.
7. Implement paired mid-month reallocations.
8. Implement one immediate-next-month full-base-limit borrowing action.
9. Reject second borrow for the same category/target period and prevent chained/future borrowing.
10. Add period close/correction behavior.
11. Add budget API endpoints and server-calculated dashboard totals.
12. Implement `EnsureBudgetPeriodExists` as the shared scheduler/first-use initialization service.
13. Implement `RecalculateBudgetChain` for late/backdated transactions, refunds, reversals, corrections, and split/category changes.
14. Implement confirmation-time borrowing snapshots and the atomic source/target reservation transaction.
15. Implement original-transaction refund links and same-period versus later-period budget behavior.
16. Persist budget timezone and period boundaries and apply timezone changes only to uninitialized future periods.

### Checklist

- [ ] Base limit resets from a historical/current configuration snapshot.
- [ ] Borrowing deduction occurs before rollover/underflow.
- [ ] Positive rollover and negative carry follow the exact order.
- [ ] Reallocations are paired and auditable.
- [ ] Borrowing never changes base_limit.
- [ ] Second borrow and chain borrowing are rejected.
- [ ] Closed periods remain historically stable after category edits.
- [ ] Initialization is idempotent under concurrent execution.
- [ ] Actual spend includes posted transactions only.
- [ ] Month boundary, timezone, leap-year, and DST tests pass.
- [ ] Budget formula, borrowing example, and correction tests pass.
- [ ] Scheduler reruns are idempotent.
- [ ] Scheduler failure is recovered by first-use initialization.
- [ ] Concurrent first-use initialization creates one period.
- [ ] Borrow amount is frozen at confirmation time.
- [ ] Source and target borrowing adjustments commit atomically.
- [ ] Duplicate/concurrent borrowing is rejected by lock and uniqueness constraint.
- [ ] Late expenses, income, splits, corrections, reversals, and refunds create compensation chains.
- [ ] Full/partial same-period refunds reduce spending.
- [ ] Later refunds, including refunds after rollover, compensate later periods without rewriting snapshots.
- [ ] Historical budget timezone boundaries remain unchanged.
- [ ] Budget constraint, concurrency, idempotency, and compensation tests pass.

Exit criteria: monthly budget history and current budget limits are deterministic, auditable, and independent of account balances.

---

## Phase 8 — receipt storage, preprocessing, locale parser, PaddleOCR, and Pending Review

Goal: deliver receipt upload/import processing without bypassing accounting review.

Dependencies: Phase 7; Phase 1 MinIO/Redis; Phase 5 Currency Core; PaddleOCR service available.

### Locale and parser decision gate

Before Phase 8 exits, approve an MVP parser matrix covering languages/scripts, currency symbols/names, decimal and thousands separators, date/time formats, and timezone interpretation. The parser must handle 1,250.50, 1.250,50, and 1 250,50 according to explicit locale rules. Ambiguous dates such as 11/08/2026 and 08/11/2026, ambiguous currency symbols, or unsupported formats become needs_review; Laravel must not guess. Persist locale, parser version, and ambiguity/confidence metadata with the extraction.

### Image preprocessing and lifecycle

Keep the original upload immutable for audit/history. Create a separate processing derivative and record its version:

    original image -> derivative -> orientation/deskew/rotation/perspective/crop/contrast/resolution -> PaddleOCR -> parser

Support EXIF/orientation correction, deskew, rotation, perspective correction, cropping, contrast normalization, resolution normalization/downscaling, screenshots/photos, and long receipts as applicable. Never replace the original. Configure retention for originals, derivatives, thumbnails, failed OCR files, abandoned Pending Review records, reports, and full exports.

### Upload security and orphan cleanup

Validate MIME and magic bytes, generate server-side object keys, reject client paths, protect against decompression bombs and unsafe image decoders, enforce byte/dimension/pixel limits at Nginx/PHP/Laravel/worker boundaries, calculate checksums, detect duplicate sources, and keep MinIO private. Reconcile upload/DB failure combinations: orphan object, missing object, report crash after object creation, and abandoned receipt. Cleanup/reconciliation is retry-safe, idempotent, and observable.

### Steps

1. Create receipts, immutable original-object metadata, processing-derivative metadata, and OCR extraction tables.
2. Implement private MinIO upload:
   - validate bytes/MIME/size/dimensions;
   - calculate checksum;
   - store ownership and metadata;
   - create a receipt processing record.
3. Implement authorized receipt download/streaming with no permanent public URLs.
4. Deploy PaddleOCR PP-OCRv6 as an internal VPS service:
   - pin PaddleOCR/PaddlePaddle/model versions;
   - CPU-first deployment;
   - internal-only network;
   - no model download during requests;
   - health and resource metrics.
5. Implement Laravel OCR adapter interface and internal service integration.
6. Implement ProcessReceiptOcr queue job with retry/backoff, timeout, idempotency, and failed-job handling.
7. Persist raw OCR response, normalized extraction, field confidence, classification confidence, parser/model version, and failure reason.
8. Implement purchase receipt parsing: merchant, date/time, currency, lines, quantity, unit price, subtotal, discounts, tax, fees, tip, total, reference.
9. Implement transfer/payment parsing: amount, currency, date/time, payee, identifiers, reference, fee, balance-after.
10. Create Pending Review transaction only after OCR completes; never post automatically.
11. Implement receipt-type correction, manual retry, unreadable-image fallback, and unsupported-file errors.
12. Implement purchase reconciliation and transfer Unitemized / Other allocation behavior.
13. Approve and encode the locale/parser matrix before enabling normalization or posting from OCR.
14. Implement versioned image preprocessing derivatives without replacing original receipt objects.
15. Implement retry-safe object/database reconciliation and lifecycle cleanup for orphaned, missing, failed, and abandoned records.
16. Enforce upload, decoder, worker, checksum, private-object, and server-generated-key security controls.

### Checklist

- [ ] Original receipt is stored before OCR begins.
- [ ] Receipt storage is private and ownership-protected.
- [ ] OCR processing is asynchronous and retry-safe.
- [ ] Raw and normalized OCR data remain separately traceable.
- [ ] Confidence and provider/model versions persist.
- [ ] OCR never creates a Posted transaction directly.
- [ ] Purchase totals block posting when reconciliation fails.
- [ ] Transfer receipts require allocations or explicit Unitemized / Other.
- [ ] Unknown/low-confidence receipt types can be corrected.
- [ ] OCR timeout, provider failure, corrupt file, unreadable text, and retry tests pass.
- [ ] PaddleOCR service is not publicly reachable.
- [ ] OCR latency, success, failure, retry, and queue age are observable.
- [ ] Locale/parser matrix is approved before phase exit.
- [ ] Ambiguous dates, numbers, currencies, and unsupported locales become needs_review.
- [ ] Original image is never replaced by a derivative.
- [ ] Preprocessing version and source/derivative relationship persist.
- [ ] Magic-byte, MIME, dimension, pixel, decompression, and worker resource protections pass.
- [ ] Client names/paths cannot control object keys.
- [ ] Orphan-object and missing-object reconciliation is retry-safe and observable.
- [ ] Retention cleanup tests cover originals, derivatives, failed OCR, abandoned receipts, and missing objects.

Exit criteria: a user can upload a receipt, receive a reviewable extraction, correct it, and proceed to the normal transaction posting flow.

---

## Phase 9 — merchant/item normalization and duplicate detection

Goal: make receipt-derived data reusable without losing source evidence.

Dependencies: Phase 8.

### Steps

1. Create merchants, items, normalization candidates, and merge relationships.
2. Implement normalized search keys and user-scoped suggestions.
3. Preserve raw OCR merchant/item labels on receipt/transaction/line-item records.
4. Rank existing merchant suggestions before create-new.
5. Implement explicit canonical merchant selection and new merchant creation.
6. Implement item normalization with unit/pack-size dimensions.
7. Prevent price comparisons across incompatible units/sizes.
8. Version normalization algorithms and store the version with derived data.
9. Implement weighted duplicate candidate matching using amount, currency, date proximity, account, merchant/payee, reference number, and source checksum.
10. Implement user decisions: view existing, keep both, replace pending draft, cancel import.
11. Make canonical merges auditable and non-destructive to raw evidence.

### Checklist

- [ ] Similar OCR merchant text suggests existing canonical merchants.
- [ ] User can reject suggestions and create a new merchant.
- [ ] Raw OCR text remains unchanged after normalization/rename/merge.
- [ ] Item unit and pack-size metadata are persisted.
- [ ] Incompatible units are excluded from price comparisons.
- [ ] Duplicate candidates are warnings, not silent deletions.
- [ ] Exact API retries are deduplicated by idempotency.
- [ ] Normalization and duplicate scoring tests cover false positives/negatives.
- [ ] Merge and suggestion authorization tests pass.

Exit criteria: merchants/items are normalized consistently and duplicate decisions remain user-controlled.

---

## Phase 10 — FX provider operations and multi-currency reporting

Goal: operate external rate providers and multi-currency reporting on top of the completed Currency Core without rewriting historical amounts.

Dependencies: Phase 5; Phase 6; Phase 9 for item/report integration.

### Steps

1. Use the Currency Core registry and seed only provider-enabled pairs, source metadata, and operational settings.
2. Create exchange_rates storage with pair/date uniqueness and provider metadata.
3. Implement exchange-rate provider adapter, daily fetch job, retry/backoff, and stale-rate handling.
4. Make provider, supported pairs, refresh cadence, stale threshold, and fallback policy configurable settings.
5. Implement historical rate lookup using the documented weekend/holiday policy.
6. Implement user rate override with reference rate, used rate, reason, and source.
7. Lock used rate and base amount at transaction posting.
8. Implement cross-currency transfers with sent amount, received amount, effective rate, and explicit fees.
9. Add multi-currency API resources and report fields.
10. Add alerting when rates exceed the stale threshold or provider jobs fail.

### Checklist

- [ ] Every foreign-currency posted transaction stores original amount/currency.
- [ ] Reference and used rates are both auditable when overridden.
- [ ] Historical base amounts do not change after later rate updates.
- [ ] Rate precision and rounding are exact and centralized.
- [ ] Cross-currency transfers reconcile sent/received amounts.
- [ ] Provider failure, stale fallback, weekend/holiday, and override tests pass.
- [ ] Rate freshness and job failure metrics/alerts work.
- [ ] Exchange-rate provider, supported pairs, quotas, and stale threshold are approved.
- [ ] Provider-independent historical lookup remains unchanged.
- [ ] Native/functional FX reporting and override metadata reconcile.

Exit criteria: historical multi-currency reporting is stable and explainable.

---

## Phase 11 — backend synchronization contract

Goal: support offline drafts while keeping Laravel authoritative.

Dependencies: Phase 2; Phase 6; Phase 8; Phase 9; Phase 10.

### Cursor and full-resync contract

Define an opaque cursor with stable ordering, pagination, lifetime, and tombstone retention. If a cursor is too old or invalid, return the stable error SYNC_CURSOR_EXPIRED and never return an incomplete incremental result. Full authoritative resync must define whether archived resources, tombstones within retention, current versions, and pending server-side states are included. The response must include an authoritative cursor and complete ordering metadata.

### Steps

1. Define shared API DTO/OpenAPI fixtures for resources, errors, versions, statuses, and cursors.
2. Define external sync-consumer fields: local_id, server_id, sync_status, local/server version, timestamps, tombstone state, operation ID.
3. Implement sync_operations table and unique user/device operation IDs.
4. Implement POST /sync/push and GET /sync/pull with cursor/version semantics.
5. Implement dependency ordering for account/category/merchant before transactions, or atomic temporary-ID resolution.
6. Implement accepted/succeeded/failed/conflict response states.
7. Implement stale version conflicts without last-write-wins.
8. Implement retry replay using idempotency.
9. Implement tombstone/archival convergence for deletions.
10. Ensure local drafts, pending sync, and conflicts are excluded from posted balances/reports.
11. Add device/session behavior for forced logout on new-device login.
12. Implement cursor expiry detection and the `SYNC_CURSOR_EXPIRED` response without returning partial incremental data.
13. Implement authoritative full-resync pagination, archived-state/tombstone/pending-state semantics, and authoritative cursor issuance.
14. Create contract tests for external API consumers, cursor expiry, pagination, tombstone retention, and full resync.

### Checklist

- [ ] Sync consumer can retrieve the last synchronized authoritative data.
- [ ] Offline expense/receipt draft operations can be accepted and reconciled.
- [ ] Queued operations retry safely after reconnect.
- [ ] A local draft is not counted as posted.
- [ ] Dependencies sync before dependents.
- [ ] Stale edits produce a conflict payload and preserve local changes.
- [ ] Tombstones allow clients to converge.
- [ ] Idempotent push cannot double-post.
- [ ] Sync status transitions and failure recovery are tested.
- [ ] OpenAPI/DTO fixtures match Laravel resources and sync behavior.
- [ ] Cursor ordering, pagination, lifetime, and tombstone retention are documented.
- [ ] Expired cursors return SYNC_CURSOR_EXPIRED.
- [ ] Full resync is authoritative and includes explicitly defined archived/tombstone/pending states.
- [ ] Full-resync and cursor-expiry contract tests pass.

Exit criteria: offline draft → reconnect → authoritative post is reliable and conflict-safe.

---

## Phase 12 — dashboard queries, forecast formulas, insights, and notifications

Goal: provide server-calculated decision support and all approved notification channels using approved, versioned formulas.

Dependencies: Phase 7; Phase 10; Phase 11.

### Formula decision gate

Approved Feature 014 formula contract: formulas are named/versioned `safe_to_spend/1`, `category_aware_projected_spend/1`, `income_concentration/1`, and `item_price_movement/1`. Safe-to-spend is active expense-budget effective limit less authoritative posted budget spending, aggregated across categories, divided by remaining calendar days including today (minimum denominator one). Global positive remaining may offset a negative category, but negative category remaining remains visible; no active expense budget returns `not_available`, not zero. Existing effective limits already include rollover, underflow, reallocation, borrowing, and budget correction effects. Future obligations are excluded in V1.

The rejected global formula `total_actual_spend / elapsed_days × total_days` must never be implemented. Projected month-end is the sum of category-aware projections: FIXED uses the median of up to three prior completed qualifying budget periods and never daily-multiplies fixed payments; PERIODIC uses a 120-day history of positive posted purchase occurrences, grouped by local day for cadence, median occurrence amount and median positive interval; VARIABLE uses posted 30-calendar-day recent spending and a calendar-day denominator. Forecast alerts are eligible from elapsed budget day five; actual budget overspend eligibility remains immediate. Income concentration is a versioned 12-month HHI using canonical source, normalized source, then `Unattributed`; suppress a strong classification when unattributed share exceeds 20%. Item Price Movement compares only same-canonical-item, compatible normalized-unit, same-original-currency posted receipt lines by occurrence date. It uses explicit line amount only and does not allocate receipt-level tax/fees/tips/discounts. No item-price notification threshold is assumed until approved as a setting.

### Steps

1. Implement dashboard read queries/projections:
   - total base-currency balance;
   - current income/expenses;
   - budget utilization;
   - safe-to-spend;
   - projected month-end;
   - recent transactions;
   - pending review/sync counts;
   - top categories/merchants;
   - income concentration;
   - item-price alerts.
2. Obtain approved product formulas; if any are missing, block Phase 12 and label PRODUCT DECISION REQUIRED BEFORE PHASE 12. Then implement and version the deterministic forecast formulas.
3. Implement income source share and concentration indicator.
4. Implement normalized item price history and inflation/deflation calculations.
5. Ensure insights use posted transactions and locked historical FX only.
6. Add in-app notification persistence and unread/read state.
7. Add local-device notification payloads.
8. Add Firebase Cloud Messaging adapter and device token lifecycle.
9. Add email notification adapter and templates.
10. Implement user preferences, threshold configuration, deduplication, retries, and notification delivery audit.
11. Queue expensive calculations and notification delivery.

### Checklist

- [ ] Dashboard totals match authoritative server queries.
- [ ] Draft/pending/conflict records are excluded from financial totals.
- [ ] Safe-to-spend and month projection formulas are versioned.
- [ ] Income concentration shows source shares and formula version.
- [ ] Item price comparisons require compatible units.
- [ ] In-app, local, push, and email notifications work through adapters.
- [ ] Notification preferences and thresholds are respected.
- [ ] Delivery retries are idempotent and observable.
- [ ] Notification failures do not affect ledger posting.
- [ ] Formula decisions are approved and versioned before phase exit.
- [ ] Safe-to-spend edge cases and formula versions have deterministic tests.
- [ ] Concentration and price-change formulas have approved fixtures.

Exit criteria: dashboard and insights are explainable, server-authoritative, and notifications are reliable across all approved channels.

---

## Phase 13 — reports, full JSON data export, and full account export

Goal: provide trustworthy, secure data portability.

Dependencies: Phase 6; Phase 7; Phase 10; Phase 12.

### Export terminology and full account portability

Use Full JSON Data Export unless a true import/restore path exists. Do not call JSON export a backup. It preserves approved IDs, relationships, transaction states/history, journal references, budgets/adjustments, merchants/items, FX metadata, receipt metadata, and approved audit/import metadata.

A full-account ZIP is separate from analytical reports and contains manifest.json, data.json, original receipt media under receipt UUID paths, and any other approved user-owned source media required for portability. It is authenticated, user-scoped, asynchronous for large accounts, stored privately, short-lived, observable, and cleaned after expiry.

### Steps

1. Define report filter DTOs shared by history, dashboard, and exports.
2. Implement transaction report.
3. Implement account statement with opening/closing balances, transfers, and currency.
4. Implement budget report with all snapshot and adjustment components.
5. Implement expense/category/merchant trends.
6. Implement income/source/concentration report.
7. Implement item price report.
8. Implement multi-currency report with original amounts, rates, base amounts, and overrides.
9. Implement CSV streaming for small bounded exports.
10. Implement queued XLSX, PDF, and JSON generation.
11. Store report jobs and private artifacts in MinIO.
12. Include date range, timezone, and base-currency context in every output.
13. Apply configurable report expiry and cleanup jobs.
14. Ensure Full JSON Data Export preserves IDs, relationships, transaction state/history, journal references, budgets/adjustments, merchants/items, FX metadata, receipt metadata, and approved audit/import metadata.
15. Implement full-account ZIP packaging with manifest.json, data.json, and original receipt media under `receipts/<receipt-uuid>.<ext>`, plus any approved user-owned source media.
16. Apply configurable report/export expiry and cleanup jobs.
17. Verify that full-account export ownership, media inclusion, expiry, and cleanup are enforced for large asynchronous jobs.

### Checklist

- [ ] All required reports exist.
- [ ] Report totals equal interactive server totals for identical filters.
- [ ] CSV handles bounded synchronous exports safely.
- [ ] Large XLSX/PDF/JSON jobs return 202 and progress/status.
- [ ] Report files are private and short-lived.
- [ ] Expired files are deleted.
- [ ] Full JSON Data Export is not described as backup.
- [ ] JSON preserves IDs, relationships, state/history, journal references, budgets/adjustments, merchants/items, FX metadata, receipts metadata, and approved audit/import metadata.
- [ ] Full-account ZIP includes original receipt media under the documented receipt UUID paths.
- [ ] Export authorization, ownership, media-inclusion, large-export, and cleanup tests pass.
- [ ] Export authorization and cross-user tests pass.
- [ ] Report duration, failures, and queue age are observable.

Exit criteria: users can securely export trustworthy reports and complete account packages, including original receipt media, in all required formats.

---

## Phase 14 — security, performance, operations, and release hardening

Goal: satisfy P0 release gates and prepare the VPS deployment.

Dependencies: Phases 0–13.

### Production retention and disaster-recovery gates

Before Phase 14 exits, approve and configure concrete values for generated report retention, full export retention, failed OCR artifact retention, processing derivative retention, abandoned receipt retention, audit-event retention, deleted-account grace period, PostgreSQL backup retention, MinIO backup retention, sync tombstone retention, and failed-job retention.

Before Phase 14 exits, approve measurable RPO/RTO objectives for PostgreSQL, MinIO receipt storage, report/export artifacts where applicable, core API/database restoration, queue, and OCR service. If values are not approved, mark PRODUCT/OPERATIONS DECISION REQUIRED BEFORE PRODUCTION and block exit. A restore drill must prove the approved objectives.

### Database integrity and workflow correlation

Use PostgreSQL constraints as defense-in-depth for positive journal amounts, exactly one debit/credit side, valid currencies, posted-state required fields, immutable posted relationships, reversal uniqueness, category/period uniqueness, borrowing reservations, exchange-rate pair/date uniqueness, and ownership consistency where feasible. Keep complex orchestration in Laravel rather than triggers.

Propagate request_id, operation_id, idempotency_key, job_id, receipt_id, transaction_id, journal_entry_id, and report_job_id through asynchronous workflows. Correlate HTTP receipt upload through storage, OCR, Pending Review, posting, journal, notifications, and reports without logging sensitive payloads.

### Steps

1. Run full authorization review across every route, query, policy, job, notification, attachment, and report.
2. Add rate limits to authentication, receipt uploads, OCR retry, exports, sync, and expensive insights.
3. Verify sensitive log redaction and request/job correlation.
4. Run PostgreSQL backup and restore drills.
5. Validate MinIO backup and restore.
6. Configure VPS TLS, firewall, private service interfaces, process supervision, health checks, log rotation, and encrypted secrets.
7. Configure worker autosizing/restart policy within VPS capacity.
8. Run migrations safely during deployment.
9. Run performance tests with at least 100,000 transactions per user.
10. Run EXPLAIN ANALYZE for dashboard, history, balance, budget, merchant, item, and report queries.
11. Test queue backlog, provider outage, stale FX, OCR downtime, MinIO outage, Redis restart, and database failure behavior.
12. Test month boundaries, user timezones, leap days, DST transitions, and year boundaries.
13. Review account deletion, retention, report expiry, OCR retention, audit retention, and backup settings.
14. Run dependency/security audits and verify pinned OCR model artifacts.
15. Perform release candidate migration on a production-like database.
16. Approve and configure concrete retention policies and failed-job cleanup values; block release if any required value is missing.
17. Approve measurable RPO/RTO objectives for PostgreSQL, MinIO, report/export artifacts where applicable, API, queue, and OCR, then complete restore drills proving them.
18. Execute migration-level constraint tests and correlation-trace tests across receipt upload, OCR, posting, journal, notification, and report workflows.

### Checklist

- [ ] All P0 release gates from the PRD pass.
- [ ] Cross-user access tests pass for every user-owned resource.
- [ ] Posted journals are immutable in application and database permissions.
- [ ] Backup restore is demonstrated.
- [ ] Queue and scheduler failure recovery is demonstrated.
- [ ] OCR source and report files remain private.
- [ ] Stale FX behavior matches configured policy.
- [ ] 100,000-transaction performance baseline passes.
- [ ] PostgreSQL query plans are acceptable.
- [ ] VPS health checks and alerts are active.
- [ ] Retention/deletion settings are seeded and documented.
- [ ] Concrete production retention values are approved.
- [ ] RPO/RTO values are approved and restore drills prove them.
- [ ] Database constraint and migration integration tests pass.
- [ ] Correlation identifiers propagate through request/job workflows.
- [ ] No known data-loss bug exists in posting, sync, corrections, refunds, or exports.

Exit criteria: the MVP is release-ready from a correctness, security, reliability, and operations perspective.

---

## Phase 15 — MVP release and post-MVP boundary

Goal: release only the approved MVP and preserve the roadmap boundary.

Dependencies: Phase 14.

### MVP release checklist

- [ ] API consumers can authenticate with single-device enforcement.
- [ ] User can onboard, create accounts/categories, and manually post transactions.
- [ ] Every posted transaction has a balanced immutable journal.
- [ ] Corrections/reversals preserve history.
- [ ] Budgets reset, roll over, carry underflow, reallocate, and borrow exactly as specified.
- [ ] Receipt upload/OCR creates Pending Review and never auto-posts.
- [ ] Merchant/item normalization and duplicate resolution work.
- [ ] Multi-currency rates are locked historically.
- [ ] Offline drafts sync safely with idempotency/conflicts.
- [ ] Dashboard, insights, notifications, and reports use server truth.
- [ ] CSV, XLSX, PDF, and JSON exports work securely.
- [ ] VPS deployment, backups, monitoring, and recovery runbooks are complete.

- [ ] Currency Core and immutable currency rules work.
- [ ] Every posted transaction balances in functional currency while retaining native amounts and FX metadata.
- [ ] Transaction inclusion rules prevent report/budget double counting.
- [ ] Late changes, refunds, corrections, reversals, and borrowing compensation preserve history.
- [ ] OCR locale, preprocessing, upload security, lifecycle, and orphan cleanup pass.
- [ ] Sync supports idempotency, conflicts, cursor expiry, tombstones, and full resync.
- [ ] Approved formulas drive dashboard/insights and all notification adapters work.
- [ ] Full JSON Data Export and full-account ZIP include approved data and original receipt media.
- [ ] Concrete retention and RPO/RTO decisions are approved and proven.

### Explicitly post-MVP

- [ ] Admin dashboard and audited break-glass support access.
- [ ] SMS capture and transaction-code parsing.
- [ ] E2EE/zero-knowledge architecture.
- [ ] Multi-device concurrent editing.
- [ ] Bank/open-banking integrations.
- [ ] Advanced optional cloud AI categorization.

## Final phase acceptance

The implementation is complete only when:

- [ ] All phase checklists are complete.
- [ ] All PRD P0 release gates pass.
- [ ] All API contracts and external-consumer DTO fixtures are versioned.
- [ ] All production services are monitored and recoverable.
- [ ] The deployed system preserves the accounting invariant under retries, concurrency, offline sync, corrections, and provider failures.
