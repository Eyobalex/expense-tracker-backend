# Backend Architecture Baseline

## Approved MVP constraints

- Laravel modular monolith and backend API are authoritative for financial business rules.
- PostgreSQL is required everywhere; Phase 1 replaces the starter SQLite test configuration.
- Redis provides queues, cache, rate limits, idempotency, and distributed locks.
- MinIO is private S3-compatible storage; PaddleOCR PP-OCRv6 is internal-only.
- Sanctum enforces single-device login. Deployment is a supervised VPS topology.

## Application boundaries

- Eloquent models stay in `app/Models`. Financial rules belong in `app/Domain/<Module>`; application services/actions orchestrate use cases.
- Controllers validate, authorize, and delegate. API routes are versioned under `/api/v1`.
- Money uses integer minor units; rates use exact decimals. Timestamps are ISO 8601 UTC with explicit user/budget timezone context.

## API conventions

- Success: `{data, meta, links}`. Errors: `{error: {code, message, fields, request_id}}`.
- Error codes are stable SCREAMING_SNAKE_CASE; PHP enum cases are TitleCase with explicit serialized values.
- Requests receive `request_id`; asynchronous workflows propagate applicable operation, idempotency, job, receipt, transaction, journal, and report identifiers.

## Development and CI baseline

- Run `php artisan test --compact` and `vendor/bin/pint --format agent` after PHP changes.
- CI must use isolated PostgreSQL with PostgreSQL and Redis services before Phase 1 exits.
- Use Laravel Boost documentation search before Laravel code changes and record settled non-obvious rules through Boost.

## Decision log

- 2026-08-12: DDD module placement and thin-controller policy recorded through Laravel Boost in `.ai/rules/app.md`.
- 2026-08-12: No product behavior changed while establishing this baseline.

## Runtime infrastructure (Feature 002)

### Local development

- Laravel Sail is the supported local runtime. Copy `.env.example` to `.env`, then run `./vendor/bin/sail up -d`.
- Sail runs PostgreSQL, Redis, private MinIO, the HTTP application, a Redis queue worker, and the scheduler. The `minio-init` service creates the configured bucket and removes anonymous access before application services start.
- The default application database is `expense_tracker`. Sail also creates the isolated `testing` PostgreSQL database; test configuration inherits the host, port, and credentials from its environment while forcing `DB_CONNECTION=pgsql` and `DB_DATABASE=testing`.
- Run `./vendor/bin/sail artisan migrate`, `./vendor/bin/sail artisan test --compact`, and `./vendor/bin/sail artisan runtime:check --deep` after the services are healthy. The deep check opens PostgreSQL and Redis connections and performs a MinIO write/read/delete probe.
- MinIO is private. Application code must use generated object keys and user-scoped authorization; never expose a public bucket, client-supplied path, or object listing endpoint. Receipts, derivatives, reports, and exports receive distinct prefixes when their bounded contexts are introduced.

### Queue and scheduler operations

- The local worker executes `queue:work redis --sleep=3 --tries=3 --backoff=3 --timeout=90 --max-time=3600`; the Redis retry window is 120 seconds, which exceeds the worker timeout. Failed jobs persist in PostgreSQL using the UUID driver.
- Production must run separate supervised PHP-FPM/API, queue-worker, and scheduler processes. Queue failures, retries, worker restarts, and queue age must be monitored before release; future jobs must declare explicit retry/backoff/timeout values appropriate to their workload.
- `runtime:check` validates configuration. `runtime:check --deep` is the deployment and health-check smoke command; it must fail deployment when PostgreSQL, Redis, or MinIO is unavailable or misconfigured.

### CI and VPS topology

- CI runs against PostgreSQL and Redis service containers, starts an isolated private MinIO container, creates the bucket, migrates the `testing` database, and runs the deep runtime check before the standard checks. CI never uses SQLite.
- The VPS topology is Nginx -> PHP-FPM/API, with separate supervised queue-worker and scheduler processes, PostgreSQL, Redis, and private MinIO reachable only on the private network. PaddleOCR remains an internal service introduced in the receipt phase.
- Environment secrets are supplied outside version control. The MinIO endpoint, credentials, bucket, and path-style setting are deployment configuration so a future S3 migration is configuration/adaptor work, not a domain rewrite.

### Backup and recovery runbook

- Before production, operations must implement encrypted PostgreSQL logical backups and encrypted MinIO object backups, store them separately from the VPS, and monitor backup success/failure.
- Perform and record a restore drill into an isolated environment before production release. The drill must restore PostgreSQL and receipt objects, validate application access with `runtime:check --deep`, and prove the approved RPO/RTO values. Retention schedules and approved RPO/RTO values remain production-release gates in the implementation plan.
