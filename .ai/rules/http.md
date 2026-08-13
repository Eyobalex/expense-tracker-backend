---
paths:
  - 'app/Http/**'
---

# Http

## API v1 boundary
API v1 controllers validate, authorize, and delegate only. Responses use the stable data/meta/links envelope; errors use error.code/message/fields/request_id. Mutations require Idempotency-Key with encrypted response replay; mutable profiles require If-Match optimistic concurrency.
