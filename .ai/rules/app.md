---
paths:
  - 'app/**'
---

# App

## Use approved DDD module boundaries
Keep Eloquent models in app/Models. Put business rules in app/Domain modules and orchestration in application services/actions; controllers only validate, authorize, and delegate. Do not place financial domain rules in controllers or API consumers.
