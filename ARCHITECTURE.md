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
