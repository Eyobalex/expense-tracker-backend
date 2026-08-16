# Next-Gen Financial Tracker — Technical Specification

Status: Development handoff draft based on Next-Gen_Financial_Tracker_Development_Ready_PRD_v1.0.docx (v1.0, 11 August 2026)

## 1. Scope and authority

The first client is Flutter Android. Laravel is the authoritative backend and contains all accounting, budget, FX, duplicate, normalization, forecasting, reporting, and authorization logic. PostgreSQL is used everywhere, including local development, CI, and tests, with isolated databases per test run. Redis is the queue/cache/lock backend. MinIO is the initial S3-compatible private object store and can later be replaced by S3 through configuration. The MVP includes manual transactions, purchase and transfer/payment receipt OCR, offline drafts/sync, budgets, multi-currency, insights, and CSV/XLSX/PDF/JSON export. SMS capture, iOS, E2EE/zero-knowledge, open banking, and advanced cloud AI are excluded.

The system is a modular monolith: one Laravel deployable with domain modules, PostgreSQL, Redis, MinIO, queue workers, scheduler, and an internal self-hosted OCR service. This preserves transaction boundaries while allowing OCR, exports, and analytics to scale independently later.

## 2. Runtime architecture

    Flutter Android
      UI / camera / local encrypted store / drafts / sync queue
              | HTTPS JSON + multipart, bearer auth
    Laravel API (modular monolith)
      Auth, policies, commands, domain services, queries
      Accounting, Budgeting, Currency, Receipts, Catalog, Insights, Reports, Sync
              |                         |                         |
          PostgreSQL                Redis                     MinIO (S3 API)
          authoritative truth       workers + retries           receipt/report files
              |                         |
          scheduler              PaddleOCR service + FX provider

All financial writes use PostgreSQL transactions. Queue jobs are at-least-once and therefore idempotent. Read-heavy dashboard/report queries may use indexed projections or materialized read models, but these are never a second source of truth. Tests use PostgreSQL schemas/databases matching production behavior.

### 2.1 Module boundaries

| Module | Owns | Must not own |
|---|---|---|
| Identity | users, devices, sessions/tokens, settings | ledger calculations |
| Accounting | chart mappings, journals, posting, reversal | OCR parsing, UI concerns |
| Transactions | lifecycle, splits, corrections, duplicate candidates | arbitrary journal-line input |
| Budgeting | category config, periods, adjustments, reset/borrow/reallocate | financial balances |
| Currency | currency metadata, rates, conversion, rounding | mutable historical rates |
| Receipts | attachments, OCR attempts/extractions, reconciliation | direct posting without approval |
| Catalog | merchants, items, normalization, suggestions | raw evidence deletion |
| Insights | forecasts, concentration, price intelligence | authoritative ledger mutation |
| Reporting | filtered queries and exports | client-side totals |
| Sync | idempotency, versions, conflicts, tombstones | last-write-wins financial overwrite |

## 3. Data model

All user-owned tables contain user_id directly or are provably owned through an immutable parent. Use UUIDs for externally visible IDs. Use created_at, updated_at, and version on mutable sync resources. Use deleted_at/archive/tombstones only where historical references or client convergence require them.

### 3.1 Identity and settings

users: Laravel identity, base_currency_code, timezone, onboarding state, timestamps.

devices: id, user_id, device client ID, platform, app version, last seen, revoked timestamp, push-token metadata. Do not store secrets in logs.

user_settings: local lock preference, alert thresholds, budget timezone, forecast method, export defaults, and feature flags. Security-sensitive changes are audited.

user_notifications: UUID, user_id, stable notification type, title/body, schema-versioned local-device payload, in-app visibility, read timestamp/version, deduplication key, request correlation ID, optional immutable insight snapshot reference, timestamps. The per-user deduplication key is unique.

notification_deliveries: UUID, user_notification_id, optional target device, channel (in_app/local/push/email), queued/delivered/failed/skipped status, deterministic idempotency key, retry attempt/job IDs, delivery/failure timestamps, and sanitized failure code. Notification delivery history is an audit record, never a source of financial truth.

### 3.2 Financial structure and chart mapping

financial_accounts: id, user_id, name, user-facing type (cash, bank, mobile_wallet, savings, credit_card, loan, investment, other), internal accounting type (asset/liability), currency code, archived timestamp, opening-balance state, version.

categories: id, user_id, parent ID, name, kind (expense/income), active state, budget configuration (base_limit_minor, rollover, overspend carry, borrowing), budget currency, version.

Internal ledger accounts may be a separate ledger_accounts table linked to financial accounts/categories, or an explicit stable mapping. The choice must preserve account identity when a display category is renamed.

### 3.3 Transactions and splits

transactions: id, user_id, type (expense, income, transfer, refund, adjustment), lifecycle state (draft, pending_review, posted, reversed, sync_conflict), occurred-at timestamp, user timezone, original total minor units, transaction currency, base amount minor units once posted, merchant ID/raw merchant text, payee/payer, description/notes, source (manual, purchase_receipt_ocr, transfer_receipt_ocr, import), receipt ID, journal entry ID, client operation ID, version, posted/reversed timestamps.

transaction_splits: transaction ID, category ID, allocation minor units, tax/fee/discount classification, currency, line reference, canonical item ID, raw item text, quantity/unit/pack-size metadata, confidence. Enforce reconciliation to the transaction total including explicit tax/fee/rounding lines.

line_items: transaction/receipt ID, raw description, canonical item ID, quantity, unit code, pack-size value/unit, unit price minor units, line total minor units, confidence, normalization version.

### 3.4 Double-entry journal

journal_entries: id, user_id, transaction ID, entry type (normal, opening_balance, reversal, correction, fx, fee), currency context, posting timestamp, immutable hash/version if selected, reversed-entry ID, correction-of ID, idempotency operation ID.

journal_lines: journal ID, ledger account ID, debit minor units, credit minor units, currency, base debit/credit for the selected multi-currency policy, line description, sequence. Each line has exactly one positive side; entry total debit equals total credit.

Never expose a client endpoint that accepts arbitrary journal lines. Build canonical lines from transaction type, accounts, splits, taxes, fees, refunds, and FX details.

### 3.5 Receipts and OCR

receipts: id, user ID, transaction ID, receipt type (purchase_receipt, transfer_receipt, unknown), private storage key, MIME, byte size, checksum, original filename, upload timestamp, retention/deleted state.

ocr_extractions: receipt ID, attempt number, provider, provider document ID, status, raw response JSON (encrypted/restricted as appropriate), normalized parsed JSON, field confidence JSON, classification confidence, parser version, failure code/message, started/completed timestamps.

Receipt metadata is committed only after storage succeeds. OCR retries are keyed by receipt/attempt or a job idempotency key.

### 3.6 Merchants and items

merchants: user ID, canonical display name, normalized search key, optional location, active/merged-to ID, normalization version.

items: user ID, canonical name, normalized key, unit/pack-size dimensions, active/merged-to ID, normalization version.

Raw receipt values remain on transaction/line-item/OCR records. A merge changes canonical references, not raw evidence.

### 3.7 Budgets

budget_periods: category ID, period year/month and timezone, status (open, closed, initializing), base-limit snapshot, borrowing deduction, positive rollover, negative carry, reallocation in/out, effective limit, actual spent, remaining, initialized/closed timestamps. Unique category and year/month.

budget_adjustments: period/category, type (rollover, underflow, reallocation_in, reallocation_out, borrowing, correction), amount, paired adjustment ID, actor, source period, target period, immutable reason and timestamps. Unique protection prevents a second borrow for the same category/target month.

Borrowing writes a positive adjustment in the current period and a full-base-limit deduction in the immediate next period. It never mutates base_limit.

### 3.8 Currency, sync, reports, audit

currencies: ISO code, minor-unit exponent, display metadata, active state.

exchange_rates: rate date, base/quote pair, reference decimal rate, provider/source, fetched timestamp, freshness/status, unique pair/date/provider policy.

transaction FX fields: original amount/currency, reference rate/date/source, used rate, override reason/source, base amount, rounding mode/version.

sync_operations: user/device, client operation UUID, operation type, entity ID, payload hash, status (queued, processing, succeeded, failed, conflict), server response/resource ID, retry count, timestamps. Unique user and client_operation_id.

report_jobs: user, type, filters JSON, base-currency/timezone context, status, progress, private file key, error code, expiry timestamp, created/completed timestamps.

audit_events: actor/user/device, event name, aggregate type/ID, before/after summary (never secrets or raw images), request/correlation ID, timestamp. Posting, reversal, correction, borrowing, reallocation, security, and deletion events are mandatory.

## 4. Exact money and FX specification

Recommended default: integer minor units for stored monetary amounts, currency exponent from currencies, and high-precision PostgreSQL NUMERIC(38,18) for rates/intermediates.

    base_amount = round(original_minor_units × used_rate,
                          target_currency_exponent, rounding_mode)

Persist original value and result; never recalculate historical base amounts from today's rate. An overridden rate stores the provider reference rate plus the used rate and an optional user reason. Cross-currency transfers store sent and received amounts, effective rate, and explicit fee lines. Weekend/holiday fallback and stale-rate thresholds are explicit configuration.

## 5. Ledger posting and correction protocol

PostTransaction runs in one database transaction:

1. Authenticate and authorize ownership.
2. Resolve idempotency key; return original result if already succeeded.
3. Lock the draft/pending transaction and validate current version.
4. Validate account state/currency, splits, receipt reconciliation, duplicate decision, and FX data.
5. Build canonical journal lines for the transaction type.
6. Assert every line has one side and total debit equals total credit.
7. Persist journal header, lines, transaction state, locked FX fields, and audit event.
8. Update/invalidate read projections and commit.

Canonical examples:

| Scenario | Debit | Credit |
|---|---|---|
| Cash expense 500 ETB | Expense 500 | Cash 500 |
| Salary 20,000 ETB | Bank 20,000 | Revenue 20,000 |
| Bank to wallet 2,000 ETB | Wallet 2,000 | Bank 2,000 |
| Credit-card purchase 1,000 ETB | Expense 1,000 | Card liability 1,000 |
| Card repayment 1,000 ETB | Card liability 1,000 | Bank 1,000 |
| Bank refund 300 ETB | Bank 300 | Expense refund/reversal 300 |

Posted rows cannot be edited or deleted. ReverseTransaction creates a linked equal/opposite entry. A correction creates reversal plus a new validated entry with original, actor, reason, and links.

## 6. Budget algorithm

On the first day of the budget timezone, initialize each active category once:

    reset_limit = base_limit_snapshot
    after_borrowing = reset_limit - borrowed_from_this_month
    effective_limit = after_borrowing
                   + positive_rollover
                   - negative_carryover
                   + reallocation_in
                   - reallocation_out

borrowed_from_this_month is zero or the full configured base limit under the MVP rule. Apply and persist the six PRD steps in order. Reallocation is paired and auditable. Closed periods are immutable except for explicit correction adjustments.

## 7. API contract

Base path: /api/v1. Authenticated JSON except multipart receipt upload. All create/post/reverse/correction/upload/budget-adjustment/sync mutations require Idempotency-Key.

### 7.1 Authentication and profile

    POST   /auth/register
    POST   /auth/login
    POST   /auth/logout
    POST   /auth/refresh-or-revoke
    GET    /me
    PATCH  /me
    GET    /devices
    DELETE /devices/{device}

### 7.2 Accounts, categories, merchants, items

    GET/POST/PATCH /accounts
    POST   /accounts/{id}/archive
    GET/POST/PATCH /categories
    POST   /categories/{id}/restore
    GET    /merchants/suggestions?q=...
    POST   /merchants
    PATCH  /merchants/{id}
    POST   /merchants/{id}/merge
    GET    /items/suggestions?q=...
    POST   /items
    PATCH  /items/{id}
    POST   /items/{id}/merge

### 7.3 Transactions and receipts

    GET    /transactions?filter...
    POST   /transactions
    GET    /transactions/{id}
    PATCH  /transactions/{id}                 draft/pending only, version required
    DELETE /transactions/{id}                 unposted only
    POST   /transactions/{id}/post
    POST   /transactions/{id}/reverse
    POST   /transactions/{id}/correct
    GET    /transactions/{id}/duplicates
    POST   /transactions/{id}/duplicate-decision
    POST   /receipts                           multipart
    GET    /receipts/{id}
    GET    /receipts/{id}/download
    POST   /receipts/{id}/retry-ocr
    PATCH  /receipts/{id}/classification

### 7.4 Budgets, FX, dashboard, sync, reports

    GET    /budgets?month=YYYY-MM
    GET    /budgets/{category}/periods
    POST   /budgets/{category}/reallocate
    POST   /budgets/{category}/borrow-next-month
    GET    /exchange-rates
    GET    /dashboard?month=YYYY-MM
    GET    /insights/forecast
    GET    /insights/income-concentration
    GET    /insights/item-prices
    POST   /sync/push
    GET    /sync/pull?cursor=...
    GET    /sync/operations/{id}
    POST   /reports
    GET    /reports/{id}
    GET    /reports/{id}/download

Success responses use {data, meta, links}. Errors use {error: {code, message, fields, request_id}}. Include version, status, authoritative timestamps, and server IDs in mutable resource responses. Use 202 for queued OCR/report work, 409 for conflicts, 422 for validation/reconciliation, and 403/404 without leaking ownership existence.

## 8. Jobs and schedules

Queue jobs:

- ProcessReceiptOcr: validate source, call provider, persist raw/parsed/confidence, classify, create Pending Review.
- NormalizeReceiptEntities: suggest merchants/items and persist versioned candidates.
- FetchExchangeRates: fetch pairs, validate freshness, retry/backoff, record failures.
- InitializeBudgetPeriod: idempotently initialize each category with a lock.
- GenerateReport: query server truth and create a private artifact.
- CleanupExpiredReports: delete expired files and metadata.
- RecomputeInsights (optional): update projections after posted transactions or period close.

Set explicit retries/backoff, timeouts, max attempts, unique keys, and failed-job alerts. Jobs must be safe to run more than once.

Scheduler:

- Daily exchange-rate refresh with documented UTC/user-timezone policy.
- Monthly budget initialization on each configured budget timezone.
- Report cleanup and health checks.
- Optional projection/analytics refresh.

## 9. Offline and synchronization protocol

Flutter local entities have local_id, server_id, sync_status, local_version, server_version, updated_at, and tombstone state. Cached posted data is separate from local drafts. An offline receipt remains local until upload succeeds.

Push payload:

    {
      "operation_id": "client-uuid",
      "device_id": "device-uuid",
      "entity": "transaction",
      "action": "create|update|delete|post",
      "local_id": "client-uuid",
      "server_id": null,
      "expected_version": null,
      "payload": {},
      "client_occurred_at": "2026-08-11T10:00:00+03:00"
    }

The server returns accepted, succeeded, failed, or conflict, authoritative resource data, validation fields, and the next cursor/version. Dependencies are pushed first (account/category/merchant before transaction), or temporary IDs are resolved atomically. Do not apply last-write-wins to posted records.

## 10. OCR and receipt reconciliation

Purchase OCR fields: merchant/location, date/time, currency, line description/quantity/unit price/line total, subtotal, discounts, tax/VAT, service charge, tip, fees, grand total, reference.

Transfer/payment OCR fields: amount/currency, date/time, payee, source/destination IDs, reference, fee, balance-after.

For purchase receipts, calculate line subtotal plus explicit adjustments and compare to printed total using smallest-unit tolerance. For transfer receipts with no lines, require allocations or an explicit Unitemized / Other line. Unknown/low-confidence classification lets the user choose receipt type. Unsupported files fail before OCR; unreadable OCR keeps the image and offers manual entry.

## 11. Reporting and insight formulas

All totals are server-side and use the same filter specification as history. Required reports: transaction, account statement, budget, expense, income/concentration, merchant, item price, and multi-currency. CSV is suitable for small synchronous exports; XLSX/PDF/JSON and large datasets use report_jobs.

Minimum insight inputs are posted transactions only, current effective budget, actual spent, remaining days in the user's budget timezone, selected forecast method, and locked historical FX. Store formula/algorithm version with derived analytics. Drafts are never spent. Concentration shows each source share and a deterministic versioned indicator. Item comparisons require equivalent canonical item/unit and show merchant/date/previous/current/unit price/absolute/percentage changes.

Feature 014 formula contract: formulas are named and versioned as `safe_to_spend/1`, `category_aware_projected_spend/1`, `income_concentration/1`, and `item_price_movement/1`. Safe-to-spend is the sum of active expense-budget effective-limit minus authoritative posted spending, floored at zero only for the per-day value and divided by calendar days remaining including today. Per-category negative remaining values remain visible. It is unavailable, not zero, when no active expense budget exists.

The rejected global projection `total_actual_spend / elapsed_days × total_days` must never be used. Projected month-end spending is the sum of independently calculated category projections: FIXED categories use the median of up to three prior completed qualifying periods and never daily-multiply a fixed payment; PERIODIC categories use a 120-day positive-occurrence cadence and median amount; VARIABLE categories use a 30-calendar-day recent spend window with calendar-day denominator. All monetary history uses the posted transaction's locked base amount. Forecast overspend alerts are eligible from elapsed budget day 5; actual overspend remains eligible immediately under notification settings.

Income concentration uses a 12-calendar-month-or-available-history HHI calculation in the reporting timezone. Canonical source, normalized source text, then `Unattributed` are the resolution order. HHI classifications are diversified (`<= 0.15`), moderately concentrated (`> 0.15` and `<= 0.25`), and concentrated (`> 0.25`); classification is suppressed when unattributed income exceeds 20%. Item Price Movement is not a general inflation metric: it compares an item only with its immediately previous compatible posted receipt line, same original currency, on occurrence date. Line-level amounts are used without allocating order-level tax, delivery, tips, service fees, or unallocated discounts. Same-merchant comparisons are alert-eligible; no item-price notification threshold is emitted until separately configured and approved.

Insight dashboard results are live indexed read queries in V1; no Redis cache is introduced, so posting, reversal, correction, refund, budget, category, normalization, and FX changes cannot serve stale cached insight output. Persist `insight_snapshots` only when a notification, scheduled/period-close snapshot, or report requires historical reproducibility. Each snapshot preserves its formula identity/version, inputs, result, coverage, timezone, base currency, and calculation timestamp and is never reinterpreted after a later formula revision.

### 11.1 Notification delivery contract

Laravel owns notification eligibility and payload construction. Budget threshold notifications use user-configurable 50/75/90/100 percent defaults; actual overspend is eligible immediately, while projected overspend is eligible only from budget-calendar day five through the approved category-aware forecast. Confirmed borrowing records an immediate consequence notification. OCR failures notify without logging or serializing raw OCR/image data. A notification stores its formula-derived insight snapshot only when it is emitted, so later formula or historical-data changes never rewrite the original alert explanation.

In-app notifications are persistent and mutable only for the user's read state. The local channel supplies a server-authored payload for an authenticated active Android device; the backend does not implement client UI behavior. FCM uses the Firebase HTTP v1 adapter with a VPS-mounted service-account credential file (`FCM_SERVICE_ACCOUNT_CREDENTIALS`) and a Redis-cached short-lived OAuth token; an explicitly injected short-lived access token exists only for controlled operational/testing use. Email uses Laravel mail. All external delivery is queued, retryable, idempotent per delivery record, and auditable. Missing FCM configuration or a device token is a skipped delivery, not a financial failure. User preferences can disable channels/types and set threshold values. Item Price Movement remains alert-eligible only for same-merchant comparisons; no item-price notification is emitted until the separate approved threshold is configured.

## 12. Security, privacy, and authorization

- HTTPS/TLS only; short-lived revocable authenticated tokens/sessions.
- Policies for every user-owned model and action; query scopes include ownership.
- Private receipt/report storage with authenticated streaming or short-lived signed URLs.
- Encrypt infrastructure, backups, and storage; use managed secrets.
- Redact tokens, raw images, OCR, and full financial payloads from logs.
- Biometric/PIN lock is a client convenience, not server authorization.
- MFA for initial/significant security actions if enabled; recovery cannot bypass policies.
- Account deletion requires confirmation, documented grace/retention, and user-data/file deletion when permitted.
- Rate-limit authentication, uploads, OCR retry, export, and expensive analytics.

## 13. Indexes and scale baseline

At minimum index:

- user_id and occurred_at descending on transactions;
- user_id, status, occurred_at and transaction type/source filters;
- user_id, account, occurred_at through the transaction-account relation;
- unique category/year/month on budget periods;
- user_id and normalized_key on merchants/items;
- user_id and checksum on receipts;
- unique user_id and client_operation_id on sync operations;
- user_id, status, created_at on report jobs;
- unique rate date/base/quote on exchange rates.

Use cursor pagination, bounded filters, partitioning only after measurement, and asynchronous analytics/exports. The baseline supports at least 100,000 transactions per user without schema redesign. Run EXPLAIN ANALYZE on dashboard, history, report, and balance queries.

## 14. Resolved architecture decisions

The following decisions are approved for the MVP and are now implementation constraints.

1. **Mobile authentication:** Laravel Sanctum bearer tokens for the first-party Flutter client, with revocation and expiry. MVP permits one active device session per user. A successful login on a new device revokes all previous device tokens and forces logout everywhere else. Device records remain for audit and future multi-device support.
2. **API contract:** Versioned Laravel API Resources with generated OpenAPI documentation and shared Flutter DTO fixtures.
3. **Database:** PostgreSQL in local development, CI, automated tests, staging, and production. Tests use isolated PostgreSQL databases or schemas; SQLite is not a test substitute.
4. **Queue/cache/locks:** Redis for queues, cache, rate limiting, idempotency locks, distributed locks, and job coordination.
5. **Object storage:** MinIO initially, accessed only through Laravel's S3-compatible filesystem abstraction. The storage contract must support a later S3 migration without domain changes.
6. **OCR:** Free, self-hosted PaddleOCR running as an internal VPS service, isolated from the public network. Use PP-OCRv6 as the initial model, with PP-OCRv5 as the compatibility fallback if CPU memory or latency requires it, start with CPU inference, and keep an OCR adapter boundary in Laravel. PaddleOCR is open-source under Apache 2.0; its official documentation provides local installation and PP-OCR pipeline usage. The service must return raw text, bounding boxes when available, confidence, and structured parsing metadata.
7. **Exchange rates:** Provider adapter with cached last-valid rates and explicit stale-rate alert/block policy. Provider credentials, supported pairs, refresh cadence, and stale threshold are application settings seeded for MVP and adjustable later through the admin dashboard.
8. **Money representation:** Integer minor units for monetary values and high-precision PostgreSQL NUMERIC rates/intermediates.
9. **Tenancy:** Single-user personal accounts for MVP, with user_id ownership on every aggregate and UUID/device-ready records for future shared workspaces.
10. **Notifications:** Implement all channels behind adapters: in-app notifications, Android local notifications, Firebase Cloud Messaging push, and email. User preferences and threshold settings are configurable; delivery jobs are idempotent and retryable.
11. **Exports:** Full-fidelity JSON export plus CSV, XLSX, and PDF. Large exports are queued as private artifacts with configurable expiry.
12. **Deployment:** VPS deployment. Run Laravel web/API, queue workers, scheduler, PostgreSQL, Redis, MinIO, and the internal PaddleOCR service with process supervision, TLS termination, encrypted backups, monitoring, and firewall isolation.
13. **Admin/support:** No admin dashboard in MVP. Build a restricted, least-privilege admin dashboard after MVP; future support access must be audited and break-glass only.
14. **Retention/deletion:** Retention, deletion grace period, report expiry, OCR source retention, audit retention, and backup retention are configurable application settings. Seed safe initial values in MVP; expose configuration through the post-MVP admin dashboard.
15. **Forecast and concentration formulas:** Ship documented, deterministic, versioned defaults. Store formula version with derived analytics and allow later settings/configuration changes without rewriting historical results.
16. **Active devices:** Single-device login is enforced for MVP. New-device login revokes all prior sessions/tokens. Concurrent multi-device editing and conflict UX remain post-MVP.

### 14.1 PaddleOCR integration requirements

- Package the OCR engine as a separately deployable service or supervised process on the VPS; do not run model inference inside PHP workers.
- Laravel uploads source images to private MinIO and sends an internal object reference to the OCR service. The service must not be publicly reachable.
- Pin the PaddleOCR/PaddlePaddle versions and model artifacts in deployment manifests; never download models during a production request.
- Use CPU-first inference for the initial VPS; measure latency and memory before considering a GPU.
- Persist provider/model/version metadata with every OCR extraction so results remain reproducible.
- Keep the existing queue, retry, confidence, reconciliation, and Pending Review rules unchanged regardless of OCR engine.
- Add a Tesseract adapter only as a fallback option if the VPS cannot run PaddleOCR reliably; it is not the primary engine.

### 14.2 VPS baseline

- Separate services/processes: web server, PHP-FPM, queue worker, scheduler, PostgreSQL, Redis, MinIO, PaddleOCR.
- Place PostgreSQL, Redis, MinIO, and PaddleOCR on private interfaces; expose only HTTPS API and required operational endpoints.
- Use automated encrypted PostgreSQL and MinIO backups, restore drills, health checks, log rotation, and resource alerts.
- Use rolling or supervised worker restarts and a deployment process that runs migrations safely before serving new code.

The remaining implementation work is now execution rather than architectural discovery.

References for the free OCR decision: [PaddleOCR documentation](https://www.paddleocr.ai/main/en/) and [PaddleOCR license](https://github.com/PaddlePaddle/PaddleOCR/blob/main/LICENSE).
